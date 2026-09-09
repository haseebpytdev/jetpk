<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Next.js on-demand cache revalidation (homepage CMS + public config)
    |--------------------------------------------------------------------------
    |
    | Laravel calls the secured Next route after CMS publish or branding updates.
    | Set JETPK_NEXT_REVALIDATE_SECRET identically in Laravel .env and the public
    | Next frontend runtime environment.
    |
    */
    'public_base_url' => rtrim((string) env('JETPK_NEXT_PUBLIC_BASE_URL', env('APP_URL', 'https://jetpakistan.pk')), '/'),

    'revalidate_secret' => (string) env('JETPK_NEXT_REVALIDATE_SECRET', ''),

    'revalidate_homepage_path' => '/api/internal/revalidate/homepage',

    'revalidate_timeout_seconds' => (int) env('JETPK_NEXT_REVALIDATE_TIMEOUT', 8),
];
