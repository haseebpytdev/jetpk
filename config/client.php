<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Standalone JetPakistan deployment
    |--------------------------------------------------------------------------
    |
    | When true, this repository behaves as a dedicated single-client OTA.
    | View/content resolvers must not silently fall back to master-client or
    | cross-tenant branded blades. Canonical client identity is resolved by
    | stable slug/key from configuration — never by numeric profile IDs.
    |
    */

    'standalone' => filter_var(env('OTA_STANDALONE', true), FILTER_VALIDATE_BOOL),

    'canonical_client' => [
        'key' => (string) env('OTA_CANONICAL_CLIENT_KEY', 'jetpk'),
        'slug' => (string) env('OTA_CANONICAL_CLIENT_SLUG', env('OTA_CLIENT_SLUG', 'jetpk')),
        'theme' => (string) env('OTA_CANONICAL_CLIENT_THEME', 'jetpakistan'),
        'mobile_theme' => (string) env('OTA_CANONICAL_MOBILE_THEME', 'jetpakistan-app'),
        'domain' => (string) env('OTA_CANONICAL_CLIENT_DOMAIN', env('OTA_BRAND_DOMAIN', 'jetpakistan.pk')),
    ],

    'fallback_policy' => [
        'allow_master_client' => filter_var(env('OTA_ALLOW_MASTER_CLIENT', false), FILTER_VALIDATE_BOOL),
        'allow_cross_client_content' => filter_var(env('OTA_ALLOW_CROSS_CLIENT_CONTENT', false), FILTER_VALIDATE_BOOL),
        'allow_cross_client_views' => filter_var(env('OTA_ALLOW_CROSS_CLIENT_VIEWS', false), FILTER_VALIDATE_BOOL),
        'allow_cross_client_branding' => filter_var(env('OTA_ALLOW_CROSS_CLIENT_BRANDING', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    | Tenant-neutral shared blades permitted in standalone mode when a themed
    | view is missing (e.g. canonical dashboard components reused by JetPK).
    */
    'approved_neutral_view_prefixes' => [
        'components.',
        'errors.',
        'vendor.',
    ],

    /*
    | Canonical JetPK contact defaults (never master-client addresses).
    */
    'canonical_support_email' => (string) env(
        'JETPK_CANONICAL_SUPPORT_EMAIL',
        env('OTA_SUPPORT_EMAIL', 'ota@jetpakistan.pk'),
    ),

    /*
    | Legacy JetPK mailboxes remapped to canonical_support_email at runtime.
    | Intentionally excludes third-party / master-client domains.
    */
    'deprecated_operational_emails' => [
        'support@jetpakistan.com',
        'ticketingjp@jetpakistan.com',
    ],

];
