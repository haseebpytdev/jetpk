<?php

return [
    'default_agency_slug' => env('OTA_DEFAULT_AGENCY_SLUG', 'asif-travels'),

    /** Require passport-style fields when origin/destination countries differ (see InternationalRouteDetector). */
    'passport_required_for_international' => filter_var(env('OTA_PASSPORT_REQUIRED_INTERNATIONAL', true), FILTER_VALIDATE_BOOL),

    /** When true, domestic itineraries require national_id_number. Default off for PK domestic flights. */
    'require_domestic_national_id' => filter_var(env('OTA_REQUIRE_DOMESTIC_NATIONAL_ID', false), FILTER_VALIDATE_BOOL),
    'passenger_age_rules' => [
        'adult_min_years' => (int) env('OTA_PASSENGER_ADULT_MIN_YEARS', 12),
        'child_min_years' => (int) env('OTA_PASSENGER_CHILD_MIN_YEARS', 2),
        'child_max_years' => (int) env('OTA_PASSENGER_CHILD_MAX_YEARS', 11),
        'infant_max_years' => (int) env('OTA_PASSENGER_INFANT_MAX_YEARS', 1),
    ],
    'guest_lookup_token_minutes' => (int) env('OTA_GUEST_LOOKUP_TOKEN_MINUTES', 30),

    /** Public checkout fare-hold countdown (passengers + review). */
    'checkout_lock_minutes' => max(1, (int) env('OTA_CHECKOUT_LOCK_MINUTES', 7)),

    /** IATI instant-payment local checkout window (minutes) before local payment request expires. */
    'iati_local_checkout_minutes' => max(1, (int) env('OTA_IATI_LOCAL_CHECKOUT_MINUTES', 15)),

    /** Active supplier_booking_attempts older than this are released and retry is allowed. */
    'supplier_booking_attempt_timeout_minutes' => max(1, (int) env('OTA_SUPPLIER_BOOKING_ATTEMPT_TIMEOUT_MINUTES', 10)),

    /** Group ticketing reservation payment window (minutes). */
    'group_booking_hold_minutes' => max(1, (int) env('OTA_GROUP_BOOKING_HOLD_MINUTES', 25)),

    /** GROUP-TICKETING-3C / GROUP-REALTIME-INVENTORY-UI-1: search-time inventory freshness (read-only sync). */
    'group_ticketing' => [
        'realtime_search_enabled' => filter_var(env('OTA_GROUP_REALTIME_SEARCH_ENABLED', env('OTA_GROUP_INVENTORY_SEARCH_SYNC_ENABLED', true)), FILTER_VALIDATE_BOOL),
        'realtime_search_ttl_seconds' => max(0, (int) env('OTA_GROUP_REALTIME_SEARCH_TTL_SECONDS', 0)),
        'require_live_provider_for_public_results' => filter_var(env('OTA_GROUP_REQUIRE_LIVE_PROVIDER_FOR_PUBLIC_RESULTS', true), FILTER_VALIDATE_BOOL),
        'allow_stale_public_results' => filter_var(env('OTA_GROUP_ALLOW_STALE_PUBLIC_RESULTS', false), FILTER_VALIDATE_BOOL),
        'require_live_provider_for_reservation' => filter_var(env('OTA_GROUP_REQUIRE_LIVE_PROVIDER_FOR_RESERVATION', true), FILTER_VALIDATE_BOOL),
        'block_booking_when_provider_unavailable' => filter_var(env('OTA_GROUP_BLOCK_BOOKING_WHEN_PROVIDER_UNAVAILABLE', true), FILTER_VALIDATE_BOOL),
        'inventory_search_sync_enabled' => filter_var(env('OTA_GROUP_INVENTORY_SEARCH_SYNC_ENABLED', true), FILTER_VALIDATE_BOOL),
        'inventory_search_sync_stale_minutes' => max(1, min(60, (int) env('OTA_GROUP_INVENTORY_SEARCH_SYNC_STALE_MINUTES', 3))),
    ],

    /**
     * Sprint 11K-F: Sabre offer freshness windows for search results and checkout gates (seconds).
     */
    'offer_freshness' => [
        'refresh_due_seconds' => max(60, (int) env('OTA_OFFER_FRESHNESS_REFRESH_DUE_SECONDS', 300)),
        'stale_after_seconds' => max(120, (int) env('OTA_OFFER_FRESHNESS_STALE_AFTER_SECONDS', 600)),
    ],

    /** F9O-R1: max minutes after F9N fresh context apply for controlled strong-linkage apply (independent of F9M stale lane). */
    'controlled_strong_linkage_apply' => [
        'max_minutes_after_fresh_context_apply' => max(
            30,
            (int) env('OTA_CONTROLLED_STRONG_LINKAGE_APPLY_MAX_MINUTES', 180),
        ),
    ],

    /** F9P: max minutes after last revalidation / fresh context for final controlled PNR retry readiness (independent of F9M stale lane). */
    'controlled_final_pnr_freshness' => [
        'max_minutes' => max(
            5,
            (int) env('OTA_CONTROLLED_FINAL_PNR_FRESHNESS_MAX_MINUTES', 15),
        ),
    ],

    /** F9Q: max minutes after explicit final retry allowance write before controlled create must consume it. */
    'controlled_final_pnr_retry_allowance' => [
        'max_minutes' => max(
            5,
            (int) env('OTA_CONTROLLED_FINAL_PNR_RETRY_ALLOWANCE_MAX_MINUTES', 15),
        ),
    ],

    /**
     * Sprint 11K-I: bounded lookup for persisted Sabre host-rejection fingerprints at checkout only.
     */
    'host_rejection_fingerprint' => [
        'lookback_days' => max(1, (int) env('OTA_HOST_REJECTION_FINGERPRINT_LOOKBACK_DAYS', 30)),
        'max_bookings_scan' => max(1, min(200, (int) env('OTA_HOST_REJECTION_FINGERPRINT_MAX_SCAN', 40))),
    ],
    'private_documents_directory' => env('OTA_PRIVATE_DOCUMENTS_DIRECTORY', 'app/private'),
    'pdf_temp_directory' => env('OTA_PDF_TEMP_DIRECTORY', 'app/private/tmp/pdf'),
    'supplier_default_provider' => env('OTA_SUPPLIER_DEFAULT_PROVIDER', 'duffel'),
    'supplier_timeout_seconds' => (int) env('OTA_SUPPLIER_TIMEOUT_SECONDS', 20),

    /**
     * Public flight results JSON/store: only these supplier_provider values are kept outside the testing environment.
     *
     * @var list<string>
     */
    'public_flight_results_suppliers' => array_values(array_filter(array_map(
        static fn (string $v): string => strtolower(trim($v)),
        explode(',', (string) env('OTA_PUBLIC_FLIGHT_RESULTS_SUPPLIERS', 'duffel,sabre,iati,pia_ndc'))
    ))),

    /**
     * RETURN-SPLIT-SELECT-R1: two-step outbound/return selection for round-trip searches.
     */
    'return_split_select_enabled' => filter_var(env('OTA_RETURN_SPLIT_SELECT_ENABLED', true), FILTER_VALIDATE_BOOL),

    /*
    | When no uploaded logo exists (airlines.logo_path + public disk file), flight results can
    | still show a logo using a public CDN template. {CODE} = 2-letter IATA (e.g. EK).
    | Set OTA_AIRLINE_LOGO_CDN_ENABLED=false to disable (e.g. offline demos).
    */
    'airline_logo_cdn_enabled' => filter_var(env('OTA_AIRLINE_LOGO_CDN_ENABLED', true), FILTER_VALIDATE_BOOL),
    'airline_logo_cdn_template' => env(
        'OTA_AIRLINE_LOGO_CDN_TEMPLATE',
        'https://images.kiwi.com/airlines/64x64/{CODE}.png'
    ),

    /*
    | Local public-disk cache for airline logos (served as /storage/airline-logos/{CODE}.png).
    | Download template is used only by ota:cache-airline-logos and on-miss cache — never hotlinked in HTML.
    */
    'airline_logo_cache' => [
        'enabled' => filter_var(env('OTA_AIRLINE_LOGO_CACHE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'directory' => env('OTA_AIRLINE_LOGO_CACHE_DIR', 'airline-logos'),
        'download_enabled' => filter_var(env('OTA_AIRLINE_LOGO_CACHE_DOWNLOAD_ENABLED', true), FILTER_VALIDATE_BOOL),
        'download_on_miss' => filter_var(env('OTA_AIRLINE_LOGO_CACHE_DOWNLOAD_ON_MISS', true), FILTER_VALIDATE_BOOL),
        'download_timeout_seconds' => (int) env('OTA_AIRLINE_LOGO_CACHE_DOWNLOAD_TIMEOUT', 8),
        'download_template' => env(
            'OTA_AIRLINE_LOGO_CACHE_DOWNLOAD_TEMPLATE',
            env('OTA_AIRLINE_LOGO_CDN_TEMPLATE', 'https://images.kiwi.com/airlines/64x64/{CODE}.png')
        ),
        'generic_fallback' => env('OTA_AIRLINE_LOGO_GENERIC_FALLBACK', 'images/airline-generic.svg'),
    ],

    /**
     * Fallback display names when shop payloads omit marketing airline names (UI only).
     *
     * @var array<string, string>
     */
    'airline_display_names' => [
        '6E' => 'IndiGo',
        'BA' => 'British Airways',
        'EK' => 'Emirates',
        'ER' => 'SereneAir',
        'EY' => 'Etihad Airways',
        'FZ' => 'flydubai',
        'G9' => 'Air Arabia',
        'GF' => 'Gulf Air',
        'J9' => 'Jazeera Airways',
        'KU' => 'Kuwait Airways',
        'PA' => 'Airblue',
        'PK' => 'Pakistan International Airlines',
        'QR' => 'Qatar Airways',
        'SV' => 'Saudia',
        'TK' => 'Turkish Airlines',
        'UL' => 'SriLankan Airlines',
        'WY' => 'Oman Air',
        'XY' => 'flynas',
    ],

    'backup' => [
        'disk' => env('OTA_BACKUP_DISK', 'local'),
        'path' => env('OTA_BACKUP_PATH', 'backups'),
    ],

    /*
    | local/testing only (see BookingController + FareHoldService): if Duffel single-offer validation
    | returns unavailable for an offer ID that belongs to a cached search created within this many
    | seconds, checkout may continue using cached normalized pricing and defer automated supplier
    | booking to manual review. Staging/production ignore this path.
    */
    'provider_unstable_test_mode_window_seconds' => max(1, (int) env('OTA_PROVIDER_UNSTABLE_WINDOW_SECONDS', 120)),

    /**
     * When true (local only), allow the provider-unstable cached-pricing checkout fallback.
     * Testing always allows; staging/production never do (see BookingController).
     */
    'allow_provider_unstable_local' => filter_var(env('OTA_ALLOW_PROVIDER_UNSTABLE_LOCAL', false), FILTER_VALIDATE_BOOL),

    /** Privileged login security emails (see AuthenticatedSessionController / LoginRequest). */
    'notify_customer_login' => filter_var(env('NOTIFY_CUSTOMER_LOGIN', false), FILTER_VALIDATE_BOOL),
    'notify_agent_login' => filter_var(env('NOTIFY_AGENT_LOGIN', false), FILTER_VALIDATE_BOOL),
    'notify_staff_login' => filter_var(env('NOTIFY_STAFF_LOGIN', true), FILTER_VALIDATE_BOOL),
    'notify_admin_login' => filter_var(env('NOTIFY_ADMIN_LOGIN', true), FILTER_VALIDATE_BOOL),
    'notify_failed_admin_login' => filter_var(env('NOTIFY_FAILED_ADMIN_LOGIN', true), FILTER_VALIDATE_BOOL),
    'notify_failed_login' => filter_var(env('NOTIFY_FAILED_LOGIN', true), FILTER_VALIDATE_BOOL),
    'auth_failed_login_email_threshold' => max(1, (int) env('AUTH_FAILED_LOGIN_EMAIL_THRESHOLD', 3)),
    'auth_failed_login_email_cooldown_minutes' => max(1, (int) env('AUTH_FAILED_LOGIN_EMAIL_COOLDOWN_MINUTES', 60)),
    'auth_login_success_email_cooldown_minutes' => max(0, (int) env('AUTH_LOGIN_SUCCESS_EMAIL_COOLDOWN_MINUTES', 15)),
    'notify_auth_new_device_login' => filter_var(env('NOTIFY_AUTH_NEW_DEVICE_LOGIN', true), FILTER_VALIDATE_BOOL),
    'auth_new_device_email_cooldown_minutes' => max(1, (int) env('AUTH_NEW_DEVICE_EMAIL_COOLDOWN_MINUTES', 60)),

    /** Demo access seeding (`ota:seed-access-demo-users`). */
    'access_demo' => [
        'owner_email' => env('OTA_ACCESS_DEMO_OWNER_EMAIL', 'myworkhaseeb@gmail.com'),
        'include_owner_email' => filter_var(env('OTA_ACCESS_DEMO_INCLUDE_OWNER_EMAIL', false), FILTER_VALIDATE_BOOL),
    ],

    /**
     * Abandoned flight search follow-up (Phase 3). 3A persists snapshots only; sending is later.
     */
    /** Daily agency booking activity summary digest (A3) — scheduler in routes/console.php. */
    'agency_booking_activity_summary_daily_enabled' => filter_var(
        env('AGENCY_BOOKING_ACTIVITY_SUMMARY_DAILY_ENABLED', true),
        FILTER_VALIDATE_BOOL
    ),

    'abandoned_search_followup' => [
        'enabled' => filter_var(env('OTA_ABANDONED_SEARCH_FOLLOWUP_ENABLED', true), FILTER_VALIDATE_BOOL),
        'delay_hours' => max(1, (int) env('OTA_ABANDONED_SEARCH_DELAY_HOURS', 4)),
        'expire_hours' => max(1, (int) env('OTA_ABANDONED_SEARCH_EXPIRE_HOURS', 48)),
        'daily_cap' => max(0, (int) env('OTA_ABANDONED_SEARCH_DAILY_CAP', 2)),
        'capture_logged_in_only' => filter_var(env('OTA_ABANDONED_SEARCH_CAPTURE_LOGGED_IN_ONLY', true), FILTER_VALIDATE_BOOL),
        'batch_size' => max(1, (int) env('OTA_ABANDONED_SEARCH_FOLLOWUP_BATCH_SIZE', 50)),
        'send_batch_size' => max(1, (int) env('OTA_ABANDONED_SEARCH_FOLLOWUP_SEND_BATCH_SIZE', 50)),
        'email_subject' => env('OTA_ABANDONED_SEARCH_FOLLOWUP_EMAIL_SUBJECT', ''),
    ],

    /** Public results: every offer gets a fare-option card (single default or multi branded). */
    'universal_fare_choice_enabled' => filter_var(env('OTA_UNIVERSAL_FARE_CHOICE_ENABLED', true), FILTER_VALIDATE_BOOL),

    'itinerary_fare_consolidation_enabled' => filter_var(env('OTA_ITINERARY_FARE_CONSOLIDATION_ENABLED', true), FILTER_VALIDATE_BOOL),

    /** Promo checkout (PROMO-1): customer payable discounts only; supplier fares unchanged. */
    'promo' => [
        'allow_zero_payable' => filter_var(env('OTA_PROMO_ALLOW_ZERO_PAYABLE', false), FILTER_VALIDATE_BOOL),
        'allow_internal_testing_codes' => filter_var(env('OTA_PROMO_ALLOW_INTERNAL_TESTING', true), FILTER_VALIDATE_BOOL),
    ],
];
