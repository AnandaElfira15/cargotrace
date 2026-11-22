<?php

namespace Tests\Feature;

use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_numbers_come_from_the_database(): void
    {
        $carrier = Carrier::factory()->create(['name' => 'Blue Wave']);

        Shipment::factory()->status(ShipmentStatus::InTransit)->create(['carrier_id' => $carrier->id]);
        Shipment::factory()->status(ShipmentStatus::Delivered)->create(['carrier_id' => $carrier->id, 'is_delayed' => false]);
        Shipment::factory()->status(ShipmentStatus::Delivered)->create(['carrier_id' => $carrier->id, 'is_delayed' => true, 'delay_hours' => 10]);
        Shipment::factory()->status(ShipmentStatus::Cancelled)->create(['carrier_id' => $carrier->id]);

        $withException = Shipment::factory()->status(ShipmentStatus::InTransit)->create(['carrier_id' => $carrier->id]);
        ShipmentException::factory()->create(['shipment_id' => $withException->id, 'status' => ExceptionStatus::Open]);
        ShipmentException::factory()->create([
            'shipment_id' => $withException->id,
            'status' => ExceptionStatus::Resolved,
            'detected_at' => now()->subHours(10),
            'resolved_at' => now()->subHours(4),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/dashboard/data')
            ->assertOk()
            ->assertJsonPath('totals.shipments', 5)
            ->assertJsonPath('totals.in_transit', 2)
            ->assertJsonPath('totals.delivered', 2)
            ->assertJsonPath('totals.delayed', 1)
            ->assertJsonPath('totals.open_exceptions', 1)
            ->assertJsonPath('kpis.on_time_delivery_rate', 50)
            ->assertJsonPath('kpis.average_delay_duration', 10)
            ->assertJsonPath('kpis.exception_resolution_time', 6)
            ->assertJsonPath('by_carrier.labels.0', 'Blue Wave')
            ->assertJsonPath('by_carrier.values.0', 5);
    }

    public function test_customer_dashboard_is_scoped_to_own_shipments(): void
    {
        $customer = User::factory()->customer()->create();
        Shipment::factory()->count(2)->create(['customer_id' => $customer->id]);
        Shipment::factory()->count(3)->create();

        $this->actingAs($customer)->getJson('/dashboard/data')
            ->assertOk()
            ->assertJsonPath('totals.shipments', 2);
    }

    public function test_empty_database_gives_zeroes_and_null_kpis_instead_of_errors(): void
    {
        $this->actingAs(User::factory()->admin()->create())->getJson('/dashboard/data')
            ->assertOk()
            ->assertJsonPath('totals.shipments', 0)
            ->assertJsonPath('kpis.on_time_delivery_rate', null);
    }
}
