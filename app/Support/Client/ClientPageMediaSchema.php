<?php

namespace App\Support\Client;

/**
 * Structured media asset definitions for JetPK page builder uploads.
 */
final class ClientPageMediaSchema
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function fieldsFor(string $pageKey): array
    {
        $usedKeys = ClientPageMediaConsumption::usedKeysFor($pageKey);
        if ($usedKeys === []) {
            return [];
        }

        $definitions = self::definitions();

        return array_values(array_filter(
            $definitions[$pageKey] ?? [],
            static fn (array $field): bool => in_array($field['key'], $usedKeys, true),
        ));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private static function definitions(): array
    {
        $imageField = static fn (string $key, string $label, string $section, string $ratio, string $usage): array => [
            'key' => $key,
            'label' => $label,
            'section' => $section,
            'ratio' => $ratio,
            'max_kb' => 5120,
            'accept' => 'image/jpeg,image/png,image/webp',
            'usage' => $usage,
            'fallback' => null,
            'alt_supported' => true,
        ];

        return [
            ClientPageKeys::HOME => [
                $imageField('hero_background', 'Hero image', 'hero', '16:9', 'Homepage hero background'),
                $imageField('support_cta_background', 'Support CTA background', 'support_cta', '21:9', 'Pre-footer support callout desktop background'),
                $imageField('support_cta_background_mobile', 'Support CTA mobile background', 'support_cta', '4:5', 'Pre-footer support callout mobile background'),
                $imageField('group_card_1', 'Group card 1 image', 'group_cards', '4:3', 'First group travel package card'),
                $imageField('group_card_2', 'Group card 2 image', 'group_cards', '4:3', 'Second group travel package card'),
                $imageField('group_card_3', 'Group card 3 image', 'group_cards', '4:3', 'Third group travel package card'),
                $imageField('destination_1', 'Destination card 1', 'destinations', '3:4', 'First popular destination card'),
                $imageField('destination_2', 'Destination card 2', 'destinations', '3:4', 'Second popular destination card'),
                $imageField('destination_3', 'Destination card 3', 'destinations', '3:4', 'Third popular destination card'),
                $imageField('destination_4', 'Destination card 4', 'destinations', '3:4', 'Fourth popular destination card'),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function assetKeysFor(string $pageKey): array
    {
        return array_column(self::fieldsFor($pageKey), 'key');
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function groupedFor(string $pageKey): array
    {
        $grouped = [];
        foreach (self::fieldsFor($pageKey) as $field) {
            $grouped[$field['section']][] = $field;
        }

        return $grouped;
    }
}
