@extends('layouts.app')
@section('title', 'Carriers')
@section('heading', 'Carriers')
@section('content')
    <div class="mb-4 flex justify-end"><a class="btn btn-primary" href="{{ route('carriers.create') }}">New carrier</a></div>
    <div class="card overflow-x-auto p-0">
        <table class="tbl">
            <thead><tr><th>Name</th><th>Code</th><th>Mode</th><th>Contact</th><th>Shipments</th><th>Active</th><th></th></tr></thead>
            <tbody>
            @foreach ($carriers as $c)
                <tr>
                    <td class="font-semibold">{{ $c->name }}</td><td>{{ $c->code }}</td><td class="capitalize">{{ $c->mode }}</td>
                    <td>{{ $c->contact_email ?? '—' }}</td><td>{{ $c->shipments_count }}</td><td>{{ $c->is_active ? 'Yes' : 'No' }}</td>
                    <td class="whitespace-nowrap text-right">
                        <a class="btn btn-outline btn-sm" href="{{ route('carriers.edit', $c) }}">Edit</a>
                        <form class="inline" method="POST" action="{{ route('carriers.destroy', $c) }}" onsubmit="return confirm('Delete this carrier?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" type="submit">Delete</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $carriers->links() }}</div>
@endsection
