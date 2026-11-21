<?php

namespace App\Enums;

/**
 * Shipment lifecycle. The transition map is the single source of truth;
 * TrackingService validates every status change against it on the backend.
 *
 * CREATED -> BOOKED -> PICKED_UP -> IN_TRANSIT -> DELIVERED
 * CREATED/BOOKED can also be CANCELLED. DELIVERED and CANCELLED are terminal.
 */
enum ShipmentStatus: string
{
    case Created = 'CREATED';
    case Booked = 'BOOKED';
    case PickedUp = 'PICKED_UP';
    case InTransit = 'IN_TRANSIT';
    case Delivered = 'DELIVERED';
    case Cancelled = 'CANCELLED';

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Created => [self::Booked, self::Cancelled],
            self::Booked => [self::PickedUp, self::Cancelled],
            self::PickedUp => [self::InTransit],
            self::InTransit => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $this->value)));
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
