@extends('layouts.app')
@php $editing = $shipment !== null; @endphp
@section('title', $editing ? 'Edit shipment' : 'New shipment')
@section('heading', $editing ? 'Edit '.$shipment->shipment_number : 'New shipment')

@php
    $val = fn (string $field, $default = '') => old($field, $editing ? $shipment->{$field} : $default);
    $dt = fn (string $field) => old($field, $editing && $shipment->{$field} ? $shipment->{$field}->format('Y-m-d\TH:i') : '');
    $items = old('items', $editing ? $shipment->items->map(fn ($i) => $i->only(['description', 'quantity', 'weight_kg', 'volume_cbm', 'hs_code']))->all() : []);
    while (count($items) < 2) { $items[] = ['description' => '', 'quantity' => 1, 'weight_kg' => '', 'volume_cbm' => '', 'hs_code' => '']; }
@endphp

@section('content')
    <form method="POST" action="{{ $editing ? route('shipments.update', $shipment) : route('shipments.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card grid gap-4 sm:grid-cols-2">
            @if ($editing)
                <div>
                    <label class="label">Shipment number</label>
                    <input class="input" name="shipment_number" value="{{ $val('shipment_number') }}" required>
                </div>
            @else
                <div>
                    <label class="label">Shipment number</label>
                    <input class="input" name="shipment_number" value="{{ old('shipment_number') }}" placeholder="Leave empty to auto-generate">
                </div>
            @endif
            <div>
                <label class="label">Customer</label>
                <select class="input" name="customer_id" required>
                    <option value="">Select customer…</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" @selected((string) $val('customer_id') === (string) $c->id)>{{ $c->name }}{{ $c->company ? ' · '.$c->company : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Carrier</label>
                <select class="input" name="carrier_id" required>
                    <option value="">Select carrier…</option>
                    @foreach ($carriers as $c)
                        <option value="{{ $c->id }}" @selected((string) $val('carrier_id') === (string) $c->id)>{{ $c->name }} ({{ $c->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Trade lane (group scenario)</label>
                <select class="input" name="trade_lane_id">
                    <option value="">None: use global thresholds</option>
                    @foreach ($lanes as $l)
                        <option value="{{ $l->id }}" @selected((string) $val('trade_lane_id') === (string) $l->id)>{{ $l->name }} · {{ $l->route }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-navy-500">The lane decides the delay thresholds and the default exception type.</p>
            </div>
            <div>
                <label class="label">Cargo type</label>
                <input class="input" name="cargo_type" value="{{ $val('cargo_type') }}" placeholder="e.g. Palm oil derivatives" required>
            </div>
            <div>
                <label class="label">Origin</label>
                <select class="input" name="origin_location_id" required>
                    <option value="">Select origin…</option>
                    @foreach ($locations as $l)
                        <option value="{{ $l->id }}" @selected((string) $val('origin_location_id') === (string) $l->id)>{{ $l->name }} ({{ $l->country }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Destination</label>
                <select class="input" name="destination_location_id" required>
                    <option value="">Select destination…</option>
                    @foreach ($locations as $l)
                        <option value="{{ $l->id }}" @selected((string) $val('destination_location_id') === (string) $l->id)>{{ $l->name }} ({{ $l->country }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Weight (kg)</label>
                <input class="input" type="number" step="0.01" min="0.01" name="weight_kg" value="{{ $val('weight_kg') }}" required>
            </div>
            <div>
                <label class="label">Volume (cbm)</label>
                <input class="input" type="number" step="0.01" min="0.01" name="volume_cbm" value="{{ $val('volume_cbm') }}" required>
            </div>
            <div>
                <label class="label">Expected departure</label>
                <input class="input" type="datetime-local" name="expected_departure_at" value="{{ $dt('expected_departure_at') }}" required>
            </div>
            <div>
                <label class="label">Expected arrival</label>
                <input class="input" type="datetime-local" name="expected_arrival_at" value="{{ $dt('expected_arrival_at') }}" required>
            </div>
            <div class="sm:col-span-2">
                <label class="label">Estimated arrival / ETA (optional)</label>
                <input class="input sm:max-w-xs" type="datetime-local" name="estimated_arrival_at" value="{{ $dt('estimated_arrival_at') }}">
                <p class="mt-1 text-xs text-navy-500">If the ETA is later than the expected arrival, the shipment is flagged as delayed.</p>
            </div>
        </div>

        <div class="card">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold">Cargo items</h2>
                <button type="button" id="addItem" class="btn btn-outline btn-sm">Add item</button>
            </div>
            <div id="items" class="space-y-2">
                @foreach ($items as $i => $item)
                    <div class="grid gap-2 sm:grid-cols-12" data-item>
                        <input class="input sm:col-span-4" name="items[{{ $i }}][description]" value="{{ $item['description'] ?? '' }}" placeholder="Description">
                        <input class="input sm:col-span-2" type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" placeholder="Qty">
                        <input class="input sm:col-span-2" type="number" step="0.01" min="0" name="items[{{ $i }}][weight_kg]" value="{{ $item['weight_kg'] ?? '' }}" placeholder="kg">
                        <input class="input sm:col-span-2" type="number" step="0.01" min="0" name="items[{{ $i }}][volume_cbm]" value="{{ $item['volume_cbm'] ?? '' }}" placeholder="cbm">
                        <input class="input sm:col-span-2" name="items[{{ $i }}][hs_code]" value="{{ $item['hs_code'] ?? '' }}" placeholder="HS code">
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-navy-500">Rows without a description are ignored.</p>
        </div>

        <div class="flex gap-2">
            <button class="btn btn-primary" type="submit">{{ $editing ? 'Save changes' : 'Create shipment' }}</button>
            <a class="btn btn-outline" href="{{ $editing ? route('shipments.show', $shipment) : route('shipments.index') }}">Cancel</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.getElementById('addItem').addEventListener('click', () => {
            const wrap = document.getElementById('items');
            const i = wrap.querySelectorAll('[data-item]').length;
            const row = wrap.querySelector('[data-item]').cloneNode(true);
            row.querySelectorAll('input').forEach(input => {
                input.name = input.name.replace(/items\[\d+\]/, 'items[' + i + ']');
                input.value = input.name.includes('[quantity]') ? 1 : '';
            });
            wrap.appendChild(row);
        });
    </script>
@endpush
