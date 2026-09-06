<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trending route dynamic fare settings (JetPakistan homepage only)
    |--------------------------------------------------------------------------
    */
    'route_date_offset_days' => (int) env('JETPK_HOMEPAGE_ROUTE_DATE_OFFSET_DAYS', 7),

    'default_return_stay_days' => (int) env('JETPK_HOMEPAGE_DEFAULT_RETURN_STAY_DAYS', 7),

    'fare_freshness_hours' => (int) env('JETPK_HOMEPAGE_FARE_FRESHNESS_HOURS', 30),

    'allow_stale_fare_display' => (bool) env('JETPK_HOMEPAGE_ALLOW_STALE_FARE_DISPLAY', true),

    'min_active_routes' => 4,

    'min_active_destinations' => 4,

    'max_routes' => 12,

    'max_destinations' => 12,

    'max_featured_deals' => 6,

    'default_adults' => 1,

    'default_cabin' => 'economy',

    'default_currency' => 'PKR',

    'default_trip_type' => 'one_way',

    /*
     | Pakistan origin pool for Destinations on Rise cheapest-fare refresh.
     | Matches the established JetPakistan primary gateways used by homepage routes.
     */
    'destination_origin_pool' => array_values(array_filter(array_map(
        static fn (string $code): string => strtoupper(trim($code)),
        explode(',', (string) env('JETPK_HOMEPAGE_DESTINATION_ORIGIN_POOL', 'KHI,LHE,ISB')),
    ))),

    'featured_deal_source' => env('JETPK_HOMEPAGE_FEATURED_DEAL_SOURCE', 'group_ticket'),

    'featured_deal_limit' => (int) env('JETPK_HOMEPAGE_FEATURED_DEAL_LIMIT', 6),

    'destination_fallback_image' => 'themes/frontend/jetpakistan/images/homepage-destination-fallback.svg',

    'destination_storage_prefix' => 'jetpk/homepage/popular-destinations',

    'support_cta_storage_prefix' => 'jetpk/homepage/support-cta',

    'hero_lcp_subdirectory' => 'lcp',

    'refresh_lock_seconds' => 900,

    'context_diagnostic_enabled' => (bool) env('JETPK_CMS_CONTEXT_DIAGNOSTIC', false),
];
