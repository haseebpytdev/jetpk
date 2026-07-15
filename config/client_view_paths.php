<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client theme view roots (MC-8B)
    |--------------------------------------------------------------------------
    |
    | Theme-specific Blade roots live under resources/views/themes/{area}/{theme}/.
    | Legacy roots describe where production views currently live; the resolver
    | falls back to existing dot-notation view names without overriding Laravel's
    | global view finder.
    |
    | Placeholders: {area}, {theme}
    |
    */

    'areas' => [
        'frontend' => [
            'theme_root' => 'themes/frontend/{theme}',
            'legacy_root' => 'resources/views',
            'theme_fallback' => 'v1-classic',
            'legacy_prefix' => 'frontend',
        ],
        'admin' => [
            'theme_root' => 'themes/admin/{theme}',
            'legacy_root' => 'resources/views/admin',
            'theme_fallback' => 'default-admin',
            'legacy_prefix' => 'dashboard.admin',
        ],
        'staff' => [
            'theme_root' => 'themes/staff/{theme}',
            'legacy_root' => 'resources/views/staff',
            'theme_fallback' => 'default-staff',
            'legacy_prefix' => 'dashboard.staff',
        ],
        'customer' => [
            'theme_root' => 'themes/customer/{theme}',
            'legacy_root' => 'resources/views/customer',
            'theme_fallback' => 'default-customer',
            'legacy_prefix' => 'dashboard.customer',
        ],
        'agent' => [
            'theme_root' => 'themes/agent/{theme}',
            'legacy_root' => 'resources/views/agent',
            'theme_fallback' => 'default-agent',
            'legacy_prefix' => 'dashboard.agent',
        ],

        /*
        | MA-1: mobile app shell area.
        | legacy_prefix 'mobile' means client_view('customer.bookings.index', 'mobile')
        | resolves themes.mobile.{theme}.customer.bookings.index when it exists, and otherwise
        | falls back to the EXISTING mobile.customer.bookings.index view.
        */
        'mobile' => [
            'theme_root' => 'themes/mobile/{theme}',
            'legacy_root' => 'resources/views/mobile',
            'theme_fallback' => 'default-mobile',
            'legacy_prefix' => 'mobile',
        ],
    ],

    /*
    | Sample logical view keys for ota:client-view-audit (MC-8B).
    | "optional" views are skipped when neither theme nor legacy exists.
    */
    'audit_samples' => [
        ['area' => 'frontend', 'name' => 'home', 'label' => 'frontend home'],
        ['area' => 'frontend', 'name' => 'welcome', 'label' => 'frontend welcome', 'optional' => true],
        ['area' => 'frontend', 'name' => 'auth.login', 'label' => 'auth login'],
        ['area' => 'admin', 'name' => 'index', 'label' => 'admin dashboard'],
        ['area' => 'staff', 'name' => 'index', 'label' => 'staff dashboard'],
        ['area' => 'agent', 'name' => 'index', 'label' => 'agent dashboard'],
        ['area' => 'customer', 'name' => 'dashboard', 'label' => 'customer dashboard'],
    ],

    'mc8b_note' => 'MC-8B resolver active; theme views resolve with legacy fallback.',

    /*
    | Sample logical layout keys for ota:client-layout-audit (MC-8D).
    */
    'layout_audit_samples' => [
        ['area' => 'frontend', 'name' => 'frontend', 'label' => 'frontend layout'],
        ['area' => 'frontend', 'name' => 'auth', 'label' => 'auth layout'],
        ['area' => 'admin', 'name' => 'dashboard', 'label' => 'admin dashboard layout'],
        ['area' => 'staff', 'name' => 'dashboard', 'label' => 'staff dashboard layout'],
        ['area' => 'agent', 'name' => 'agent-portal', 'label' => 'agent portal layout'],
        ['area' => 'customer', 'name' => 'customer-account', 'label' => 'customer account layout'],
        ['area' => 'mobile', 'name' => 'mobile-app', 'label' => 'mobile app shell layout'],
    ],

    'mc8d_note' => 'MC-8D: client_layout() resolves theme layout shells with legacy fallback; opt-in page migration only.',

    /*
    | MC-8C first migrated page — public homepage desktop shell (HomeController).
    | Mobile shell still uses mobile.home until a later phase.
    */
    'mc8c_migrated_page' => [
        'area' => 'frontend',
        'logical_name' => 'frontend.home',
        'label' => 'Public homepage (desktop)',
        'fallback_sample' => [
            'area' => 'frontend',
            'logical_name' => 'auth.login',
            'label' => 'auth login (legacy fallback sample)',
        ],
    ],

    'mc8c_note' => 'MC-8C: homepage desktop uses client_view(); theme shell delegates to frontend.home.',

    /*
    | MC-9A/9B layout migration scope — page views opt into client_layout().
    | Theme shells under resources/views/themes/{area}/{theme}/layouts/ delegate to legacy layouts.
    */
    'mc9_migrated_layout_scope' => [
        'frontend_paths' => ['resources/views/frontend'],
        'auth_paths' => ['resources/views/auth', 'resources/views/frontend/agent-registration'],
        'admin_paths' => ['resources/views/dashboard/admin'],
        'staff_paths' => ['resources/views/dashboard/staff'],
        'agent_paths' => ['resources/views/dashboard/agent'],
        'customer_paths' => ['resources/views/dashboard/customer'],
        'deferred_paths' => [
            'resources/views/profile/edit-dashboard.blade.php',
            'resources/views/profile/edit-agent.blade.php',
            'resources/views/profile/edit-frontend.blade.php',
        ],
    ],

    'mc9_note' => 'MC-9A–9E: page views opt into client_layout(); theme shells delegate to legacy layouts.',

];
