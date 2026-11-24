@extends('layouts.app')
@section('title', 'Shipments')
@section('heading', 'Shipments')

@section('content')
    <form method="GET" action="{{ route('shipments.index') }}" class="card mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
            <label class="label" for="q">Shipment number</label>
            <input class="input" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="e.g. CT2611…">
        </div>
        <div>
            <label class="label" for="status">Status</label>
            <select class="input" id="status" name="status">
                <option value="">All</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="delayed">Delay</label>
            <select class="input" id="delayed" name="delayed">
                <option value="">All</option>
                <option value="1" @selected(($filters['delayed'] ?? '') === '1')>Delayed</option>
                <option value="0" @selected(($filters['delayed'] ?? '') === '0')>On time</option>
            </select>
        </div>
        <div>
            <label class="label" for="carrier_id">Carrier</label>
            <select class="input" id="carrier_id" name="carrier_id">
                <option value="">All</option>
                @foreach ($carriers as $c)
                    <option value="{{ $c->id }}" @selected((string) ($filters['carrier_id'] ?? '') === (string) $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="trade_lane_id">Trade lane</label>
            <select class="input" id="trade_lane_id" name="trade_lane_id">
                <option value="">All</option>
                @foreach ($lanes as $l)
                    <option value="{{ $l->id }}" @selected((string) ($filters['trade_lane_id'] ?? '') === (string) $l->id)>{{ $l->name }} · {{ $l->route }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="origin_id">Origin</label>
            <select class="input" id="origin_id" name="origin_id">
                <option value="">All</option>
                @foreach ($locations as $l)
                    <option value="{{ $l->id }}" @selected((string) ($filters['origin_id'] ?? '') === (string) $l->id)>{{ $l->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="destination_id">Destination</label>
            <select class="input" id="destination_id" name="destination_id">
                <option value="">All</option>
                @foreach ($locations as $l)
                    <option value="{{ $l->id }}" @selected((string) ($filters['destination_id'] ?? '') === (string) $l->id)>{{ $l->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="date_from">Departure from</label>
            <input class="input" type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div>
            <label class="label" for="date_to">Departure to</label>
            <input class="input" type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div>
            <label class="label" for="sort">Sort by</label>
            <select class="input" id="sort" name="sort">
                @foreach (\App\Models\Shipment::SORTABLE as $col)
                    <option value="{{ $col }}" @selected(($filters['sort'] ?? 'created_at') === $col)>{{ ucwords(str_replace('_', ' ', $col)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="direction">Direction</label>
            <select class="input" id="direction" name="direction">
                <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Descending</option>
                <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>Ascending</option>
            </select>
        </div>
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-1">
            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-outline" href="{{ route('shipments.index') }}">Reset</a>
            @can('create', \App\Models\Shipment::class)
                <a class="btn btn-primary ml-auto" href="{{ route('shipments.create') }}">New shipment</a>
            @endcan
        </div>
    </form>

    <div class="card overflow-x-auto p-0">
        <table class="tbl">
            <thead>
            <tr>
                <th>Shipment</th><th>Lane</th><th>Route</th><th>Customer</th><th>Carrier</th><th>Status</th><th>Expected arrival</th><th>Delay</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($shipments as $s)
                <tr>
                    <td><a class="font-semibold" href="{{ route('shipments.show', $s) }}">{{ $s->shipment_number }}</a><p class="text-xs text-navy-500">{{ $s->cargo_type }}</p></td>
                    <td>
                        @if ($s->tradeLane)
                            <span title="{{ $s->tradeLane->route }}" class="inline-flex h-6 w-6 items-center justify-center rounded bg-navy-800 text-xs font-bold text-white">{{ $s->tradeLane->code }}</span>
                        @else
                            <span class="text-navy-300">–</span>
                        @endif
                    </td>
                    <td>{{ $s->origin->name }}<span class="text-navy-400"> → </span>{{ $s->destination->name }}</td>
                    <td>{{ $s->customer->name }}</td>
                    <td>{{ $s->carrier->name }}</td>
                    <td><x-status-badge :status="$s->status" /></td>
                    <td class="whitespace-nowrap">{{ $s->expected_arrival_at->format('Y-m-d H:i') }}</td>
                    <td class="whitespace-nowrap">
                        @if ($s->is_delayed)
                            <span class="font-semibold">{{ number_format($s->delay_hours, 1) }} h</span> <x-severity-badge :severity="$s->delay_severity" />
                        @else
                            <span class="text-navy-400">On time</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-10 text-center text-navy-500">No shipments match your filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $shipments->links() }}</div>
@endsection
