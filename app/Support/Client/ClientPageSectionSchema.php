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
        if (ClientPageKeys::isCustom($pageKey)) {
            return [
                ['key' => 'identity', 'label' => 'Page identity', 'fields' => ['title', 'slug', 'nav_label']],
                ['key' => 'sections', 'label' => 'Typed sections', 'fields' => ['items']],
                ['key' => 'seo', 'label' => 'SEO', 'fields' => ['title', 'description', 'canonical', 'robots', 'og_title', 'og_description', 'og_image']],
            ];
        }

        return match ($pageKey) {
            ClientPageKeys::HOME => [
                ['key' => 'hero', 'label' => 'Hero', 'fields' => ['eyebrow', 'headline', 'headline_highlight', 'subtitle', 'search_visible', 'eyebrow_size', 'headline_size', 'highlight_size', 'subtitle_size', 'search_ui_scale']],
                ['key' => 'trust_chips', 'label' => 'Trust badges', 'fields' => ['label']],
                ['key' => 'feature_board', 'label' => 'Stats strip', 'fields' => ['items']],
                ['key' => 'why_book', 'label' => 'Why JetPakistan', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'cards']],
                ['key' => 'trust', 'label' => 'Trust cards', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'cards']],
                ['key' => 'routes', 'label' => 'Popular routes', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'cta_text', 'cta_url', 'items']],
                ['key' => 'destinations', 'label' => 'Popular destinations', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'cta_text', 'cta_url', 'items']],
                ['key' => 'group_cards', 'label' => 'Group travel cards', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'cta_text', 'cta_url', 'items']],
                ['key' => 'featured_deals', 'label' => 'Featured deals', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'cta_text', 'cta_url', 'card_count', 'source']],
                ['key' => 'support_cta', 'label' => 'Support callout', 'fields' => ['enabled', 'eyebrow', 'title', 'subtitle', 'phone_label', 'phone_value', 'call_enabled', 'call_label', 'call_url', 'chat_enabled', 'chat_label', 'chat_url', 'cta_label', 'cta_link', 'background_mode', 'overlay_strength', 'text_alignment']],
                ['key' => 'seo', 'label' => 'SEO', 'fields' => ['title', 'description', 'canonical', 'robots', 'og_title', 'og_description', 'og_image']],
            ],
            ClientPageKeys::ABOUT => [
                ['key' => 'hero', 'label' => 'Page hero', 'fields' => ['kicker', 'title', 'description']],
                ['key' => 'feature_cards', 'label' => 'Feature cards', 'fields' => ['enabled', 'items']],
                ['key' => 'content_grid', 'label' => 'Content sections', 'fields' => ['enabled', 'items']],
                ['key' => 'contact', 'label' => 'Contact block', 'fields' => ['phone', 'email', 'website', 'office', 'hours']],
                ['key' => 'cta', 'label' => 'CTA buttons', 'fields' => ['primary_label', 'primary_url', 'secondary_label', 'secondary_url']],
                ['key' => 'seo', 'label' => 'SEO', 'fields' => ['title', 'description', 'canonical', 'robots', 'og_title', 'og_description', 'og_image']],
            ],
            ClientPageKeys::SUPPORT => [
                ['key' => 'hero', 'label' => 'Page hero', 'fields' => ['kicker', 'title', 'description']],
                ['key' => 'department_cards', 'label' => 'Department cards', 'fields' => ['enabled', 'items']],
                ['key' => 'contact', 'label' => 'Contact details', 'fields' => ['phone', 'email', 'whatsapp', 'website', 'office', 'hours']],
                ['key' => 'form', 'label' => 'Contact form', 'fields' => ['helper_text', 'success_copy']],
                ['key' => 'faq_teaser', 'label' => 'FAQ teaser', 'fields' => ['enabled', 'title', 'link_label', 'link_url']],
                ['key' => 'seo', 'label' => 'SEO', 'fields' => ['title', 'description', 'canonical', 'robots', 'og_title', 'og_description', 'og_image']],
            ],
            ClientPageKeys::FOOTER => [
                ['key' => 'description', 'label' => 'Footer intro', 'fields' => ['text']],
                ['key' => 'columns', 'label' => 'Link columns', 'fields' => ['title', 'links']],
                ['key' => 'social', 'label' => 'Social links', 'fields' => ['platform', 'url']],
                ['key' => 'legal', 'label' => 'Legal', 'fields' => ['copyright', 'company_line']],
                ['key' => 'contact', 'label' => 'Contact', 'fields' => ['phone', 'email', 'address', 'hours']],
            ],
            ClientPageKeys::GLOBAL => [
                ['key' => 'announcement', 'label' => 'Announcement banner', 'fields' => ['enabled', 'text', 'link', 'style']],
                ['key' => 'header', 'label' => 'Header & navigation', 'fields' => ['logo_asset', 'logo_dark_asset', 'support_pill_label', 'support_pill_url', 'sign_in_label', 'register_label', 'theme_toggle_visible', 'sticky_enabled', 'nav_items']],
                ['key' => 'header_support', 'label' => 'Header support', 'fields' => ['phone', 'email', 'hours']],
                ['key' => 'contact', 'label' => 'Global contact', 'fields' => ['phone', 'phone_e164', 'email', 'whatsapp', 'website', 'office', 'hours', 'company_legal_name']],
                ['key' => 'seo', 'label' => 'Default SEO', 'fields' => ['title', 'description', 'og_image', 'robots']],
            ],
            ClientPageKeys::GROUP_SEARCH => [
                ['key' => 'hero', 'label' => 'Group search hero', 'fields' => ['kicker', 'title', 'description']],
                ['key' => 'seo', 'label' => 'SEO', 'fields' => ['title', 'description', 'canonical', 'robots']],
            ],
            ClientPageKeys::BOOKING_LOOKUP => [
                ['key' => 'hero', 'label' => 'Lookup hero', 'fields' => ['kicker', 'title', 'description']],
                ['key' => 'instructions', 'label' => 'Instructions', 'fields' => ['how_it_works', 'hint', 'requirements']],
                ['key' => 'help_text', 'label' => 'Form help', 'fields' => ['text']],
                ['key' => 'cta', 'label' => 'Support CTA', 'fields' => ['label', 'url']],
                ['key' => 'seo', 'label' => 'SEO', 'fields' => ['title', 'description', 'robots']],
            ],
            ClientPageKeys::AGENT_REGISTRATION => [
                ['key' => 'hero', 'label' => 'Agent landing hero', 'fields' => ['kicker', 'title', 'description', 'cta_text', 'cta_url']],
                ['key' => 'steps', 'label' => 'Onboarding steps', 'fields' => ['items']],
                ['key' => 'benefits', 'label' => 'Benefits', 'fields' => ['title', 'items']],
                ['key' => 'faq', 'label' => 'FAQ', 'fields' => ['items']],
                ['key' => 'cta', 'label' => 'Bottom CTA', 'fields' => ['title', 'body', 'primary_label', 'primary_url', 'secondary_label', 'secondary_url']],
                ['key' => 'seo', 'label' => 'SEO', 'fields' => ['title', 'description', 'robots']],
            ],
            ClientPageKeys::LOGIN, ClientPageKeys::REGISTER => [
                ['key' => 'hero', 'label' => 'Form hero', 'fields' => ['title', 'subtitle']],
                ['key' => 'side_panel', 'label' => 'Side panel', 'fields' => ['eyebrow', 'title', 'body']],
                ['key' => 'benefits', 'label' => 'Benefits', 'fields' => ['items']],
                ['key' => 'footer_text', 'label' => 'Footer text', 'fields' => ['text']],
                ['key' => 'seo', 'label' => 'SEO', 'fields' => ['title', 'description', 'robots']],
            ],
            ClientPageKeys::TERMS, ClientPageKeys::PRIVACY => [
                ['key' => 'legal', 'label' => 'Legal content', 'fields' => ['title', 'effective_date', 'last_updated', 'intro', 'sections']],
                ['key' => 'seo', 'label' => 'SEO', 'fields' => ['title', 'description', 'canonical', 'robots', 'og_title', 'og_description', 'og_image']],
            ],
            ClientPageKeys::FAQ => [
                ['key' => 'hero', 'label' => 'Page hero', 'fields' => ['kicker', 'title', 'description']],
                ['key' => 'categories', 'label' => 'FAQ categories', 'fields' => ['enabled', 'items']],
                ['key' => 'cta', 'label' => 'CTA', 'fields' => ['label', 'url']],
                ['key' => 'seo', 'label' => 'SEO', 'fields' => ['title', 'description', 'canonical', 'robots']],
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
