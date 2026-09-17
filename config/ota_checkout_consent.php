<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public checkout terms acceptance (versionable)
    |--------------------------------------------------------------------------
    |
    | Temporary customer-facing wording lives in the frontend. This identifier
    | records which terms revision was accepted for audit metadata. Replace
    | legal copy without redesigning the acceptance contract.
    |
    */
    'terms_version' => env('OTA_CHECKOUT_TERMS_VERSION', 'jetpk-checkout-terms-2026-08-22'),
    'privacy_version' => env('OTA_CHECKOUT_PRIVACY_VERSION', 'jetpk-privacy-2026-08-22'),
];
