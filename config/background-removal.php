<?php

return [
    'default_provider' => env('BACKGROUND_REMOVAL_PROVIDER', 'disabled'),
    'staging_ttl_hours' => 72,
    'accepted_logo_prefix' => 'logo',
    'force_mock_provider' => env('BACKGROUND_REMOVAL_FORCE_MOCK', false),
    'force_fixture_provider' => env('BACKGROUND_REMOVAL_FORCE_FIXTURE', false),
    'fixture_path' => env('BACKGROUND_REMOVAL_FIXTURE_PATH', base_path('tests/fixtures/branding/transparent-logo.png')),
    'max_timeout_seconds' => 120,
    'min_opaque_pixel_ratio' => 0.01,
    'remove_bg' => [
        'endpoint' => env('REMOVE_BG_ENDPOINT', 'https://api.remove.bg/v1.0/removebg'),
        'allowed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('REMOVE_BG_ALLOWED_HOSTS', 'api.remove.bg,remove.bg')),
        ))),
    ],
    'rembg_http' => [
        'endpoint' => env('REMBG_HTTP_ENDPOINT'),
        'enabled' => env('REMBG_HTTP_ENABLED', false),
    ],
];
