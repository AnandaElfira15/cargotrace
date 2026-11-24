<?php

namespace Tests\Feature;

use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the state-changing actions of the web UI (forms), complementing
 * WebTest (page rendering) and the *ApiTest classes (REST API).
 */
class WebActionsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- tracking

    public function test_operator_adds_a_valid_tracking_event_through_the_web_form(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->create();

        $this->actingAs($operator)->post(route('shipments.tracking.store', $shipment), [
            'event_type' => 'STATUS_UPDATE',
            'status' => 'BOOKED',
            'occurred_at' => now()->subHours(2)->format('Y-m-d\TH:i'),
            'description' => 'Booking confirmed with carrier',
        ])->assertRedirect(route('shipments.show', $shipment))->assertSessionHasNoErrors();

        $this->assertSame(ShipmentStatus::Booked, $shipment->refresh()->status);

        $this->actingAs($operator)->get(route('shipments.show', $shipment))
            ->assertOk()->assertSee('Booking confirmed with carrier');
    }

    public function test_event_in_the_same_minute_as_the_previous_one_is_accepted(): void
    {
        // The datetime-local form field has no seconds, so "same minute" must work.
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->create();
        $first = now()->subMinutes(10)->startOfMinute()->addSeconds(40);

        app(TrackingService::class)->record($shipment, [
            'event_type' => 'NOTE', 'occurred_at' => $first->toDateTimeString(),
        ], $operator);

        $this->actingAs($operator)->post(route('shipments.tracking.store', $shipment), [
            'event_type' => 'NOTE',
            'occurred_at' => $first->copy()->startOfMinute()->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tracking_events', 2);
    }

    public function test_customer_cannot_use_the_tracking_or_exception_forms(): void
    {
        $customer = User::factory()->customer()->create();
        $shipment = Shipment::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($customer)->post(route('shipments.tracking.store', $shipment), [
            'event_type' => 'NOTE', 'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ])->assertForbidden();

        $this->actingAs($customer)->post(route('shipments.exceptions.store', $shipment), [
            'type' => 'DAMAGED', 'severity' => 'LOW',
        ])->assertForbidden();
    }

    // -------------------------------------------------------------- exceptions

    public function test_operator_reports_and_resolves_an_exception_through_the_web(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create();

        $this->actingAs($operator)->post(route('shipments.exceptions.store', $shipment), [
            'type' => 'CUSTOMS_HOLD', 'severity' => 'HIGH', 'description' => 'Held for inspection',
        ])->assertRedirect(route('shipments.show', $shipment))->assertSessionHasNoErrors();

        $exception = ShipmentException::query()->firstOrFail();
        $this->assertSame(ExceptionStatus::Open, $exception->status);

        $this->actingAs($operator)->patch(route('exceptions.update', $exception), ['status' => 'INVESTIGATING'])
            ->assertSessionHasNoErrors();
        $this->actingAs($operator)->patch(route('exceptions.update', $exception), [
            'status' => 'RESOLVED', 'resolution_notes' => 'Cleared by customs',
        ])->assertSessionHasNoErrors();

        $exception->refresh();
        $this->assertSame(ExceptionStatus::Resolved, $exception->status);
        $this->assertNotNull($exception->resolved_at);

        $this->actingAs($operator)->get(route('shipments.show', $shipment))->assertOk()->assertSee('Cleared by customs');
        $this->actingAs($operator)->get('/exceptions?status=RESOLVED')->assertOk()->assertSee($shipment->shipment_number);
    }

    // --------------------------------------------------------------- shipments

    public function test_admin_updates_and_deletes_a_shipment_through_the_web(): void
    {
        $admin = User::factory()->admin()->create();
        $shipment = Shipment::factory()->create();

        $this->actingAs($admin)->put(route('shipments.update', $shipment), ['cargo_type' => 'Machinery'])
            ->assertRedirect(route('shipments.show', $shipment))->assertSessionHasNoErrors();
        $this->assertSame('Machinery', $shipment->refresh()->cargo_type);

        $this->actingAs($admin)->delete(route('shipments.destroy', $shipment))->assertRedirect(route('shipments.index'));
        $this->assertSoftDeleted('shipments', ['id' => $shipment->id]);
    }

    public function test_operator_cannot_delete_a_shipment(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs(User::factory()->operator()->create())->delete(route('shipments.destroy', $shipment))->assertForbidden();
        $this->assertNotSoftDeleted('shipments', ['id' => $shipment->id]);
    }

    public function test_customer_list_shows_only_their_own_shipments(): void
    {
        $customer = User::factory()->customer()->create();
        $own = Shipment::factory()->create(['customer_id' => $customer->id, 'shipment_number' => 'CT-OWN-001']);
        Shipment::factory()->create(['shipment_number' => 'CT-FOREIGN-999']);

        $this->actingAs($customer)->get(route('shipments.index'))
            ->assertOk()->assertSee($own->shipment_number)->assertDontSee('CT-FOREIGN-999');
    }

    public function test_shipment_list_filters_by_status_and_delay(): void
    {
        Shipment::factory()->create(['shipment_number' => 'CT-BOOKED-1', 'status' => ShipmentStatus::Booked]);
        Shipment::factory()->create(['shipment_number' => 'CT-LATE-1', 'status' => ShipmentStatus::InTransit, 'is_delayed' => true, 'delay_hours' => 9, 'delay_severity' => 'MEDIUM']);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/shipments?status=BOOKED')->assertOk()->assertSee('CT-BOOKED-1')->assertDontSee('CT-LATE-1');
        $this->actingAs($admin)->get('/shipments?delayed=1')->assertOk()->assertSee('CT-LATE-1')->assertDontSee('CT-BOOKED-1');
        $this->actingAs($admin)->get('/shipments?status=NOPE')->assertSessionHasErrors('status');
    }

    // ---------------------------------------------------- carriers & locations

    public function test_admin_manages_carriers_through_the_web(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('carriers.store'), [
            'name' => 'Blue Wave', 'code' => 'BWV', 'mode' => 'sea', 'contact_email' => 'ops@bluewave.test', 'is_active' => 1,
        ])->assertRedirect(route('carriers.index'))->assertSessionHasNoErrors();

        $carrier = Carrier::query()->where('code', 'BWV')->firstOrFail();

        $this->actingAs($admin)->put(route('carriers.update', $carrier), [
            'name' => 'Blue Wave Lines', 'code' => 'BWV', 'mode' => 'sea',
        ])->assertRedirect(route('carriers.index'));
        $carrier->refresh();
        $this->assertSame('Blue Wave Lines', $carrier->name);
        $this->assertFalse($carrier->is_active); // unchecked checkbox = inactive

        $this->actingAs($admin)->delete(route('carriers.destroy', $carrier))->assertRedirect(route('carriers.index'));
        $this->assertDatabaseMissing('carriers', ['code' => 'BWV']);
    }

    public function test_carrier_in_use_cannot_be_deleted(): void
    {
        $carrier = Carrier::factory()->create();
        Shipment::factory()->create(['carrier_id' => $carrier->id]);

        $this->actingAs(User::factory()->admin()->create())->from(route('carriers.index'))
            ->delete(route('carriers.destroy', $carrier))->assertSessionHasErrors('carrier');
        $this->assertDatabaseHas('carriers', ['id' => $carrier->id]);
    }

    public function test_admin_manages_locations_through_the_web(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('locations.store'), [
            'name' => 'Port X', 'code' => 'PRTX', 'type' => 'port', 'country' => 'Nowhere', 'latitude' => 10.5, 'longitude' => 20.5,
        ])->assertRedirect(route('locations.index'))->assertSessionHasNoErrors();

        $location = Location::query()->where('code', 'PRTX')->firstOrFail();

        $this->actingAs($admin)->put(route('locations.update', $location), [
            'name' => 'Port X', 'code' => 'PRTX', 'type' => 'port', 'country' => 'Nowhere', 'latitude' => 11.25, 'longitude' => 20.5,
        ])->assertRedirect(route('locations.index'));
        $this->assertEquals(11.25, $location->refresh()->latitude);

        $this->actingAs($admin)->post(route('locations.store'), [
            'name' => 'Bad', 'code' => 'BAD1', 'type' => 'port', 'country' => 'X', 'latitude' => 120, 'longitude' => 20,
        ])->assertSessionHasErrors('latitude');

        $this->actingAs($admin)->delete(route('locations.destroy', $location))->assertRedirect(route('locations.index'));
        $this->assertDatabaseMissing('locations', ['code' => 'PRTX']);
    }

    public function test_location_in_use_cannot_be_deleted(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->from(route('locations.index'))
            ->delete(route('locations.destroy', $shipment->origin_location_id))->assertSessionHasErrors('location');
        $this->assertDatabaseHas('locations', ['id' => $shipment->origin_location_id]);
    }

    // ------------------------------------------------------------------- users

    public function test_admin_creates_and_updates_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New Operator', 'email' => 'newop@example.com', 'role' => 'operator', 'password' => 'password123',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'newop@example.com', 'role' => 'operator', 'is_active' => true]);

        $user = User::query()->where('email', 'newop@example.com')->firstOrFail();
        $this->actingAs($admin)->put(route('users.update', $user), ['name' => 'Renamed', 'role' => 'customer', 'is_active' => 1])
            ->assertRedirect(route('users.index'));
        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertSame('customer', $user->role->value);
    }

    public function test_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from(route('users.edit', $admin))
            ->put(route('users.update', $admin), ['name' => $admin->name, 'role' => 'operator', 'is_active' => 1])
            ->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->refresh()->role->value);
    }

    public function test_deactivating_a_user_revokes_api_tokens_and_blocks_login(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->operator()->create(['email' => 'target@example.com']);
        $target->createToken('api');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->actingAs($admin)->put(route('users.update', $target), ['name' => $target->name, 'role' => 'operator']) // no is_active = off
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/login', ['email' => 'target@example.com', 'password' => 'password'])->assertForbidden();
    }

    // ---------------------------------------------------------------- settings

    public function test_admin_changes_delay_thresholds_and_they_take_effect(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('settings.update'), ['low_max_hours' => 4, 'medium_max_hours' => 10])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', ['key' => 'delay.low_max_hours', 'value' => '4']);
        $this->assertDatabaseHas('settings', ['key' => 'delay.medium_max_hours', 'value' => '10']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);

        $this->actingAs($admin)->get(route('settings.edit'))->assertOk()->assertSee('value="10"', false);
    }

    public function test_threshold_validation_and_permissions(): void
    {
        $this->actingAs(User::factory()->admin()->create())->from(route('settings.edit'))
            ->put(route('settings.update'), ['low_max_hours' => 10, 'medium_max_hours' => 5])
            ->assertSessionHasErrors('medium_max_hours');

        $this->actingAs(User::factory()->operator()->create())
            ->put(route('settings.update'), ['low_max_hours' => 1, 'medium_max_hours' => 2])->assertForbidden();
    }

    // --------------------------------------------------- audit, notifications

    public function test_audit_log_filters_work(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('carriers.store'), ['name' => 'Audit Carrier', 'code' => 'AUD', 'mode' => 'sea']);

        $this->actingAs($admin)->get('/audit-logs?action=carrier')->assertOk()->assertSee('carrier.created');
        $this->actingAs($admin)->get('/audit-logs?entity_type=Carrier')->assertOk()->assertSee('carrier.created');
        $this->actingAs($admin)->get('/audit-logs?action=nothing-like-this')->assertOk()->assertDontSee('carrier.created');
    }

    public function test_opening_a_notification_marks_it_read_and_goes_to_the_shipment(): void
    {
        $customer = User::factory()->customer()->create();
        $shipment = Shipment::factory()->create(['customer_id' => $customer->id]);
        app(NotificationService::class)->notify([$customer], 'test', 'Look here', 'Something happened.', $shipment);
        $notification = $customer->inbox()->firstOrFail();

        $this->actingAs($customer)->post(route('notifications.read', $notification))
            ->assertRedirect(route('shipments.show', $shipment));

        $this->assertNotNull($notification->refresh()->read_at);
        $this->assertSame(0, $customer->unreadNotificationCount());
    }

    public function test_dashboard_page_contains_the_chart_containers_and_group_kpi(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('statusChart')->assertSee('carrierChart')
            ->assertSee('group KPI');
    }

    public function test_location_form_offers_coordinate_lookup_and_shipment_page_offers_weather(): void
    {
        $admin = User::factory()->admin()->create();
        $shipment = Shipment::factory()->create();

        $this->actingAs($admin)->get(route('locations.create'))->assertOk()->assertSee('Find coordinates');
        $this->actingAs($admin)->get(route('shipments.show', $shipment))->assertOk()->assertSee('data-weather="current"', false)->assertSee('id="map"', false);
    }
}
