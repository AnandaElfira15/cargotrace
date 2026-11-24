<?php

/*
|--------------------------------------------------------------------------
| GROUP A scenario (GROUP_SCENARIO=a). Copy and edit this file for your own variation.
|--------------------------------------------------------------------------
| All demo data is SYNTHETIC but consistent: coordinates are approximate
| port positions, carriers and customers are fictional.
| Southeast Asia -> Europe. Matching .env: DELAY_MEDIUM_MAX_HOURS=12, GROUP_KPI=on_time_delivery_rate,
| GROUP_PRIMARY_EXCEPTION=CUSTOMS_HOLD.
*/

return [
    'name' => 'Group A: Southeast Asia to Europe',

    'lane' => [
        'code' => 'A',
        'name' => 'Group A',
        'route' => 'Southeast Asia → Europe',
        'delay_low_max_hours' => 6,
        'delay_medium_max_hours' => 12,
        'primary_exception' => 'CUSTOMS_HOLD',
        'secondary_exception' => 'PORT_CONGESTION',
        'kpi' => 'on_time_delivery_rate',
    ],

    'carriers' => [
        ['name' => 'Pacific Meridian Lines', 'code' => 'PML', 'mode' => 'sea', 'contact_email' => 'ops@pml.example'],
        ['name' => 'Nusantara Ocean Freight', 'code' => 'NOF', 'mode' => 'sea', 'contact_email' => 'ops@nof.example'],
        ['name' => 'Eurasia Container Express', 'code' => 'ECE', 'mode' => 'sea', 'contact_email' => 'ops@ece.example'],
    ],

    'locations' => [
        ['code' => 'SGSIN', 'name' => 'Port of Singapore', 'type' => 'port', 'city' => 'Singapore', 'country' => 'Singapore', 'lat' => 1.2644, 'lng' => 103.8222],
        ['code' => 'MYPKG', 'name' => 'Port Klang', 'type' => 'port', 'city' => 'Klang', 'country' => 'Malaysia', 'lat' => 3.0000, 'lng' => 101.3900],
        ['code' => 'IDJKT', 'name' => 'Tanjung Priok, Jakarta', 'type' => 'port', 'city' => 'Jakarta', 'country' => 'Indonesia', 'lat' => -6.1048, 'lng' => 106.8803],
        ['code' => 'THLCH', 'name' => 'Laem Chabang', 'type' => 'port', 'city' => 'Chonburi', 'country' => 'Thailand', 'lat' => 13.0827, 'lng' => 100.8830],
        ['code' => 'LKCMB', 'name' => 'Port of Colombo', 'type' => 'port', 'city' => 'Colombo', 'country' => 'Sri Lanka', 'lat' => 6.9497, 'lng' => 79.8428],
        ['code' => 'SAJED', 'name' => 'Jeddah Islamic Port', 'type' => 'port', 'city' => 'Jeddah', 'country' => 'Saudi Arabia', 'lat' => 21.4667, 'lng' => 39.1667],
        ['code' => 'EGPSD', 'name' => 'Port Said', 'type' => 'port', 'city' => 'Port Said', 'country' => 'Egypt', 'lat' => 31.2653, 'lng' => 32.3019],
        ['code' => 'NLRTM', 'name' => 'Port of Rotterdam', 'type' => 'port', 'city' => 'Rotterdam', 'country' => 'Netherlands', 'lat' => 51.9490, 'lng' => 4.1420],
        ['code' => 'DEHAM', 'name' => 'Port of Hamburg', 'type' => 'port', 'city' => 'Hamburg', 'country' => 'Germany', 'lat' => 53.5400, 'lng' => 9.9690],
        ['code' => 'BEANR', 'name' => 'Port of Antwerp', 'type' => 'port', 'city' => 'Antwerp', 'country' => 'Belgium', 'lat' => 51.2670, 'lng' => 4.3000],
    ],

    // origin, destination and the waypoints a ship passes (in order)
    'routes' => [
        ['origin' => 'SGSIN', 'destination' => 'NLRTM', 'waypoints' => ['LKCMB', 'SAJED', 'EGPSD'], 'transit_days' => 24],
        ['origin' => 'MYPKG', 'destination' => 'DEHAM', 'waypoints' => ['LKCMB', 'SAJED', 'EGPSD'], 'transit_days' => 26],
        ['origin' => 'IDJKT', 'destination' => 'BEANR', 'waypoints' => ['SGSIN', 'LKCMB', 'EGPSD'], 'transit_days' => 28],
        ['origin' => 'THLCH', 'destination' => 'NLRTM', 'waypoints' => ['SGSIN', 'LKCMB', 'SAJED'], 'transit_days' => 27],
    ],

    'cargo_types' => ['Electronics', 'Palm oil derivatives', 'Textiles', 'Furniture', 'Rubber products', 'Machinery parts'],
];
