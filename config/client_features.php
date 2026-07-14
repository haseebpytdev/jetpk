<?php

/**
 * Client feature readiness map (planning stub — not runtime gates yet).
 *
 * Used by ota:jetpk-deep-flow-isolation-audit for reporting only.
 * Shared backend/supplier logic may be enabled globally; UI flows are client-scoped.
 */
return [
    'clients' => [
        'jetpk' => [
            'frontend_theme' => 'jetpakistan',
            'admin_theme' => 'jetpakistan',
            'fallback_ui_allowed' => false,
            'shared_backend_allowed' => true,
            'features' => [
                'flight_search_v2' => true,
                'jetpk_result_cards_v1' => true,
                'jetpk_checkout_v1' => true,
                'return_flow_v1' => true,
                'multi_city_enabled' => true,
                'multi_city_checkout' => false,
                'multi_city_inquiry_only' => true,
                'card_payment_enabled' => true,
                'manual_payment_enabled' => true,
                'branded_fares_carousel_v1' => true,
                'page_builder_enabled' => true,
            ],
        ],
        'haseeb-master' => [
            'frontend_theme' => 'default',
            'admin_theme' => 'default',
            'fallback_ui_allowed' => true,
            'shared_backend_allowed' => true,
            'features' => [
                'flight_search_v2' => true,
                'jetpk_result_cards_v1' => false,
                'jetpk_checkout_v1' => false,
                'return_flow_v1' => true,
                'multi_city_enabled' => true,
                'multi_city_checkout' => false,
                'multi_city_inquiry_only' => true,
                'card_payment_enabled' => true,
                'manual_payment_enabled' => true,
                'branded_fares_carousel_v1' => true,
                'page_builder_enabled' => false,
            ],
        ],
    ],
];
