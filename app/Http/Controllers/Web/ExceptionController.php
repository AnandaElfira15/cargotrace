<?php

namespace App\Http\Controllers\Web;

use App\Enums\ExceptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExceptionRequest;
use App\Http\Requests\UpdateExceptionRequest;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\ExceptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ExceptionController extends Controller
{
    public function __construct(private readonly ExceptionService $exceptions) {}

    /** Staff-only overview of all exceptions (route has role:admin,operator). */
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::in(ExceptionStatus::values())]]);

        $list = ShipmentException::query()
            ->with(['shipment', 'reporter'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByRaw("case status when 'OPEN' then 0 when 'INVESTIGATING' then 1 else 2 end")
            ->orderByDesc('detected_at')
            ->paginate(15)
            ->withQueryString();

        return view('exceptions.index', ['exceptions' => $list, 'statuses' => ExceptionStatus::cases(), 'selected' => $request->input('status')]);
    }

    public function store(StoreExceptionRequest $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('reportException', $shipment);

        $this->exceptions->open($shipment, $request->validated(), $request->user());

        return redirect()->route('shipments.show', $shipment)->with('status', 'Exception reported.');
    }

    public function update(UpdateExceptionRequest $request, ShipmentException $shipmentException): RedirectResponse
    {
        Gate::authorize('update', $shipmentException);

        $this->exceptions->update($shipmentException, $request->validated(), $request->user());

        return back()->with('status', 'Exception updated.');
    }
}
