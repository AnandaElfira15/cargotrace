<?php

namespace Database\Seeders;

use App\Enums\ExceptionType;
use App\Enums\ShipmentStatus;
use App\Enums\TrackingEventType;
use App\Enums\UserRole;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ExceptionService;
use App\Services\ShipmentService;
use App\Services\TrackingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Deterministic, synthetic demo data driven by database/seeders/scenarios/{a,b,c}.php (GROUP_SCENARIO).
 * Everything goes through the real services, so business rules, audit logs,
 * notifications and delay flags are produced exactly as in production use.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $key = strtolower((string) env('GROUP_SCENARIO', 'a'));
        $file = database_path("seeders/scenarios/{$key}.php");
        $scenario = require (file_exists($file) ? $file : database_path('seeders/scenarios/a.php'));
        $password = env('DEMO_PASSWORD', 'password');

        $admin = $this->user('Ayu Administrator', 'admin@cargotrace.test', UserRole::Admin, 'CargoTrace HQ', $password);
        $operator = $this->user('Omar Operator', 'operator@cargotrace.test', UserRole::Operator, 'CargoTrace Operations', $password);
        $customers = [
            $this->user('Citra Customer', 'customer@cargotrace.test', UserRole::Customer, 'PT Nusantara Export', $password),
            $this->user('Lars van Dijk', 'lars@eurotrade.test', UserRole::Customer, 'EuroTrade BV', $password),
            $this->user('Hana Schmidt', 'hana@hanseatic.test', UserRole::Customer, 'Hanseatic Imports GmbH', $password),
        ];

        $carriers = [];
        foreach ($scenario['carriers'] as $c) {
            $carriers[] = Carrier::query()->updateOrCreate(['code' => $c['code']], $c + ['is_active' => true]);
        }

        $locations = [];
        foreach ($scenario['locations'] as $l) {
            $locations[$l['code']] = Location::query()->updateOrCreate(['code' => $l['code']], [
                'name' => $l['name'], 'type' => $l['type'], 'city' => $l['city'], 'country' => $l['country'],
                'latitude' => $l['lat'], 'longitude' => $l['lng'],
            ]);
        }

        if (Shipment::query()->exists()) {
            $this->command?->warn('Shipments already exist, skipping shipment seeding.');

            return;
        }

        $shipments = app(ShipmentService::class);
        $tracking = app(TrackingService::class);
        $exceptions = app(ExceptionService::class);
        $primary = ExceptionType::tryFrom((string) config('cargotrace.group.primary_exception')) ?? ExceptionType::CustomsHold;
        $now = now();

        // target   = final lifecycle step
        // late     = hours late (delivery time, or the ETA for in-transit shipments)
        // extra    = (DELIVERED) extra days since arrival, so deliveries are spread over time
        // progress = (IN_TRANSIT) fraction of the voyage completed now; > 1 means overdue
        $second = ExceptionType::tryFrom((string) config('cargotrace.group.secondary_exception')) ?? ExceptionType::PortCongestion;
        $profiles = [
            ['target' => 'DELIVERED', 'extra' => 30, 'late' => 0],
            ['target' => 'DELIVERED', 'extra' => 26, 'late' => 0],
            ['target' => 'DELIVERED', 'extra' => 22, 'late' => 4],
            ['target' => 'DELIVERED', 'extra' => 18, 'late' => 0],
            ['target' => 'DELIVERED', 'extra' => 14, 'late' => 15, 'exception' => [$primary, 'RESOLVED']],
            ['target' => 'DELIVERED', 'extra' => 10, 'late' => 30, 'exception' => [$second, 'RESOLVED']],
            ['target' => 'DELIVERED', 'extra' => 6, 'late' => 0],
            ['target' => 'IN_TRANSIT', 'progress' => 0.35, 'late' => 0],
            ['target' => 'IN_TRANSIT', 'progress' => 0.50, 'late' => 0],
            ['target' => 'IN_TRANSIT', 'progress' => 0.60, 'late' => 5],
            ['target' => 'IN_TRANSIT', 'progress' => 0.45, 'late' => 9, 'exception' => [$primary, 'OPEN']],
            ['target' => 'IN_TRANSIT', 'progress' => 0.55, 'late' => 20, 'exception' => [ExceptionType::WeatherDelay, 'INVESTIGATING']],
            ['target' => 'IN_TRANSIT', 'progress' => 0.70, 'late' => 40, 'exception' => [ExceptionType::DocumentationIssue, 'OPEN']],
            ['target' => 'IN_TRANSIT', 'progress' => 0.30, 'late' => 0],
            ['target' => 'IN_TRANSIT', 'progress' => 1.25, 'late' => 0], // overdue, still moving
            ['target' => 'PICKED_UP', 'age' => 3, 'late' => 0],
            ['target' => 'PICKED_UP', 'age' => 3, 'late' => 0],
            ['target' => 'BOOKED', 'age' => 2, 'late' => 0],
            ['target' => 'BOOKED', 'age' => 2, 'late' => 0],
            ['target' => 'CREATED', 'age' => 1, 'late' => 0],
            ['target' => 'CREATED', 'age' => 1, 'late' => 0],
            ['target' => 'CANCELLED', 'age' => 6, 'late' => 0],
        ];

        $order = ['CREATED' => 0, 'BOOKED' => 1, 'PICKED_UP' => 2, 'IN_TRANSIT' => 3, 'DELIVERED' => 4];

        foreach ($profiles as $i => $p) {
            $route = $scenario['routes'][$i % count($scenario['routes'])];
            $origin = $locations[$route['origin']];
            $destination = $locations[$route['destination']];
            $age = match ($p['target']) {
                'DELIVERED' => $route['transit_days'] + 3 + $p['extra'],
                'IN_TRANSIT' => 1 + $p['progress'] * $route['transit_days'],
                default => $p['age'],
            };
            $created = $now->copy()->subMinutes((int) round($age * 24 * 60));
            $depart = $created->copy()->addDay();
            $arrive = $depart->copy()->addDays($route['transit_days']);

            $shipment = $shipments->create([
                'customer_id' => $customers[$i % count($customers)]->id,
                'carrier_id' => $carriers[$i % count($carriers)]->id,
                'origin_location_id' => $origin->id,
                'destination_location_id' => $destination->id,
                'cargo_type' => $scenario['cargo_types'][$i % count($scenario['cargo_types'])],
                'weight_kg' => 4000 + ($i * 750),
                'volume_cbm' => 20 + ($i * 2),
                'expected_departure_at' => $depart,
                'expected_arrival_at' => $arrive,
                'items' => [
                    ['description' => $scenario['cargo_types'][$i % count($scenario['cargo_types'])].' (lot A)', 'quantity' => 10 + $i, 'weight_kg' => 2000 + ($i * 300), 'volume_cbm' => 10, 'hs_code' => '8517.12'],
                    ['description' => 'Packaging and pallets', 'quantity' => 40, 'weight_kg' => 500, 'volume_cbm' => 4, 'hs_code' => '4415.20'],
                ],
            ], $operator, $created);

            if ($p['target'] === 'CANCELLED') {
                $this->event($tracking, $shipment, $operator, $created->copy()->addHours(5), 'CANCELLED', $origin->id, 'Cancelled at customer request.');

                continue;
            }

            $step = $order[$p['target']];
            $lastIso = null;

            $plan = [];
            if ($step >= 1) {
                $plan[] = [$created->copy()->addHours(3), 'BOOKED', $origin->id, 'Booking confirmed with carrier.'];
            }
            if ($step >= 2) {
                $plan[] = [$depart->copy()->addHours(2), 'PICKED_UP', $origin->id, 'Cargo picked up and gated in.'];
            }
            if ($step >= 3) {
                $plan[] = [$depart->copy()->addHours(10), 'IN_TRANSIT', $origin->id, 'Vessel departed.'];
                $waypoints = $route['waypoints'];
                $span = max(1, ($route['transit_days'] * 24) - 24);
                foreach ($waypoints as $w => $code) {
                    $at = $depart->copy()->addHours(10 + (int) round($span * (($w + 1) / (count($waypoints) + 1))));
                    $plan[] = [$at, 'LOCATION', $locations[$code]->id, 'Passed '.$locations[$code]->name.'.'];
                }
            }
            if ($step >= 4) {
                $plan[] = [$arrive->copy()->addHours($p['late']), 'DELIVERED', $destination->id, 'Delivered to consignee.'];
            }

            $etaApplied = false;
            foreach ($plan as [$at, $kind, $locationId, $text]) {
                if ($at->greaterThan($now->copy()->subMinutes(10))) {
                    break; // never create events in the future
                }
                $eta = null;
                if (! $etaApplied && $p['target'] === 'IN_TRANSIT' && $p['late'] > 0 && $kind === 'IN_TRANSIT') {
                    $eta = $arrive->copy()->addHours($p['late']);
                    $etaApplied = true;
                }
                $this->event($tracking, $shipment, $operator, $at, $kind, $locationId, $text, $eta);
                $lastIso = $at;
            }

            if (isset($p['exception'])) {
                [$type, $final] = $p['exception'];
                $detected = ($lastIso ?? $created)->copy()->addHours(1);
                if ($detected->greaterThan($now)) {
                    $detected = $now->copy()->subMinutes(30);
                }
                $ex = $exceptions->open($shipment, [
                    'type' => $type->value,
                    'severity' => $p['late'] > 24 ? 'HIGH' : ($p['late'] > 6 ? 'MEDIUM' : 'LOW'),
                    'description' => $type->label().' reported during transit; carrier and customer informed.',
                    'detected_at' => $detected->toDateTimeString(),
                ], $operator);

                if ($final !== 'OPEN') {
                    $ex = $exceptions->update($ex, ['status' => 'INVESTIGATING'], $operator);
                }
                if ($final === 'RESOLVED') {
                    $exceptions->update($ex, [
                        'status' => 'RESOLVED',
                        'resolution_notes' => 'Cleared after documents were re-submitted.',
                        'resolved_at' => $detected->copy()->addHours(18)->min($now)->toDateTimeString(),
                    ], $operator);
                }
            }
        }

        $this->command?->info('Demo data ready. Accounts: admin@cargotrace.test, operator@cargotrace.test, customer@cargotrace.test (password: '.$password.')');
    }

    private function user(string $name, string $email, UserRole $role, string $company, string $password): User
    {
        $user = User::query()->where('email', $email)->first() ?? new User(['email' => $email]);
        $user->fill(['name' => $name, 'password' => $password, 'company' => $company]);
        $user->role = $role;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    private function event(TrackingService $tracking, Shipment $shipment, User $actor, Carbon $at, string $kind, int $locationId, string $text, ?Carbon $eta = null): void
    {
        $data = [
            'occurred_at' => $at->toDateTimeString(),
            'location_id' => $locationId,
            'description' => $text,
        ];

        if ($kind === 'LOCATION') {
            $data['event_type'] = TrackingEventType::LocationUpdate->value;
        } else {
            $data['event_type'] = TrackingEventType::StatusUpdate->value;
            $data['status'] = ShipmentStatus::from($kind)->value;
        }
        if ($eta) {
            $data['estimated_arrival_at'] = $eta->toDateTimeString();
        }

        $tracking->record($shipment->refresh(), $data, $actor);
    }
}
