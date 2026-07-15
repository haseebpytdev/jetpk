<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local / testing fixed OTP for seeded demo users (JetPK fork only)
    |--------------------------------------------------------------------------
    |
    | Disabled by default. Never honored when APP_ENV=production.
    | Does not weaken production OTP — see DemoFixedLoginOtpGate.
    |
    */

    'fixed_enabled' => filter_var(env('OTP_DEMO_FIXED_ENABLED', false), FILTER_VALIDATE_BOOL),

    'fixed_code' => (string) env('OTP_DEMO_FIXED_CODE', ''),

    'allowed_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('OTP_DEMO_ALLOWED_EMAILS', '')),
    ))),

    'allow_devcp' => filter_var(env('OTP_DEMO_ALLOW_DEVCP', false), FILTER_VALIDATE_BOOL),

];
