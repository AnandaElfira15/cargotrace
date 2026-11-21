<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\UserNotification;
use App\Services\EtaService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EtaDelayTest extends TestCase
{
    use RefreshDatabase;

    private function shipmentWithEta(float $lateHours, ShipmentStatus $status = ShipmentStatus::InTransit): Shipment
    {
        $expected = Carbon::parse('2026-06-10 12:00:00');

        return Shipment::factory()->create([
            'status' => $status,
            'expected_departure_at' => $expected->copy()->subDays(10),
            'expected_arrival_at' => $expected,
            'estimated_arrival_at' => $expected->copy()->addMinutes((int) round($lateHours * 60)),
        ]);
    }

    public static function delayCases(): array
    {
        return [
            'early' => [-2.0, false, null],
            'exactly on time' => [0.0, false, null],
            '3 hours' => [3.0, true, 'LOW'],
            '6 hours is still LOW' => [6.0, true, 'LOW'],
            '6.5 hours' => [6.5, true, 'MEDIUM'],
            '24 hours is still MEDIUM' => [24.0, true, 'MEDIUM'],
            '24.5 hours' => [24.5, true, 'HIGH'],
            '72 hours' => [72.0, true, 'HIGH'],
        ];
    }

    #[DataProvider('delayCases')]
    public function test_delay_and_severity_rules(float $lateHours, bool $delayed, ?string $severity): void
    {
        $shipment = $this->shipmentWithEta($lateHours);

        $result = app(EtaService::class)->calculate($shipment, Carbon::parse('2026-06-01 00:00:00'));

        $this->assertSame($delayed, $result->isDelayed);
        $this->assertSame($severity, $result->severity?->value);
        if ($delayed) {
            $this->assertEqualsWithDelta($lateHours, $result->delayHours, 0.01);
        } else {
            $this->assertSame(0.0, $result->delayHours);
        }
    }

    public function test_threshold_is_configurable_per_group(): void
    {
        $shipment = $this->shipmentWithEta(18);
        $now = Carbon::parse('2026-06-01 00:00:00');

        // default 6 / 24 => MEDIUM
        $this->assertSame('MEDIUM', app(EtaService::class)->calculate($shipment, $now)->severity->value);

        // a group with a 12 hour threshold => HIGH
        app(SettingService::class)->set('delay.medium_max_hours', 12);
        $this->assertSame('HIGH', app(EtaService::class)->calculate($shipment, $now)->severity->value);
    }

    public function test_explanation_contains_the_values_used(): void
    {
        $result = app(EtaService::class)->calculate($this->shipmentWithEta(10), Carbon::parse('2026-06-01 00:00:00'));

        $text = $result->explanation();
        $this->assertStringContainsString('Delayed by 10 h', $text);
        $this->assertStringContainsString('2026-06-10 12:00', $text);
        $this->assertStringContainsString('MEDIUM', $text);
    }

    public function test_moving_shipment_past_its_eta_is_late_by_at_least_now(): void
    {
        $expected = Carbon::parse('2026-06-10 12:00:00');
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create([
            'expected_departure_at' => $expected->copy()->subDays(10),
            'expected_arrival_at' => $expected,
            'estimated_arrival_at' => null,
        ]);

        $result = app(EtaService::class)->calculate($shipment, Carbon::parse('2026-06-11 18:00:00'));

        $this->assertTrue($result->isDelayed);
        $this->assertSame(30.0, $result->delayHours);
        $this->assertSame('HIGH', $result->severity->value);
    }

    public function test_shipment_that_has_not_started_is_not_marked_late_by_the_clock(): void
    {
        $expected = Carbon::parse('2026-06-10 12:00:00');
        $shipment = Shipment::factory()->status(ShipmentStatus::Booked)->create([
            'expected_departure_at' => $expected->copy()->subDays(10),
            'expected_arrival_at' => $expected,
        ]);

        $this->assertFalse(app(EtaService::class)->calculate($shipment, Carbon::parse('2026-06-20 00:00:00'))->isDelayed);
    }

    public function test_delivered_shipment_uses_actual_arrival(): void
    {
        $expected = Carbon::parse('2026-06-10 12:00:00');
        $shipment = Shipment::factory()->status(ShipmentStatus::Delivered)->create([
            'expected_departure_at' => $expected->copy()->subDays(10),
            'expected_arrival_at' => $expected,
            'actual_arrival_at' => $expected->copy()->addHours(4),
        ]);

        $result = app(EtaService::class)->calculate($shipment, Carbon::parse('2026-07-01 00:00:00'));

        $this->assertTrue($result->isDelayed);
        $this->assertSame(4.0, $result->delayHours);
        $this->assertSame('LOW', $result->severity->value);
    }

    public function test_evaluate_persists_the_result_and_notifies_the_customer_once(): void
    {
        $shipment = $this->shipmentWithEta(10);
        $eta = app(EtaService::class);

        $now = Carbon::parse('2026-06-01 00:00:00');
        $eta->evaluate($shipment, $now);
        $eta->evaluate($shipment->refresh(), $now); // unchanged => no duplicate notification

        $shipment->refresh();
        $this->assertTrue($shipment->is_delayed);
        $this->assertSame(10.0, $shipment->delay_hours);
        $this->assertSame('MEDIUM', $shipment->delay_severity->value);
        $this->assertSame(1, UserNotification::query()->where('user_id', $shipment->customer_id)->where('type', 'shipment.delayed')->count());
    }

    public function test_evaluate_delays_command_flags_overdue_shipments(): void
    {
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create([
            'expected_departure_at' => now()->subDays(20),
            'expected_arrival_at' => now()->subDays(2),
            'estimated_arrival_at' => null,
        ]);

        $this->artisan('cargotrace:evaluate-delays')->assertSuccessful();

        $this->assertTrue($shipment->refresh()->is_delayed);
        $this->assertSame('HIGH', $shipment->delay_severity->value);
    }
}
