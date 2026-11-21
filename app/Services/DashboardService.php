<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** All dashboard numbers come from the database; customers only see their own shipments. */
class DashboardService
{
    /** @return array<string, mixed> */
    public function metrics(User $user): array
    {
        $base = fn () => Shipment::query()->visibleTo($user);

        $total = $base()->count();
        $active = $base()->where('status', '!=', ShipmentStatus::Cancelled->value)->count();
        $delivered = $base()->where('status', ShipmentStatus::Delivered->value)->count();
        $deliveredOnTime = $base()->where('status', ShipmentStatus::Delivered->value)->where('is_delayed', false)->count();
        $delayed = $base()->where('status', '!=', ShipmentStatus::Cancelled->value)->where('is_delayed', true)->count();
        $withExceptions = $base()->where('status', '!=', ShipmentStatus::Cancelled->value)->whereHas('exceptions')->count();

        $openExceptions = ShipmentException::query()
            ->whereIn('status', [ExceptionStatus::Open->value, ExceptionStatus::Investigating->value])
            ->whereHas('shipment', fn ($q) => $q->visibleTo($user))
            ->count();

        $byStatus = $base()
            ->select('shipments.status', DB::raw('count(*) as total'))
            ->groupBy('shipments.status')
            ->pluck('total', 'status');

        $byCarrier = $base()
            ->join('carriers', 'carriers.id', '=', 'shipments.carrier_id')
            ->select('carriers.name', DB::raw('count(*) as total'))
            ->groupBy('carriers.name')
            ->orderByDesc('total')
            ->pluck('total', 'name');

        $avgDelay = $base()->where('is_delayed', true)->where('status', '!=', ShipmentStatus::Cancelled->value)->avg('delay_hours');

        $resolved = ShipmentException::query()
            ->whereNotNull('resolved_at')
            ->whereHas('shipment', fn ($q) => $q->visibleTo($user))
            ->get(['detected_at', 'resolved_at']);
        $avgResolution = $resolved->isEmpty()
            ? null
            : round($resolved->avg(fn ($e) => $e->detected_at->diffInMinutes($e->resolved_at) / 60), 1);

        $statusLabels = array_map(fn (ShipmentStatus $s) => $s->value, ShipmentStatus::cases());

        return [
            'totals' => [
                'shipments' => $total,
                'in_transit' => (int) ($byStatus[ShipmentStatus::InTransit->value] ?? 0),
                'delivered' => $delivered,
                'delayed' => $delayed,
                'open_exceptions' => $openExceptions,
            ],
            'by_status' => [
                'labels' => $statusLabels,
                'values' => array_map(fn ($s) => (int) ($byStatus[$s] ?? 0), $statusLabels),
            ],
            'by_carrier' => [
                'labels' => $byCarrier->keys()->values()->all(),
                'values' => $byCarrier->values()->map(fn ($v) => (int) $v)->all(),
            ],
            'rates' => [
                'delay_rate' => $active > 0 ? round($delayed / $active * 100, 1) : 0.0,
                'exception_rate' => $active > 0 ? round($withExceptions / $active * 100, 1) : 0.0,
            ],
            'kpis' => [
                'on_time_delivery_rate' => $delivered > 0 ? round($deliveredOnTime / $delivered * 100, 1) : null,
                'average_delay_duration' => $avgDelay !== null ? round((float) $avgDelay, 1) : null,
                'exception_resolution_time' => $avgResolution,
            ],
            'primary_kpi' => config('cargotrace.group.kpi'),
        ];
    }
}
