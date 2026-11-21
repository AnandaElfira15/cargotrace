<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_geocoding_returns_normalised_results(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '1.2644', 'lon' => '103.8222', 'display_name' => 'Singapore', 'address' => ['city' => 'Singapore', 'country' => 'Singapore']],
        ], 200)]);

        $this->actingAs(User::factory()->operator()->create())
            ->getJson('/geocode?q=Singapore')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('results.0.latitude', 1.2644)
            ->assertJsonPath('results.0.country', 'Singapore');

        Http::assertSent(fn ($request) => $request->hasHeader('User-Agent') && $request['q'] === 'Singapore');
    }

    public function test_geocoding_handles_empty_results(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/geocode?q=Nowhereland')
            ->assertOk()->assertJsonPath('ok', true)->assertJsonCount(0, 'results');
    }

    public function test_geocoding_handles_timeouts(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => fn () => throw new ConnectionException('timeout')]);

        $this->actingAs(User::factory()->operator()->create())->getJson('/geocode?q=Singapore')
            ->assertStatus(502)->assertJsonPath('ok', false)->assertJsonStructure(['error']);
    }

    public function test_geocoding_handles_error_responses(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('boom', 500)]);

        $this->actingAs(User::factory()->operator()->create())->getJson('/geocode?q=Singapore')
            ->assertStatus(502)->assertJsonPath('ok', false);
    }

    public function test_geocoding_is_staff_only_and_validates_input(): void
    {
        $this->actingAs(User::factory()->customer()->create())->getJson('/geocode?q=Singapore')->assertForbidden();
        $this->actingAs(User::factory()->operator()->create())->getJson('/geocode')->assertStatus(422);
    }

    public function test_weather_is_returned_for_authorised_users(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([
            'current' => ['time' => '2026-01-01T10:00', 'temperature_2m' => 29.5, 'wind_speed_10m' => 12.0, 'precipitation' => 0.0, 'weather_code' => 3],
        ], 200)]);

        $shipment = Shipment::factory()->create();

        $this->actingAs(User::factory()->operator()->create())
            ->getJson("/shipments/{$shipment->id}/weather?point=destination")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.temperature_c', 29.5)
            ->assertJsonPath('data.summary', 'Overcast');
    }

    public function test_weather_failure_is_reported_without_crashing(): void
    {
        Http::fake(['api.open-meteo.com/*' => fn () => throw new ConnectionException('timeout')]);
        $shipment = Shipment::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/shipments/{$shipment->id}/weather")
            ->assertStatus(502)->assertJsonPath('ok', false);
    }

    public function test_weather_without_usable_data_is_reported(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response(['unexpected' => true], 200)]);
        $shipment = Shipment::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/shipments/{$shipment->id}/weather")
            ->assertStatus(502)->assertJsonPath('ok', false);
    }

    public function test_customer_cannot_request_weather_for_someone_elses_shipment(): void
    {
        Http::fake();
        $shipment = Shipment::factory()->create();

        $this->actingAs(User::factory()->customer()->create())
            ->getJson("/shipments/{$shipment->id}/weather")
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
