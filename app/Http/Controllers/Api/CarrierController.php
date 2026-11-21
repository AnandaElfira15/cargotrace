<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCarrierRequest;
use App\Http\Resources\CarrierResource;
use App\Models\Carrier;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarrierController extends Controller
{
    public function index(Request $request)
    {
        $carriers = Carrier::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->trim().'%'))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return CarrierResource::collection($carriers);
    }

    public function store(StoreCarrierRequest $request, AuditService $audit): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $carrier = Carrier::query()->create($data);
        $audit->log('carrier.created', $carrier, null, $carrier->only(['name', 'code', 'mode']), $request->user());

        return (new CarrierResource($carrier))->response()->setStatusCode(201);
    }
}
