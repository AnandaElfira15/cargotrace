@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Operations dashboard')

@php
    $t = $metrics['totals'];
    $k = $metrics['kpis'];
    $primary = $metrics['primary_kpi'];
    $kpiCards = [
        'on_time_delivery_rate' => ['On-time delivery rate', $k['on_time_delivery_rate'] !== null ? $k['on_time_delivery_rate'].'%' : 'n/a', 'Delivered shipments that arrived on time'],
        'average_delay_duration' => ['Average delay duration', $k['average_delay_duration'] !== null ? $k['average_delay_duration'].' h' : 'n/a', 'Across delayed shipments'],
        'exception_resolution_time' => ['Exception resolution time', $k['exception_resolution_time'] !== null ? $k['exception_resolution_time'].' h' : 'n/a', 'Average, detection to resolution'],
    ];
@endphp

@section('content')
    <form method="GET" action="{{ route('dashboard') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="lane">Trade lane</label>
            <select class="input" id="lane" name="lane" onchange="this.form.submit()">
                <option value="">All lanes (A + B + C)</option>
                @foreach ($lanes as $lane)
                    <option value="{{ $lane->id }}" @selected((int) $laneId === $lane->id)>{{ $lane->name }} · {{ $lane->route }}</option>
                @endforeach
            </select>
        </div>
        @if ($laneId)
            <a class="btn btn-outline" href="{{ route('dashboard') }}">Show all lanes</a>
        @endif
        <noscript><button class="btn btn-primary" type="submit">Apply</button></noscript>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['Total shipments', $t['shipments']],
            ['In transit', $t['in_transit']],
            ['Delivered', $t['delivered']],
            ['Delayed', $t['delayed']],
            ['Open exceptions', $t['open_exceptions']],
        ] as [$label, $value])
            <div class="card">
                <p class="text-xs font-semibold uppercase tracking-wide text-navy-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold text-navy-900" data-metric="{{ \Illuminate\Support\Str::slug($label) }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        @foreach ($kpiCards as $key => [$label, $value, $hint])
            <div class="card {{ $primary === $key ? 'bg-navy-900 text-white ring-2 ring-navy-300' : '' }}">
                <p class="text-xs font-semibold uppercase tracking-wide {{ $primary === $key ? 'text-navy-200' : 'text-navy-500' }}">
                    {{ $label }} @if ($primary === $key) · group KPI @endif
                </p>
                <p class="mt-2 text-3xl font-bold">{{ $value }}</p>
                <p class="mt-1 text-xs {{ $primary === $key ? 'text-navy-200' : 'text-navy-500' }}">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    @if (count($metrics['lanes']) > 0)
        @php $fmt = fn ($v, $unit) => $v === null ? 'n/a' : $v.$unit; @endphp
        <div class="card mt-4 overflow-x-auto p-0">
            <div class="px-5 pt-5">
                <h2 class="text-sm font-bold text-navy-900">Trade lane performance (Groups A, B and C)</h2>
                <p class="mt-1 text-xs text-navy-500">Each lane has its own delay threshold, main exception type and highlighted KPI. Click a row to filter the dashboard.</p>
            </div>
            <table class="tbl mt-3">
                <thead>
                <tr>
                    <th>Lane</th><th>Delay threshold</th><th>Main exception</th><th>On-time rate</th><th>Avg delay</th><th>Resolution time</th><th>Shipments</th><th>Delayed</th><th>Open exc.</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($metrics['lanes'] as $l)
                    @php $hl = fn ($key) => $l['kpi'] === $key ? 'bg-navy-50 font-bold text-navy-900' : ''; @endphp
                    <tr class="{{ (int) $laneId === $l['id'] ? 'bg-navy-50' : '' }}">
                        <td>
                            <a href="{{ route('dashboard', ['lane' => $l['id']]) }}" class="font-semibold">
                                <span class="mr-1 inline-flex h-6 w-6 items-center justify-center rounded bg-navy-800 text-xs font-bold text-white">{{ $l['code'] }}</span>{{ $l['name'] }}
                            </a>
                            <p class="text-xs text-navy-500">{{ $l['route'] }}</p>
                        </td>
                        <td class="whitespace-nowrap">{{ $l['threshold_hours'] }} h <span class="text-xs text-navy-400">(LOW ≤ {{ $l['low_hours'] }} h)</span></td>
                        <td>{{ $l['primary_exception_label'] }} <span class="text-xs text-navy-400">({{ $l['primary_exception_count'] }})</span></td>
                        <td class="{{ $hl('on_time_delivery_rate') }}">{{ $fmt($l['kpis']['on_time_delivery_rate'], '%') }}</td>
                        <td class="{{ $hl('average_delay_duration') }}">{{ $fmt($l['kpis']['average_delay_duration'], ' h') }}</td>
                        <td class="{{ $hl('exception_resolution_time') }}">{{ $fmt($l['kpis']['exception_resolution_time'], ' h') }}</td>
                        <td>{{ $l['shipments'] }}</td>
                        <td>{{ $l['delayed'] }}</td>
                        <td>{{ $l['open_exceptions'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="px-5 py-3 text-xs text-navy-500">Highlighted cell = the KPI chosen for that group.</p>
        </div>
    @endif

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="card">
            <h2 class="mb-3 text-sm font-bold text-navy-900">Shipments by status</h2>
            <div class="h-64"><canvas id="statusChart"></canvas></div>
        </div>
        <div class="card lg:col-span-2">
            <h2 class="mb-3 text-sm font-bold text-navy-900">Shipments by carrier</h2>
            <div class="h-64"><canvas id="carrierChart"></canvas></div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="card">
            <h2 class="text-sm font-bold text-navy-900">Rates</h2>
            <dl class="mt-3 space-y-3 text-sm">
                <div class="flex items-center justify-between"><dt class="text-navy-600">Delay rate</dt><dd class="font-bold">{{ $metrics['rates']['delay_rate'] }}%</dd></div>
                <div class="flex items-center justify-between"><dt class="text-navy-600">Exception rate</dt><dd class="font-bold">{{ $metrics['rates']['exception_rate'] }}%</dd></div>
            </dl>
        </div>

        <div class="card lg:col-span-2">
            <h2 class="text-sm font-bold text-navy-900">Needs attention: delayed shipments</h2>
            @forelse ($delayed as $s)
                <a href="{{ route('shipments.show', $s) }}" class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-navy-100 px-3 py-2 hover:bg-navy-50">
                    <span>
                        <span class="font-semibold">{{ $s->shipment_number }}</span>
                        <span class="block text-xs text-navy-500">{{ $s->origin->name }} → {{ $s->destination->name }}</span>
                    </span>
                    <span class="flex items-center gap-2 text-sm">
                        <span class="font-semibold">{{ number_format($s->delay_hours, 1) }} h</span>
                        @if ($s->delay_severity) <x-severity-badge :severity="$s->delay_severity" /> @endif
                    </span>
                </a>
            @empty
                <p class="mt-3 text-sm text-navy-500">No delayed shipments right now.</p>
            @endforelse
        </div>
    </div>

    <div class="card mt-4">
        <h2 class="text-sm font-bold text-navy-900">Open exceptions</h2>
        @forelse ($exceptions as $e)
            <a href="{{ route('shipments.show', $e->shipment_id) }}" class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-navy-100 px-3 py-2 hover:bg-navy-50">
                <span>
                    <span class="font-semibold">{{ $e->shipment->shipment_number }}</span>
                    <span class="block text-xs text-navy-500">{{ $e->type->label() }} · detected {{ $e->detected_at->format('Y-m-d H:i') }}</span>
                </span>
                <span class="flex items-center gap-2"><x-severity-badge :severity="$e->severity" /><x-status-badge :status="$e->status" /></span>
            </a>
        @empty
            <p class="mt-3 text-sm text-navy-500">No open exceptions.</p>
        @endforelse
    </div>
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
        // Vanilla JS + Fetch API: charts are fed by /dashboard/data (live database numbers)
        fetch(@json(route('dashboard.data', array_filter(['lane' => $laneId]))), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(data => {
                const navy = ['#0f1b3f', '#223470', '#3b58a8', '#5a77c2', '#869fd9', '#b3c5eb'];
                new Chart(document.getElementById('statusChart'), {
                    type: 'doughnut',
                    data: { labels: data.by_status.labels.map(l => l.replace('_', ' ')), datasets: [{ data: data.by_status.values, backgroundColor: navy, borderColor: '#fff', borderWidth: 2 }] },
                    options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                });
                new Chart(document.getElementById('carrierChart'), {
                    type: 'bar',
                    data: { labels: data.by_carrier.labels, datasets: [{ label: 'Shipments', data: data.by_carrier.values, backgroundColor: '#223470', borderRadius: 6 }] },
                    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
                });
            })
            .catch(() => {
                document.querySelectorAll('canvas').forEach(c => c.parentElement.innerHTML = '<p class="text-sm text-navy-500">Charts could not be loaded.</p>');
            });
    </script>
@endpush
