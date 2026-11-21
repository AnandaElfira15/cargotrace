<?php

/*
| GROUP B scenario (GROUP_SCENARIO=b): Asia -> North America.
| Matching .env: DELAY_MEDIUM_MAX_HOURS=18, GROUP_KPI=average_delay_duration,
| GROUP_PRIMARY_EXCEPTION=WEATHER_DELAY. Synthetic data, approximate coordinates.
*/

return [
    'name' => 'Group B: Asia to North America',

    'carriers' => [
        ['name' => 'Pacific Crest Lines', 'code' => 'PCL', 'mode' => 'sea', 'contact_email' => 'ops@pcl.example'],
        ['name' => 'Transpacific Marine', 'code' => 'TPM', 'mode' => 'sea', 'contact_email' => 'ops@tpm.example'],
        ['name' => 'Orient Bridge Shipping', 'code' => 'OBS', 'mode' => 'sea', 'contact_email' => 'ops@obs.example'],
    ],

    'locations' => [
        ['code' => 'CNSHA', 'name' => 'Port of Shanghai (Yangshan)', 'type' => 'port', 'city' => 'Shanghai', 'country' => 'China', 'lat' => 30.6300, 'lng' => 122.0600],
        ['code' => 'CNYTN', 'name' => 'Yantian Port, Shenzhen', 'type' => 'port', 'city' => 'Shenzhen', 'country' => 'China', 'lat' => 22.5700, 'lng' => 114.2800],
        ['code' => 'KRPUS', 'name' => 'Port of Busan', 'type' => 'port', 'city' => 'Busan', 'country' => 'South Korea', 'lat' => 35.1028, 'lng' => 129.0403],
        ['code' => 'JPYOK', 'name' => 'Port of Yokohama', 'type' => 'port', 'city' => 'Yokohama', 'country' => 'Japan', 'lat' => 35.4437, 'lng' => 139.6380],
        ['code' => 'USHNL', 'name' => 'Port of Honolulu', 'type' => 'port', 'city' => 'Honolulu', 'country' => 'United States', 'lat' => 21.3069, 'lng' => -157.8583],
        ['code' => 'USLAX', 'name' => 'Port of Los Angeles', 'type' => 'port', 'city' => 'Los Angeles', 'country' => 'United States', 'lat' => 33.7290, 'lng' => -118.2620],
        ['code' => 'USLGB', 'name' => 'Port of Long Beach', 'type' => 'port', 'city' => 'Long Beach', 'country' => 'United States', 'lat' => 33.7540, 'lng' => -118.2160],
        ['code' => 'USOAK', 'name' => 'Port of Oakland', 'type' => 'port', 'city' => 'Oakland', 'country' => 'United States', 'lat' => 37.7955, 'lng' => -122.2790],
        ['code' => 'USTIW', 'name' => 'Port of Tacoma', 'type' => 'port', 'city' => 'Tacoma', 'country' => 'United States', 'lat' => 47.2700, 'lng' => -122.4100],
        ['code' => 'CAVAN', 'name' => 'Port of Vancouver', 'type' => 'port', 'city' => 'Vancouver', 'country' => 'Canada', 'lat' => 49.2900, 'lng' => -123.1100],
    ],

    'routes' => [
        ['origin' => 'CNSHA', 'destination' => 'USLAX', 'waypoints' => ['KRPUS', 'JPYOK', 'USHNL'], 'transit_days' => 18],
        ['origin' => 'CNYTN', 'destination' => 'USLGB', 'waypoints' => ['CNSHA', 'JPYOK', 'USHNL'], 'transit_days' => 20],
        ['origin' => 'KRPUS', 'destination' => 'CAVAN', 'waypoints' => ['JPYOK'], 'transit_days' => 14],
        ['origin' => 'CNSHA', 'destination' => 'USTIW', 'waypoints' => ['KRPUS', 'JPYOK'], 'transit_days' => 16],
    ],

    'cargo_types' => ['Consumer electronics', 'Automotive parts', 'Toys', 'Apparel', 'Home appliances', 'Solar panels'],
];
