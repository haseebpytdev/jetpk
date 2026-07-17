<?php

/*
|--------------------------------------------------------------------------
| JetPakistan Email Configuration
|--------------------------------------------------------------------------
|
| Single universal shell + event-content system. All logical types render
| through emails.themes.jetpakistan.universal-event inside layouts.base.
| Nothing here changes Master behaviour.
|
*/

return [

    'client_slug' => 'jetpk',

    'shell_view' => 'emails.themes.jetpakistan.layouts.base',

    'content_view' => 'emails.themes.jetpakistan.universal-event',

    /*
    | Legacy type keys preserved for routing compatibility (JetpkEmailEventTypeMap).
    */
    'types' => [
        'otp', 'sign_in_success', 'password_reset', 'account_created', 'email_verification',
        'password_changed', 'security_notice', 'booking_created', 'booking_pending_manual_payment',
        'booking_confirmed', 'booking_failed', 'booking_cancelled', 'booking_updated',
        'booking_expiring', 'pnr_created', 'manual_payment_received', 'payment_success',
        'payment_failed', 'invoice', 'refund_requested', 'refund_updated',
        'support_ticket_created', 'support_reply', 'group_reservation_created',
        'group_reservation_expiring', 'agent_registration_received', 'agent_registration_approved',
        'admin_operational_notification', 'notification',
    ],

    'brand' => [],

    'full_html_override_enabled_by_default' => false,

    /*
    | Fragments that must never appear in resolved email brand/agency names.
    | Rejection markers only — not runtime branding fallbacks.
    */
    'prohibited_brand_markers' => [
        'Parwaaz',
        'parwaaz',
        'Parwaaz Travels',
        'YD Travel',
        'YoursDomain',
        'yoursdomain',
        'haseeb-master',
        'placeholder 123',
    ],

    // @deprecated alias — use prohibited_brand_markers
    'forbidden_brand_fragments' => [
        'Parwaaz',
        'parwaaz',
        'Parwaaz Travels',
        'YD Travel',
        'YoursDomain',
        'yoursdomain',
        'haseeb-master',
        'placeholder 123',
    ],

];
