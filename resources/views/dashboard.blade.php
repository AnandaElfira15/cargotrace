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
        fetch(@json(route('dashboard.data')), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
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
