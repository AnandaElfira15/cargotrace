<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Enums\TrackingEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    protected $fillable = [
        'event_type', 'status', 'occurred_at', 'location_id', 'latitude', 'longitude',
        'description', 'is_exception', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => TrackingEventType::class,
            'status' => ShipmentStatus::class,
            'occurred_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_exception' => 'boolean',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
