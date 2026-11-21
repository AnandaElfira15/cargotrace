<?php

namespace Tests\Unit;

use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StatusRulesTest extends TestCase
{
    public static function validShipmentTransitions(): array
    {
        return [
            ['CREATED', 'BOOKED'], ['CREATED', 'CANCELLED'], ['BOOKED', 'PICKED_UP'], ['BOOKED', 'CANCELLED'],
            ['PICKED_UP', 'IN_TRANSIT'], ['IN_TRANSIT', 'DELIVERED'],
        ];
    }

    public static function invalidShipmentTransitions(): array
    {
        return [
            ['CREATED', 'DELIVERED'], ['CREATED', 'IN_TRANSIT'], ['BOOKED', 'DELIVERED'], ['PICKED_UP', 'BOOKED'],
            ['PICKED_UP', 'CANCELLED'], ['IN_TRANSIT', 'CANCELLED'], ['IN_TRANSIT', 'BOOKED'],
            ['DELIVERED', 'CREATED'], ['DELIVERED', 'IN_TRANSIT'], ['CANCELLED', 'BOOKED'], ['CREATED', 'CREATED'],
        ];
    }

    #[DataProvider('validShipmentTransitions')]
    public function test_valid_shipment_transitions(string $from, string $to): void
    {
        $this->assertTrue(ShipmentStatus::from($from)->canTransitionTo(ShipmentStatus::from($to)));
    }

    #[DataProvider('invalidShipmentTransitions')]
    public function test_invalid_shipment_transitions(string $from, string $to): void
    {
        $this->assertFalse(ShipmentStatus::from($from)->canTransitionTo(ShipmentStatus::from($to)));
    }

    public function test_terminal_statuses(): void
    {
        $this->assertTrue(ShipmentStatus::Delivered->isTerminal());
        $this->assertTrue(ShipmentStatus::Cancelled->isTerminal());
        $this->assertFalse(ShipmentStatus::InTransit->isTerminal());
    }

    public function test_exception_status_flow(): void
    {
        $this->assertTrue(ExceptionStatus::Open->canTransitionTo(ExceptionStatus::Investigating));
        $this->assertTrue(ExceptionStatus::Open->canTransitionTo(ExceptionStatus::Resolved));
        $this->assertTrue(ExceptionStatus::Investigating->canTransitionTo(ExceptionStatus::Resolved));
        $this->assertFalse(ExceptionStatus::Investigating->canTransitionTo(ExceptionStatus::Open));
        $this->assertFalse(ExceptionStatus::Resolved->canTransitionTo(ExceptionStatus::Open));
        $this->assertTrue(ExceptionStatus::Resolved->isTerminal());
    }
}
