<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrackingApiTest extends TestCase
{
    use RefreshDatabase;

    private function post_event(Shipment $shipment, array $data)
    {
        return $this->postJson("/api/shipments/{$shipment->id}/tracking", $data);
    }

    public function test_full_valid_lifecycle_is_recorded_in_order(): void
    {
        $shipment = Shipment::factory()->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        foreach (['BOOKED' => 6, 'PICKED_UP' => 5, 'IN_TRANSIT' => 4, 'DELIVERED' => 1] as $status => $hoursAgo) {
            $this->post_event($shipment, [
                'event_type' => 'STATUS_UPDATE', 'status' => $status,
                'occurred_at' => now()->subHours($hoursAgo)->toIso8601String(),
            ])->assertCreated()->assertJsonPath('data.status', $status);
        }

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Delivered, $shipment->status);
        $this->assertNotNull($shipment->actual_arrival_at);

        $this->getJson("/api/shipments/{$shipment->id}/tracking")
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.status', 'BOOKED')
            ->assertJsonPath('data.3.status', 'DELIVERED');
    }

    public function test_invalid_status_transition_is_rejected_and_nothing_is_stored(): void
    {
        $shipment = Shipment::factory()->create(); // CREATED
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->post_event($shipment, [
            'event_type' => 'STATUS_UPDATE', 'status' => 'DELIVERED', 'occurred_at' => now()->subHour()->toIso8601String(),
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);

        $this->assertDatabaseCount('tracking_events', 0);
        $this->assertSame(ShipmentStatus::Created, $shipment->refresh()->status);
    }

    public function test_terminal_status_cannot_go_back_to_an_earlier_status(): void
    {
        $shipment = Shipment::factory()->status(ShipmentStatus::Delivered)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->post_event($shipment, [
            'event_type' => 'STATUS_UPDATE', 'status' => 'BOOKED', 'occurred_at' => now()->subHour()->toIso8601String(),
        ])->assertStatus(422);

        $this->assertSame(ShipmentStatus::Delivered, $shipment->refresh()->status);
    }

    public function test_status_is_required_for_status_updates(): void
    {
        $shipment = Shipment::factory()->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->post_event($shipment, ['event_type' => 'STATUS_UPDATE', 'occurred_at' => now()->subHour()->toIso8601String()])
            ->assertStatus(422)->assertJsonValidationErrors(['status']);
        $this->post_event($shipment, ['event_type' => 'EXCEPTION', 'occurred_at' => now()->subHour()->toIso8601String()])
            ->assertStatus(422)->assertJsonValidationErrors(['event_type']);
    }

    public function test_event_time_must_not_be_earlier_than_the_latest_event_or_in_the_future(): void
    {
        $shipment = Shipment::factory()->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->post_event($shipment, ['event_type' => 'STATUS_UPDATE', 'status' => 'BOOKED', 'occurred_at' => now()->subHour()->toIso8601String()])->assertCreated();

        $this->post_event($shipment, ['event_type' => 'STATUS_UPDATE', 'status' => 'PICKED_UP', 'occurred_at' => now()->subHours(3)->toIso8601String()])
            ->assertStatus(422)->assertJsonValidationErrors(['occurred_at']);

        $this->post_event($shipment, ['event_type' => 'NOTE', 'occurred_at' => now()->addDay()->toIso8601String()])
            ->assertStatus(422)->assertJsonValidationErrors(['occurred_at']);
    }

    public function test_history_is_append_only_and_current_location_follows_the_newest_valid_event(): void
    {
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create();
        $port = Location::factory()->create(['latitude' => 5.5, 'longitude' => 100.5]);
        Sanctum::actingAs(User::factory()->operator()->create());

        $first = $this->post_event($shipment, ['event_type' => 'LOCATION_UPDATE', 'occurred_at' => now()->subHours(5)->toIso8601String(), 'latitude' => 1.5, 'longitude' => 103.5])
            ->assertCreated()->json('data.id');
        $this->post_event($shipment, ['event_type' => 'LOCATION_UPDATE', 'occurred_at' => now()->subHours(2)->toIso8601String(), 'location_id' => $port->id])
            ->assertCreated();
        $this->post_event($shipment, ['event_type' => 'NOTE', 'occurred_at' => now()->subHour()->toIso8601String(), 'description' => 'Bridge check, no location'])
            ->assertCreated();

        $this->assertDatabaseCount('tracking_events', 3);
        $this->assertDatabaseHas('tracking_events', ['id' => $first, 'latitude' => 1.5]); // old event untouched

        $this->getJson("/api/shipments/{$shipment->id}/tracking")
            ->assertOk()
            ->assertJsonPath('meta.current_location.latitude', 5.5)
            ->assertJsonPath('meta.current_location.longitude', 100.5);
    }

    public function test_estimated_arrival_in_event_triggers_delay_detection(): void
    {
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create();
        Sanctum::actingAs(User::factory()->operator()->create());

        $this->post_event($shipment, [
            'event_type' => 'LOCATION_UPDATE', 'occurred_at' => now()->subHour()->toIso8601String(),
            'latitude' => 1.0, 'longitude' => 1.0,
            'estimated_arrival_at' => $shipment->expected_arrival_at->copy()->addHours(30)->toIso8601String(),
        ])->assertCreated();

        $shipment->refresh();
        $this->assertTrue($shipment->is_delayed);
        $this->assertSame(30.0, $shipment->delay_hours);
        $this->assertSame('HIGH', $shipment->delay_severity->value);
    }

    public function test_customer_cannot_add_events_but_can_read_own_timeline_only(): void
    {
        $customer = User::factory()->customer()->create();
        $own = Shipment::factory()->create(['customer_id' => $customer->id]);
        $foreign = Shipment::factory()->create();
        Sanctum::actingAs($customer);

        $this->post_event($own, ['event_type' => 'NOTE', 'occurred_at' => now()->subHour()->toIso8601String()])->assertForbidden();
        $this->getJson("/api/shipments/{$own->id}/tracking")->assertOk();
        $this->getJson("/api/shipments/{$foreign->id}/tracking")->assertForbidden();
    }
}
