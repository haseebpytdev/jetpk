<?php

namespace App\Support\Client;

/**
 * Canonical typed section schema for JetPK content-owned and hybrid pages.
 */
final class ClientCanonicalPageSchema
{
    /** @var list<string> */
    public const SECTION_TYPES = [
        'hero',
        'rich_text',
        'split_content_image',
        'image_banner',
        'feature_cards',
        'statistics',
        'faq_accordion',
        'cta_banner',
        'support_callout',
        'legal_content',
        'link_list',
        'team_or_values_cards',
        'timeline',
        'content_grid',
        'department_cards',
    ];

    /** @var list<string> */
    public const SECTION_FIELDS = [
        'id',
        'enabled',
        'order',
        'eyebrow',
        'heading',
        'highlight',
        'body',
        'media_key',
        'alt_text',
        'cta_label',
        'cta_url',
        'alignment',
        'variant',
        'spacing',
    ];

    /**
     * @return list<array{key: string, label: string, fields: list<string>}>
     */
    public static function sectionsFor(string $pageKey): array
    {
        return ClientPageSectionSchema::sectionsFor($pageKey);
    }
}
