<?php

namespace Tests\Feature;

use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExceptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_reports_an_exception_and_it_appears_in_the_timeline(): void
    {
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->postJson("/api/shipments/{$shipment->id}/exceptions", [
            'type' => 'CUSTOMS_HOLD', 'severity' => 'HIGH', 'description' => 'Documents under review',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'OPEN')
            ->assertJsonPath('data.type', 'CUSTOMS_HOLD');

        $this->assertDatabaseHas('tracking_events', ['shipment_id' => $shipment->id, 'is_exception' => true, 'event_type' => 'EXCEPTION']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'exception.opened']);
    }

    public function test_exception_input_is_validated(): void
    {
        $shipment = Shipment::factory()->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->postJson("/api/shipments/{$shipment->id}/exceptions", ['type' => 'UFO_ATTACK', 'severity' => 'EXTREME'])
            ->assertStatus(422)->assertJsonValidationErrors(['type', 'severity']);
    }

    public function test_customer_cannot_report_or_update_exceptions(): void
    {
        $customer = User::factory()->customer()->create();
        $shipment = Shipment::factory()->create(['customer_id' => $customer->id]);
        $exception = ShipmentException::factory()->create(['shipment_id' => $shipment->id]);
        Sanctum::actingAs($customer);

        $this->postJson("/api/shipments/{$shipment->id}/exceptions", ['type' => 'DAMAGED', 'severity' => 'LOW'])->assertForbidden();
        $this->patchJson("/api/exceptions/{$exception->id}", ['status' => 'RESOLVED'])->assertForbidden();
        $this->getJson("/api/shipments/{$shipment->id}/exceptions")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_exception_goes_open_investigating_resolved_and_resolved_is_final(): void
    {
        $exception = ShipmentException::factory()->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->patchJson("/api/exceptions/{$exception->id}", ['status' => 'INVESTIGATING'])
            ->assertOk()->assertJsonPath('data.status', 'INVESTIGATING')->assertJsonPath('data.resolved_at', null);

        $this->patchJson("/api/exceptions/{$exception->id}", ['status' => 'RESOLVED', 'resolution_notes' => 'Cleared by customs'])
            ->assertOk()->assertJsonPath('data.status', 'RESOLVED');

        $exception->refresh();
        $this->assertSame(ExceptionStatus::Resolved, $exception->status);
        $this->assertNotNull($exception->resolved_at);
        $this->assertNotNull($exception->resolved_by);

        $this->patchJson("/api/exceptions/{$exception->id}", ['status' => 'OPEN'])->assertStatus(422);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        $exception = ShipmentException::factory()->create();

        $this->patchJson("/api/exceptions/{$exception->id}", ['status' => 'RESOLVED'])->assertUnauthorized();
    }
}
