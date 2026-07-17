<?php

return [

    /*
    |--------------------------------------------------------------------------
    | UI version channels (site / admin / staff)
    |--------------------------------------------------------------------------
    |
    | v1 = today's canonical Blade paths (no prefix). v2+ overlays live under
    | ui/{channel}/{version}/... and fall back to v1 when missing.
    |
    */

    'preview_query_param' => 'ui',

    'public_asset_root_reminder' => env(
        'OTA_PUBLIC_ASSET_ROOT_REMINDER',
        'Set OTA_PUBLIC_WEBROOT_PATH to the live document root (production: /home/pkjetp/public_html).',
    ),

    'channels' => [
        'site' => [
            'default' => env('OTA_UI_SITE_DEFAULT', 'v1'),
            'active_versions' => ['v1', 'v2'],
            'fallback' => 'v1',
            'preview_enabled' => filter_var(env('OTA_UI_SITE_PREVIEW_ENABLED', true), FILTER_VALIDATE_BOOL),
            'route_prefix_versions' => ['v1', 'v2'],
        ],
        'admin' => [
            'default' => env('OTA_UI_ADMIN_DEFAULT', 'v1'),
            'active_versions' => ['v1', 'v2'],
            'fallback' => 'v1',
            'preview_enabled' => filter_var(env('OTA_UI_ADMIN_PREVIEW_ENABLED', true), FILTER_VALIDATE_BOOL),
            'route_prefix_versions' => [],
        ],
        'staff' => [
            'default' => env('OTA_UI_STAFF_DEFAULT', 'v1'),
            'active_versions' => ['v1', 'v2'],
            'fallback' => 'v1',
            'preview_enabled' => filter_var(env('OTA_UI_STAFF_PREVIEW_ENABLED', true), FILTER_VALIDATE_BOOL),
            'route_prefix_versions' => [],
        ],
    ],

    /*
    | Logical view paths audited for v1 presence (dot notation).
    */
    'critical_views' => [
        'site' => [
            'frontend.home',
            'dashboard.customer.dashboard',
            'dashboard.agent.index',
            'frontend.flights.results',
        ],
        'admin' => [
            'dashboard.admin.index',
        ],
        'staff' => [
            'dashboard.staff.index',
        ],
    ],

    /*
    | Route first segments excluded from site path-prefix preview stripping.
    */
    'path_prefix_excluded_segments' => [
        'dev',
        'api',
        'up',
        'vendor',
        'css',
        'js',
        'images',
        'storage',
    ],

];
