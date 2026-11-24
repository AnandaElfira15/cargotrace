<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fakeServices(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['lat' => '1.26', 'lon' => '103.82', 'display_name' => 'Singapore']], 200),
            'api.open-meteo.com/*' => Http::response(['current' => ['temperature_2m' => 30.1, 'weather_code' => 1]], 200),
        ]);
    }

    public function test_doctor_passes_when_everything_is_in_place(): void
    {
        $this->fakeServices();
        User::factory()->count(3)->create();
        Shipment::factory()->create();

        $this->artisan('cargotrace:doctor')
            ->expectsOutputToContain('Database connection')
            ->expectsOutputToContain('All checks passed.')
            ->assertExitCode(0);
    }

    public function test_doctor_fails_when_demo_data_is_missing(): void
    {
        $this->fakeServices();

        $this->artisan('cargotrace:doctor')
            ->expectsOutputToContain('Demo users present')
            ->assertExitCode(1);
    }

    public function test_doctor_reports_external_service_failures_without_crashing(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);
        User::factory()->count(3)->create();
        Shipment::factory()->create();

        $this->artisan('cargotrace:doctor')
            ->expectsOutputToContain('Geocoding service (Nominatim)')
            ->assertExitCode(1);
    }
}
