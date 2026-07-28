<?php

namespace App\Services\Client;

use App\Support\Client\ClientPageBootstrapTemplate;
use App\Support\Client\ClientPageKeys;

/**
 * Presents CMS-owned header and footer content for JetPK public layout partials.
 */
final class ClientHeaderFooterPresenter
{
    public function __construct(
        private readonly ClientPageContentResolver $contentResolver,
        private readonly ClientPageRenderer $renderer,
        private readonly ClientGlobalContactResolver $contactResolver,
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
        if ($columns !== []) {
            return $columns;
        }

        return $this->renderer->enabledItems(ClientPageBootstrapTemplate::footerContent()['columns'] ?? []);
    }
}
