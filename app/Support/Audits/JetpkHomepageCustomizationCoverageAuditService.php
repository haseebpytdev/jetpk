<?php

namespace App\Support\Audits;

use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientPageMediaConsumption;
use App\Support\Client\ClientPageMediaSchema;
use App\Support\Client\ClientPageSectionSchema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Read-only homepage customization coverage audit for JetPK 9H-E closure.
 */
final class JetpkHomepageCustomizationCoverageAuditService
{
    private const OUTPUT_DIR = 'app/audits/jetpk-9h-e';

    /**
     * @return array{pass: int, fail: int, path: string, md_path: string}
     */
    public function run(): array
    {
        $sections = $this->sectionMatrix();
        $failures = [];
        foreach ($sections as $section) {
            foreach ($section['checks'] ?? [] as $check => $ok) {
                if (! $ok) {
                    $failures[] = ($section['id'] ?? 'unknown').': '.$check;
                }
            }
        }

        $failedSections = count(array_filter(
            $sections,
            static fn (array $section): bool => array_filter($section['checks'] ?? [], static fn (bool $ok): bool => ! $ok) !== [],
        ));
        $passSections = count($sections) - $failedSections;
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'phase' => 'jetpk-9h-e',
            'page_key' => ClientPageKeys::HOME,
            'section_count' => count($sections),
            'pass' => $passSections,
            'fail' => $failedSections,
            'failure_details' => $failures,
            'sections' => $sections,
        ];

        $dir = storage_path(self::OUTPUT_DIR);
        File::ensureDirectoryExists($dir);
        $jsonPath = $dir.'/HOMEPAGE-CUSTOMIZATION-COVERAGE.json';
        $mdPath = $dir.'/HOMEPAGE-CUSTOMIZATION-COVERAGE.md';
        File::put($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($mdPath, $this->markdown($payload));

        return [
            'pass' => $passSections,
            'fail' => $failedSections,
            'path' => $jsonPath,
            'md_path' => $mdPath,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sectionMatrix(): array
    {
        $editor = $this->homepageEditorSource();
        $schemaSections = collect(ClientPageSectionSchema::sectionsFor(ClientPageKeys::HOME))->keyBy('key');
        $mediaKeys = ClientPageMediaSchema::assetKeysFor(ClientPageKeys::HOME);

        $definitions = [
            ['id' => 'hero', 'label' => 'Hero', 'consumer' => 'themes/frontend/jetpakistan/sections/hero.blade.php', 'owner' => 'hero', 'panel' => 'hero'],
            ['id' => 'flight_search', 'label' => 'Flight search visibility', 'consumer' => 'themes/frontend/jetpakistan/sections/hero.blade.php', 'owner' => 'hero', 'panel' => 'hero', 'field' => 'search_visible'],
            ['id' => 'trust_strip', 'label' => 'Trust strip below hero', 'consumer' => 'themes/frontend/jetpakistan/sections/feature-board.blade.php', 'owner' => 'feature_board', 'panel' => 'feature-board'],
            ['id' => 'why_travellers', 'label' => 'Why travellers stay', 'consumer' => 'themes/frontend/jetpakistan/sections/trust.blade.php', 'owner' => 'trust', 'panel' => 'trust'],
            ['id' => 'trust_card_pricing', 'label' => 'Transparent PKR Pricing card', 'consumer' => 'trust.cards.0', 'owner' => 'trust', 'panel' => 'trust', 'repeatable' => true],
            ['id' => 'trust_card_licensed', 'label' => 'Licensed operations card', 'consumer' => 'trust.cards.1', 'owner' => 'trust', 'panel' => 'trust', 'repeatable' => true],
            ['id' => 'trust_card_support', 'label' => 'Human support card', 'consumer' => 'trust.cards.2', 'owner' => 'trust', 'panel' => 'trust', 'repeatable' => true],
            ['id' => 'group_packages', 'label' => 'Group travel packages', 'consumer' => 'themes/frontend/jetpakistan/sections/groups.blade.php', 'owner' => 'group_cards', 'panel' => 'group-cards'],
            ['id' => 'group_card_1', 'label' => 'Group card 1', 'consumer' => 'groups.blade.php card 1', 'owner' => 'group_cards.items.0', 'panel' => 'group-cards', 'media' => 'group_card_1'],
            ['id' => 'group_card_2', 'label' => 'Group card 2', 'consumer' => 'groups.blade.php card 2', 'owner' => 'group_cards.items.1', 'panel' => 'group-cards', 'media' => 'group_card_2'],
            ['id' => 'group_card_3', 'label' => 'Group card 3', 'consumer' => 'groups.blade.php card 3', 'owner' => 'group_cards.items.2', 'panel' => 'group-cards', 'media' => 'group_card_3'],
            ['id' => 'featured_deals', 'label' => 'Featured deals', 'consumer' => 'themes/frontend/jetpakistan/sections/fares.blade.php', 'owner' => 'featured_deals', 'panel' => 'featured-deals'],
            ['id' => 'popular_routes', 'label' => 'Popular routes', 'consumer' => 'themes/frontend/jetpakistan/sections/routes.blade.php', 'owner' => 'routes', 'panel' => 'routes'],
            ['id' => 'popular_destinations', 'label' => 'Popular destinations', 'consumer' => 'themes/frontend/jetpakistan/sections/destinations.blade.php', 'owner' => 'destinations', 'panel' => 'destinations'],
            ['id' => 'destination_1', 'label' => 'Destination 1', 'consumer' => 'destinations card 1', 'owner' => 'destinations.items.0', 'panel' => 'destinations', 'media' => 'destination_1'],
            ['id' => 'destination_2', 'label' => 'Destination 2', 'consumer' => 'destinations card 2', 'owner' => 'destinations.items.1', 'panel' => 'destinations', 'media' => 'destination_2'],
            ['id' => 'destination_3', 'label' => 'Destination 3', 'consumer' => 'destinations card 3', 'owner' => 'destinations.items.2', 'panel' => 'destinations', 'media' => 'destination_3'],
            ['id' => 'destination_4', 'label' => 'Destination 4', 'consumer' => 'destinations card 4', 'owner' => 'destinations.items.3', 'panel' => 'destinations', 'media' => 'destination_4'],
            ['id' => 'feature_board', 'label' => 'Built for how Pakistan books / feature board', 'consumer' => 'themes/frontend/jetpakistan/sections/why-book.blade.php', 'owner' => 'why_book', 'panel' => 'why-book'],
            ['id' => 'feature_board_card_1', 'label' => 'Feature board card 1', 'consumer' => 'why_book.cards.0', 'owner' => 'why_book', 'panel' => 'why-book', 'repeatable' => true],
            ['id' => 'feature_board_card_2', 'label' => 'Feature board card 2', 'consumer' => 'why_book.cards.1', 'owner' => 'why_book', 'panel' => 'why-book', 'repeatable' => true],
            ['id' => 'feature_board_card_3', 'label' => 'Feature board card 3', 'consumer' => 'why_book.cards.2', 'owner' => 'why_book', 'panel' => 'why-book', 'repeatable' => true],
            ['id' => 'feature_board_card_4', 'label' => 'Feature board card 4', 'consumer' => 'why_book.cards.3', 'owner' => 'why_book', 'panel' => 'why-book', 'repeatable' => true],
            ['id' => 'support_cta', 'label' => 'Support CTA / pre-footer', 'consumer' => 'themes/frontend/jetpakistan/sections/support-cta.blade.php', 'owner' => 'support_cta', 'panel' => 'support-cta'],
            ['id' => 'footer_promo', 'label' => 'Footer promotional areas', 'consumer' => 'themes/frontend/jetpakistan/partials/footer.blade.php', 'owner' => 'footer page settings', 'panel' => 'footer (separate page)'],
            ['id' => 'trust_chips', 'label' => 'Trust badges below hero', 'consumer' => 'hero trust chips', 'owner' => 'trust_chips', 'panel' => 'trust-chips'],
            ['id' => 'groups_cta', 'label' => 'Group ticketing CTA', 'consumer' => 'groups section CTA', 'owner' => 'groups', 'panel' => 'groups'],
        ];

        $rows = [];
        foreach ($definitions as $def) {
            $owner = (string) ($def['owner'] ?? '');
            $panel = (string) ($def['panel'] ?? '');
            $schema = $schemaSections->get(explode('.', $owner)[0] ?? '');
            $consumerExists = $this->consumerExists((string) ($def['consumer'] ?? ''));
            $panelInEditor = $panel !== '' && str_contains($editor, 'data-jp-section-panel="'.$panel.'"');
            $mediaKey = $def['media'] ?? null;
            $mediaInSchema = $mediaKey === null || in_array($mediaKey, $mediaKeys, true);
            $mediaFieldInEditor = $mediaKey === null || str_contains($editor, $mediaKey) || $this->mediaFieldRenderable($mediaKey);

            $rows[] = array_merge($def, [
                'schema_owner' => $schema !== null ? ($schema['key'] ?? '') : ($owner === 'footer page settings' ? 'footer' : ''),
                'editor_panel' => $panel,
                'text_fields' => $schema['fields'] ?? [],
                'media_fields' => $mediaKey ? [$mediaKey] : [],
                'enabled_flag' => in_array('enabled', $schema['fields'] ?? [], true) || str_contains($editor, '['.$owner.'][enabled]'),
                'ordering' => (bool) ($def['repeatable'] ?? false),
                'repeatable_items' => (bool) ($def['repeatable'] ?? false) || str_contains($owner, 'items'),
                'empty_semantics' => true,
                'public_consumption' => $consumerExists,
                'preview_consumption' => View::exists('themes.admin.jetpakistan.page-settings.edit'),
                'checks' => [
                    'public_consumer_exists' => $consumerExists,
                    'has_editor_owner' => $panelInEditor || $owner === 'footer page settings',
                    'schema_registered' => $schema !== null || in_array($owner, ['featured_deals', 'footer page settings'], true),
                    'media_schema_when_needed' => $mediaInSchema,
                    'media_editor_when_needed' => $mediaFieldInEditor,
                ],
            ]);
        }

        return $rows;
    }

    private function homepageEditorSource(): string
    {
        $paths = [
            resource_path('views/themes/admin/jetpakistan/page-settings/partials/home-sections.blade.php'),
            resource_path('views/themes/admin/jetpakistan/page-settings/partials/home-routes-manager.blade.php'),
            resource_path('views/themes/admin/jetpakistan/page-settings/partials/home-destinations-manager.blade.php'),
            resource_path('views/themes/admin/jetpakistan/page-settings/partials/home-support-cta-manager.blade.php'),
        ];

        return implode("\n", array_map(
            static fn (string $path): string => is_file($path) ? (string) file_get_contents($path) : '',
            $paths,
        ));
    }

    private function consumerExists(string $consumer): bool
    {
        if (str_contains($consumer, 'themes/frontend')) {
            $blade = str_replace('themes/frontend/jetpakistan/', '', $consumer);
            $blade = preg_replace('/\s.*$/', '', $blade) ?? $blade;

            return View::exists('themes.frontend.jetpakistan.'.$blade)
                || is_file(resource_path('views/themes/frontend/jetpakistan/'.$blade));
        }

        return true;
    }

    private function mediaFieldRenderable(string $mediaKey): bool
    {
        return in_array($mediaKey, ClientPageMediaSchema::assetKeysFor(ClientPageKeys::HOME), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markdown(array $payload): string
    {
        $lines = [
            '# Homepage Customization Coverage (9H-E)',
            '',
            'Generated: '.($payload['generated_at'] ?? ''),
            'Sections: '.($payload['section_count'] ?? 0),
            'Fail: '.($payload['fail'] ?? 0),
            '',
            '| Section | Consumer | Schema owner | Editor panel | Media | Checks |',
            '|---------|----------|--------------|--------------|-------|--------|',
        ];

        foreach ($payload['sections'] as $section) {
            $checks = $section['checks'] ?? [];
            $failed = array_keys(array_filter($checks, static fn (bool $ok) => ! $ok));
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s | %s |',
                $section['label'] ?? '',
                $section['consumer'] ?? '',
                $section['schema_owner'] ?? '',
                $section['editor_panel'] ?? '',
                implode(', ', $section['media_fields'] ?? []) ?: '—',
                $failed === [] ? 'PASS' : 'FAIL: '.implode(', ', $failed),
            );
        }

        if (($payload['failure_details'] ?? []) !== []) {
            $lines[] = '';
            $lines[] = '## Failures';
            foreach ($payload['failure_details'] as $detail) {
                $lines[] = '- '.$detail;
            }
        }

        return implode("\n", $lines);
    }
}
