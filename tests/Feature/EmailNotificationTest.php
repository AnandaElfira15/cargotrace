<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Mail\ShipmentNotificationMail;
use App\Models\Shipment;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ExceptionService;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function book(Shipment $shipment, User $actor): void
    {
        app(TrackingService::class)->record($shipment, [
            'event_type' => 'STATUS_UPDATE',
            'status' => 'BOOKED',
            'occurred_at' => now()->subHour()->toDateTimeString(),
        ], $actor);
    }

    private function scenario(): array
    {
        $operator = User::factory()->operator()->create();
        $customer = User::factory()->customer()->create();
        $shipment = Shipment::factory()->create(['customer_id' => $customer->id]);

        return [$operator, $customer, $shipment];
    }

    public function test_no_email_is_sent_by_default_but_the_in_app_notification_is_created(): void
    {
        Mail::fake();
        [$operator, $customer, $shipment] = $this->scenario();

        $this->book($shipment, $operator);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('notifications', ['user_id' => $customer->id, 'type' => 'shipment.status']);
    }

    public function test_customer_gets_a_status_email_when_enabled_but_staff_do_not(): void
    {
        config(['cargotrace.notifications.email' => true]);
        Mail::fake();
        [$operator, $customer, $shipment] = $this->scenario();

        $this->book($shipment, $operator);

        Mail::assertSent(ShipmentNotificationMail::class, fn ($mail) => $mail->hasTo($customer->email));
        Mail::assertNotSent(ShipmentNotificationMail::class, fn ($mail) => $mail->hasTo($operator->email));
    }

    public function test_staff_and_customer_get_an_email_when_an_exception_is_opened(): void
    {
        config(['cargotrace.notifications.email' => true]);
        Mail::fake();
        [$operator, $customer, $shipment] = $this->scenario();
        $admin = User::factory()->admin()->create();

        app(ExceptionService::class)->open($shipment, ['type' => 'CUSTOMS_HOLD', 'severity' => 'HIGH'], $operator);

        Mail::assertSent(ShipmentNotificationMail::class, fn ($mail) => $mail->hasTo($customer->email));
        Mail::assertSent(ShipmentNotificationMail::class, fn ($mail) => $mail->hasTo($admin->email));
        Mail::assertSent(ShipmentNotificationMail::class, fn ($mail) => $mail->hasTo($operator->email));
    }

    public function test_a_mail_failure_does_not_break_the_workflow(): void
    {
        config(['cargotrace.notifications.email' => true]);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp is down'));
        [$operator, $customer, $shipment] = $this->scenario();

        $this->book($shipment, $operator);

        $this->assertSame(ShipmentStatus::Booked, $shipment->refresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $customer->id, 'type' => 'shipment.status']);
    }

    public function test_the_email_contains_the_message_and_a_link_to_the_shipment(): void
    {
        $customer = User::factory()->customer()->create();
        $shipment = Shipment::factory()->create(['customer_id' => $customer->id]);
        $notification = UserNotification::query()->create([
            'user_id' => $customer->id, 'shipment_id' => $shipment->id, 'type' => 'shipment.status',
            'title' => 'Shipment is now BOOKED', 'message' => 'Status changed from CREATED to BOOKED.',
        ]);

        $mail = new ShipmentNotificationMail($notification);

        $mail->assertHasSubject('[CargoTrace] Shipment is now BOOKED');
        $mail->assertSeeInHtml('Status changed from CREATED to BOOKED.');
        $mail->assertSeeInHtml(route('shipments.show', $shipment));
    }
}
