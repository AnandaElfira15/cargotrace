<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Port of '.fake()->unique()->city(),
            'code' => strtoupper(fake()->unique()->lexify('?????')),
            'type' => 'port',
            'city' => fake()->city(),
            'country' => fake()->country(),
            'latitude' => fake()->latitude(-60, 60),
            'longitude' => fake()->longitude(),
        ];
    }
}
