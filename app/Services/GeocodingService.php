<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Geocoding through OpenStreetMap Nominatim using Laravel's HTTP client.
 * Handles timeouts, error responses and empty results without throwing.
 *
 * @phpstan-type Result array{ok: bool, results: array<int, array<string, mixed>>, error: ?string}
 */
class GeocodingService
{
    /** @return array{ok: bool, results: array<int, array<string, mixed>>, error: ?string} */
    public function search(string $query, int $limit = 5): array
    {
        $cfg = config('cargotrace.external.geocoding');

        $params = ['q' => $query, 'format' => 'jsonv2', 'limit' => $limit, 'addressdetails' => 1];
        if (! empty($cfg['contact_email'])) {
            $params['email'] = $cfg['contact_email'];
        }

        try {
            $response = Http::withHeaders(['User-Agent' => $cfg['user_agent']])
                ->acceptJson()
                ->timeout((int) config('cargotrace.external.timeout', 5))
                ->get(rtrim($cfg['base_url'], '/').'/search', $params);
        } catch (ConnectionException $e) {
            Log::warning('Geocoding timeout/connection error: '.$e->getMessage());

            return $this->failure('The geocoding service did not respond in time. Please enter the coordinates manually.');
        } catch (\Throwable $e) {
            Log::error('Geocoding unexpected error: '.$e->getMessage());

            return $this->failure('Geocoding is temporarily unavailable.');
        }

        if ($response->failed()) {
            Log::warning('Geocoding HTTP error '.$response->status());

            return $this->failure('The geocoding service returned an error ('.$response->status().').');
        }

        $rows = $response->json();
        if (! is_array($rows) || $rows === []) {
            return ['ok' => true, 'results' => [], 'error' => null];
        }

        $results = [];
        foreach ($rows as $row) {
            if (! isset($row['lat'], $row['lon'])) {
                continue;
            }
            $results[] = [
                'display_name' => (string) ($row['display_name'] ?? $query),
                'latitude' => (float) $row['lat'],
                'longitude' => (float) $row['lon'],
                'city' => $row['address']['city'] ?? $row['address']['town'] ?? $row['address']['village'] ?? null,
                'country' => $row['address']['country'] ?? null,
            ];
        }

        return ['ok' => true, 'results' => $results, 'error' => null];
    }

    /** @return array{ok: bool, results: array<int, array<string, mixed>>, error: ?string} */
    private function failure(string $message): array
    {
        return ['ok' => false, 'results' => [], 'error' => $message];
    }
}
