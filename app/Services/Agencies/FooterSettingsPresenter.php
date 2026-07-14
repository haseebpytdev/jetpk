<?php

namespace App\Services\Agencies;

use App\Models\AgencySetting;
use App\Support\Branding\BrandDisplayResolver;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Structured public footer defaults, admin presentation, persistence in agency_settings.meta.footer.
 */
class FooterSettingsPresenter
{
    public const META_KEY = 'footer';

    public const SECTION_COMPANY = 'company';

    public const SECTION_SUPPORT = 'support';

    public const SECTION_EXPLORE = 'explore';

    /** @var list<string> */
    public const MENU_SECTION_KEYS = [
        self::SECTION_COMPANY,
        self::SECTION_SUPPORT,
        self::SECTION_EXPLORE,
    ];

    /** @var list<string> */
    public const SPACING_OPTIONS = ['compact', 'normal', 'spacious'];

    /** @var list<string> */
    public const SOCIAL_PLATFORMS = ['facebook', 'instagram', 'twitter', 'youtube', 'linkedin', 'whatsapp'];

    /**
     * @return array<string, mixed>
     */
    public function presentForPublic(?AgencySetting $settings, array $client = [], array $brand = []): array
    {
        $stored = $this->storedPayload($settings);
        $brandName = BrandDisplayResolver::displayName($settings);

        $brandBlock = array_merge($this->defaultBrandBlock($brandName, $client, $brand, $settings), $stored['brand'] ?? []);
        $brandBlock['description'] = trim((string) ($brandBlock['description'] ?: ($settings?->footer_about ?: ($client['footer_text'] ?? ($brand['company_note'] ?? '')))));
        $brandBlock['name'] = trim((string) ($brandBlock['name'] ?: $brandName));
        $brandBlock['logo_url'] = $this->resolveLogoUrl($settings, $brandBlock);

        $contact = $this->mergeContactDefaults($stored['contact'] ?? [], $settings, $client, $brand);
        $style = $this->normalizeStyle(array_merge($this->defaultStyle(), $stored['style'] ?? []));
        $bottomBar = $this->normalizeBottomBar(array_merge($this->defaultBottomBar($brandName, $settings), $stored['bottom_bar'] ?? []));
        $bottomBar['trust_badges'] = $this->publicTrustBadges($bottomBar['trust_badges'] ?? [], $settings);
        $social = $this->normalizeSocialLinks(array_merge($this->defaultSocialLinks($settings), $stored['social'] ?? []));
        $supportCard = $this->normalizeSupportCard(array_merge($this->defaultSupportCard(), $stored['support_card'] ?? []));
        $menuSections = $this->normalizeMenuSections($stored['menu_sections'] ?? null);

        return [
            'is_enabled' => (bool) ($stored['is_enabled'] ?? true),
            'brand' => $brandBlock,
            'support_card' => $supportCard,
            'menu_sections' => $menuSections,
            'contact' => $contact,
            'social' => $social,
            'bottom_bar' => $bottomBar,
            'style' => $style,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForAdmin(?AgencySetting $settings): array
    {
        $stored = $this->storedPayload($settings);

        return [
            'is_enabled' => (bool) ($stored['is_enabled'] ?? true),
            'brand' => array_merge($this->defaultBrandBlock(
                BrandDisplayResolver::displayName($settings),
                [],
                [],
                $settings
            ), $stored['brand'] ?? []),
            'support_card' => $this->normalizeSupportCard(array_merge($this->defaultSupportCard(), $stored['support_card'] ?? [])),
            'menu_sections' => $this->normalizeMenuSections($stored['menu_sections'] ?? null),
            'contact' => $this->mergeContactDefaults($stored['contact'] ?? [], $settings, [], []),
            'social' => $this->normalizeSocialLinks(array_merge($this->defaultSocialLinks($settings), $stored['social'] ?? [])),
            'bottom_bar' => $this->normalizeBottomBar(array_merge($this->defaultBottomBar(BrandDisplayResolver::displayName($settings), $settings), $stored['bottom_bar'] ?? [])),
            'style' => $this->normalizeStyle(array_merge($this->defaultStyle(), $stored['style'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildPayloadForStorage(array $input, ?AgencySetting $settings = null): array
    {
        $brandName = BrandDisplayResolver::displayName($settings);

        $menuSections = [];
        foreach (self::MENU_SECTION_KEYS as $sectionKey) {
            $sectionInput = is_array($input['menu_sections'][$sectionKey] ?? null) ? $input['menu_sections'][$sectionKey] : [];
            $itemsInput = is_array($sectionInput['items'] ?? null) ? $sectionInput['items'] : [];
            $items = [];
            foreach ($itemsInput as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $label = trim((string) ($item['label'] ?? ''));
                $urlRaw = trim((string) ($item['url'] ?? ''));
                if ($label === '' || $urlRaw === '') {
                    continue;
                }
                $url = $this->sanitizeUrl($urlRaw);
                $items[] = [
                    'item_key' => trim((string) ($item['item_key'] ?? '')) ?: 'item-'.$sectionKey.'-'.$index,
                    'label' => $label,
                    'url' => $url,
                    'is_enabled' => filter_var($item['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'sort_order' => (int) ($item['sort_order'] ?? (($index + 1) * 10)),
                ];
            }
            usort($items, fn (array $a, array $b): int => ($a['sort_order'] <=> $b['sort_order']) ?: strcmp($a['label'], $b['label']));

            $menuSections[] = [
                'section_key' => $sectionKey,
                'heading' => trim((string) ($sectionInput['heading'] ?? $this->defaultSectionHeading($sectionKey))),
                'is_enabled' => filter_var($sectionInput['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => (int) ($sectionInput['sort_order'] ?? $this->defaultSectionSortOrder($sectionKey)),
                'items' => $items,
            ];
        }

        $legalLinks = [];
        foreach (is_array($input['bottom_bar']['legal_links'] ?? null) ? $input['bottom_bar']['legal_links'] : [] as $index => $link) {
            if (! is_array($link)) {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            $urlRaw = trim((string) ($link['url'] ?? ''));
            if ($label === '' || $urlRaw === '') {
                continue;
            }
            $legalLinks[] = [
                'item_key' => trim((string) ($link['item_key'] ?? '')) ?: 'legal-'.$index,
                'label' => $label,
                'url' => $this->sanitizeUrl($urlRaw),
                'is_enabled' => filter_var($link['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => (int) ($link['sort_order'] ?? (($index + 1) * 10)),
            ];
        }
        usort($legalLinks, fn (array $a, array $b): int => ($a['sort_order'] <=> $b['sort_order']) ?: strcmp($a['label'], $b['label']));

        $trustBadges = [];
        foreach (is_array($input['bottom_bar']['trust_badges'] ?? null) ? $input['bottom_bar']['trust_badges'] : [] as $index => $badge) {
            if (! is_array($badge)) {
                continue;
            }
            $label = trim((string) ($badge['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $trustBadges[] = [
                'item_key' => trim((string) ($badge['item_key'] ?? '')) ?: 'badge-'.$index,
                'label' => $label,
                'is_enabled' => filter_var($badge['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => (int) ($badge['sort_order'] ?? (($index + 1) * 10)),
            ];
        }

        $social = [];
        foreach (self::SOCIAL_PLATFORMS as $platform) {
            $row = is_array($input['social'][$platform] ?? null) ? $input['social'][$platform] : [];
            $url = trim((string) ($row['url'] ?? ''));
            if ($url !== '') {
                $url = $this->sanitizeUrl($url, allowExternal: true);
            }
            $social[$platform] = [
                'url' => $url,
                'is_enabled' => filter_var($row['is_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) && $url !== '',
            ];
        }

        $styleInput = is_array($input['style'] ?? null) ? $input['style'] : [];

        return [
            'is_enabled' => filter_var($input['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'brand' => [
                'name' => trim((string) ($input['brand']['name'] ?? $brandName)),
                'description' => trim((string) ($input['brand']['description'] ?? '')),
                'use_brand_logo' => filter_var($input['brand']['use_brand_logo'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'show_logo' => filter_var($input['brand']['show_logo'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'support_card' => [
                'is_enabled' => filter_var($input['support_card']['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'title' => trim((string) ($input['support_card']['title'] ?? '24/7 Support')),
                'subtitle' => trim((string) ($input['support_card']['subtitle'] ?? 'Always here to help')),
                'icon' => trim((string) ($input['support_card']['icon'] ?? 'headphones')),
            ],
            'menu_sections' => $menuSections,
            'contact' => [
                'is_enabled' => filter_var($input['contact']['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'heading' => trim((string) ($input['contact']['heading'] ?? 'Get In Touch')),
                'address' => trim((string) ($input['contact']['address'] ?? '')),
                'phone' => trim((string) ($input['contact']['phone'] ?? '')),
                'email' => trim((string) ($input['contact']['email'] ?? '')),
                'whatsapp' => trim((string) ($input['contact']['whatsapp'] ?? '')),
                'whatsapp_label' => trim((string) ($input['contact']['whatsapp_label'] ?? 'WhatsApp')),
                'city' => trim((string) ($input['contact']['city'] ?? '')),
                'show_phone' => filter_var($input['contact']['show_phone'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'show_email' => filter_var($input['contact']['show_email'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'show_whatsapp' => filter_var($input['contact']['show_whatsapp'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'show_address' => filter_var($input['contact']['show_address'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'show_city' => filter_var($input['contact']['show_city'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'social' => $social,
            'bottom_bar' => [
                'copyright' => trim((string) ($input['bottom_bar']['copyright'] ?? '')),
                'disclaimer' => trim((string) ($input['bottom_bar']['disclaimer'] ?? 'Subject to airline confirmation.')),
                'powered_by_label' => trim((string) ($input['bottom_bar']['powered_by_label'] ?? '')),
                'powered_by_url' => $this->optionalSanitizedUrl($input['bottom_bar']['powered_by_url'] ?? null),
                'legal_links' => $legalLinks,
                'trust_badges' => $trustBadges,
                'show_trust_badges' => filter_var($input['bottom_bar']['show_trust_badges'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'show_legal_links' => filter_var($input['bottom_bar']['show_legal_links'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'style' => $this->normalizeStyle([
                'background_color' => $styleInput['background_color'] ?? null,
                'bottom_bar_background_color' => $styleInput['bottom_bar_background_color'] ?? null,
                'text_color' => $styleInput['text_color'] ?? null,
                'heading_color' => $styleInput['heading_color'] ?? null,
                'link_color' => $styleInput['link_color'] ?? null,
                'link_hover_color' => $styleInput['link_hover_color'] ?? null,
                'accent_color' => $styleInput['accent_color'] ?? null,
                'spacing' => $styleInput['spacing'] ?? 'normal',
                'show_support_card' => $styleInput['show_support_card'] ?? true,
                'show_social' => $styleInput['show_social'] ?? true,
                'columns' => $styleInput['columns'] ?? 5,
            ], strictColors: true),
        ];
    }

    public function sanitizeUrl(string $url, bool $allowExternal = true): string
    {
        $url = trim($url);
        if ($url === '') {
            throw ValidationException::withMessages(['url' => 'URL is required for menu links.']);
        }

        if (preg_match('/^\s*(javascript|data|vbscript):/i', $url) !== 0) {
            throw ValidationException::withMessages(['url' => 'Unsafe URL scheme is not allowed.']);
        }

        if (str_starts_with($url, '#')) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        if (preg_match('/^mailto:/i', $url) === 1 || preg_match('/^tel:/i', $url) === 1) {
            return $url;
        }

        if (preg_match('#^https://wa\.me/#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        if ($allowExternal && filter_var($url, FILTER_VALIDATE_URL)) {
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                throw ValidationException::withMessages(['url' => 'Only http and https external URLs are allowed.']);
            }

            return $url;
        }

        throw ValidationException::withMessages(['url' => 'URL must start with / or be a valid http(s), mailto, tel, or WhatsApp link.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function storedPayload(?AgencySetting $settings): array
    {
        $meta = $settings?->meta;
        if (! is_array($meta)) {
            return [];
        }

        $footer = $meta[self::META_KEY] ?? [];

        return is_array($footer) ? $footer : [];
    }

    /**
     * @param  array<string, mixed>|null  $sections
     * @return list<array<string, mixed>>
     */
    protected function normalizeMenuSections(?array $sections): array
    {
        if ($sections === null || $sections === []) {
            return $this->defaultMenuSections();
        }

        $byKey = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $key = (string) ($section['section_key'] ?? '');
            if ($key !== '') {
                $byKey[$key] = $section;
            }
        }

        $normalized = [];
        foreach (self::MENU_SECTION_KEYS as $sectionKey) {
            $section = $byKey[$sectionKey] ?? null;
            $normalized[] = $this->normalizeMenuSection($sectionKey, is_array($section) ? $section : []);
        }

        usort($normalized, fn (array $a, array $b): int => ($a['sort_order'] <=> $b['sort_order']));

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    protected function normalizeMenuSection(string $sectionKey, array $section): array
    {
        $defaults = collect($this->defaultMenuSections())->firstWhere('section_key', $sectionKey) ?? [];
        $items = [];
        foreach (is_array($section['items'] ?? null) ? $section['items'] : [] as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            try {
                $url = $this->sanitizeUrl((string) ($item['url'] ?? '/'));
            } catch (ValidationException) {
                $url = '/';
            }
            $items[] = [
                'item_key' => (string) ($item['item_key'] ?? 'item-'.$index),
                'label' => $label,
                'url' => $url,
                'is_enabled' => (bool) ($item['is_enabled'] ?? true),
                'sort_order' => (int) ($item['sort_order'] ?? (($index + 1) * 10)),
            ];
        }
        if ($items === [] && is_array($defaults['items'] ?? null)) {
            $items = $defaults['items'];
        }
        usort($items, fn (array $a, array $b): int => ($a['sort_order'] <=> $b['sort_order']));

        return [
            'section_key' => $sectionKey,
            'heading' => trim((string) ($section['heading'] ?? ($defaults['heading'] ?? Str::headline($sectionKey)))),
            'is_enabled' => (bool) ($section['is_enabled'] ?? true),
            'sort_order' => (int) ($section['sort_order'] ?? ($defaults['sort_order'] ?? 100)),
            'items' => $items,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function defaultMenuSections(): array
    {
        return [
            $this->sectionWithItems(self::SECTION_COMPANY, 'Company', 20, [
                ['label' => 'Contact us', 'url' => '/about-us'],
                ['label' => 'About us', 'url' => '/about-us'],
            ]),
            $this->sectionWithItems(self::SECTION_SUPPORT, 'Support', 30, [
                ['label' => 'How to Book', 'url' => '/support'],
                ['label' => 'File a Claim', 'url' => '/support'],
                ['label' => 'Refund Policy', 'url' => '/support'],
                ['label' => 'FAQ / Answers', 'url' => '/support'],
            ]),
            $this->sectionWithItems(self::SECTION_EXPLORE, 'Explore', 40, [
                ['label' => 'Best Travel Deals', 'url' => '/'],
                ['label' => 'Travel Documents', 'url' => '/support'],
                ['label' => 'Travel Insurance', 'url' => '/support'],
                ['label' => 'Disruption', 'url' => '/support'],
                ['label' => 'Accessibility', 'url' => '/about-us'],
            ]),
        ];
    }

    /**
     * @param  list<array{label: string, url: string}>  $items
     * @return array<string, mixed>
     */
    protected function sectionWithItems(string $key, string $heading, int $sortOrder, array $items): array
    {
        $normalizedItems = [];
        foreach ($items as $index => $item) {
            $normalizedItems[] = [
                'item_key' => $key.'-'.$index,
                'label' => $item['label'],
                'url' => $item['url'],
                'is_enabled' => true,
                'sort_order' => ($index + 1) * 10,
            ];
        }

        return [
            'section_key' => $key,
            'heading' => $heading,
            'is_enabled' => true,
            'sort_order' => $sortOrder,
            'items' => $normalizedItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $brandBlock
     */
    protected function resolveLogoUrl(?AgencySetting $settings, array $brandBlock): ?string
    {
        if (! ($brandBlock['show_logo'] ?? true)) {
            return null;
        }

        if (($brandBlock['use_brand_logo'] ?? true) && $settings?->logo_path) {
            return asset('storage/'.$settings->logo_path);
        }

        if ($settings?->footer_logo_path) {
            return asset('storage/'.$settings->footer_logo_path);
        }

        if ($settings?->logo_path) {
            return asset('storage/'.$settings->logo_path);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBrandBlock(string $brandName, array $client, array $brand, ?AgencySetting $settings): array
    {
        return [
            'name' => $brandName,
            'description' => (string) ($settings?->footer_about ?: ($client['footer_text'] ?? ($brand['company_note'] ?? 'Your trusted travel partner for reliable flight booking and travel support.'))),
            'use_brand_logo' => true,
            'show_logo' => true,
            'logo_url' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultSupportCard(): array
    {
        return [
            'is_enabled' => true,
            'title' => '24/7 Support',
            'subtitle' => 'Always here to help',
            'icon' => 'headphones',
        ];
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    protected function mergeContactDefaults(array $stored, ?AgencySetting $settings, array $client, array $brand): array
    {
        $defaults = [
            'is_enabled' => true,
            'heading' => 'Get In Touch',
            'address' => (string) ($settings?->office_address ?? ''),
            'phone' => (string) ($settings?->support_phone ?: ($client['support_phone'] ?? ($brand['support_phone'] ?? ''))),
            'email' => (string) ($settings?->support_email ?: ($client['support_email'] ?? ($brand['support_email'] ?? ''))),
            'whatsapp' => (string) ($settings?->support_whatsapp ?: ($client['support_whatsapp'] ?? ($brand['support_whatsapp'] ?? ''))),
            'whatsapp_label' => 'WhatsApp',
            'city' => (string) ($settings?->city ?? ($client['office_city'] ?? '')),
            'show_phone' => true,
            'show_email' => true,
            'show_whatsapp' => true,
            'show_address' => true,
            'show_city' => true,
        ];

        return array_merge($defaults, $stored);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBottomBar(string $brandName, ?AgencySetting $settings): array
    {
        $year = date('Y');

        return [
            'copyright' => (string) ($settings?->footer_copyright ?: "© {$year} {$brandName}. All rights reserved."),
            'disclaimer' => 'Subject to airline confirmation.',
            'powered_by_label' => '',
            'powered_by_url' => '',
            'legal_links' => [],
            'trust_badges' => [
                ['item_key' => 'ssl', 'label' => 'SSL Secure', 'is_enabled' => true, 'sort_order' => 10],
                ['item_key' => 'iata', 'label' => 'IATA', 'is_enabled' => false, 'sort_order' => 20],
                ['item_key' => 'pci', 'label' => 'PCI DSS', 'is_enabled' => false, 'sort_order' => 30],
            ],
            'show_trust_badges' => true,
            'show_legal_links' => true,
        ];
    }

    /**
     * @return array<string, array{url: string, is_enabled: bool}>
     */
    protected function defaultSocialLinks(?AgencySetting $settings): array
    {
        $legacy = is_array($settings?->social_links) ? $settings->social_links : [];
        $social = [];
        foreach (self::SOCIAL_PLATFORMS as $platform) {
            $legacyKey = $platform === 'twitter' ? 'twitter' : $platform;
            $url = trim((string) ($legacy[$legacyKey] ?? ''));
            $social[$platform] = [
                'url' => $url,
                'is_enabled' => $url !== '',
            ];
        }

        return $social;
    }

    /**
     * @param  array<string, mixed>  $bar
     * @return array<string, mixed>
     */
    protected function normalizeBottomBar(array $bar): array
    {
        $legal = [];
        foreach (is_array($bar['legal_links'] ?? null) ? $bar['legal_links'] : [] as $index => $link) {
            if (! is_array($link)) {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            try {
                $url = $this->sanitizeUrl((string) ($link['url'] ?? '/'));
            } catch (ValidationException) {
                continue;
            }
            $legal[] = [
                'item_key' => (string) ($link['item_key'] ?? 'legal-'.$index),
                'label' => $label,
                'url' => $url,
                'is_enabled' => (bool) ($link['is_enabled'] ?? true),
                'sort_order' => (int) ($link['sort_order'] ?? (($index + 1) * 10)),
            ];
        }
        usort($legal, fn (array $a, array $b): int => ($a['sort_order'] <=> $b['sort_order']));

        $badges = [];
        foreach (is_array($bar['trust_badges'] ?? null) ? $bar['trust_badges'] : [] as $index => $badge) {
            if (! is_array($badge)) {
                continue;
            }
            $label = trim((string) ($badge['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $badges[] = [
                'item_key' => (string) ($badge['item_key'] ?? 'badge-'.$index),
                'label' => $label,
                'is_enabled' => (bool) ($badge['is_enabled'] ?? true),
                'sort_order' => (int) ($badge['sort_order'] ?? (($index + 1) * 10)),
            ];
        }
        if ($badges === []) {
            $badges = $this->defaultBottomBar('', null)['trust_badges'];
        }

        return [
            'copyright' => trim((string) ($bar['copyright'] ?? '')),
            'disclaimer' => trim((string) ($bar['disclaimer'] ?? 'Subject to airline confirmation.')),
            'powered_by_label' => trim((string) ($bar['powered_by_label'] ?? '')),
            'powered_by_url' => trim((string) ($bar['powered_by_url'] ?? '')),
            'legal_links' => $legal,
            'trust_badges' => $badges,
            'show_trust_badges' => (bool) ($bar['show_trust_badges'] ?? true),
            'show_legal_links' => (bool) ($bar['show_legal_links'] ?? true),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $badges
     * @return list<array<string, mixed>>
     */
    protected function publicTrustBadges(array $badges, ?AgencySetting $settings): array
    {
        $visible = [];
        foreach ($badges as $badge) {
            if (! is_array($badge) || ! ($badge['is_enabled'] ?? false)) {
                continue;
            }
            if (! $this->trustBadgeIsVerified($badge, $settings)) {
                continue;
            }
            $visible[] = $badge;
        }

        return $visible;
    }

    /**
     * @param  array<string, mixed>  $badge
     */
    protected function trustBadgeIsVerified(array $badge, ?AgencySetting $settings): bool
    {
        $key = strtolower((string) ($badge['item_key'] ?? ''));
        if (! in_array($key, ['iata', 'pci'], true)) {
            return true;
        }

        $meta = is_array($settings?->meta) ? $settings->meta : [];
        $footerMeta = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];
        $trust = is_array($footerMeta['trust_verification'] ?? null) ? $footerMeta['trust_verification'] : [];

        if ($key === 'iata') {
            if (filter_var($trust['iata'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }

            return trim((string) ($meta['iata_number'] ?? '')) !== '';
        }

        return filter_var($trust['pci_dss'] ?? $trust['pci'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $social
     * @return array<string, array{url: string, is_enabled: bool}>
     */
    protected function normalizeSocialLinks(array $social): array
    {
        $normalized = [];
        foreach (self::SOCIAL_PLATFORMS as $platform) {
            $row = is_array($social[$platform] ?? null) ? $social[$platform] : [];
            $normalized[$platform] = [
                'url' => trim((string) ($row['url'] ?? '')),
                'is_enabled' => (bool) ($row['is_enabled'] ?? false),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    protected function normalizeSupportCard(array $card): array
    {
        return [
            'is_enabled' => (bool) ($card['is_enabled'] ?? true),
            'title' => trim((string) ($card['title'] ?? '24/7 Support')),
            'subtitle' => trim((string) ($card['subtitle'] ?? 'Always here to help')),
            'icon' => trim((string) ($card['icon'] ?? 'headphones')),
        ];
    }

    /**
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    protected function normalizeStyle(array $style, bool $strictColors = false): array
    {
        $defaults = $this->defaultStyle();
        $merged = array_merge($defaults, $style);
        $merged['spacing'] = in_array($merged['spacing'] ?? '', self::SPACING_OPTIONS, true)
            ? $merged['spacing']
            : 'normal';

        foreach (['background_color', 'bottom_bar_background_color', 'text_color', 'heading_color', 'link_color', 'link_hover_color', 'accent_color'] as $key) {
            $value = trim((string) ($merged[$key] ?? ''));
            if ($value === '') {
                $merged[$key] = $defaults[$key];

                continue;
            }
            if ($strictColors && ! $this->isValidHexColor($value)) {
                throw ValidationException::withMessages([$key => 'Must be a valid hex color (#RRGGBB).']);
            }
            $merged[$key] = $this->isValidHexColor($value) ? strtoupper($value) : $defaults[$key];
        }

        $merged['show_support_card'] = (bool) ($merged['show_support_card'] ?? true);
        $merged['show_social'] = (bool) ($merged['show_social'] ?? true);
        $merged['columns'] = in_array((int) ($merged['columns'] ?? 5), [4, 5], true) ? (int) $merged['columns'] : 5;

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultStyle(): array
    {
        return [
            'background_color' => '#F8FAFC',
            'bottom_bar_background_color' => '#F1F5F9',
            'text_color' => '#334155',
            'heading_color' => '#0F172A',
            'link_color' => '#1E3A5F',
            'link_hover_color' => '#0C4A6E',
            'accent_color' => '#0284C7',
            'spacing' => 'normal',
            'show_support_card' => true,
            'show_social' => true,
            'columns' => 5,
        ];
    }

    protected function isValidHexColor(string $value): bool
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }

    protected function defaultSectionHeading(string $sectionKey): string
    {
        return match ($sectionKey) {
            self::SECTION_COMPANY => 'Company',
            self::SECTION_SUPPORT => 'Support',
            self::SECTION_EXPLORE => 'Explore',
            default => Str::headline($sectionKey),
        };
    }

    protected function defaultSectionSortOrder(string $sectionKey): int
    {
        return match ($sectionKey) {
            self::SECTION_COMPANY => 20,
            self::SECTION_SUPPORT => 30,
            self::SECTION_EXPLORE => 40,
            default => 100,
        };
    }

    protected function optionalSanitizedUrl(mixed $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        return $this->sanitizeUrl($url, allowExternal: true);
    }
}
