<?php

namespace App\Models;

use App\Enums\Severity;
use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Shipment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Only business input is mass assignable. status, delay fields, actual_arrival_at
     * and created_by are system managed by the service layer.
     */
    protected $fillable = [
        'shipment_number', 'customer_id', 'carrier_id', 'origin_location_id', 'destination_location_id',
        'cargo_type', 'weight_kg', 'volume_cbm',
        'expected_departure_at', 'expected_arrival_at', 'estimated_arrival_at',
    ];

    public const SORTABLE = ['shipment_number', 'status', 'expected_departure_at', 'expected_arrival_at', 'delay_hours', 'created_at'];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'delay_severity' => Severity::class,
            'weight_kg' => 'float',
            'volume_cbm' => 'float',
            'delay_hours' => 'float',
            'is_delayed' => 'boolean',
            'expected_departure_at' => 'datetime',
            'expected_arrival_at' => 'datetime',
            'estimated_arrival_at' => 'datetime',
            'actual_arrival_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ShipmentException::class);
    }

    /** Customers only ever see their own shipments; staff see everything. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::Customer) {
            $query->where('shipments.customer_id', $user->id);
        }

        return $query;
    }

    /**
     * Server-side search, filter and sorting. Input is validated by ShipmentFilterRequest.
     */
    public function scopeFilter(Builder $query, array $f): Builder
    {
        $query
            ->when(filled($f['q'] ?? null), fn (Builder $q) => $q->where('shipments.shipment_number', 'like', '%'.trim($f['q']).'%'))
            ->when(filled($f['status'] ?? null), fn (Builder $q) => $q->where('shipments.status', $f['status']))
            ->when(filled($f['carrier_id'] ?? null), fn (Builder $q) => $q->where('shipments.carrier_id', $f['carrier_id']))
            ->when(filled($f['origin_id'] ?? null), fn (Builder $q) => $q->where('shipments.origin_location_id', $f['origin_id']))
            ->when(filled($f['destination_id'] ?? null), fn (Builder $q) => $q->where('shipments.destination_location_id', $f['destination_id']))
            ->when(filled($f['date_from'] ?? null), fn (Builder $q) => $q->where('shipments.expected_departure_at', '>=', Carbon::parse($f['date_from'])->startOfDay()))
            ->when(filled($f['date_to'] ?? null), fn (Builder $q) => $q->where('shipments.expected_departure_at', '<=', Carbon::parse($f['date_to'])->endOfDay()))
            ->when(filled($f['delayed'] ?? null), fn (Builder $q) => $q->where('shipments.is_delayed', filter_var($f['delayed'], FILTER_VALIDATE_BOOLEAN)));

        $sort = in_array($f['sort'] ?? null, self::SORTABLE, true) ? $f['sort'] : 'created_at';
        $direction = ($f['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy('shipments.'.$sort, $direction)->orderByDesc('shipments.id');
    }
}
