@extends('layouts.guest')
@section('title', 'Create account')
@section('content')
    <h1 class="text-2xl font-bold text-navy-900">Create customer account</h1>
    <p class="mt-1 text-sm text-navy-600">Customers can follow their own shipments, ETA and route.</p>

    <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label class="label" for="name">Full name</label>
            <input class="input" id="name" name="name" value="{{ old('name') }}" required>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="company">Company (optional)</label>
            <input class="input" id="company" name="company" value="{{ old('company') }}">
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input class="input" id="password" type="password" name="password" required autocomplete="new-password">
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="password_confirmation">Confirm password</label>
            <input class="input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary w-full" type="submit">Create account</button>
    </form>

    <p class="mt-6 text-center text-sm text-navy-600">Already registered? <a class="font-semibold" href="{{ route('login') }}">Sign in</a></p>
@endsection
