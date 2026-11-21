<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Models\Location;
use App\Models\Shipment;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Admin only (route group has role:admin). */
class LocationController extends Controller
{
    public function index()
    {
        return view('locations.index', ['locations' => Location::query()->orderBy('name')->paginate(15)]);
    }

    public function create()
    {
        return view('locations.form', ['location' => null]);
    }

    public function store(StoreLocationRequest $request, AuditService $audit): RedirectResponse
    {
        $location = Location::query()->create($request->validated());
        $audit->log('location.created', $location, null, $location->only(['name', 'code', 'latitude', 'longitude']), $request->user());

        return redirect()->route('locations.index')->with('status', 'Location created.');
    }

    public function edit(Location $location)
    {
        return view('locations.form', ['location' => $location]);
    }

    public function update(StoreLocationRequest $request, Location $location, AuditService $audit): RedirectResponse
    {
        $old = $location->only(['name', 'code', 'latitude', 'longitude']);
        $location->update($request->validated());
        $audit->log('location.updated', $location, $old, $location->only(['name', 'code', 'latitude', 'longitude']), $request->user());

        return redirect()->route('locations.index')->with('status', 'Location updated.');
    }

    public function destroy(Request $request, Location $location, AuditService $audit): RedirectResponse
    {
        $inUse = Shipment::withTrashed()
            ->where('origin_location_id', $location->id)
            ->orWhere('destination_location_id', $location->id)
            ->exists();

        if ($inUse) {
            return back()->withErrors(['location' => 'This location is used by shipments and cannot be deleted.']);
        }

        $audit->log('location.deleted', $location, $location->only(['name', 'code']), null, $request->user());
        $location->delete();

        return redirect()->route('locations.index')->with('status', 'Location deleted.');
    }
}
