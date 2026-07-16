<?php

return [

    'enabled' => env('CLIENT_UI_VERSIONING_ENABLED', true),

    'default_version' => env('CLIENT_UI_DEFAULT_VERSION', 'v1'),

    'master_client_slug' => env('CLIENT_UI_MASTER_SLUG', config('client.canonical_client.slug', 'jetpk')),

    'allowed_versions' => ['v1', 'v2'],

    'preview_enabled' => env('CLIENT_UI_PREVIEW_ENABLED', true),

    'preview_query_key' => 'ui',

    'preview_session_key' => 'client_ui_preview_version',

    'force_v1_default_until_verified' => env('CLIENT_UI_FORCE_V1_DEFAULT', true),

    'preview_namespace_enabled' => env('CLIENT_UI_PREVIEW_NAMESPACE_ENABLED', true),

    'preview_namespace' => env('CLIENT_UI_PREVIEW_NAMESPACE', 'v2'),

    'preview_protection_enabled' => env('CLIENT_UI_PREVIEW_PROTECTION_ENABLED', true),

    'preview_key' => env('CLIENT_UI_PREVIEW_KEY', null),

    'preview_session_grant_key' => 'client_ui_preview_granted',

    'preserve_preview_namespace_links' => env('CLIENT_UI_PRESERVE_PREVIEW_NAMESPACE_LINKS', true),

    /*
    |--------------------------------------------------------------------------
    | Project-owned theme assets (v1 source → v2 clone mapping)
    |--------------------------------------------------------------------------
    */
    'theme_assets' => [
        'css' => [
            'css/ota-design-system.css' => 'css/v2/ota-design-system-v2.css',
            'css/ota-public.css' => 'css/v2/ota-public-v2.css',
            'css/ota-mobile-app.css' => 'css/v2/ota-mobile-app-v2.css',
            'css/ota-admin-console.css' => 'css/v2/ota-admin-console-v2.css',
            'css/ota-portal-console.css' => 'css/v2/ota-portal-console-v2.css',
        ],
        'js' => [
            'js/ota-mobile-app.js' => 'js/v2/ota-mobile-app-v2.js',
            'js/ota-flight-detail-builders.js' => 'js/v2/ota-flight-detail-builders-v2.js',
            'js/ota-flight-details-modal.js' => 'js/v2/ota-flight-details-modal-v2.js',
            'js/ota-branded-fares.js' => 'js/v2/ota-branded-fares-v2.js',
            'js/ota-fare-breakdown-modal.js' => 'js/v2/ota-fare-breakdown-modal-v2.js',
            'js/ota-return-split-cards.js' => 'js/v2/ota-return-split-cards-v2.js',
            'js/public-form-validation.js' => 'js/v2/public-form-validation-v2.js',
            'js/admin-branding-logo-palette.js' => 'js/v2/admin-branding-logo-palette-v2.js',
        ],
    ],

];
