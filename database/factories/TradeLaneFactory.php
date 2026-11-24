<?php

namespace Database\Factories;

use App\Enums\ExceptionType;
use App\Models\TradeLane;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TradeLane> */
class TradeLaneFactory extends Factory
{
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('??'));

        return [
            'code' => $code,
            'name' => "Group {$code}",
            'route' => fake()->city().' to '.fake()->city(),
            'delay_low_max_hours' => 6,
            'delay_medium_max_hours' => 12,
            'primary_exception' => ExceptionType::CustomsHold,
            'kpi' => 'on_time_delivery_rate',
            'is_active' => true,
        ];
    }
}
