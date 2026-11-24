<?php

/*
|--------------------------------------------------------------------------
| CargoTrace business configuration
|--------------------------------------------------------------------------
| Group-specific parameters (delay threshold, KPI, primary exception, route)
| live here and are driven by .env, so each group can have its own scenario
| without touching the code.
*/

return [
    'group' => [
        'name' => env('GROUP_NAME', 'Groups A, B & C'),
        'route' => env('GROUP_ROUTE', 'SE Asia → Europe · Asia → N. America · SE Asia → Australia'),
        // Fallbacks only: every trade lane (Group A/B/C) stores its own KPI and main exception.
        // on_time_delivery_rate | average_delay_duration | exception_resolution_time
        'kpi' => env('GROUP_KPI', 'on_time_delivery_rate'),
        'primary_exception' => env('GROUP_PRIMARY_EXCEPTION', 'CUSTOMS_HOLD'),
        'secondary_exception' => env('GROUP_SECONDARY_EXCEPTION', 'PORT_CONGESTION'),
    ],

    'delay' => [
        // 0 < delay <= low_max_hours            => LOW
        // low_max_hours < delay <= medium_max   => MEDIUM
        // delay > medium_max_hours              => HIGH
        'low_max_hours' => (float) env('DELAY_LOW_MAX_HOURS', 6),
        'medium_max_hours' => (float) env('DELAY_MEDIUM_MAX_HOURS', 24),
    ],

    'notifications' => [
        // In-app notifications are always on. E-mail is optional (NOTIFY_BY_EMAIL=true) and needs a working MAIL_* setup.
        'email' => (bool) env('NOTIFY_BY_EMAIL', false),
    ],

    'tracking' => [
        'future_tolerance_minutes' => 5,
    ],

    'external' => [
        'timeout' => (int) env('EXTERNAL_API_TIMEOUT', 5),
        'geocoding' => [
            'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
            'contact_email' => env('NOMINATIM_CONTACT_EMAIL'),
            'user_agent' => 'CargoTrace/1.0 (student project; '.env('NOMINATIM_CONTACT_EMAIL', 'no-contact').')',
        ],
        'weather' => [
            'base_url' => env('OPEN_METEO_BASE_URL', 'https://api.open-meteo.com/v1'),
            'api_key' => env('OPEN_METEO_API_KEY'),
            'cache_minutes' => 10,
        ],
    ],
];
