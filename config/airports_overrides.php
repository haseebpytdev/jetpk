<?php

/**
 * Manual airport catalog corrections applied after CSV import (upsert by IATA).
 *
 * Keys: preferred display name, city, country, country_code, is_active,
 * priority_score, aliases (extra search tokens, space-separated in search_keywords).
 *
 * CSV overrides at storage/app/imports/airport_overrides.csv merge on top when present.
 *
 * @return array<string, array<string, mixed>>
 */
return [

    'LHE' => [
        'name' => 'Allama Iqbal International Airport',
        'city' => 'Lahore',
        'country' => 'Pakistan',
        'country_code' => 'PK',
        'priority_score' => 280,
        'aliases' => ['lahore pakistan', 'allama iqbal'],
    ],

    'DXB' => [
        'name' => 'Dubai International Airport',
        'city' => 'Dubai',
        'country' => 'United Arab Emirates',
        'country_code' => 'AE',
        'priority_score' => 270,
        'aliases' => ['dubai uae'],
    ],

    'LHR' => [
        'name' => 'Heathrow Airport',
        'city' => 'London',
        'country' => 'United Kingdom',
        'country_code' => 'GB',
        'priority_score' => 240,
        'aliases' => ['london heathrow', 'heathrow'],
    ],

    'LGW' => [
        'name' => 'Gatwick Airport',
        'city' => 'London',
        'country' => 'United Kingdom',
        'country_code' => 'GB',
        'priority_score' => 210,
        'aliases' => ['london gatwick', 'gatwick'],
    ],

    'ISB' => [
        'name' => 'Islamabad International Airport',
        'city' => 'Islamabad',
        'country' => 'Pakistan',
        'country_code' => 'PK',
        'priority_score' => 275,
        'aliases' => ['islamabad pakistan', 'new islamabad airport'],
    ],

    'DMM' => [
        'name' => 'King Fahd International Airport',
        'city' => 'Dammam',
        'country' => 'Saudi Arabia',
        'country_code' => 'SA',
        'priority_score' => 260,
        'aliases' => ['dammam saudi', 'dammam airport'],
    ],

    'MCT' => [
        'name' => 'Muscat International Airport',
        'city' => 'Muscat',
        'country' => 'Oman',
        'country_code' => 'OM',
        'priority_score' => 250,
        'aliases' => ['muscat oman'],
    ],

    'PEW' => [
        'name' => 'Bacha Khan International Airport',
        'city' => 'Peshawar',
        'country' => 'Pakistan',
        'country_code' => 'PK',
        'priority_score' => 240,
        'aliases' => ['peshawar pakistan', 'bacha khan'],
    ],

];
