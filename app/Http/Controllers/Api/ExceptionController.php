<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExceptionRequest;
use App\Http\Requests\UpdateExceptionRequest;
use App\Http\Resources\ExceptionResource;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\ExceptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExceptionController extends Controller
{
    public function __construct(private readonly ExceptionService $exceptions) {}

    public function index(Request $request, Shipment $shipment)
    {
        Gate::authorize('view', $shipment);

        return ExceptionResource::collection($shipment->exceptions()->orderByDesc('detected_at')->get());
    }

    public function store(StoreExceptionRequest $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('reportException', $shipment);

        $exception = $this->exceptions->open($shipment, $request->validated(), $request->user());

        return (new ExceptionResource($exception))->response()->setStatusCode(201);
    }

    public function update(UpdateExceptionRequest $request, ShipmentException $shipmentException): ExceptionResource
    {
        Gate::authorize('update', $shipmentException);

        return new ExceptionResource($this->exceptions->update($shipmentException, $request->validated(), $request->user()));
    }
}
