<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCarrierRequest;
use App\Models\Carrier;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Admin only (route group has role:admin). */
class CarrierController extends Controller
{
    public function index()
    {
        return view('carriers.index', ['carriers' => Carrier::query()->withCount('shipments')->orderBy('name')->paginate(15)]);
    }

    public function create()
    {
        return view('carriers.form', ['carrier' => null]);
    }

    public function store(StoreCarrierRequest $request, AuditService $audit): RedirectResponse
    {
        $carrier = Carrier::query()->create($request->validated());
        $audit->log('carrier.created', $carrier, null, $carrier->only(['name', 'code', 'mode']), $request->user());

        return redirect()->route('carriers.index')->with('status', 'Carrier created.');
    }

    public function edit(Carrier $carrier)
    {
        return view('carriers.form', ['carrier' => $carrier]);
    }

    public function update(StoreCarrierRequest $request, Carrier $carrier, AuditService $audit): RedirectResponse
    {
        $old = $carrier->only(['name', 'code', 'mode', 'is_active']);
        $carrier->update($request->validated());
        if ($carrier->wasChanged()) {
            $audit->log('carrier.updated', $carrier, $old, $carrier->only(['name', 'code', 'mode', 'is_active']), $request->user());
        }

        return redirect()->route('carriers.index')->with('status', 'Carrier updated.');
    }

    public function destroy(Request $request, Carrier $carrier, AuditService $audit): RedirectResponse
    {
        if ($carrier->shipments()->withTrashed()->exists()) {
            return back()->withErrors(['carrier' => 'This carrier is used by shipments. Deactivate it instead of deleting it.']);
        }

        $audit->log('carrier.deleted', $carrier, $carrier->only(['name', 'code']), null, $request->user());
        $carrier->delete();

        return redirect()->route('carriers.index')->with('status', 'Carrier deleted.');
    }
}
