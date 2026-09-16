<?php

/**
 * Production-parity homepage CMS content for local media-authority certification.
 *
 * @return array<string, mixed>
 */
return [
    'hero' => ['enabled' => '1', 'headline' => 'Every flight from Pakistan'],
    'routes' => [
        'enabled' => '1',
        'items' => [
            [
                'id' => 'seed-khi-dxb',
                'from' => 'LHE',
                'to' => 'DXB',
                'enabled' => '1',
                'sort_order' => 0,
                'image_asset_key' => 'route_seed-khi-dxb',
                'dynamic_fare_enabled' => '0',
                'manual_fallback_price' => 42500,
            ],
            [
                'id' => 'seed-lhe-jed',
                'from' => 'LHE',
                'to' => 'JED',
                'enabled' => '1',
                'sort_order' => 1,
                'image_asset_key' => 'route_seed-lhe-jed',
                'dynamic_fare_enabled' => '0',
                'manual_fallback_price' => 68900,
            ],
            [
                'id' => 'seed-isb-lhr',
                'from' => 'ISB',
                'to' => 'LHR',
                'enabled' => '1',
                'sort_order' => 2,
                'image_asset_key' => 'route_seed_isb_lhr',
                'dynamic_fare_enabled' => '0',
                'manual_fallback_price' => 198000,
            ],
            [
                'id' => 'seed-khi-ruh',
                'from' => 'KHI',
                'to' => 'RUH',
                'enabled' => '1',
                'sort_order' => 3,
                'image_asset_key' => 'route_seed_khi_ruh',
                'dynamic_fare_enabled' => '0',
                'manual_fallback_price' => 72000,
            ],
        ],
    ],
    'destinations' => ['enabled' => '1', 'items' => []],
    'featured_deals' => [
        'enabled' => '1',
        'items' => [
            [
                'id' => '2b3881220c1477fe53deab40b3d0170a',
                'airline' => 'Air Arabia',
                'from' => 'ISB',
                'to' => 'DXB',
                'enabled' => '1',
                'sort_order' => 0,
            ],
            [
                'id' => '9ae5ad6bca0bf3481c79635c582d4531',
                'airline' => 'AirBlue',
                'from' => 'LHE',
                'to' => 'IST',
                'enabled' => '1',
                'sort_order' => 1,
            ],
            [
                'id' => 'aa6546f91e6984f9ae3ad93929189a6b',
                'airline' => 'AirSial',
                'from' => 'ISB',
                'to' => 'JED',
                'enabled' => '1',
                'sort_order' => 2,
            ],
        ],
    ],
    'support_cta' => ['enabled' => '1', 'title' => 'Support'],
];
