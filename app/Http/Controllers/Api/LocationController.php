<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $locations = Location::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->trim().'%'))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return LocationResource::collection($locations);
    }

    public function store(StoreLocationRequest $request, AuditService $audit): JsonResponse
    {
        $location = Location::query()->create($request->validated());
        $audit->log('location.created', $location, null, $location->only(['name', 'code', 'latitude', 'longitude']), $request->user());

        return (new LocationResource($location))->response()->setStatusCode(201);
    }
}
