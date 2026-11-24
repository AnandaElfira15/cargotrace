<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TradeLane;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * All dashboard numbers come from the database. Customers only see their own shipments.
 * Optionally scoped to one trade lane (Group A/B/C); the per-lane breakdown is always included.
 */
class DashboardService
{
    public function __construct(private readonly EtaService $eta) {}

    /** @return array<string, mixed> */
    public function metrics(User $user, ?int $laneId = null): array
    {
        $scope = fn (Builder $q): Builder => $q->visibleTo($user)
            ->when($laneId, fn (Builder $q2) => $q2->where('shipments.trade_lane_id', $laneId));
        $base = fn (): Builder => $scope(Shipment::query());

        $total = $base()->count();
        $active = $base()->where('status', '!=', ShipmentStatus::Cancelled->value)->count();
        $delayed = $base()->where('status', '!=', ShipmentStatus::Cancelled->value)->where('is_delayed', true)->count();
        $withExceptions = $base()->where('status', '!=', ShipmentStatus::Cancelled->value)->whereHas('exceptions')->count();

        $openExceptions = ShipmentException::query()
            ->whereIn('status', [ExceptionStatus::Open->value, ExceptionStatus::Investigating->value])
            ->whereHas('shipment', fn ($q) => $scope($q))
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

        $kpis = $this->kpis($base, $scope);
        $statusLabels = array_map(fn (ShipmentStatus $s) => $s->value, ShipmentStatus::cases());
        $selectedLane = $laneId ? TradeLane::query()->find($laneId) : null;

        return [
            'totals' => [
                'shipments' => $total,
                'in_transit' => (int) ($byStatus[ShipmentStatus::InTransit->value] ?? 0),
                'delivered' => $this->countStatus($base, ShipmentStatus::Delivered),
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
            'kpis' => $kpis,
            'primary_kpi' => $selectedLane?->kpi ?? config('cargotrace.group.kpi'),
            'selected_lane' => $selectedLane ? ['id' => $selectedLane->id, 'code' => $selectedLane->code, 'name' => $selectedLane->name, 'route' => $selectedLane->route] : null,
            'lanes' => $this->lanes($user),
        ];
    }

    private function countStatus(callable $base, ShipmentStatus $status): int
    {
        return $base()->where('status', $status->value)->count();
    }

    /**
     * @param callable(): Builder $base
     * @param callable(Builder): Builder $scope
     * @return array{on_time_delivery_rate: ?float, average_delay_duration: ?float, exception_resolution_time: ?float}
     */
    private function kpis(callable $base, callable $scope): array
    {
        $delivered = $this->countStatus($base, ShipmentStatus::Delivered);
        $deliveredOnTime = $base()->where('status', ShipmentStatus::Delivered->value)->where('is_delayed', false)->count();
        $avgDelay = $base()->where('is_delayed', true)->where('status', '!=', ShipmentStatus::Cancelled->value)->avg('delay_hours');

        $resolved = ShipmentException::query()
            ->whereNotNull('resolved_at')
            ->whereHas('shipment', fn ($q) => $scope($q))
            ->get(['detected_at', 'resolved_at']);

        return [
            'on_time_delivery_rate' => $delivered > 0 ? round($deliveredOnTime / $delivered * 100, 1) : null,
            'average_delay_duration' => $avgDelay !== null ? round((float) $avgDelay, 1) : null,
            'exception_resolution_time' => $resolved->isEmpty()
                ? null
                : round($resolved->avg(fn ($e) => $e->detected_at->diffInMinutes($e->resolved_at) / 60), 1),
        ];
    }

    /** One row per active trade lane (Group A/B/C) with its own parameters and KPIs. */
    private function lanes(User $user): array
    {
        return TradeLane::query()->where('is_active', true)->orderBy('code')->get()->map(function (TradeLane $lane) use ($user) {
            $scope = fn (Builder $q): Builder => $q->visibleTo($user)->where('shipments.trade_lane_id', $lane->id);
            $base = fn (): Builder => $scope(Shipment::query());
            $thresholds = $this->eta->thresholds($lane);

            return [
                'id' => $lane->id,
                'code' => $lane->code,
                'name' => $lane->name,
                'route' => $lane->route,
                'low_hours' => $thresholds['low_max'],
                'threshold_hours' => $thresholds['medium_max'],
                'primary_exception' => $lane->primary_exception->value,
                'primary_exception_label' => $lane->primary_exception->label(),
                'primary_exception_count' => ShipmentException::query()
                    ->where('type', $lane->primary_exception->value)
                    ->whereHas('shipment', fn ($q) => $scope($q))
                    ->count(),
                'kpi' => $lane->kpi,
                'kpi_label' => $lane->kpiLabel(),
                'kpis' => $this->kpis($base, $scope),
                'shipments' => $base()->count(),
                'delayed' => $base()->where('status', '!=', ShipmentStatus::Cancelled->value)->where('is_delayed', true)->count(),
                'open_exceptions' => ShipmentException::query()
                    ->whereIn('status', [ExceptionStatus::Open->value, ExceptionStatus::Investigating->value])
                    ->whereHas('shipment', fn ($q) => $scope($q))
                    ->count(),
            ];
        })->values()->all();
    }
}
