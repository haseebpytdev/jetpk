<?php

namespace App\Services\Client;

use App\Support\Client\ClientPageBootstrapTemplate;
use App\Support\Client\ClientPageKeys;
use App\Support\Cms\FooterCmsLinks;

/**
 * Presents CMS-owned header and footer content for JetPK public layout partials.
 */
final class ClientHeaderFooterPresenter
{
    public function __construct(
        private readonly ClientPageContentResolver $contentResolver,
        private readonly ClientPageRenderer $renderer,
        private readonly ClientGlobalContactResolver $contactResolver,
        private readonly FooterCmsLinks $footerCmsLinks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function header(): array
    {
        $global = $this->contentResolver->contentFor(ClientPageKeys::GLOBAL);
        $header = is_array($global['header'] ?? null) ? $global['header'] : [];
        $navItems = $this->renderer->enabledItems($header['nav_items'] ?? []);
        if ($navItems === []) {
            $bootstrapHeader = ClientPageBootstrapTemplate::globalContent()['header'] ?? [];
            $navItems = $this->renderer->enabledItems($bootstrapHeader['nav_items'] ?? []);
        }

        return [
            'support_pill_label' => (string) ($header['support_pill_label'] ?? ''),
            'support_pill_url' => $this->renderer->resolveDestination((string) ($header['support_pill_url'] ?? '')),
            'sign_in_label' => (string) ($header['sign_in_label'] ?? 'Sign in'),
            'register_label' => (string) ($header['register_label'] ?? 'Register'),
            'theme_toggle_visible' => ($header['theme_toggle_visible'] ?? '1') !== '0',
            'nav_items' => $navItems,
            'announcement' => is_array($global['announcement'] ?? null) ? $global['announcement'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function footer(): array
    {
        $footer = $this->contentResolver->contentFor(ClientPageKeys::FOOTER);
        $contact = $this->contactResolver->contact(is_array($footer['contact'] ?? null) ? $footer['contact'] : []);

        return [
            'intro' => trim((string) data_get($footer, 'description.text', '')),
            'columns' => $this->footerColumns($footer),
            'social' => is_array($footer['social'] ?? null) ? $footer['social'] : [],
            'legal' => is_array($footer['legal'] ?? null) ? $footer['legal'] : [],
            'contact' => $contact,
        ];
    }

    /**
     * @param  array<string, mixed>  $footer
     * @return list<array<string, mixed>>
     */
    private function footerColumns(array $footer): array
    {
        $columns = $this->renderer->enabledItems($footer['columns'] ?? []);
        if ($columns === []) {
            $columns = $this->renderer->enabledItems(ClientPageBootstrapTemplate::footerContent()['columns'] ?? []);
        }

        return $this->mergeCmsFooterLinks($columns);
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @return list<array<string, mixed>>
     */
    private function mergeCmsFooterLinks(array $columns): array
    {
        $byGroup = $this->footerCmsLinks->linksByGroup();
        if ($byGroup === []) {
            return $columns;
        }

        $titleByGroup = [
            'company' => 'Company',
            'policies' => 'Policies',
            'support' => 'Support',
            'travel_info' => 'Travel Info',
            'agent_b2b' => 'B2B & agents',
        ];

        foreach ($columns as &$column) {
            $title = strtolower((string) ($column['title'] ?? ''));
            $matchedGroup = null;
            foreach ($titleByGroup as $group => $label) {
                if (strtolower($label) === $title) {
                    $matchedGroup = $group;
                    break;
                }
            }
            if ($matchedGroup === null && str_contains($title, 'agent')) {
                $matchedGroup = 'agent_b2b';
            }
            if ($matchedGroup === null || ! isset($byGroup[$matchedGroup])) {
                continue;
            }

            $column['links'] = array_values(array_merge(
                is_array($column['links'] ?? null) ? $column['links'] : [],
                $this->cmsItemsToFooterLinks($byGroup[$matchedGroup]),
            ));
            unset($byGroup[$matchedGroup]);
        }
        unset($column);

        foreach (FooterCmsLinks::GROUP_ORDER as $group) {
            if (! isset($byGroup[$group])) {
                continue;
            }

            $columns[] = [
                'id' => 'cms-'.$group,
                'title' => $titleByGroup[$group] ?? (FooterCmsLinks::GROUP_LABELS[$group] ?? $group),
                'enabled' => '1',
                'sort_order' => 100,
                'links' => $this->cmsItemsToFooterLinks($byGroup[$group]),
            ];
        }

        return $columns;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function cmsItemsToFooterLinks(array $items): array
    {
        $links = [];
        foreach ($items as $item) {
            $links[] = [
                'id' => (string) ($item['item_key'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'destination' => (string) ($item['url'] ?? ''),
                'enabled' => '1',
                'sort_order' => (int) ($item['sort_order'] ?? 0),
                'open_in_new_tab' => (bool) ($item['open_in_new_tab'] ?? false),
            ];
        }

        return $links;
    }
}
