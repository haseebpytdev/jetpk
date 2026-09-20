<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Next.js on-demand revalidation (SEO publish → live metadata)
    |--------------------------------------------------------------------------
    |
    | Laravel calls the internal Next route after SEO Management publish so
    | cached public metadata refreshes without PM2 restart.
    |
    */
    'next_revalidate_url' => rtrim((string) env('JETPK_NEXT_REVALIDATE_URL', ''), '/'),
    /*
    | Primary allowlisted public-content invalidation endpoint (unstable_cache tags).
    | When empty, derived from next_revalidate_url by replacing /revalidate/seo with
    | /revalidate-public-content.
    */
    'next_public_content_revalidate_url' => rtrim((string) env('JETPK_NEXT_PUBLIC_CONTENT_REVALIDATE_URL', ''), '/'),
    'next_revalidate_secret' => (string) env('JETPK_NEXT_REVALIDATE_SECRET', ''),
];
