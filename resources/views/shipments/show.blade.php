@extends('layouts.app')
@section('title', $shipment->shipment_number)
@section('heading', 'Shipment '.$shipment->shipment_number)

@push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
@endpush

@section('content')
    @php $me = auth()->user(); @endphp

    {{-- Header --}}
    <div class="card flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge :status="$shipment->status" class="text-sm" />
                @if ($delay->isDelayed) <x-severity-badge :severity="$delay->severity" /> <span class="text-sm font-semibold text-red-700">Delayed {{ number_format($delay->delayHours, 1) }} h</span>
                @else <span class="text-sm font-semibold text-emerald-700">On time</span> @endif
            </div>
            <p class="mt-2 text-lg font-bold">{{ $shipment->origin->name }} <span class="text-navy-400">→</span> {{ $shipment->destination->name }}</p>
            <p class="text-sm text-navy-600">{{ $shipment->customer->name }} · {{ $shipment->carrier->name }} · {{ $shipment->cargo_type }}</p>
        </div>
        <div class="flex gap-2">
            @can('update', $shipment) <a class="btn btn-outline" href="{{ route('shipments.edit', $shipment) }}">Edit</a> @endcan
            @can('delete', $shipment)
                <form method="POST" action="{{ route('shipments.destroy', $shipment) }}" onsubmit="return confirm('Delete this shipment?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger" type="submit">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        {{-- Map + details --}}
        <div class="space-y-4 lg:col-span-2">
            <div class="card p-0 overflow-hidden">
                <div id="map" class="h-96 w-full"></div>
                <div class="flex flex-wrap gap-4 border-t border-navy-100 px-4 py-2 text-xs text-navy-600">
                    <span><span class="mr-1 inline-block h-3 w-3 rounded-full bg-navy-900 align-middle"></span>Origin</span>
                    <span><span class="mr-1 inline-block h-3 w-3 rounded-full border-2 border-navy-900 bg-white align-middle"></span>Destination</span>
                    <span><span class="mr-1 inline-block h-3 w-3 rounded-full bg-navy-400 align-middle"></span>Tracking point</span>
                    <span><span class="mr-1 inline-block h-3 w-3 rounded-full bg-red-600 align-middle"></span>Exception</span>
                    <span><span class="mr-1 inline-block h-3 w-3 rounded-full bg-emerald-500 align-middle"></span>Current location</span>
                </div>
            </div>

            <div class="card">
                <h2 class="mb-3 text-sm font-bold">Shipment details</h2>
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Weight' => number_format($shipment->weight_kg, 2).' kg',
                        'Volume' => number_format($shipment->volume_cbm, 2).' cbm',
                        'Expected departure' => $shipment->expected_departure_at->format('Y-m-d H:i'),
                        'Expected arrival' => $shipment->expected_arrival_at->format('Y-m-d H:i'),
                        'Estimated arrival (ETA)' => $shipment->estimated_arrival_at?->format('Y-m-d H:i') ?? '—',
                        'Actual arrival' => $shipment->actual_arrival_at?->format('Y-m-d H:i') ?? '—',
                        'Current location' => $current ? (($current->location?->name ?? 'Coordinates').' · '.number_format($current->latitude, 4).', '.number_format($current->longitude, 4)) : '—',
                    ] as $k => $v)
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-navy-500">{{ $k }}</dt><dd class="mt-0.5">{{ $v }}</dd></div>
                    @endforeach
                </dl>

                @if ($shipment->items->isNotEmpty())
                    <h3 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-navy-500">Items</h3>
                    <table class="tbl">
                        <thead><tr><th>Description</th><th>Qty</th><th>kg</th><th>cbm</th><th>HS code</th></tr></thead>
                        <tbody>
                        @foreach ($shipment->items as $item)
                            <tr><td>{{ $item->description }}</td><td>{{ $item->quantity }}</td><td>{{ $item->weight_kg ?? '—' }}</td><td>{{ $item->volume_cbm ?? '—' }}</td><td>{{ $item->hs_code ?? '—' }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Side column --}}
        <div class="space-y-4">
            <div class="card">
                <h2 class="mb-2 text-sm font-bold">ETA &amp; delay rule</h2>
                <p class="text-sm text-navy-700">{{ $delay->explanation() }}</p>
            </div>

            <div class="card">
                <h2 class="mb-2 text-sm font-bold">Weather (context only)</h2>
                <div class="flex flex-wrap gap-2">
                    <button class="btn btn-outline btn-sm" data-weather="current">Current position</button>
                    <button class="btn btn-outline btn-sm" data-weather="destination">Destination</button>
                </div>
                <div id="weatherBox" class="mt-3 text-sm text-navy-600">Click a button to load the current weather from Open-Meteo.</div>
            </div>

            {{-- Timeline --}}
            <div class="card">
                <h2 class="mb-3 text-sm font-bold">Tracking timeline</h2>
                <ol class="relative ml-2 border-l-2 border-navy-100">
                    @forelse ($timeline as $event)
                        <li class="mb-5 ml-4">
                            <span class="absolute -left-[7px] mt-1.5 h-3 w-3 rounded-full {{ $event->is_exception ? 'bg-red-600' : 'bg-navy-700' }}"></span>
                            <p class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                                @if ($event->is_exception)
                                    <span class="text-red-700">Exception</span>
                                @elseif ($event->status)
                                    <x-status-badge :status="$event->status" />
                                @else
                                    {{ $event->event_type->label() }}
                                @endif
                                <span class="text-xs font-normal text-navy-500">{{ $event->occurred_at->format('Y-m-d H:i') }}</span>
                            </p>
                            @if ($event->location) <p class="text-xs text-navy-600">{{ $event->location->name }}, {{ $event->location->country }}</p> @endif
                            @if ($event->description) <p class="mt-0.5 text-sm text-navy-700">{{ $event->description }}</p> @endif
                            @if ($event->creator) <p class="text-xs text-navy-400">by {{ $event->creator->name }}</p> @endif
                        </li>
                    @empty
                        <li class="ml-4 text-sm text-navy-500">No events yet.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>

    {{-- Exceptions --}}
    <div class="card mt-4">
        <h2 class="mb-3 text-sm font-bold">Exceptions</h2>
        @forelse ($shipment->exceptions->sortByDesc('detected_at') as $ex)
            <div class="mb-3 rounded-lg border border-navy-100 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold">{{ $ex->type->label() }}</span>
                    <x-severity-badge :severity="$ex->severity" /> <x-status-badge :status="$ex->status" />
                    <span class="text-xs text-navy-500">detected {{ $ex->detected_at->format('Y-m-d H:i') }}@if ($ex->resolved_at), resolved {{ $ex->resolved_at->format('Y-m-d H:i') }}@endif</span>
                </div>
                @if ($ex->description) <p class="mt-1 text-sm text-navy-700">{{ $ex->description }}</p> @endif
                @if ($ex->resolution_notes) <p class="mt-1 text-sm text-emerald-700">Resolution: {{ $ex->resolution_notes }}</p> @endif

                @if ($me->isStaff() && ! $ex->status->isTerminal())
                    <form method="POST" action="{{ route('exceptions.update', $ex) }}" class="mt-3 grid gap-2 sm:grid-cols-12">
                        @csrf @method('PATCH')
                        <select name="status" class="input sm:col-span-3">
                            @foreach (array_merge([$ex->status], $ex->status->allowedTransitions()) as $st)
                                <option value="{{ $st->value }}" @selected($st === $ex->status)>{{ $st->label() }}</option>
                            @endforeach
                        </select>
                        <input class="input sm:col-span-7" name="resolution_notes" value="{{ $ex->resolution_notes }}" placeholder="Notes / resolution">
                        <button class="btn btn-primary btn-sm sm:col-span-2" type="submit">Update</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-sm text-navy-500">No exceptions reported.</p>
        @endforelse

        @can('reportException', $shipment)
            <details class="mt-4">
                <summary class="btn btn-outline btn-sm inline-flex">Report an exception</summary>
                <form method="POST" action="{{ route('shipments.exceptions.store', $shipment) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label class="label">Type</label>
                        <select class="input" name="type" required>
                            @foreach ($exceptionTypes as $t) <option value="{{ $t->value }}" @selected($t->value === config('cargotrace.group.primary_exception'))>{{ $t->label() }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Severity</label>
                        <select class="input" name="severity" required>
                            @foreach ($severities as $s) <option value="{{ $s->value }}">{{ $s->label() }}</option> @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Description</label>
                        <textarea class="input" name="description" rows="2"></textarea>
                    </div>
                    <div><button class="btn btn-primary" type="submit">Report exception</button></div>
                </form>
            </details>
        @endcan
    </div>

    {{-- Add tracking event --}}
    @can('addTracking', $shipment)
        @unless ($shipment->status->isTerminal())
            <div class="card mt-4">
                <h2 class="mb-3 text-sm font-bold">Add tracking event</h2>
                <form method="POST" action="{{ route('shipments.tracking.store', $shipment) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @csrf
                    <div>
                        <label class="label">Event type</label>
                        <select class="input" name="event_type" id="event_type" required>
                            @foreach ($eventTypes as $t) <option value="{{ $t }}" @selected(old('event_type') === $t)>{{ ucwords(strtolower(str_replace('_', ' ', $t))) }}</option> @endforeach
                        </select>
                    </div>
                    <div id="statusWrap">
                        <label class="label">New status</label>
                        <select class="input" name="status">
                            <option value="">Select…</option>
                            @foreach ($nextStatuses as $st) <option value="{{ $st->value }}" @selected(old('status') === $st->value)>{{ $st->label() }}</option> @endforeach
                        </select>
                        <p class="mt-1 text-xs text-navy-500">Only valid next statuses from {{ $shipment->status->label() }} are listed.</p>
                    </div>
                    <div>
                        <label class="label">Occurred at</label>
                        <input class="input" type="datetime-local" name="occurred_at" value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    <div>
                        <label class="label">Location</label>
                        <select class="input" name="location_id">
                            <option value="">None</option>
                            @foreach ($locations as $l) <option value="{{ $l->id }}" @selected((string) old('location_id') === (string) $l->id)>{{ $l->name }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Latitude (optional)</label>
                        <input class="input" type="number" step="any" name="latitude" value="{{ old('latitude') }}">
                    </div>
                    <div>
                        <label class="label">Longitude (optional)</label>
                        <input class="input" type="number" step="any" name="longitude" value="{{ old('longitude') }}">
                    </div>
                    <div>
                        <label class="label">New ETA (optional)</label>
                        <input class="input" type="datetime-local" name="estimated_arrival_at" value="{{ old('estimated_arrival_at') }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Description</label>
                        <input class="input" name="description" value="{{ old('description') }}">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3"><button class="btn btn-primary" type="submit">Add event</button></div>
                </form>
            </div>
        @endunless
    @endcan
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script>
        const data = @json($mapData);
        const map = L.map('map', { scrollWheelZoom: false });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        const bounds = [];
        const dot = (ll, opts, popup) => { bounds.push(ll); return L.circleMarker(ll, opts).addTo(map).bindPopup(popup); };
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

        const line = data.points.map(p => [p.lat, p.lng]);
        if (line.length > 1) L.polyline(line, { color: '#3b58a8', weight: 3, dashArray: '6 6' }).addTo(map);

        dot([data.origin.lat, data.origin.lng], { radius: 9, color: '#0f1b3f', fillColor: '#0f1b3f', fillOpacity: 1 }, '<b>Origin</b><br>' + esc(data.origin.name));
        dot([data.destination.lat, data.destination.lng], { radius: 9, color: '#0f1b3f', weight: 3, fillColor: '#ffffff', fillOpacity: 1 }, '<b>Destination</b><br>' + esc(data.destination.name));
        data.points.forEach(p => dot([p.lat, p.lng],
            { radius: 6, color: '#fff', weight: 1, fillColor: p.exception ? '#dc2626' : '#5a77c2', fillOpacity: 1 },
            '<b>' + esc(p.label.replace('_', ' ')) + '</b><br>' + esc(p.time) + (p.place ? '<br>' + esc(p.place) : '')));
        if (data.current) dot([data.current.lat, data.current.lng], { radius: 10, color: '#fff', weight: 2, fillColor: '#10b981', fillOpacity: 1 }, '<b>Current location</b>');

        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 6 });

        // Tracking form: status only matters for STATUS_UPDATE
        const typeSel = document.getElementById('event_type');
        if (typeSel) {
            const wrap = document.getElementById('statusWrap');
            const sync = () => wrap.style.display = typeSel.value === 'STATUS_UPDATE' ? '' : 'none';
            typeSel.addEventListener('change', sync); sync();
        }

        // Weather via Fetch API; failures are shown, never thrown
        const box = document.getElementById('weatherBox');
        document.querySelectorAll('[data-weather]').forEach(btn => btn.addEventListener('click', () => {
            box.textContent = 'Loading…';
            fetch(@json(route('shipments.weather', $shipment)) + '?point=' + btn.dataset.weather, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(r => r.json().catch(() => ({ ok: false, error: 'Unexpected response.' })))
                .then(res => {
                    if (!res.ok) { box.innerHTML = '<span class="text-red-700">' + esc(res.error || 'Weather unavailable.') + '</span>'; return; }
                    const d = res.data;
                    box.innerHTML = '<p class="font-semibold text-navy-900">' + esc(res.place) + '</p>'
                        + '<p>' + esc(d.summary) + ', ' + d.temperature_c + ' °C</p>'
                        + '<p class="text-xs">Wind ' + (d.wind_kmh ?? '–') + ' km/h · Rain ' + (d.precipitation_mm ?? '–') + ' mm</p>'
                        + '<p class="mt-1 text-xs text-navy-400">' + esc(d.note) + '</p>';
                })
                .catch(() => { box.innerHTML = '<span class="text-red-700">Weather service is unreachable.</span>'; });
        }));
    </script>
@endpush
