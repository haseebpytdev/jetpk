<?php

namespace App\Support\Client;

/**
 * Structured section schema for JetPK page builder admin editors.
 */
final class ClientPageSectionSchema
{
    /**
     * @return list<array{key: string, label: string, fields: list<string>}>
     */
    public static function sectionsFor(string $pageKey): array
    {
        return match ($pageKey) {
            ClientPageKeys::HOME => [
                ['key' => 'hero', 'label' => 'Hero', 'fields' => ['eyebrow', 'headline', 'headline_highlight', 'subtitle', 'search_visible']],
                ['key' => 'trust_chips', 'label' => 'Trust badges', 'fields' => ['label']],
                ['key' => 'feature_board', 'label' => 'Stats strip', 'fields' => ['items']],
                ['key' => 'why_book', 'label' => 'Why JetPakistan', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'cards']],
                ['key' => 'trust', 'label' => 'Trust cards', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'cards']],
                ['key' => 'routes', 'label' => 'Popular routes', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'cta_text', 'cta_url', 'items']],
                ['key' => 'destinations', 'label' => 'Popular destinations', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'cta_text', 'cta_url', 'items']],
                ['key' => 'group_cards', 'label' => 'Group travel cards', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'cta_text', 'cta_url', 'items']],
                ['key' => 'featured_deals', 'label' => 'Featured deals', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'cta_text', 'cta_url', 'card_count', 'source']],
                ['key' => 'support_cta', 'label' => 'Support callout', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'phone_label', 'phone_value', 'call_enabled', 'call_label', 'call_url', 'chat_enabled', 'chat_label', 'chat_url', 'cta_label', 'cta_link', 'background_mode', 'overlay_strength', 'text_alignment']],
            ],
            ClientPageKeys::ABOUT, ClientPageKeys::SUPPORT => [
                ['key' => 'hero', 'label' => 'Page hero', 'fields' => ['kicker', 'title', 'description']],
                ['key' => 'contact', 'label' => 'Contact details', 'fields' => ['phone', 'email', 'whatsapp', 'website', 'office', 'hours']],
                ['key' => 'form', 'label' => 'Contact form', 'fields' => ['helper_text']],
            ],
            ClientPageKeys::FOOTER => [
                ['key' => 'description', 'label' => 'Footer intro', 'fields' => ['text']],
                ['key' => 'columns', 'label' => 'Link columns', 'fields' => ['title', 'links']],
                ['key' => 'social', 'label' => 'Social links', 'fields' => ['platform', 'url']],
                ['key' => 'legal', 'label' => 'Legal', 'fields' => ['copyright', 'company_line']],
            ],
            ClientPageKeys::GLOBAL => [
                ['key' => 'announcement', 'label' => 'Announcement banner', 'fields' => ['enabled', 'text', 'link', 'style']],
                ['key' => 'header_support', 'label' => 'Header support', 'fields' => ['phone', 'email', 'hours']],
                ['key' => 'seo', 'label' => 'Default SEO', 'fields' => ['title', 'description', 'og_image']],
            ],
            ClientPageKeys::GROUP_SEARCH => [
                ['key' => 'hero', 'label' => 'Group search hero', 'fields' => ['kicker', 'title', 'description']],
            ],
            ClientPageKeys::BOOKING_LOOKUP => [
                ['key' => 'hero', 'label' => 'Lookup hero', 'fields' => ['title', 'description', 'help_text']],
            ],
            ClientPageKeys::AGENT_REGISTRATION => [
                ['key' => 'hero', 'label' => 'Agent landing hero', 'fields' => ['kicker', 'title', 'description', 'cta_text']],
                ['key' => 'benefits', 'label' => 'Benefits', 'fields' => ['title', 'items']],
            ],
            ClientPageKeys::TERMS, ClientPageKeys::PRIVACY, ClientPageKeys::FAQ => [
                ['key' => 'content', 'label' => 'Page content', 'fields' => ['title', 'intro', 'body']],
            ],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function requiredAssetKeys(string $pageKey): array
    {
        return ClientPageMediaSchema::assetKeysFor($pageKey);
    }
}
