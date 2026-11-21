@extends('layouts.app')
@php $editing = $user !== null; @endphp
@section('title', $editing ? 'Edit user' : 'New user')
@section('heading', $editing ? 'Edit user' : 'New user')
@section('content')
    <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" class="card grid max-w-2xl gap-4 sm:grid-cols-2">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div><label class="label">Name</label><input class="input" name="name" value="{{ old('name', $user?->name) }}" required></div>
        <div>
            <label class="label">Email</label>
            @if ($editing) <input class="input bg-navy-50" value="{{ $user->email }}" disabled>
            @else <input class="input" type="email" name="email" value="{{ old('email') }}" required> @endif
        </div>
        <div><label class="label">Company</label><input class="input" name="company" value="{{ old('company', $user?->company) }}"></div>
        <div>
            <label class="label">Role</label>
            <select class="input" name="role" required>
                @foreach ($roles as $r) <option value="{{ $r->value }}" @selected(old('role', $user?->role?->value ?? 'customer') === $r->value)>{{ $r->label() }}</option> @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="label">Password {{ $editing ? '(leave empty to keep)' : '' }}</label>
            <input class="input" type="password" name="password" autocomplete="new-password" @required(! $editing)>
        </div>
        @if ($editing)
            <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Active (deactivating also revokes API tokens)</label>
        @endif
        <div class="flex gap-2 sm:col-span-2"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-outline" href="{{ route('users.index') }}">Cancel</a></div>
    </form>
@endsection
