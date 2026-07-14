<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile app shell view preference
    |--------------------------------------------------------------------------
    |
    | Cookie absent = auto (phone/tablet UA uses mobile shell where mapped).
    | ota_view_mode=mobile|desktop = manual override until changed again.
    |
    */

    'cookie_name' => 'ota_view_mode',

    'cookie_minutes' => 525600,

    'values' => [
        'mobile' => 'mobile',
        'desktop' => 'desktop',
    ],

    'session_key' => 'ota_view_mode',

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
