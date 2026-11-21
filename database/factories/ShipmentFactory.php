<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        $departure = now()->addDay()->startOfHour();

        return [
            'shipment_number' => 'CT'.fake()->unique()->numerify('######-#####'),
            'customer_id' => User::factory()->customer(),
            'carrier_id' => Carrier::factory(),
            'origin_location_id' => Location::factory(),
            'destination_location_id' => Location::factory(),
            'cargo_type' => fake()->randomElement(['Electronics', 'Furniture', 'Textiles', 'Palm oil derivatives']),
            'weight_kg' => fake()->randomFloat(2, 500, 20000),
            'volume_cbm' => fake()->randomFloat(2, 5, 60),
            'expected_departure_at' => $departure,
            'expected_arrival_at' => $departure->copy()->addDays(14),
            'status' => ShipmentStatus::Created,
            'is_delayed' => false,
            'delay_hours' => 0,
            'delay_severity' => null,
        ];
    }

    public function status(ShipmentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
