@extends('layouts.app')
@section('title', 'Settings')
@section('heading', 'Delay rule settings')
@section('content')
    <form method="POST" action="{{ route('settings.update') }}" class="card max-w-xl space-y-4">
        @csrf @method('PUT')
        <p class="text-sm text-navy-600">
            A shipment is delayed when its ETA is later than the expected arrival. The delay duration decides the severity.
            These values are the group parameters for <strong>{{ config('cargotrace.group.name') }}</strong>.
        </p>
        <div>
            <label class="label" for="low_max_hours">LOW up to (hours)</label>
            <input class="input" id="low_max_hours" type="number" step="0.5" min="0" name="low_max_hours" value="{{ old('low_max_hours', $thresholds['low_max']) }}" required>
        </div>
        <div>
            <label class="label" for="medium_max_hours">MEDIUM up to / HIGH above (hours)</label>
            <input class="input" id="medium_max_hours" type="number" step="0.5" min="0" name="medium_max_hours" value="{{ old('medium_max_hours', $thresholds['medium_max']) }}" required>
        </div>
        <div class="rounded-lg bg-navy-50 p-3 text-xs text-navy-700">
            Example with 6 / 12: a 5 h delay is LOW, 10 h is MEDIUM, 13 h is HIGH.
        </div>
        <button class="btn btn-primary" type="submit">Save thresholds</button>
    </form>
@endsection
