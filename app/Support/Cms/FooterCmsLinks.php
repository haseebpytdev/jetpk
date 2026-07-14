<?php

namespace App\Support\Cms;

use App\Models\CmsPage;

/**
 * Loads active CMS footer links (single query) and merges them into public footer menu sections.
 */
final class FooterCmsLinks
{
    /** @var list<string> */
    public const GROUP_ORDER = [
        'company',
        'policies',
        'support',
        'travel_info',
        'agent_b2b',
    ];

    /** @var array<string, string> */
    public const GROUP_LABELS = [
        'company' => 'Company',
        'policies' => 'Policies',
        'support' => 'Support',
        'travel_info' => 'Travel Info',
        'agent_b2b' => 'Agent / B2B',
    ];

    /** @var array<string, string> */
    private const MERGE_INTO_SECTIONS = [
        'company' => 'company',
        'support' => 'support',
    ];

    /** @var array<string, int> */
    private const GROUP_SORT_ORDER = [
        'company' => 20,
        'policies' => 25,
        'support' => 30,
        'travel_info' => 35,
        'agent_b2b' => 45,
    ];

    /**
     * @param  array<string, mixed>  $footerPresentation
     * @return array<string, mixed>
     */
    public function mergeIntoFooterPresentation(array $footerPresentation): array
    {
        $byGroup = $this->linksByGroup();
        if ($byGroup === []) {
            return $footerPresentation;
        }

        $menuSections = $footerPresentation['menu_sections'] ?? [];

        foreach ($menuSections as &$section) {
            $sectionKey = (string) ($section['section_key'] ?? '');
            if (! isset(self::MERGE_INTO_SECTIONS[$sectionKey], $byGroup[$sectionKey])) {
                continue;
            }

            $section['items'] = array_values(array_merge(
                is_array($section['items'] ?? null) ? $section['items'] : [],
                $byGroup[$sectionKey]
            ));
            unset($byGroup[$sectionKey]);
        }
        unset($section);

        foreach (self::GROUP_ORDER as $group) {
            if (! isset($byGroup[$group])) {
                continue;
            }

            $menuSections[] = [
                'section_key' => 'cms_'.$group,
                'heading' => self::GROUP_LABELS[$group],
                'is_enabled' => true,
                'sort_order' => self::GROUP_SORT_ORDER[$group] ?? 100,
                'items' => $byGroup[$group],
            ];
        }

        usort($menuSections, fn (array $a, array $b): int => ($a['sort_order'] <=> $b['sort_order']));

        $footerPresentation['menu_sections'] = $menuSections;

        return $footerPresentation;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function linksByGroup(): array
    {
        $pages = CmsPage::query()->forFooter()->get();
        if ($pages->isEmpty()) {
            return [];
        }

        /** @var array<string, list<array<string, mixed>>> $grouped */
        $grouped = array_fill_keys(self::GROUP_ORDER, []);

        foreach ($pages as $page) {
            $group = (string) ($page->footer_group ?? '');
            if (! array_key_exists($group, $grouped)) {
                continue;
            }

            $grouped[$group][] = $this->pageToLinkItem($page);
        }

        return array_filter($grouped, fn (array $items): bool => $items !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageToLinkItem(CmsPage $page): array
    {
        $label = trim((string) ($page->footer_label ?? ''));
        if ($label === '') {
            $label = (string) $page->title;
        }

        return [
            'item_key' => 'cms-'.$page->id,
            'label' => $label,
            'url' => $page->route_url,
            'is_enabled' => true,
            'sort_order' => (int) $page->footer_sort_order,
            'open_in_new_tab' => (bool) $page->open_in_new_tab,
        ];
    }
}
