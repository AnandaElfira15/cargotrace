<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @include('layouts.assets')
</head>
<body class="min-h-screen bg-navy-900">
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="hidden flex-col justify-between p-12 text-white lg:flex">
        <div class="flex items-center gap-2">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-white text-xl font-bold text-navy-900">C</span>
            <span class="text-2xl font-bold tracking-tight">CargoTrace</span>
        </div>
        <div>
            <h2 class="text-4xl font-bold leading-tight">Global shipment visibility<br>&amp; tracking platform</h2>
            <p class="mt-4 max-w-md text-navy-200">Track every shipment from origin to destination, detect delays with transparent rules and resolve exceptions before customers have to ask.</p>
        </div>
        <p class="text-xs text-navy-300">Web Programming 2 · UAS Final Project</p>
    </div>
    <div class="flex items-center justify-center bg-white p-6 lg:rounded-l-[2.5rem]">
        <div class="w-full max-w-md">
            @yield('content')
        </div>
    </div>
</div>
</body>
</html>
