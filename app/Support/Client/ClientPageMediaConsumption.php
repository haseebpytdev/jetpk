<?php

namespace App\Support\Client;

/**
 * Maps page-builder media keys to actual JetPakistan frontend consumption.
 */
final class ClientPageMediaConsumption
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function matrix(): array
    {
        return [
            [
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => 'hero_background',
                'blade' => 'themes/frontend/jetpakistan/sections/hero.blade.php',
                'element' => 'picture.hero-media img',
                'collection' => 'Homepage',
                'owner' => 'Page Settings',
                'status' => 'used',
            ],
            [
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => 'support_cta_background',
                'blade' => 'themes/frontend/jetpakistan/sections/support-cta.blade.php',
                'element' => '--jp-support-bg',
                'collection' => 'Support CTA',
                'owner' => 'Page Settings',
                'status' => 'used',
            ],
            [
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => 'support_cta_background_mobile',
                'blade' => 'themes/frontend/jetpakistan/sections/support-cta.blade.php',
                'element' => '--jp-support-bg-mobile',
                'collection' => 'Support CTA',
                'owner' => 'Page Settings',
                'status' => 'used',
            ],
            [
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => 'group_card_1',
                'blade' => 'themes/frontend/jetpakistan/sections/groups.blade.php',
                'element' => 'group card 1 image',
                'collection' => 'Group Cards',
                'owner' => 'Page Settings',
                'status' => 'used',
            ],
            [
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => 'group_card_2',
                'blade' => 'themes/frontend/jetpakistan/sections/groups.blade.php',
                'element' => 'group card 2 image',
                'collection' => 'Group Cards',
                'owner' => 'Page Settings',
                'status' => 'used',
            ],
            [
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => 'group_card_3',
                'blade' => 'themes/frontend/jetpakistan/sections/groups.blade.php',
                'element' => 'group card 3 image',
                'collection' => 'Group Cards',
                'owner' => 'Page Settings',
                'status' => 'used',
            ],
            [
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => 'destination_1',
                'blade' => 'themes/frontend/jetpakistan/sections/destinations.blade.php',
                'element' => 'destination card 1',
                'collection' => 'Destinations',
                'owner' => 'Page Settings',
                'status' => 'used',
            ],
            [
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => 'destination_2',
                'blade' => 'themes/frontend/jetpakistan/sections/destinations.blade.php',
                'element' => 'destination card 2',
                'collection' => 'Destinations',
                'owner' => 'Page Settings',
                'status' => 'used',
            ],
            [
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => 'destination_3',
                'blade' => 'themes/frontend/jetpakistan/sections/destinations.blade.php',
                'element' => 'destination card 3',
                'collection' => 'Destinations',
                'owner' => 'Page Settings',
                'status' => 'used',
            ],
            [
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => 'destination_4',
                'blade' => 'themes/frontend/jetpakistan/sections/destinations.blade.php',
                'element' => 'destination card 4',
                'collection' => 'Destinations',
                'owner' => 'Page Settings',
                'status' => 'used',
            ],
            [
                'page_key' => ClientPageKeys::GLOBAL,
                'asset_key' => 'logo',
                'blade' => '—',
                'element' => 'Settings → Branding',
                'collection' => 'Branding',
                'owner' => 'Branding',
                'status' => 'duplicate',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function usedKeysFor(string $pageKey): array
    {
        return array_values(array_unique(array_map(
            static fn (array $row): string => $row['asset_key'],
            array_filter(
                self::matrix(),
                static fn (array $row): bool => $row['page_key'] === $pageKey && $row['status'] === 'used',
            ),
        )));
    }

    /**
     * @return list<string>
     */
    public static function collections(): array
    {
        return [
            'General',
            'Branding',
            'Homepage',
            'Page Settings',
            'Group Cards',
            'Destinations',
            'Support CTA',
            'Email',
            'Other',
        ];
    }
}
