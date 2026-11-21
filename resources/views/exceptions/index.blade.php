@extends('layouts.app')
@section('title', 'Exceptions')
@section('heading', 'Exceptions')
@section('content')
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="status">Status</label>
            <select class="input" id="status" name="status">
                <option value="">All</option>
                @foreach ($statuses as $s) <option value="{{ $s->value }}" @selected($selected === $s->value)>{{ $s->label() }}</option> @endforeach
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn btn-outline" href="{{ route('exceptions.index') }}">Reset</a>
    </form>

    <div class="card overflow-x-auto p-0">
        <table class="tbl">
            <thead><tr><th>Shipment</th><th>Type</th><th>Severity</th><th>Status</th><th>Detected</th><th>Resolved</th><th>Reported by</th></tr></thead>
            <tbody>
            @forelse ($exceptions as $e)
                <tr>
                    <td><a class="font-semibold" href="{{ route('shipments.show', $e->shipment_id) }}">{{ $e->shipment->shipment_number }}</a></td>
                    <td>{{ $e->type->label() }}@if ($e->description)<p class="text-xs text-navy-500">{{ \Illuminate\Support\Str::limit($e->description, 80) }}</p>@endif</td>
                    <td><x-severity-badge :severity="$e->severity" /></td>
                    <td><x-status-badge :status="$e->status" /></td>
                    <td class="whitespace-nowrap">{{ $e->detected_at->format('Y-m-d H:i') }}</td>
                    <td class="whitespace-nowrap">{{ $e->resolved_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $e->reporter?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center text-navy-500">No exceptions found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $exceptions->links() }}</div>
@endsection
