<?php

/*
|--------------------------------------------------------------------------
| JetPakistan Email Configuration
|--------------------------------------------------------------------------
|
| Client-specific config for the JetPK email template system. Nothing here
| changes Master behaviour. The view map is consumed by JetpkEmailViewResolver
| and the brand map by JetpkEmailBrandingResolver (as overrides on top of the
| safe JetPK constants).
|
*/

return [

    'client_slug' => 'jetpk',

    /*
    | Logical email type => Blade view path.
    | Override any entry here without touching resolver code.
    */
    'views' => [
        // Auth / security
        'otp'                            => 'emails.themes.jetpakistan.auth.otp',
        'sign_in_success'                => 'emails.themes.jetpakistan.auth.sign-in-success',
        'password_reset'                 => 'emails.themes.jetpakistan.auth.password-reset',
        'account_created'                => 'emails.themes.jetpakistan.auth.account-created',

        // Booking
        'booking_created'                => 'emails.themes.jetpakistan.booking.booking-created',
        'booking_pending_manual_payment' => 'emails.themes.jetpakistan.booking.booking-pending-manual-payment',
        'booking_confirmed'              => 'emails.themes.jetpakistan.booking.booking-confirmed',
        'booking_failed'                 => 'emails.themes.jetpakistan.booking.booking-failed',
        'booking_cancelled'              => 'emails.themes.jetpakistan.booking.booking-cancelled',
        'booking_updated'                => 'emails.themes.jetpakistan.booking.booking-updated',
        'booking_expiring'               => 'emails.themes.jetpakistan.booking.booking-expiring',

        // Payment
        'manual_payment_received'        => 'emails.themes.jetpakistan.payment.manual-payment-received',
        'payment_success'                => 'emails.themes.jetpakistan.payment.payment-success',
        'payment_failed'                 => 'emails.themes.jetpakistan.payment.payment-failed',
        'invoice'                        => 'emails.themes.jetpakistan.payment.invoice',
        'refund_requested'               => 'emails.themes.jetpakistan.payment.refund-requested',
        'refund_updated'                 => 'emails.themes.jetpakistan.payment.refund-updated',

        // Support
        'support_ticket_created'         => 'emails.themes.jetpakistan.support.support-ticket-created',
        'support_reply'                  => 'emails.themes.jetpakistan.support.support-reply',

        // Generic
        'notification'                   => 'emails.themes.jetpakistan.generic.notification',
    ],

    /*
    | Optional brand overrides. Any value set here wins over the safe JetPK
    | constants but is still overridden by a real client/branding profile once
    | JetpkEmailBrandingResolver::fetchClientProfile() is wired.
    |
    | Leave logo_url null to use the "JetPakistan" text fallback safely.
    */
    'brand' => [
        // 'brand_name'    => env('JETPK_BRAND_NAME', 'JetPakistan'),
        // 'logo_url'      => env('JETPK_LOGO_URL'),       // absolute or app-relative
        // 'home_url'      => env('JETPK_HOME_URL', 'https://ota.haseebasif.com/jetpk'),
        // 'support_email' => env('JETPK_SUPPORT_EMAIL', 'support@jetpakistan.com'),
        // 'support_phone' => env('JETPK_SUPPORT_PHONE'),
        // 'primary_color' => '#00843D',
        // 'accent_color'  => '#F58220',
        // 'address'       => null,
    ],

];
