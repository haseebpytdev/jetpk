<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile app shell view preference
    |--------------------------------------------------------------------------
    |
    | Cookie absent = auto (viewport + device UA; see viewport_breakpoint).
    | ota_view_mode=mobile|desktop = manual override until changed again.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | MA-1: mobile app skin toggle (INDEPENDENT of the desktop theme)
    |--------------------------------------------------------------------------
    |
    | null / '' / 'default-mobile' = today's shared mobile shell (no visual change).
    | 'jetpakistan-app'            = JetPakistan compact app-style shell (MA-2+).
    |
    | Deliberately NOT derived from active_admin_theme/active_frontend_theme, so the app skin
    | can be switched on or off without touching the desktop theme. Unknown values fall back to
    | 'default-mobile'. Reversible instantly via env; no schema change.
    |
    */

    'app_theme' => env(
        'OTA_MOBILE_APP_THEME',
        env('OTA_CLIENT_SLUG') === 'jetpk' ? 'jetpakistan-app' : 'default-mobile',
    ),

    'cookie_name' => 'ota_view_mode',

    'cookie_minutes' => 525600,

    'values' => [
        'mobile' => 'mobile',
        'desktop' => 'desktop',
    ],

    'session_key' => 'ota_view_mode',

    /*
    | Auto shell breakpoint (px). <= breakpoint prefers mobile; > breakpoint prefers desktop.
    | Manual ota_view_mode cookie always wins. Client script may pass ?_ota_auto_shell= for
    | one-request reconciliation without persisting a preference.
    */
    'viewport_breakpoint' => (int) env('OTA_MOBILE_VIEWPORT_BREAKPOINT', 768),

    /*
    | User-agent regex for auto mobile mode (phones and tablets).
    */
    'device_patterns' => '/(android|webos|iphone|ipod|blackberry|iemobile|opera mini|mobile|ipad|tablet|silk|playbook|kindle)/i',

    /*
    | Route-name aliases when the page key differs from route()->getName().
    */
    'route_aliases' => [
        'flights.search' => 'home',
        'profile.edit' => 'profile.edit-frontend',
    ],

    /*
    | Pages that branch to layouts.mobile-app when shouldUseMobileShell() is true.
    | false = keep desktop layout even under mobile preference or auto mobile UA.
    */
    'mobile_pages' => [
        'home' => true,
        'flights.results' => true,
        'flights.results.offer' => true,
        'flights.details' => true,
        'booking.passengers' => true,
        'booking.review' => true,
        'booking.confirmation' => true,
        'customer.dashboard' => true,
        'agent.dashboard' => true,

        'login' => true,
        'register' => true,
        'password.request' => true,
        'password.reset' => true,
        'support' => true,
        'about' => true,
        'booking.lookup' => true,
        'guest.bookings.show' => true,
        'profile.edit-frontend' => true,
        'customer.bookings.index' => true,
        'customer.bookings.show' => true,
        'customer.support.index' => true,
        'customer.support.tickets.index' => true,
        'customer.support.tickets.create' => true,
        'customer.support.tickets.show' => true,
        'profile.edit' => false,
        'agent.bookings.index' => true,
        'agent.bookings.create' => true,
        'agent.bookings.show' => true,
        'agent.travelers.index' => true,
        'agent.travelers.create' => true,
        'agent.travelers.edit' => true,
        'agent.wallet.show' => true,
        'agent.deposits.index' => true,
        'agent.deposits.create' => true,
        'agent.ledger.index' => true,
        'agent.reports.index' => true,
        'agent.commissions.index' => true,
        'agent.commissions.statements.show' => true,
        'agent.finance.statement.show' => true,
        'agent.accounting.ledger.index' => true,
        'agent.accounting.ledger.show' => true,
        'agent.staff.index' => true,
        'agent.staff.create' => true,
        'agent.staff.edit' => true,
        'agent.support.tickets.index' => true,
        'agent.support.tickets.create' => true,
        'agent.support.tickets.show' => true,
        'agent.agency.show' => true,
        'agent.agency.edit' => true,
        'agent.register' => true,
        'agent.register.form' => true,
        'agent.register.submitted' => true,
    ],

];
