<?php

/**
 * IATA-code airport dataset import settings (OurAirports-style CSV).
 *
 * This is an open community dataset filtered by valid IATA codes — not an
 * official IATA publication. Replace the CSV source with licensed IATA data
 * when available; keep the same import command and override files.
 */
return [

    'default_source' => storage_path('app/imports/airports.csv'),

    'overrides_csv' => storage_path('app/imports/airport_overrides.csv'),

    'dataset_label' => 'IATA-code airport dataset (OurAirports-style open source)',

    /**
     * Manual IATA boosts for Pakistan / GCC / UK / US / Canada / Australia hubs
     * and other high-traffic OTA routes. Merged with per-row type scores.
     *
     * @var array<string, int>
     */
    'priority_boosts' => [
        'LHE' => 250,
        'KHI' => 250,
        'ISB' => 250,
        'PEW' => 220,
        'SKT' => 220,
        'MUX' => 200,
        'UET' => 200,
        'DXB' => 260,
        'SHJ' => 220,
        'AUH' => 220,
        'DWC' => 200,
        'JED' => 240,
        'RUH' => 240,
        'MED' => 220,
        'DMM' => 210,
        'DOH' => 220,
        'KWI' => 210,
        'BAH' => 210,
        'MCT' => 200,
        'IST' => 210,
        'SAW' => 190,
        'LHR' => 230,
        'LGW' => 200,
        'STN' => 170,
        'LTN' => 170,
        'MAN' => 180,
        'BHX' => 180,
        'EDI' => 170,
        'JFK' => 190,
        'EWR' => 175,
        'LGA' => 175,
        'ORD' => 180,
        'LAX' => 180,
        'SFO' => 170,
        'MIA' => 170,
        'YYZ' => 180,
        'YUL' => 180,
        'YVR' => 170,
        'MEL' => 180,
        'SYD' => 180,
        'BNE' => 170,
        'PER' => 160,
        'KUL' => 180,
        'BKK' => 180,
        'SIN' => 200,
        'HKG' => 190,
        'NRT' => 180,
        'HND' => 180,
    ],

    /**
     * ISO 3166-1 alpha-2 regions that receive a small ranking boost on import.
     *
     * @var list<string>
     */
    'route_region_country_codes' => [
        'PK', 'AE', 'SA', 'QA', 'KW', 'BH', 'OM',
        'GB', 'US', 'CA', 'AU',
    ],

    'type_priority' => [
        'large_airport' => 80,
        'medium_airport' => 60,
        'small_airport' => 35,
    ],

    'region_priority_boost' => 15,

];
