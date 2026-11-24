<?php

/*
| GROUP C scenario (GROUP_SCENARIO=c): Southeast Asia -> Australia.
| Matching .env: DELAY_MEDIUM_MAX_HOURS=24, GROUP_KPI=exception_resolution_time,
| GROUP_PRIMARY_EXCEPTION=DOCUMENTATION_ISSUE. Synthetic data, approximate coordinates.
*/

return [
    'name' => 'Group C: Southeast Asia to Australia',

    'lane' => [
        'code' => 'C',
        'name' => 'Group C',
        'route' => 'Southeast Asia → Australia',
        'delay_low_max_hours' => 12,
        'delay_medium_max_hours' => 24,
        'primary_exception' => 'DOCUMENTATION_ISSUE',
        'secondary_exception' => 'CUSTOMS_HOLD',
        'kpi' => 'exception_resolution_time',
    ],

    'carriers' => [
        ['name' => 'Coral Sea Container Line', 'code' => 'CSC', 'mode' => 'sea', 'contact_email' => 'ops@csc.example'],
        ['name' => 'Arafura Maritime', 'code' => 'ARM', 'mode' => 'sea', 'contact_email' => 'ops@arm.example'],
        ['name' => 'Southern Cross Freight', 'code' => 'SCF', 'mode' => 'sea', 'contact_email' => 'ops@scf.example'],
    ],

    'locations' => [
        ['code' => 'SGSIN', 'name' => 'Port of Singapore', 'type' => 'port', 'city' => 'Singapore', 'country' => 'Singapore', 'lat' => 1.2644, 'lng' => 103.8222],
        ['code' => 'MYPKG', 'name' => 'Port Klang', 'type' => 'port', 'city' => 'Klang', 'country' => 'Malaysia', 'lat' => 3.0000, 'lng' => 101.3900],
        ['code' => 'IDJKT', 'name' => 'Tanjung Priok, Jakarta', 'type' => 'port', 'city' => 'Jakarta', 'country' => 'Indonesia', 'lat' => -6.1048, 'lng' => 106.8803],
        ['code' => 'IDSUB', 'name' => 'Tanjung Perak, Surabaya', 'type' => 'port', 'city' => 'Surabaya', 'country' => 'Indonesia', 'lat' => -7.2000, 'lng' => 112.7300],
        ['code' => 'THLCH', 'name' => 'Laem Chabang', 'type' => 'port', 'city' => 'Chonburi', 'country' => 'Thailand', 'lat' => 13.0827, 'lng' => 100.8830],
        ['code' => 'AUDRW', 'name' => 'Port of Darwin', 'type' => 'port', 'city' => 'Darwin', 'country' => 'Australia', 'lat' => -12.4634, 'lng' => 130.8456],
        ['code' => 'AUFRE', 'name' => 'Port of Fremantle', 'type' => 'port', 'city' => 'Fremantle', 'country' => 'Australia', 'lat' => -32.0560, 'lng' => 115.7440],
        ['code' => 'AUSYD', 'name' => 'Port Botany, Sydney', 'type' => 'port', 'city' => 'Sydney', 'country' => 'Australia', 'lat' => -33.9680, 'lng' => 151.2090],
        ['code' => 'AUMEL', 'name' => 'Port of Melbourne', 'type' => 'port', 'city' => 'Melbourne', 'country' => 'Australia', 'lat' => -37.8230, 'lng' => 144.9000],
        ['code' => 'AUBNE', 'name' => 'Port of Brisbane', 'type' => 'port', 'city' => 'Brisbane', 'country' => 'Australia', 'lat' => -27.3800, 'lng' => 153.1700],
    ],

    'routes' => [
        ['origin' => 'SGSIN', 'destination' => 'AUBNE', 'waypoints' => ['IDJKT', 'IDSUB', 'AUDRW'], 'transit_days' => 14],
        ['origin' => 'IDJKT', 'destination' => 'AUFRE', 'waypoints' => ['IDSUB'], 'transit_days' => 9],
        ['origin' => 'MYPKG', 'destination' => 'AUMEL', 'waypoints' => ['SGSIN', 'IDJKT'], 'transit_days' => 16],
        ['origin' => 'THLCH', 'destination' => 'AUSYD', 'waypoints' => ['SGSIN', 'IDJKT', 'IDSUB'], 'transit_days' => 18],
    ],

    'cargo_types' => ['Electronics', 'Palm oil derivatives', 'Furniture', 'Rubber products', 'Machinery parts', 'Packaged food'],
];
