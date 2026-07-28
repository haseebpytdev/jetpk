<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Next.js dashboard server URL
    |--------------------------------------------------------------------------
    |
    | When static HTML export is not present in storage, Laravel proxies
    | authenticated dashboard requests to this Next.js server (next start).
    |
    */
    'next_server_url' => env('DASHBOARD_NEXT_SERVER_URL', 'http://127.0.0.1:3001'),

    'next_proxy_enabled' => env('DASHBOARD_NEXT_PROXY_ENABLED', true),

];
