<?php

namespace App\Http\Controllers\Web;

use App\Enums\ExceptionType;
use App\Enums\Severity;
use App\Enums\ShipmentStatus;
use App\Enums\TrackingEventType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShipmentFilterRequest;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\TradeLane;
use App\Models\User;
use App\Services\EtaService;
use App\Services\ShipmentService;
use App\Services\TrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShipmentController extends Controller
{
    public function __construct(private readonly ShipmentService $shipments) {}

    public function index(ShipmentFilterRequest $request)
    {
        Gate::authorize('viewAny', Shipment::class);

        $filters = $request->validated();

        $shipments = Shipment::query()
            ->with(['customer', 'carrier', 'tradeLane', 'origin', 'destination'])
            ->visibleTo($request->user())
            ->filter($filters)
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return view('shipments.index', [
            'shipments' => $shipments,
            'filters' => $filters,
            'carriers' => Carrier::query()->orderBy('name')->get(),
            'lanes' => TradeLane::query()->orderBy('code')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'statuses' => ShipmentStatus::cases(),
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Shipment::class);

        return view('shipments.form', $this->formData(null));
    }

    public function store(StoreShipmentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Shipment::class);

        $shipment = $this->shipments->create($request->validated(), $request->user());

        return redirect()->route('shipments.show', $shipment)->with('status', "Shipment {$shipment->shipment_number} created.");
    }

    public function show(Request $request, Shipment $shipment, TrackingService $tracking, EtaService $eta)
    {
        Gate::authorize('view', $shipment);

        $shipment->load(['customer', 'carrier', 'tradeLane', 'origin', 'destination', 'items', 'exceptions.reporter']);

        $timeline = $tracking->timeline($shipment);
        $current = $tracking->currentLocation($shipment);

        $mapData = [
            'origin' => ['name' => $shipment->origin->name, 'lat' => $shipment->origin->latitude, 'lng' => $shipment->origin->longitude],
            'destination' => ['name' => $shipment->destination->name, 'lat' => $shipment->destination->latitude, 'lng' => $shipment->destination->longitude],
            'points' => $timeline->filter(fn ($e) => $e->hasCoordinates())->map(fn ($e) => [
                'lat' => $e->latitude,
                'lng' => $e->longitude,
                'label' => $e->status?->value ?? $e->event_type->value,
                'time' => $e->occurred_at->format('Y-m-d H:i'),
                'place' => $e->location?->name,
                'exception' => $e->is_exception,
            ])->values()->all(),
            'current' => $current ? ['lat' => $current->latitude, 'lng' => $current->longitude] : null,
        ];

        return view('shipments.show', [
            'shipment' => $shipment,
            'timeline' => $timeline,
            'current' => $current,
            'delay' => $eta->calculate($shipment),
            'mapData' => $mapData,
            'nextStatuses' => $shipment->status->allowedTransitions(),
            'locations' => Location::query()->orderBy('name')->get(),
            'eventTypes' => TrackingEventType::inputValues(),
            'exceptionTypes' => ExceptionType::cases(),
            'severities' => Severity::cases(),
        ]);
    }

    public function edit(Shipment $shipment)
    {
        Gate::authorize('update', $shipment);

        return view('shipments.form', $this->formData($shipment->load('items')));
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('update', $shipment);

        $this->shipments->update($shipment, $request->validated(), $request->user());

        return redirect()->route('shipments.show', $shipment)->with('status', 'Shipment updated.');
    }

    public function destroy(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('delete', $shipment);

        $this->shipments->delete($shipment, $request->user());

        return redirect()->route('shipments.index')->with('status', 'Shipment deleted.');
    }

    private function formData(?Shipment $shipment): array
    {
        return [
            'shipment' => $shipment,
            'customers' => User::query()->where('role', UserRole::Customer->value)->where('is_active', true)->orderBy('name')->get(),
            'carriers' => Carrier::query()->where('is_active', true)->orderBy('name')->get(),
            'lanes' => TradeLane::query()->where('is_active', true)->orderBy('code')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
        ];
    }
}
