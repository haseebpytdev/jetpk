<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client route parity (MC-7B)
    |--------------------------------------------------------------------------
    |
    | Registers GET/HEAD parity routes under /{clientSlug}/{originalUri} for
    | safe read-only pages. Production unprefixed routes are unchanged.
    |
    */

    'enabled' => env('CLIENT_ROUTE_PARITY_ENABLED', true),

    'master_host' => env('CLIENT_ROUTE_PARITY_MASTER_HOST'),

    'host_guard_enabled' => env('CLIENT_ROUTE_PARITY_HOST_GUARD_ENABLED', false),

    'default_client_slug' => env('CLIENT_ROUTE_PARITY_DEFAULT_SLUG', config('client.canonical_client.slug', 'jetpk')),

    'allow_haseeb_master_prefixed_parity' => env('CLIENT_ROUTE_PARITY_ALLOW_HASEEB_MASTER', true), // deprecated: default slug always redirects

    'allowed_methods' => ['GET', 'HEAD'],

    'max_risk' => 'low',

    'allowed_classifications' => [
        'public_page',
        'auth_page',
        'customer_dashboard',
        'agent_dashboard',
        'staff_dashboard',
        'admin_dashboard',
        'group_ticketing',
        'booking_flow',
    ],

    'excluded_route_names' => [
        'client.preview.*',
        'client.parity.*',
        'sanctum.*',
        'social.callback',
    ],

    'excluded_uri_prefixes' => [
        'dev/cp',
        'api/',
        '{clientslug}',
    ],

];
