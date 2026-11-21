<?php

namespace App\Enums;

enum ExceptionStatus: string
{
    case Open = 'OPEN';
    case Investigating = 'INVESTIGATING';
    case Resolved = 'RESOLVED';

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Investigating, self::Resolved],
            self::Investigating => [self::Resolved],
            self::Resolved => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Resolved;
    }

    public function label(): string
    {
        return ucfirst(strtolower($this->value));
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
