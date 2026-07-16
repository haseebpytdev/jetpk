<?php

namespace App\Services\Homepage;

/**
 * Merges homepage Page Settings saves without wiping unrelated sections or fare cache.
 */
final class JetpkHomepageContentMergeService
{
    /** @var array<string, string> Panel slug (data-jp-section) → content_json top-level key */
    private const PANEL_TO_SECTION = [
        'hero' => 'hero',
        'trust-chips' => 'trust_chips',
        'feature-board' => 'feature_board',
        'why-book' => 'why_book',
        'trust' => 'trust',
        'featured-deals' => 'featured_deals',
        'routes' => 'routes',
        'destinations' => 'destinations',
        'group-cards' => 'group_cards',
        'groups' => 'groups',
        'support-cta' => 'support_cta',
    ];

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $submitted
     * @param  list<string>  $submittedPanels  data-jp-section panel slugs from the active editor tab
     * @return array<string, mixed>
     */
    public function mergeOnSave(array $existing, array $submitted, array $submittedPanels = []): array
    {
        if ($existing === []) {
            return $submitted;
        }

        $merged = $existing;

        if ($submittedPanels !== []) {
            $sectionKeys = $this->panelsToSectionKeys($submittedPanels);
            foreach ($sectionKeys as $sectionKey) {
                if (array_key_exists($sectionKey, $submitted)) {
                    $merged[$sectionKey] = $submitted[$sectionKey];
                }
            }
        } else {
            foreach ($submitted as $key => $value) {
                if ($key === '_fare_cache') {
                    continue;
                }
                $merged[$key] = $value;
            }
        }

        if (! array_key_exists('_fare_cache', $submitted) && array_key_exists('_fare_cache', $existing)) {
            $merged['_fare_cache'] = $existing['_fare_cache'];
        }

        if (array_key_exists('_media_removed', $submitted)) {
            $merged['_media_removed'] = $submitted['_media_removed'];
        }

        return $merged;
    }

    /**
     * @param  list<string>  $panels
     * @return list<string>
     */
    public function panelsToSectionKeys(array $panels): array
    {
        $keys = [];
        foreach ($panels as $panel) {
            $panel = trim($panel);
            if ($panel === '') {
                continue;
            }
            $keys[] = self::PANEL_TO_SECTION[$panel] ?? $panel;
        }

        return array_values(array_unique($keys));
    }
}
