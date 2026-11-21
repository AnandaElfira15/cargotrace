<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrackingEventRequest;
use App\Http\Resources\LocationResource;
use App\Http\Resources\TrackingEventResource;
use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TrackingController extends Controller
{
    public function __construct(private readonly TrackingService $tracking) {}

    public function index(Request $request, Shipment $shipment)
    {
        Gate::authorize('view', $shipment);

        $current = $this->tracking->currentLocation($shipment);

        return TrackingEventResource::collection($this->tracking->timeline($shipment))->additional([
            'meta' => [
                'shipment_status' => $shipment->status->value,
                'current_location' => $current ? [
                    'event_id' => $current->id,
                    'latitude' => $current->latitude,
                    'longitude' => $current->longitude,
                    'occurred_at' => $current->occurred_at->toIso8601String(),
                    'location' => $current->location ? new LocationResource($current->location) : null,
                ] : null,
            ],
        ]);
    }

    public function store(StoreTrackingEventRequest $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('addTracking', $shipment);

        $event = $this->tracking->record($shipment, $request->validated(), $request->user());

        return (new TrackingEventResource($event))->response()->setStatusCode(201);
    }
}
