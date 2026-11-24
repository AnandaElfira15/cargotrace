<?php

namespace App\Http\Controllers\Web;

use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TradeLane;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        $request->validate(['lane' => ['nullable', 'integer', 'exists:trade_lanes,id']]);

        $user = $request->user();
        $laneId = $request->integer('lane') ?: null;

        $delayed = Shipment::query()->visibleTo($user)
            ->with(['carrier', 'tradeLane', 'origin', 'destination'])
            ->when($laneId, fn ($q) => $q->where('trade_lane_id', $laneId))
            ->where('is_delayed', true)
            ->whereNotIn('status', [ShipmentStatus::Delivered->value, ShipmentStatus::Cancelled->value])
            ->orderByDesc('delay_hours')
            ->limit(6)
            ->get();

        $exceptions = ShipmentException::query()
            ->with('shipment')
            ->whereIn('status', [ExceptionStatus::Open->value, ExceptionStatus::Investigating->value])
            ->whereHas('shipment', fn ($q) => $q->visibleTo($user)->when($laneId, fn ($q2) => $q2->where('shipments.trade_lane_id', $laneId)))
            ->orderByDesc('detected_at')
            ->limit(6)
            ->get();

        return view('dashboard', [
            'metrics' => $dashboard->metrics($user, $laneId),
            'delayed' => $delayed,
            'exceptions' => $exceptions,
            'lanes' => TradeLane::query()->where('is_active', true)->orderBy('code')->get(),
            'laneId' => $laneId,
        ]);
    }

    public function data(Request $request, DashboardService $dashboard): JsonResponse
    {
        $request->validate(['lane' => ['nullable', 'integer', 'exists:trade_lanes,id']]);

        return response()->json($dashboard->metrics($request->user(), $request->integer('lane') ?: null));
    }
}
