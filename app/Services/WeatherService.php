<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Current weather via Open-Meteo (no key needed; an optional key is read from the environment).
 * Weather is shown as operational context only. It is NOT used to predict delays.
 */
class WeatherService
{
    private const CODES = [
        0 => 'Clear sky', 1 => 'Mainly clear', 2 => 'Partly cloudy', 3 => 'Overcast',
        45 => 'Fog', 48 => 'Rime fog', 51 => 'Light drizzle', 53 => 'Drizzle', 55 => 'Heavy drizzle',
        61 => 'Light rain', 63 => 'Rain', 65 => 'Heavy rain', 71 => 'Light snow', 73 => 'Snow', 75 => 'Heavy snow',
        80 => 'Rain showers', 81 => 'Rain showers', 82 => 'Violent rain showers', 95 => 'Thunderstorm', 96 => 'Thunderstorm with hail', 99 => 'Thunderstorm with hail',
    ];

    /** @return array{ok: bool, data: ?array<string, mixed>, error: ?string} */
    public function current(float $latitude, float $longitude): array
    {
        $cfg = config('cargotrace.external.weather');
        $cacheKey = sprintf('weather:%.2f:%.2f', $latitude, $longitude);

        if ($cached = Cache::get($cacheKey)) {
            return ['ok' => true, 'data' => $cached, 'error' => null];
        }

        $params = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m,wind_speed_10m,precipitation,weather_code',
            'timezone' => 'auto',
        ];
        if (! empty($cfg['api_key'])) {
            $params['apikey'] = $cfg['api_key'];
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('cargotrace.external.timeout', 5))
                ->get(rtrim($cfg['base_url'], '/').'/forecast', $params);
        } catch (ConnectionException $e) {
            Log::warning('Weather timeout/connection error: '.$e->getMessage());

            return $this->failure('The weather service did not respond in time.');
        } catch (\Throwable $e) {
            Log::error('Weather unexpected error: '.$e->getMessage());

            return $this->failure('Weather is temporarily unavailable.');
        }

        if ($response->failed()) {
            return $this->failure('The weather service returned an error ('.$response->status().').');
        }

        $current = $response->json('current');
        if (! is_array($current) || ! isset($current['temperature_2m'])) {
            return $this->failure('No weather data is available for this location.');
        }

        $data = [
            'temperature_c' => (float) $current['temperature_2m'],
            'wind_kmh' => isset($current['wind_speed_10m']) ? (float) $current['wind_speed_10m'] : null,
            'precipitation_mm' => isset($current['precipitation']) ? (float) $current['precipitation'] : null,
            'summary' => self::CODES[(int) ($current['weather_code'] ?? -1)] ?? 'Unknown conditions',
            'observed_at' => $current['time'] ?? null,
            'note' => 'Informational only. Weather is not a prediction of shipment delay.',
        ];

        Cache::put($cacheKey, $data, now()->addMinutes((int) ($cfg['cache_minutes'] ?? 10)));

        return ['ok' => true, 'data' => $data, 'error' => null];
    }

    /** @return array{ok: bool, data: ?array<string, mixed>, error: ?string} */
    private function failure(string $message): array
    {
        return ['ok' => false, 'data' => null, 'error' => $message];
    }
}
