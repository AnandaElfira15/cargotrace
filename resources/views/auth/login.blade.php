@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
    <h1 class="text-2xl font-bold text-navy-900">Sign in</h1>
    <p class="mt-1 text-sm text-navy-600">Welcome back. Enter your account details to continue.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input class="input" id="password" type="password" name="password" required autocomplete="current-password">
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-navy-700">
            <input type="checkbox" name="remember" value="1" class="rounded border-navy-300"> Remember me
        </label>
        <button class="btn btn-primary w-full" type="submit">Sign in</button>
    </form>

    <p class="mt-6 text-center text-sm text-navy-600">New customer? <a class="font-semibold" href="{{ route('register') }}">Create an account</a></p>

    @if (app()->environment('local'))
        <div class="mt-8 rounded-lg bg-navy-50 p-4 text-xs text-navy-700">
            <p class="font-semibold">Demo accounts (seeded data)</p>
            <p class="mt-1">admin@cargotrace.test · operator@cargotrace.test · customer@cargotrace.test</p>
            <p>Password: see <code>DEMO_PASSWORD</code> in .env (default: <code>password</code>)</p>
        </div>
    @endif
@endsection
