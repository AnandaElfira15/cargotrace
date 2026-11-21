<?php

namespace Database\Factories;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\Severity;
use App\Models\Shipment;
use App\Models\ShipmentException;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShipmentException> */
class ShipmentExceptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'type' => ExceptionType::CustomsHold,
            'severity' => Severity::Medium,
            'status' => ExceptionStatus::Open,
            'description' => fake()->sentence(),
            'detected_at' => now()->subHours(3),
        ];
    }
}
