<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client theme registry (MC-8A)
    |--------------------------------------------------------------------------
    |
    | Canonical list of deployable themes per portal area. Runtime resolution
    | validates profile/config selections against this registry before falling
    | back to the area default.
    |
    | On-disk assets live under public/{asset_base}/ (e.g. themes/frontend/v1-classic/).
    |
    */

    'areas' => [

        'frontend' => [
            'fallback' => 'v1-classic',
            'themes' => [
                'v1-classic' => [
                    'key' => 'v1-classic',
                    'name' => 'Classic (v1)',
                    'area' => 'frontend',
                    'version' => '1',
                    'status' => 'active',
                    'asset_base' => 'themes/frontend/v1-classic',
                    'preview_image' => null,
                    'description' => 'Canonical public site theme aligned with v1 UI channel.',
                    'supports' => ['css', 'js', 'layouts'],
                ],
                'jetpakistan' => [
                    'key' => 'jetpakistan',
                    'name' => 'JetPakistan',
                    'area' => 'frontend',
                    'version' => '1',
                    'status' => 'active',
                    'asset_base' => 'themes/frontend/jetpakistan',
                    'preview_image' => null,
                    'description' => 'JetPakistan client theme — public UI in progress (see docs/JETPK-V1-CLIENT-UI-COMPLETION-ROADMAP.md).',
                    'supports' => ['css', 'js', 'layouts'],
                ],
                'v2-modern' => [
                    'key' => 'v2-modern',
                    'name' => 'Modern (v2)',
                    'area' => 'frontend',
                    'version' => '2',
                    'status' => 'active',
                    'asset_base' => 'themes/frontend/v2-modern',
                    'preview_image' => null,
                    'description' => 'Modern public site overlay aligned with v2 UI channel.',
                    'supports' => ['css', 'js', 'layouts'],
                ],
            ],
        ],
        'admin' => [
            'fallback' => 'default-admin',
            'themes' => [
                'default-admin' => [
                    'key' => 'default-admin',
                    'name' => 'Default Admin',
                    'area' => 'admin',
                    'version' => '1',
                    'status' => 'active',
                    'asset_base' => 'themes/admin/default-admin',
                    'preview_image' => null,
                    'description' => 'Standard operator admin console theme.',
                    'supports' => ['css', 'js', 'layouts'],
                ],
                'bento-admin' => [
                    'key' => 'bento-admin',
                    'name' => 'Bento Admin',
                    'area' => 'admin',
                    'version' => '1',
                    'status' => 'active',
                    'asset_base' => 'themes/admin/bento-admin',
                    'preview_image' => null,
                    'description' => 'Bento-style admin dashboard theme.',
                    'supports' => ['css', 'js', 'layouts'],
                ],
                'jetpakistan' => [
                    'key' => 'jetpakistan',
                    'name' => 'JetPakistan Admin',
                    'area' => 'admin',
                    'version' => '1',
                    'status' => 'active',
                    'asset_base' => 'themes/admin/jetpakistan',
                    'preview_image' => null,
                    'description' => 'JetPakistan admin preview theme (foundation metadata; full UI deferred).',
                    'supports' => ['css', 'js', 'layouts'],
                ],
            ],
        ],
        'staff' => [
            'fallback' => 'default-staff',
            'themes' => [
                'default-staff' => [
                    'key' => 'default-staff',
                    'name' => 'Default Staff',
                    'area' => 'staff',
                    'version' => '1',
                    'status' => 'active',
                    'asset_base' => 'themes/staff/default-staff',
                    'preview_image' => null,
                    'description' => 'Standard staff portal theme.',
                    'supports' => ['css', 'js', 'layouts'],
                ],
                'bento-staff' => [
                    'key' => 'bento-staff',
                    'name' => 'Bento Staff',
                    'area' => 'staff',
                    'version' => '1',
                    'status' => 'active',
                    'asset_base' => 'themes/staff/bento-staff',
                    'preview_image' => null,
                    'description' => 'Bento-style staff portal theme.',
                    'supports' => ['css', 'js', 'layouts'],
                ],
                'jetpakistan' => [
                    'key' => 'jetpakistan',
                    'name' => 'JetPakistan Staff',
                    'area' => 'staff',
                    'version' => '1',
                    'status' => 'active',
                    'asset_base' => 'themes/admin/jetpakistan',
                    'preview_image' => null,
                    'description' => 'JetPakistan staff portal — shared ops shell with admin theme assets.',
                    'supports' => ['css', 'js', 'layouts'],
                ],
            ],
        ],
    ],

];
