<?php

namespace App\Services;

use App\Enums\Severity;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Carbon\CarbonInterface;

/**
 * Deterministic ETA / delay rules (no AI/ML):
 *   - effective arrival = actual arrival, else ETA, else expected arrival
 *   - an undelivered shipment that is already moving and past its ETA is at least "now" late
 *   - delayed when effective arrival > expected arrival
 *   - severity: <= low_max => LOW, <= medium_max => MEDIUM, else HIGH (thresholds configurable)
 */
class EtaService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly NotificationService $notifications,
    ) {}

    /** @return array{low_max: float, medium_max: float} */
    public function thresholds(): array
    {
        return [
            'low_max' => (float) $this->settings->get('delay.low_max_hours', config('cargotrace.delay.low_max_hours')),
            'medium_max' => (float) $this->settings->get('delay.medium_max_hours', config('cargotrace.delay.medium_max_hours')),
        ];
    }

    public function classify(float $delayHours): Severity
    {
        $t = $this->thresholds();

        return match (true) {
            $delayHours <= $t['low_max'] => Severity::Low,
            $delayHours <= $t['medium_max'] => Severity::Medium,
            default => Severity::High,
        };
    }

    /** Pure calculation, nothing is persisted. */
    public function calculate(Shipment $shipment, ?CarbonInterface $now = null): DelayResult
    {
        $now ??= now();
        $t = $this->thresholds();
        $expected = $shipment->expected_arrival_at;

        if ($shipment->actual_arrival_at) {
            $effective = $shipment->actual_arrival_at;
            $basis = 'actual arrival';
        } else {
            $effective = $shipment->estimated_arrival_at ?? $expected;
            $basis = $shipment->estimated_arrival_at ? 'estimated arrival (ETA)' : 'expected arrival, no ETA recorded';

            $inMotion = in_array($shipment->status, [ShipmentStatus::PickedUp, ShipmentStatus::InTransit], true);
            if ($inMotion && $now->greaterThan($effective)) {
                $effective = $now;
                $basis = 'current time, shipment is past its ETA and not delivered';
            }
        }

        $isDelayed = $effective->greaterThan($expected);
        $hours = $isDelayed ? round($expected->diffInMinutes($effective) / 60, 2) : 0.0;

        return new DelayResult(
            isDelayed: $isDelayed,
            delayHours: $hours,
            severity: $isDelayed ? $this->classify($hours) : null,
            expectedArrival: $expected,
            effectiveArrival: $effective,
            basis: $basis,
            lowMaxHours: $t['low_max'],
            mediumMaxHours: $t['medium_max'],
        );
    }

    /** Calculate, store on the shipment and notify when a delay appears or its severity changes. */
    public function evaluate(Shipment $shipment, ?CarbonInterface $now = null): DelayResult
    {
        $result = $this->calculate($shipment, $now);

        if ($shipment->status === ShipmentStatus::Cancelled) {
            $shipment->forceFill(['is_delayed' => false, 'delay_hours' => 0, 'delay_severity' => null])->save();

            return $this->calculate($shipment, $now);
        }

        $wasDelayed = (bool) $shipment->is_delayed;
        $previousSeverity = $shipment->delay_severity;

        $shipment->forceFill([
            'is_delayed' => $result->isDelayed,
            'delay_hours' => $result->delayHours,
            'delay_severity' => $result->severity,
        ])->save();

        $changed = ! $wasDelayed || $previousSeverity !== $result->severity;
        if ($result->isDelayed && $changed && $shipment->status !== ShipmentStatus::Delivered) {
            $this->notifications->notifyStakeholders(
                $shipment,
                'shipment.delayed',
                "Shipment {$shipment->shipment_number} is delayed",
                $result->explanation()
            );
        }

        return $result;
    }
}
