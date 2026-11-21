<?php

namespace App\Enums;

enum TrackingEventType: string
{
    case StatusUpdate = 'STATUS_UPDATE';
    case LocationUpdate = 'LOCATION_UPDATE';
    case Note = 'NOTE';
    case Exception = 'EXCEPTION'; // created automatically when an exception is reported

    /** Types an operator may submit directly (EXCEPTION is system generated). */
    public static function inputValues(): array
    {
        return [self::StatusUpdate->value, self::LocationUpdate->value, self::Note->value];
    }

    public function label(): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $this->value)));
    }
}
