<?php

return [
    /*
    | Optional Visa module (JP-VISA-MOFA-01B).
    | Live Saudi MOFA transport requires module_enabled + provider_enabled + policy_approved + transport=live.
    */
    'module_enabled' => filter_var(env('OTA_VISA_MODULE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'allow_in_testing' => filter_var(env('OTA_VISA_ALLOW_IN_TESTING', true), FILTER_VALIDATE_BOOL),
    'default_provider' => env('OTA_VISA_DEFAULT_PROVIDER', 'mock'), // mock|saudi_mofa
    'session_ttl_seconds' => max(60, (int) env('OTA_VISA_SESSION_TTL', 900)),
    'max_provider_body_bytes' => max(100_000, (int) env('OTA_VISA_MAX_BODY_BYTES', 2_000_000)),

    'saudi_mofa' => [
        'provider_enabled' => filter_var(env('OTA_VISA_SAUDI_MOFA_ENABLED', false), FILTER_VALIDATE_BOOL),
        'policy_approved' => filter_var(env('OTA_VISA_SAUDI_MOFA_POLICY_APPROVED', false), FILTER_VALIDATE_BOOL),
        /** fixture|live — live denied unless policy_approved */
        'transport' => env('OTA_VISA_SAUDI_MOFA_TRANSPORT', 'fixture'),
        'base_url' => rtrim((string) env('OTA_VISA_SAUDI_MOFA_BASE_URL', 'https://visa.mofa.gov.sa'), '/'),
        'official_fallback_url' => (string) env(
            'OTA_VISA_SAUDI_MOFA_FALLBACK_URL',
            'https://visa.mofa.gov.sa/visaservices/searchvisa'
        ),
        'timeout_seconds' => max(5, (int) env('OTA_VISA_SAUDI_MOFA_TIMEOUT', 20)),
        'allowed_hosts' => ['visa.mofa.gov.sa'],
        'allowed_path_prefixes' => [
            '/visaservices/searchvisa',
            '/Base/GetRandomCaptchaImage',
            '/Home/PrintedUmrahVisa',
        ],
    ],
];
