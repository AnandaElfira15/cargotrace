{{-- Uses the Vite/Tailwind build when present, otherwise falls back to the Tailwind browser build
     so the app still looks right before "npm run build" has been executed. --}}
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css'])
@else
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style type="text/tailwindcss">
        {!! preg_replace(["/^@import.*$/m", "/^@source.*$/m"], '', file_get_contents(resource_path('css/app.css'))) !!}
    </style>
@endif
