<?php

namespace App\Http\Controllers\Web;

use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        $user = $request->user();

        $delayed = Shipment::query()->visibleTo($user)
            ->with(['carrier', 'origin', 'destination'])
            ->where('is_delayed', true)
            ->whereNotIn('status', [ShipmentStatus::Delivered->value, ShipmentStatus::Cancelled->value])
            ->orderByDesc('delay_hours')
            ->limit(6)
            ->get();

        $exceptions = ShipmentException::query()
            ->with('shipment')
            ->whereIn('status', [ExceptionStatus::Open->value, ExceptionStatus::Investigating->value])
            ->whereHas('shipment', fn ($q) => $q->visibleTo($user))
            ->orderByDesc('detected_at')
            ->limit(6)
            ->get();

        return view('dashboard', [
            'metrics' => $dashboard->metrics($user),
            'delayed' => $delayed,
            'exceptions' => $exceptions,
        ]);
    }

    public function data(Request $request, DashboardService $dashboard): JsonResponse
    {
        return response()->json($dashboard->metrics($request->user()));
    }
}
