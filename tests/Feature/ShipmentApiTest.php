<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'customer_id' => User::factory()->customer()->create()->id,
            'carrier_id' => Carrier::factory()->create()->id,
            'origin_location_id' => Location::factory()->create()->id,
            'destination_location_id' => Location::factory()->create()->id,
            'cargo_type' => 'Electronics',
            'weight_kg' => 1500.5,
            'volume_cbm' => 12.25,
            'expected_departure_at' => now()->addDay()->toIso8601String(),
            'expected_arrival_at' => now()->addDays(20)->toIso8601String(),
            'items' => [['description' => 'Laptops', 'quantity' => 10, 'weight_kg' => 300]],
        ], $override);
    }

    public function test_operator_can_create_a_shipment_with_initial_tracking_event_and_audit_log(): void
    {
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->postJson('/api/shipments', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'CREATED')
            ->assertJsonPath('data.delay.is_delayed', false)
            ->assertJsonCount(1, 'data.items');

        $this->assertDatabaseCount('tracking_events', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.created', 'entity_type' => 'Shipment']);
    }

    public function test_create_validates_required_fields_and_business_rules(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/shipments', [])->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'carrier_id', 'origin_location_id', 'destination_location_id', 'cargo_type', 'weight_kg', 'volume_cbm', 'expected_departure_at', 'expected_arrival_at']);

        $this->postJson('/api/shipments', $this->payload([
            'expected_arrival_at' => now()->subDay()->toIso8601String(),
        ]))->assertStatus(422)->assertJsonValidationErrors(['expected_arrival_at']);

        $same = Location::factory()->create()->id;
        $this->postJson('/api/shipments', $this->payload(['origin_location_id' => $same, 'destination_location_id' => $same]))
            ->assertStatus(422)->assertJsonValidationErrors(['destination_location_id']);
    }

    public function test_status_cannot_be_set_through_create(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/shipments', $this->payload(['status' => 'DELIVERED']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'CREATED');
    }

    public function test_customer_cannot_create_update_or_delete(): void
    {
        $customer = User::factory()->customer()->create();
        $shipment = Shipment::factory()->create(['customer_id' => $customer->id]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/shipments', $this->payload())->assertForbidden();
        $this->patchJson("/api/shipments/{$shipment->id}", ['cargo_type' => 'Hacked'])->assertForbidden();
        $this->deleteJson("/api/shipments/{$shipment->id}")->assertForbidden();
    }

    public function test_customer_only_sees_own_shipments(): void
    {
        $mine = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $own = Shipment::factory()->create(['customer_id' => $mine->id]);
        $foreign = Shipment::factory()->create(['customer_id' => $other->id]);

        Sanctum::actingAs($mine);

        $this->getJson('/api/shipments')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson("/api/shipments/{$own->id}")->assertOk();
        $this->getJson("/api/shipments/{$foreign->id}")->assertForbidden();
    }

    public function test_staff_can_see_all_shipments(): void
    {
        Shipment::factory()->count(3)->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->getJson('/api/shipments')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_only_admin_can_delete_and_delete_is_soft(): void
    {
        $shipment = Shipment::factory()->create();

        Sanctum::actingAs(User::factory()->operator()->create());
        $this->deleteJson("/api/shipments/{$shipment->id}")->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->deleteJson("/api/shipments/{$shipment->id}")->assertNoContent();
        $this->assertSoftDeleted('shipments', ['id' => $shipment->id]);
        $this->getJson("/api/shipments/{$shipment->id}")->assertNotFound();
    }

    public function test_update_validates_dates_against_stored_values(): void
    {
        $shipment = Shipment::factory()->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->patchJson("/api/shipments/{$shipment->id}", [
            'expected_arrival_at' => $shipment->expected_departure_at->copy()->subDay()->toIso8601String(),
        ])->assertStatus(422)->assertJsonValidationErrors(['expected_arrival_at']);

        $this->patchJson("/api/shipments/{$shipment->id}", ['cargo_type' => 'Machinery'])
            ->assertOk()->assertJsonPath('data.cargo_type', 'Machinery');
    }

    public function test_delayed_eta_flags_the_shipment_on_update(): void
    {
        $shipment = Shipment::factory()->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->patchJson("/api/shipments/{$shipment->id}", [
            'estimated_arrival_at' => $shipment->expected_arrival_at->copy()->addHours(10)->toIso8601String(),
        ])->assertOk()
            ->assertJsonPath('data.delay.is_delayed', true)
            ->assertJsonPath('data.delay.severity', 'MEDIUM');
    }

    public function test_search_filter_sort_and_pagination_work_on_the_server(): void
    {
        Shipment::factory()->create(['shipment_number' => 'CT-ALPHA-1', 'status' => ShipmentStatus::Booked]);
        Shipment::factory()->create(['shipment_number' => 'CT-ALPHA-2', 'status' => ShipmentStatus::Created]);
        Shipment::factory()->create(['shipment_number' => 'CT-BETA-1', 'status' => ShipmentStatus::Created, 'is_delayed' => true]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/shipments?q=ALPHA')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/shipments?status=BOOKED')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/shipments?delayed=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.shipment_number', 'CT-BETA-1');
        $this->getJson('/api/shipments?sort=shipment_number&direction=asc')->assertOk()->assertJsonPath('data.0.shipment_number', 'CT-ALPHA-1');
        $this->getJson('/api/shipments?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
        $this->getJson('/api/shipments?sort=password')->assertStatus(422);
        $this->getJson('/api/shipments?status=NOPE')->assertStatus(422);
    }

    public function test_only_admin_can_create_carriers_and_locations(): void
    {
        $carrier = ['name' => 'Blue Wave', 'code' => 'BWV', 'mode' => 'sea'];
        $location = ['name' => 'Port X', 'code' => 'PRTX', 'type' => 'port', 'country' => 'Nowhere', 'latitude' => 10.5, 'longitude' => 20.5];

        Sanctum::actingAs(User::factory()->operator()->create());
        $this->postJson('/api/carriers', $carrier)->assertForbidden();
        $this->postJson('/api/locations', $location)->assertForbidden();
        $this->getJson('/api/carriers')->assertOk();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson('/api/carriers', $carrier)->assertCreated()->assertJsonPath('data.code', 'BWV');
        $this->postJson('/api/locations', $location)->assertCreated()->assertJsonPath('data.code', 'PRTX');
        $this->postJson('/api/locations', array_merge($location, ['latitude' => 999]))->assertStatus(422);
    }
}
