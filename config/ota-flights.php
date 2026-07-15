<?php

return [
    'offers' => [
        'fixture-offer-1' => [
            'airline_name' => 'Pakistan International Airlines',
            'airline_code' => 'PK',
            'baggage' => '30 kg checked + 7 kg cabin',
            'refundable' => true,
            'fare_family' => 'Economy Flex',
            'seats_left' => 9,
        ],
        'fixture-offer-2' => [
            'airline_name' => 'Emirates',
            'airline_code' => 'EK',
            'baggage' => '25 kg checked + 7 kg cabin',
            'refundable' => false,
            'fare_family' => 'Economy Saver',
            'seats_left' => 4,
        ],
        'fixture-offer-3' => [
            'airline_name' => 'Saudia',
            'airline_code' => 'SV',
            'baggage' => '2 pcs (23 kg each)',
            'refundable' => true,
            'fare_family' => 'Premium Economy',
            'seats_left' => 6,
        ],
    ],
    'nearby_date_strip' => [
        'enabled' => env('OTA_NEARBY_DATE_STRIP_ENABLED', true),
        'radius_days' => 3,
        'cache_ttl_seconds' => 900,
    ],
    'nearby_departure_airports' => [
        'enabled' => env('OTA_NEARBY_DEPARTURE_AIRPORTS_ENABLED', true),
        'max_radius_km' => (float) env('OTA_NEARBY_DEPARTURE_AIRPORTS_RADIUS_KM', 350),
        'max_airports' => (int) env('OTA_NEARBY_DEPARTURE_AIRPORTS_MAX', 4),
        'same_country_only' => filter_var(env('OTA_NEARBY_DEPARTURE_AIRPORTS_SAME_COUNTRY', true), FILTER_VALIDATE_BOOLEAN),
        'cache_ttl_seconds' => (int) env('OTA_NEARBY_DEPARTURE_AIRPORTS_CACHE_TTL', 3600),
    ],
];
