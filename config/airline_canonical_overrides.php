<?php

/**
 * JetPK canonical airline identities for current commercial routes.
 *
 * Precedence: this map wins over Kaggle/global duplicate IATA rows.
 */
return [
    'required_jetpk_codes' => ['PK', 'PA', 'PF', '9P', 'SV', 'G9', 'WY'],

  /**
     * Supplier / GDS codes mapped to canonical JetPK IATA.
     *
     * @var array<string, string>
     */
    'supplier_aliases' => [
        'PIA' => 'PK',
        'ABQ' => 'PA',
        'SIF' => 'PF',
        'FJL' => '9P',
        'SVA' => 'SV',
        'ABY' => 'G9',
        'OMA' => 'WY',
        'SAUDIA' => 'SV',
        'AIRBLUE' => 'PA',
        'AIRSIAL' => 'PF',
        'FLYJINNAH' => '9P',
    ],

    /**
     * Canonical airline records keyed by IATA.
     *
     * @var array<string, array<string, mixed>>
     */
    'overrides' => [
        'PK' => [
            'iata' => 'PK',
            'icao' => 'PIA',
            'name' => 'Pakistan International Airlines',
            'aliases' => ['PIA', 'Pakistan International', 'Pakistan Intl'],
            'country' => 'Pakistan',
            'is_active' => true,
            'logo_code' => 'PK',
            'logo_path' => null,
            'supplier_aliases' => ['PK', 'PIA'],
        ],
        'PA' => [
            'iata' => 'PA',
            'icao' => 'ABQ',
            'name' => 'Airblue',
            'aliases' => ['Air Blue', 'ABQ'],
            'country' => 'Pakistan',
            'is_active' => true,
            'logo_code' => 'PA',
            'logo_path' => null,
            'supplier_aliases' => ['PA', 'ABQ'],
        ],
        'PF' => [
            'iata' => 'PF',
            'icao' => 'SIF',
            'name' => 'AirSial',
            'aliases' => ['Air Sial', 'SIF'],
            'country' => 'Pakistan',
            'is_active' => true,
            'logo_code' => 'PF',
            'logo_path' => null,
            'supplier_aliases' => ['PF', 'SIF'],
        ],
        '9P' => [
            'iata' => '9P',
            'icao' => 'FJL',
            'name' => 'Fly Jinnah',
            'aliases' => ['FlyJinnah', 'FJL'],
            'country' => 'Pakistan',
            'is_active' => true,
            'logo_code' => '9P',
            'logo_path' => null,
            'supplier_aliases' => ['9P', 'FJL'],
        ],
        'SV' => [
            'iata' => 'SV',
            'icao' => 'SVA',
            'name' => 'Saudia',
            'aliases' => ['Saudi Arabian Airlines', 'Saudi Airlines', 'SVA'],
            'country' => 'Saudi Arabia',
            'is_active' => true,
            'logo_code' => 'SV',
            'logo_path' => null,
            'supplier_aliases' => ['SV', 'SVA'],
        ],
        'G9' => [
            'iata' => 'G9',
            'icao' => 'ABY',
            'name' => 'Air Arabia',
            'aliases' => ['ABY'],
            'country' => 'United Arab Emirates',
            'is_active' => true,
            'logo_code' => 'G9',
            'logo_path' => null,
            'supplier_aliases' => ['G9', 'ABY'],
        ],
        'WY' => [
            'iata' => 'WY',
            'icao' => 'OMA',
            'name' => 'Oman Air',
            'aliases' => ['OMA'],
            'country' => 'Oman',
            'is_active' => true,
            'logo_code' => 'WY',
            'logo_path' => null,
            'supplier_aliases' => ['WY', 'OMA'],
        ],
    ],
];
