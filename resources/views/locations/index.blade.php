@extends('layouts.app')
@section('title', 'Locations')
@section('heading', 'Locations')
@section('content')
    <div class="mb-4 flex justify-end"><a class="btn btn-primary" href="{{ route('locations.create') }}">New location</a></div>
    <div class="card overflow-x-auto p-0">
        <table class="tbl">
            <thead><tr><th>Name</th><th>Code</th><th>Type</th><th>Country</th><th>Coordinates</th><th></th></tr></thead>
            <tbody>
            @foreach ($locations as $l)
                <tr>
                    <td class="font-semibold">{{ $l->name }}</td><td>{{ $l->code }}</td><td class="capitalize">{{ $l->type }}</td><td>{{ $l->country }}</td>
                    <td>{{ number_format($l->latitude, 4) }}, {{ number_format($l->longitude, 4) }}</td>
                    <td class="whitespace-nowrap text-right">
                        <a class="btn btn-outline btn-sm" href="{{ route('locations.edit', $l) }}">Edit</a>
                        <form class="inline" method="POST" action="{{ route('locations.destroy', $l) }}" onsubmit="return confirm('Delete this location?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" type="submit">Delete</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $locations->links() }}</div>
@endsection
