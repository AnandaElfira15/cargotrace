@extends('layouts.app')
@section('title', 'Settings')
@section('heading', 'Delay rules & trade lanes')
@section('content')
    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="card max-w-2xl space-y-4">
            <div>
                <h2 class="text-sm font-bold text-navy-900">Global delay thresholds</h2>
                <p class="mt-1 text-sm text-navy-600">
                    A shipment is delayed when its ETA is later than the expected arrival; the delay duration decides the severity.
                    These values apply to shipments <strong>without</strong> a trade lane, and to any lane value left empty.
                </p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="low_max_hours">LOW up to (hours)</label>
                    <input class="input" id="low_max_hours" type="number" step="0.5" min="0" name="low_max_hours" value="{{ old('low_max_hours', $thresholds['low_max']) }}" required>
                </div>
                <div>
                    <label class="label" for="medium_max_hours">MEDIUM up to / HIGH above (hours)</label>
                    <input class="input" id="medium_max_hours" type="number" step="0.5" min="0" name="medium_max_hours" value="{{ old('medium_max_hours', $thresholds['medium_max']) }}" required>
                </div>
            </div>
        </div>

        @if ($lanes->isNotEmpty())
            <div class="card overflow-x-auto p-0">
                <div class="px-5 pt-5">
                    <h2 class="text-sm font-bold text-navy-900">Trade lanes (Groups A, B and C)</h2>
                    <p class="mt-1 text-sm text-navy-600">
                        Every lane has its own delay threshold, main exception type and highlighted KPI.
                        Leave a threshold empty to use the global value. Example with 6 / 12: a 5 h delay is LOW, 10 h is MEDIUM, 13 h is HIGH.
                    </p>
                </div>
                <table class="tbl mt-3">
                    <thead>
                    <tr><th>Lane</th><th>LOW up to (h)</th><th>MEDIUM up to (h)</th><th>Main exception</th><th>KPI</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($lanes as $lane)
                        <tr>
                            <td>
                                <span class="mr-1 inline-flex h-6 w-6 items-center justify-center rounded bg-navy-800 text-xs font-bold text-white">{{ $lane->code }}</span>
                                <span class="font-semibold">{{ $lane->name }}</span>
                                <p class="text-xs text-navy-500">{{ $lane->route }}</p>
                            </td>
                            <td><input class="input" type="number" step="0.5" min="0" name="lanes[{{ $lane->id }}][delay_low_max_hours]" value="{{ old("lanes.{$lane->id}.delay_low_max_hours", $lane->delay_low_max_hours) }}" placeholder="global"></td>
                            <td><input class="input" type="number" step="0.5" min="0" name="lanes[{{ $lane->id }}][delay_medium_max_hours]" value="{{ old("lanes.{$lane->id}.delay_medium_max_hours", $lane->delay_medium_max_hours) }}" placeholder="global"></td>
                            <td>
                                <select class="input" name="lanes[{{ $lane->id }}][primary_exception]">
                                    @foreach ($exceptionTypes as $type)
                                        <option value="{{ $type->value }}" @selected(old("lanes.{$lane->id}.primary_exception", $lane->primary_exception->value) === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="input" name="lanes[{{ $lane->id }}][kpi]">
                                    @foreach ($kpis as $key => $label)
                                        <option value="{{ $key }}" @selected(old("lanes.{$lane->id}.kpi", $lane->kpi) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <button class="btn btn-primary" type="submit">Save settings</button>
    </form>
@endsection
