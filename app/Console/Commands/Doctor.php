<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Models\TradeLane;
use App\Models\User;
use App\Services\EtaService;
use App\Services\GeocodingService;
use App\Services\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Doctor extends Command
{
    protected $signature = 'cargotrace:doctor';

    protected $description = 'Check the local setup: PHP, key, database, demo data, assets, mail and the external services.';

    private int $failed = 0;

    public function handle(EtaService $eta, GeocodingService $geocoding, WeatherService $weather): int
    {
        $this->info('CargoTrace setup check');

        $this->check('PHP 8.2 or newer', fn () => [version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION]);
        $this->check('Application key (APP_KEY)', fn () => [filled(config('app.key')), filled(config('app.key')) ? 'set' : 'missing, run "php artisan key:generate"']);
        $this->check('Database connection', function () {
            $connection = DB::connection();
            $connection->getPdo();

            return [true, $connection->getDriverName().' / '.$connection->getDatabaseName()];
        });
        $this->check('Migrations executed', function () {
            $ok = Schema::hasTable('shipments') && Schema::hasTable('audit_logs') && Schema::hasTable('settings');

            return [$ok, $ok ? 'all required tables exist' : 'run "php artisan migrate"'];
        });
        $this->check('Demo users present', function () {
            $count = User::query()->count();

            return [$count >= 3, $count >= 3 ? "{$count} users" : "{$count} users, run \"php artisan migrate:fresh --seed\""];
        });
        $this->check('Demo shipments present', function () {
            $count = Shipment::query()->count();

            return [$count > 0, $count > 0 ? "{$count} shipments" : 'none, run "php artisan migrate:fresh --seed"'];
        });
        $this->check('Delay thresholds', function () use ($eta) {
            $t = $eta->thresholds(); // reads the settings table, so it must run inside check()

            return [$t['medium_max'] > $t['low_max'], "LOW up to {$t['low_max']} h, MEDIUM up to {$t['medium_max']} h, HIGH above"];
        });
        $this->check('Trade lanes (groups A/B/C)', function () use ($eta) {
            $lanes = TradeLane::query()->orderBy('code')->get();
            if ($lanes->isEmpty()) {
                return [true, 'none yet, run "php artisan migrate:fresh --seed"'];
            }

            return [true, $lanes->map(fn ($lane) => $lane->code.': '.$eta->thresholds($lane)['medium_max'].' h')->implode(', ')];
        });

        $this->check('Geocoding service (Nominatim)', function () use ($geocoding) {
            $r = $geocoding->search('Singapore', 1);

            return [$r['ok'] && $r['results'] !== [], $r['error'] ?? count($r['results']).' result(s)'];
        });
        $this->check('Weather service (Open-Meteo)', function () use ($weather) {
            $r = $weather->current(1.2644, 103.8222);

            return [$r['ok'], $r['error'] ?? $r['data']['summary'].', '.$r['data']['temperature_c'].' C'];
        });

        $this->newLine();
        $this->line('Info: compiled CSS (npm run build) '.(file_exists(public_path('build/manifest.json')) ? 'found' : 'not found, the Tailwind CDN fallback is used'));
        $this->line('Info: mail driver "'.config('mail.default').'", e-mail notifications '.(config('cargotrace.notifications.email') ? 'ON' : 'OFF'));

        if ($this->failed > 0) {
            $this->error("{$this->failed} check(s) failed.");

            return self::FAILURE;
        }

        $this->info('All checks passed.');

        return self::SUCCESS;
    }

    /** @param callable(): array{0: bool, 1: ?string} $callback */
    private function check(string $label, callable $callback): void
    {
        try {
            [$ok, $detail] = $callback();
        } catch (\Throwable $e) {
            [$ok, $detail] = [false, $e->getMessage()];
        }

        if (! $ok) {
            $this->failed++;
        }

        $this->line(sprintf('  [%s] %s%s', $ok ? ' OK ' : 'FAIL', $label, $detail ? ' - '.$detail : ''));
    }
}
