<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrackingEventRequest;
use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TrackingController extends Controller
{
    public function store(StoreTrackingEventRequest $request, Shipment $shipment, TrackingService $tracking): RedirectResponse
    {
        Gate::authorize('addTracking', $shipment);

        $tracking->record($shipment, $request->validated(), $request->user());

        return redirect()->route('shipments.show', $shipment)->with('status', 'Tracking event added.');
    }
}
