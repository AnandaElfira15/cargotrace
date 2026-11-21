<?php

namespace App\Services;

use App\Enums\Severity;
use Carbon\CarbonInterface;

/** Immutable outcome of an ETA/delay calculation, including the values used. */
final class DelayResult
{
    public function __construct(
        public readonly bool $isDelayed,
        public readonly float $delayHours,
        public readonly ?Severity $severity,
        public readonly CarbonInterface $expectedArrival,
        public readonly CarbonInterface $effectiveArrival,
        public readonly string $basis,
        public readonly float $lowMaxHours,
        public readonly float $mediumMaxHours,
    ) {}

    public function explanation(): string
    {
        $rules = sprintf(
            'Rules: up to %s h = LOW, above %s h up to %s h = MEDIUM, above %s h = HIGH.',
            $this->fmt($this->lowMaxHours), $this->fmt($this->lowMaxHours), $this->fmt($this->mediumMaxHours), $this->fmt($this->mediumMaxHours)
        );

        if (! $this->isDelayed) {
            return sprintf(
                'On time: %s (%s) is not later than the expected arrival %s.',
                $this->effectiveArrival->format('Y-m-d H:i'), $this->basis, $this->expectedArrival->format('Y-m-d H:i')
            );
        }

        return sprintf(
            'Delayed by %s h: %s (%s) is later than the expected arrival %s. Severity %s. %s',
            $this->fmt($this->delayHours), $this->effectiveArrival->format('Y-m-d H:i'), $this->basis,
            $this->expectedArrival->format('Y-m-d H:i'), $this->severity?->value, $rules
        );
    }

    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
