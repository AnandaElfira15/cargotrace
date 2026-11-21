<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @include('layouts.assets')
    @stack('head')
</head>
<body class="min-h-screen">
@php $me = auth()->user(); @endphp
<div class="flex min-h-screen flex-col lg:flex-row">
    {{-- Sidebar --}}
    <aside class="bg-navy-900 text-white lg:fixed lg:inset-y-0 lg:w-64 lg:overflow-y-auto">
        <div class="flex items-center justify-between px-5 py-5 lg:block">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-white hover:text-white">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-white text-lg font-bold text-navy-900">C</span>
                <span class="text-lg font-bold tracking-tight">CargoTrace</span>
            </a>
            <p class="mt-3 hidden text-xs text-navy-200 lg:block">{{ config('cargotrace.group.name') }} · {{ config('cargotrace.group.route') }}</p>
        </div>
        <nav class="flex flex-wrap gap-1 px-3 pb-4 text-sm lg:flex-col">
            @php
                $links = [
                    ['dashboard', 'Dashboard', 'dashboard*', true],
                    ['shipments.index', 'Shipments', 'shipments*', true],
                    ['exceptions.index', 'Exceptions', 'exceptions*', $me->isStaff()],
                    ['carriers.index', 'Carriers', 'carriers*', $me->isAdmin()],
                    ['locations.index', 'Locations', 'locations*', $me->isAdmin()],
                    ['users.index', 'Users', 'users*', $me->isAdmin()],
                    ['audit-logs.index', 'Audit log', 'audit-logs*', $me->isAdmin()],
                    ['settings.edit', 'Settings', 'settings*', $me->isAdmin()],
                ];
            @endphp
            @foreach ($links as [$route, $label, $pattern, $visible])
                @if ($visible)
                    <a href="{{ route($route) }}"
                       class="rounded-lg px-3 py-2 font-medium text-white hover:bg-navy-700 hover:text-white {{ request()->routeIs($pattern) ? 'bg-navy-700' : '' }}">{{ $label }}</a>
                @endif
            @endforeach
        </nav>
    </aside>

    <div class="flex-1 lg:ml-64">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-navy-100 bg-white px-6 py-3">
            <h1 class="text-lg font-bold text-navy-900">@yield('heading', 'Dashboard')</h1>
            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('notifications.index') }}" class="relative font-medium">
                    Notifications
                    @if (($unread = $me->unreadNotificationCount()) > 0)
                        <span class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-navy-800 px-1.5 text-xs font-semibold text-white">{{ $unread }}</span>
                    @endif
                </a>
                <span class="hidden text-navy-600 sm:inline">{{ $me->name }} · <span class="font-semibold">{{ $me->role->label() }}</span></span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline btn-sm" type="submit">Log out</button>
                </form>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-navy-200 bg-white px-4 py-3 text-sm font-medium text-navy-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-semibold">Please fix the following:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="px-6 pb-6 text-center text-xs text-navy-400">
            CargoTrace · Web Programming 2 UAS · Map data © OpenStreetMap contributors
        </footer>
    </div>
</div>
@stack('scripts')
</body>
</html>
