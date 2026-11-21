@extends('layouts.app')
@php $editing = $location !== null; @endphp
@section('title', $editing ? 'Edit location' : 'New location')
@section('heading', $editing ? 'Edit location' : 'New location')
@section('content')
    <form method="POST" action="{{ $editing ? route('locations.update', $location) : route('locations.store') }}" class="card grid max-w-2xl gap-4 sm:grid-cols-2">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="sm:col-span-2">
            <label class="label">Name</label>
            <div class="flex gap-2">
                <input class="input" id="name" name="name" value="{{ old('name', $location?->name) }}" required>
                <button type="button" id="lookup" class="btn btn-outline whitespace-nowrap">Find coordinates</button>
            </div>
            <p id="lookupMsg" class="mt-1 text-xs text-navy-500">Uses OpenStreetMap Nominatim. You can always type the coordinates manually.</p>
        </div>
        <div><label class="label">Code</label><input class="input" name="code" value="{{ old('code', $location?->code) }}" placeholder="e.g. SGSIN" required></div>
        <div>
            <label class="label">Type</label>
            <select class="input" name="type">
                @foreach (['port', 'airport', 'warehouse', 'city'] as $t) <option value="{{ $t }}" @selected(old('type', $location?->type ?? 'port') === $t)>{{ ucfirst($t) }}</option> @endforeach
            </select>
        </div>
        <div><label class="label">City</label><input class="input" id="city" name="city" value="{{ old('city', $location?->city) }}"></div>
        <div><label class="label">Country</label><input class="input" id="country" name="country" value="{{ old('country', $location?->country) }}" required></div>
        <div><label class="label">Latitude</label><input class="input" id="latitude" type="number" step="any" name="latitude" value="{{ old('latitude', $location?->latitude) }}" required></div>
        <div><label class="label">Longitude</label><input class="input" id="longitude" type="number" step="any" name="longitude" value="{{ old('longitude', $location?->longitude) }}" required></div>
        <div class="flex gap-2 sm:col-span-2"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-outline" href="{{ route('locations.index') }}">Cancel</a></div>
    </form>
@endsection

@push('scripts')
    <script>
        const msg = document.getElementById('lookupMsg');
        document.getElementById('lookup').addEventListener('click', () => {
            const q = document.getElementById('name').value.trim();
            if (q.length < 2) { msg.textContent = 'Type a location name first.'; return; }
            msg.textContent = 'Searching…';
            fetch(@json(route('geocode')) + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(r => r.json().catch(() => ({ ok: false, error: 'Unexpected response.' })))
                .then(res => {
                    if (!res.ok) { msg.textContent = res.error || 'Geocoding failed. Enter the coordinates manually.'; return; }
                    if (!res.results.length) { msg.textContent = 'No results found. Try a different name or enter the coordinates manually.'; return; }
                    const r = res.results[0];
                    document.getElementById('latitude').value = r.latitude;
                    document.getElementById('longitude').value = r.longitude;
                    if (r.city && !document.getElementById('city').value) document.getElementById('city').value = r.city;
                    if (r.country && !document.getElementById('country').value) document.getElementById('country').value = r.country;
                    msg.textContent = 'Filled from: ' + r.display_name;
                })
                .catch(() => { msg.textContent = 'Geocoding service unreachable. Enter the coordinates manually.'; });
        });
    </script>
@endpush
