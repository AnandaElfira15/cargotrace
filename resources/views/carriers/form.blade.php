@extends('layouts.app')
@php $editing = $carrier !== null; @endphp
@section('title', $editing ? 'Edit carrier' : 'New carrier')
@section('heading', $editing ? 'Edit carrier' : 'New carrier')
@section('content')
    <form method="POST" action="{{ $editing ? route('carriers.update', $carrier) : route('carriers.store') }}" class="card grid max-w-2xl gap-4 sm:grid-cols-2">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="sm:col-span-2"><label class="label">Name</label><input class="input" name="name" value="{{ old('name', $carrier?->name) }}" required></div>
        <div><label class="label">Code</label><input class="input" name="code" value="{{ old('code', $carrier?->code) }}" required></div>
        <div>
            <label class="label">Mode</label>
            <select class="input" name="mode">
                @foreach (['sea', 'air', 'road', 'rail'] as $m) <option value="{{ $m }}" @selected(old('mode', $carrier?->mode ?? 'sea') === $m)>{{ ucfirst($m) }}</option> @endforeach
            </select>
        </div>
        <div class="sm:col-span-2"><label class="label">Contact email</label><input class="input" type="email" name="contact_email" value="{{ old('contact_email', $carrier?->contact_email) }}"></div>
        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $carrier?->is_active ?? true))> Active</label>
        <div class="flex gap-2 sm:col-span-2"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-outline" href="{{ route('carriers.index') }}">Cancel</a></div>
    </form>
@endsection
