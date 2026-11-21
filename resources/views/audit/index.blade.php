@extends('layouts.app')
@section('title', 'Audit log')
@section('heading', 'Audit log')
@section('content')
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3">
        <div><label class="label">Action contains</label><input class="input" name="action" value="{{ request('action') }}" placeholder="e.g. shipment"></div>
        <div>
            <label class="label">Entity</label>
            <select class="input" name="entity_type">
                <option value="">All</option>
                @foreach ($entityTypes as $t) <option value="{{ $t }}" @selected(request('entity_type') === $t)>{{ $t }}</option> @endforeach
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn btn-outline" href="{{ route('audit-logs.index') }}">Reset</a>
    </form>
    <div class="card overflow-x-auto p-0">
        <table class="tbl">
            <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->actor?->name ?? 'System' }}</td>
                    <td class="font-semibold">{{ $log->action }}</td>
                    <td>{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                    <td class="max-w-md break-words text-xs text-navy-600">
                        @if ($log->old_values) <p>from {{ json_encode($log->old_values) }}</p> @endif
                        @if ($log->new_values) <p>to {{ json_encode($log->new_values) }}</p> @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-navy-500">No audit entries yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
