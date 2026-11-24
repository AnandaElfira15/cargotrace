<?php

namespace App\Models;

use App\Enums\ExceptionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One "group scenario": route + delay thresholds + main exception + KPI (Groups A, B, C). */
class TradeLane extends Model
{
    use HasFactory;

    public const KPIS = [
        'on_time_delivery_rate' => 'On-time delivery rate',
        'average_delay_duration' => 'Average delay duration',
        'exception_resolution_time' => 'Exception resolution time',
    ];

    protected $fillable = [
        'code', 'name', 'route', 'delay_low_max_hours', 'delay_medium_max_hours',
        'primary_exception', 'kpi', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'delay_low_max_hours' => 'float',
            'delay_medium_max_hours' => 'float',
            'primary_exception' => ExceptionType::class,
            'is_active' => 'boolean',
        ];
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function label(): string
    {
        return "{$this->code} · {$this->route}";
    }

    public function kpiLabel(): string
    {
        return self::KPIS[$this->kpi] ?? $this->kpi;
    }
}
