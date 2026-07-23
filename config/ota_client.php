<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTA client deployment profile
    |--------------------------------------------------------------------------
    |
    | Separate from config/ota-client.php (branding/contact fallbacks).
    | Reads OTA_CLIENT_* and OTA_MODULE_* env vars. Safe defaults preserve
    | current single-client behavior when env values are missing.
    |
    */

    'slug' => (string) env('OTA_CLIENT_SLUG', ''),

    /*
    | JetPK dedicated / single-client fork (copy-only; not required on OTA master).
    | When both flags are true, unprefixed root routes resolve the deployment client.
    */
    'single_client_mode' => filter_var(env('OTA_SINGLE_CLIENT_MODE', false), FILTER_VALIDATE_BOOL),

    'single_client_root' => filter_var(env('OTA_SINGLE_CLIENT_ROOT', false), FILTER_VALIDATE_BOOL),

    'default_client' => (string) env('OTA_DEFAULT_CLIENT', env('OTA_CLIENT_SLUG', '')),

    'master_client_slug' => (string) env('OTA_MASTER_CLIENT_SLUG', env('OTA_CLIENT_SLUG', 'haseeb-master')),

    'theme' => (string) env('OTA_ACTIVE_THEME', 'v1-classic'),

    'asset_profile' => (string) env('OTA_PUBLIC_ASSET_PROFILE', env('OTA_CLIENT_SLUG', '')),

    /*
    | Live public document root for on-disk client-assets/themes checks (diagnostics only).
    | URL generation still uses asset() against the web server public URL.
    | Falls back to Laravel public_path() when this directory does not exist.
    */
    'public_webroot_path' => env(
        'OTA_PUBLIC_WEBROOT_PATH',
        public_path(),
    ),

    'modules' => [
        'sabre' => filter_var(env('OTA_MODULE_SABRE', true), FILTER_VALIDATE_BOOL),
        'al_haider_group_ticketing' => filter_var(env('OTA_MODULE_AL_HAIDER_GROUP_TICKETING', true), FILTER_VALIDATE_BOOL),
        'accounting' => filter_var(env('OTA_MODULE_ACCOUNTING', true), FILTER_VALIDATE_BOOL),
        'hotels' => filter_var(env('OTA_MODULE_HOTELS', true), FILTER_VALIDATE_BOOL),
        'visa' => filter_var(env('OTA_MODULE_VISA', true), FILTER_VALIDATE_BOOL),
        'payment_gateway' => filter_var(env('OTA_MODULE_PAYMENT_GATEWAY', true), FILTER_VALIDATE_BOOL),
        'dev_cp' => filter_var(env('OTA_MODULE_DEV_CP', true), FILTER_VALIDATE_BOOL),
        'staff_panel' => filter_var(env('OTA_MODULE_STAFF_PANEL', true), FILTER_VALIDATE_BOOL),
        'admin_panel' => filter_var(env('OTA_MODULE_ADMIN_PANEL', true), FILTER_VALIDATE_BOOL),
    ],

    'auth' => [
        'require_login_otp' => filter_var(env('OTA_CLIENT_REQUIRE_LOGIN_OTP', false), FILTER_VALIDATE_BOOL),
        'login_otp_expiry_minutes' => max(1, (int) env('OTA_CLIENT_LOGIN_OTP_EXPIRY_MINUTES', 10)),
        'login_otp_resend_cooldown_seconds' => max(15, (int) env('OTA_CLIENT_LOGIN_OTP_RESEND_COOLDOWN', 60)),
        'login_otp_max_attempts' => max(1, (int) env('OTA_CLIENT_LOGIN_OTP_MAX_ATTEMPTS', 5)),
    ],

];

