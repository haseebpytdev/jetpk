<?php

/**
 * OTA Layered UI Override manifest.
 *
 * Add new UI fixes as named layer files under public/css/layers/ and public/js/layers/
 * instead of editing base assets (ota-public.css, ota-admin-console.css, etc.).
 *
 * Load order: ascending `order`, then `key`. Layers load after base CSS/JS in layouts.
 * Toggle via env (OTA_UI_LAYER_{KEY}), config default, or Developer CP overrides.
 *
 * @see docs/ui-layers/README.md
 */
return [

    'enabled' => filter_var(env('OTA_UI_LAYERS_ENABLED', true), FILTER_VALIDATE_BOOL),

    'contexts' => [
        'public' => 'Public site (guest pages, home, checkout shell)',
        'admin' => 'Admin operator console',
        'staff' => 'Staff operator console',
        'agent' => 'Agent portal account shell',
        'customer' => 'Customer account shell',
        'flight-results' => 'Flight search results (desktop + mobile)',
        'supplier-sabre' => 'Sabre-specific admin/staff UI overlays',
        'supplier-duffel' => 'Duffel-specific admin/staff UI overlays',
        'supplier-pia-ndc' => 'PIA NDC-specific admin/staff UI overlays',
    ],

    /*
    | Layer registry. Each entry:
    | - key: stable identifier (env OTA_UI_LAYER_{UPPER_SNAKE_KEY})
    | - contexts: where the layer may load (see contexts above)
    | - order: sort priority (lower = earlier among layers)
    | - enabled: config default when no DB override
    | - css/js: paths relative to public/
    | - rollback: operator rollback notes (shown in Dev CP)
    | - description: short purpose (Dev CP + docs)
    | - suppliers: optional; when set, layer loads only when $uiLayerSupplier matches
    */
    'layers' => [
        [
            'key' => 'example-public-shell',
            'contexts' => ['public'],
            'order' => 100,
            'enabled' => false,
            'css' => ['css/layers/public/example-public-shell.css'],
            'js' => [],
            'description' => 'Template public layer (disabled by default).',
            'rollback' => 'Set enabled=false in Dev CP or remove css/layers/public/example-public-shell.css. Base ota-public.css unchanged.',
        ],
        [
            'key' => 'example-flight-results',
            'contexts' => ['flight-results'],
            'order' => 100,
            'enabled' => false,
            'css' => ['css/layers/flight-results/example-flight-results.css'],
            'js' => ['js/layers/flight-results/example-flight-results.js'],
            'description' => 'Template flight-results layer (disabled by default).',
            'rollback' => 'Disable key example-flight-results in Dev CP; delete layer files under css/js/layers/flight-results/.',
        ],
        [
            'key' => 'price-button-text-cleanup',
            'contexts' => ['flight-results'],
            'order' => 20,
            'enabled' => true,
            'css' => ['css/layers/flight-results/price-button-text-cleanup.css'],
            'js' => [],
            'description' => 'UI-HOTFIX-PRICE-BUTTON-1: result price CTA shows fare only (no visible Continue).',
            'rollback' => 'Disable key price-button-text-cleanup in Dev CP; remove css/layers/flight-results/price-button-text-cleanup.css.',
        ],
        [
            'key' => 'example-admin-console',
            'contexts' => ['admin'],
            'order' => 100,
            'enabled' => false,
            'css' => ['css/layers/admin/example-admin-console.css'],
            'js' => [],
            'description' => 'Template admin layer (disabled by default).',
            'rollback' => 'Disable in Dev CP; remove css/layers/admin/example-admin-console.css. ota-admin-console.css untouched.',
        ],
        [
            'key' => 'example-staff-console',
            'contexts' => ['staff'],
            'order' => 100,
            'enabled' => false,
            'css' => ['css/layers/staff/example-staff-console.css'],
            'js' => [],
            'description' => 'Template staff layer (disabled by default).',
            'rollback' => 'Disable in Dev CP; remove css/layers/staff/example-staff-console.css.',
        ],
        [
            'key' => 'example-agent-portal',
            'contexts' => ['agent'],
            'order' => 100,
            'enabled' => false,
            'css' => ['css/layers/agent/example-agent-portal.css'],
            'js' => [],
            'description' => 'Template agent portal layer (disabled by default).',
            'rollback' => 'Disable in Dev CP; remove css/layers/agent/example-agent-portal.css. ota-portal-console.css untouched.',
        ],
        [
            'key' => 'example-customer-portal',
            'contexts' => ['customer'],
            'order' => 100,
            'enabled' => false,
            'css' => ['css/layers/customer/example-customer-portal.css'],
            'js' => [],
            'description' => 'Template customer portal layer (disabled by default).',
            'rollback' => 'Disable in Dev CP; remove css/layers/customer/example-customer-portal.css.',
        ],
        [
            'key' => 'example-supplier-sabre',
            'contexts' => ['admin', 'staff'],
            'suppliers' => ['sabre'],
            'order' => 200,
            'enabled' => false,
            'css' => ['css/layers/suppliers/sabre/example-supplier-sabre.css'],
            'js' => [],
            'description' => 'Template Sabre booking UI layer (requires $uiLayerSupplier=sabre).',
            'rollback' => 'Disable in Dev CP; remove css/layers/suppliers/sabre/. Set $uiLayerSupplier only on views that need it.',
        ],
        [
            'key' => 'example-supplier-duffel',
            'contexts' => ['admin', 'staff'],
            'suppliers' => ['duffel'],
            'order' => 200,
            'enabled' => false,
            'css' => ['css/layers/suppliers/duffel/example-supplier-duffel.css'],
            'js' => [],
            'description' => 'Template Duffel booking UI layer (requires $uiLayerSupplier=duffel).',
            'rollback' => 'Disable in Dev CP; remove css/layers/suppliers/duffel/.',
        ],
    ],

];
