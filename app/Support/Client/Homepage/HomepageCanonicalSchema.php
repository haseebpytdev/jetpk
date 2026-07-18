<?php

namespace App\Support\Client\Homepage;

/**
 * CANONICAL homepage content schema — JETPK-HOMEPAGE-CMS programme, Task 6.
 *
 * This is the single authoritative definition of every homepage editorial
 * field: what it's called, what section it belongs to, what type it is,
 * how it validates, what happens when it's empty/null/absent, what its
 * default is, whether it's part of a repeating collection, how media
 * attaches to it, and — critically — its current CONNECTION STATUS as
 * found by the JETPK-HOMEPAGE-CMS Tasks 1-5 audit.
 *
 * This class does not yet DRIVE the Admin editor, presenter, or Blade
 * views (that's Task 7 onward — normalization/coverage work). Its purpose
 * right now is to be the one place that says what SHOULD be true, so
 * Admin/presenter/Blade can all be brought into line with it instead of
 * three slightly-different implicit schemas as exists today.
 *
 * STATUS values and what they mean for follow-up work:
 *   - connected              : Admin field exists, frontend reads it, no action needed.
 *   - dead                   : Admin field exists, frontend never reads it. Task 9 must
 *                              either wire it up or remove the Admin field — this schema
 *                              records the DECISION already made on which one.
 *   - needs_frontend_wiring  : this schema declares the field as canonical and keeps the
 *                              Admin field, but the Blade/component side needs new code
 *                              to actually render it. Not yet done — flagged for Task 9.
 *   - needs_admin_field      : frontend can render this today (or could, trivially) but
 *                              no Admin input exists yet. Task 10 must add one.
 *   - deprecated_remove      : field should be deleted from Admin, schema, and any saved
 *                              content during Task 7's normalization pass — no product
 *                              value identified, and no code path uses it.
 *   - deprecated_alias       : superseded by a newer canonical field (see 'canonical_of'
 *                              on the newer field, or 'migration_alias_for' here); kept
 *                              only so Task 7's normalizer has something to migrate FROM.
 *   - out_of_scope           : real, known problem, but not a schema-shape issue — e.g.
 *                              Featured Deals' hardcoded fare data is a data-source problem,
 *                              not something a schema definition can fix by itself.
 */
final class HomepageCanonicalSchema
{
    public const CATEGORY_EDITORIAL = 'editorial';

    public const CATEGORY_BRANDING = 'global_branding';

    public const CATEGORY_DYNAMIC = 'dynamic_system';

    public const CATEGORY_MEDIA = 'media';

    public const CATEGORY_SECTION_META = 'section_metadata';

    public const CATEGORY_REPEATING = 'repeating_collection';

    public const CATEGORY_SEO = 'seo_metadata';

    /**
     * Section render order is currently 100% hardcoded in
     * home.blade.php's @include sequence — there is no data-driven
     * "section order" field anywhere (Task 10 acceptance criteria
     * explicitly requires one; it does not exist today). The
     * `render_order` values below reflect current hardcoded reality,
     * not a saved/editable setting.
     *
     * @return list<array{
     *     key: string, label: string, render_order: int, blade_view: string,
     *     enable_field: string|null, order_field: string|null,
     *     order_field_status: string,
     * }>
     */
    public static function sections(): array
    {
        return [
            ['key' => 'hero', 'label' => 'Hero', 'render_order' => 1, 'blade_view' => 'sections/hero.blade.php', 'enable_field' => null, 'order_field' => null, 'order_field_status' => self::CATEGORY_SECTION_META.':no_toggle_by_design'],
            ['key' => 'feature_board', 'label' => 'Stats strip', 'render_order' => 2, 'blade_view' => 'sections/feature-board.blade.php', 'enable_field' => 'feature_board.enabled', 'order_field' => null, 'order_field_status' => 'no_section_ordering_exists'],
            ['key' => 'trust', 'label' => "Why travellers stay", 'render_order' => 3, 'blade_view' => 'sections/trust.blade.php', 'enable_field' => 'trust.enabled', 'order_field' => null, 'order_field_status' => 'no_section_ordering_exists'],
            ['key' => 'group_cards', 'label' => 'Group travel packages', 'render_order' => 4, 'blade_view' => 'sections/groups.blade.php', 'enable_field' => 'group_cards.enabled', 'order_field' => null, 'order_field_status' => 'no_section_ordering_exists'],
            ['key' => 'featured_deals', 'label' => 'Featured deals', 'render_order' => 5, 'blade_view' => 'sections/fares.blade.php', 'enable_field' => 'featured_deals.enabled', 'order_field' => null, 'order_field_status' => 'no_section_ordering_exists'],
            ['key' => 'routes', 'label' => 'Trending routes', 'render_order' => 6, 'blade_view' => 'sections/routes.blade.php', 'enable_field' => 'routes.enabled', 'order_field' => null, 'order_field_status' => 'no_section_ordering_exists'],
            ['key' => 'destinations', 'label' => 'Popular destinations', 'render_order' => 7, 'blade_view' => 'sections/destinations.blade.php', 'enable_field' => 'destinations.enabled', 'order_field' => null, 'order_field_status' => 'no_section_ordering_exists'],
            ['key' => 'why_book', 'label' => 'Why book with us', 'render_order' => 8, 'blade_view' => 'sections/why-book.blade.php', 'enable_field' => 'why_book.enabled', 'order_field' => null, 'order_field_status' => 'no_section_ordering_exists'],
            ['key' => 'support_cta', 'label' => 'Support callout', 'render_order' => 9, 'blade_view' => 'sections/support-cta.blade.php', 'enable_field' => 'support_cta.enabled', 'order_field' => null, 'order_field_status' => 'no_section_ordering_exists'],
        ];
    }

    /**
     * DECISION (Task 6, resolving H1/Task 3 §6): the `groups.*` top-level key
     * is retired. Its live field (`groups.cta_url`, previously fetched-but-
     * discarded) and its dead fields (`enabled`, `title`, `subtitle`,
     * `cta_text`) are all superseded by the equivalent `group_cards.*` field,
     * which already exists in the schema and is the one the frontend's
     * enable-gate and title/eyebrow actually use. `group_cards.subtitle`,
     * `.cta_text`, and `.cta_url` become the canonical home for that content
     * going forward, marked `needs_frontend_wiring` below since the Blade
     * side doesn't read them yet either — but they are the RIGHT key now,
     * not a second wrong one.
     *
     * @return array<string, list<string>>
     */
    public static function migrationAliases(): array
    {
        return [
            // old (deprecated) => new (canonical)
            'groups.enabled' => 'group_cards.enabled',
            'groups.title' => 'group_cards.title',
            'groups.subtitle' => 'group_cards.subtitle',
            'groups.cta_text' => 'group_cards.cta_text',
            'groups.cta_url' => 'group_cards.cta_url',
            'support_cta.phone_label' => 'support_cta.call_label',
            'support_cta.cta_label' => 'support_cta.chat_label',
            'support_cta.cta_link' => 'support_cta.chat_url',
        ];
    }

    /**
     * Full field-level schema, keyed by section. Every field carries its
     * current audit status from Tasks 2-5 so this document is truthful
     * about today's reality, not just an aspirational target.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function fields(): array
    {
        return [
            'hero' => [
                self::field('hero.eyebrow', 'Eyebrow', 'string', default: 'Now boarding · Pakistan', emptyBehavior: 'hide'),
                self::field('hero.headline', 'Headline', 'string', default: 'Every flight from Pakistan,', emptyBehavior: 'show_empty'),
                self::field('hero.headline_highlight', 'Highlighted line', 'string', default: 'one honest fare.', emptyBehavior: 'hide'),
                self::field('hero.subtitle', 'Subtitle', 'text', default: 'Compare 400+ airlines...', emptyBehavior: 'hide'),
                self::field('hero.search_visible', 'Show flight search on hero', 'bool_string', default: '1'),
                self::field('hero.eyebrow_size', 'Hero eyebrow size', 'int', default: '100', validation: 'min:75,max:140', note: 'Percentage scale; 100 = responsive baseline.'),
                self::field('hero.headline_size', 'Hero headline size', 'int', default: '100', validation: 'min:75,max:140'),
                self::field('hero.highlight_size', 'Hero highlighted line size', 'int', default: '100', validation: 'min:75,max:140'),
                self::field('hero.subtitle_size', 'Hero subtitle size', 'int', default: '100', validation: 'min:75,max:140'),
                self::field('hero.search_ui_scale', 'Search box size', 'int', default: '100', validation: 'min:80,max:115', note: 'Percentage maps directly to --jp-search-ui-scale (100 = 1.0). Compact baseline is encoded in design tokens.'),
                self::field('trust_chips', 'Trust badges (top-level key, rendered inside hero)', 'repeating_scalar', repeating: ['item_fields' => ['label'], 'fixed_count' => 4], note: 'Lives at the top level, not hero.trust_chips — kept as-is; renaming would be a breaking migration for no functional gain.'),
            ],

            'feature_board' => [
                self::field('feature_board.enabled', 'Section enabled', 'bool_string', default: '1', note: 'Task 9: Admin checkbox and Blade isEnabled() gate both added — was the only homepage section with no enable toggle at all (Task 2 §2).'),
                self::field('feature_board.items', 'Stat items', 'repeating', repeating: ['item_fields' => ['value', 'label'], 'fixed_count' => 5]),
            ],

            'trust' => [
                self::field('trust.enabled', 'Section enabled', 'bool_string', default: '1'),
                self::field('trust.eyebrow', 'Eyebrow', 'string', default: 'Why travellers stay', note: 'Task 9: reconciled — defaultHomeContent() is now the single source; trust.blade.php no longer hardcodes its own separate fallback strings.'),
                self::field('trust.title', 'Heading', 'string', default: 'Booking that respects your time and money.', note: 'Task 9: reconciled, see trust.eyebrow note.'),
                self::field('trust.subtitle', 'Subtitle', 'text', default: 'No hidden markups, no chasing call centres...'),
                self::field('trust.cards', 'Trust cards', 'repeating', repeating: ['item_fields' => ['icon', 'title', 'text', 'enabled'], 'fixed_count' => 3]),
            ],

            'group_cards' => [
                self::field('group_cards.enabled', 'Section enabled', 'bool_string', default: '1'),
                self::field('group_cards.eyebrow', 'Eyebrow', 'string', default: 'Curated journeys'),
                self::field('group_cards.title', 'Heading', 'string', default: 'Group travel packages.'),
                self::field('group_cards.subtitle', 'Subtitle', 'text', note: 'Task 9: groups.blade.php now reads this field. Canonical home for what groups.subtitle used to (incorrectly) hold — see migrationAliases().'),
                self::field('group_cards.cta_text', 'CTA label', 'string', note: 'Task 9: the "View all packages" link in groups.blade.php now reads this instead of a hardcoded client_route() call.'),
                self::field('group_cards.cta_url', 'CTA URL', 'url', note: 'Task 9: same fix as cta_text — previously fetched-but-discarded (Task 2 §4), now actually rendered.'),
                self::field('group_cards.items', 'Group cards', 'repeating', repeating: [
                    'item_fields' => ['title', 'badge', 'meta', 'price', 'link', 'enabled', 'gold'],
                    'fixed_count' => 3,
                    'field_status' => [
                        'gold' => ['status' => 'connected', 'note' => 'Task 9: Admin checkbox added to the group_cards items form. Frontend (x-jp.group-card) already supported the prop.'],
                        'route' => ['status' => 'removed', 'note' => 'Task 9: field removed from the Admin form entirely. Task 7\'s normalizer strips it from any already-saved content on read.'],
                        'alt' => ['status' => 'removed', 'note' => 'Task 9: field removed from the Admin form entirely (component still has no <img> tag to apply alt text to). Task 7\'s normalizer strips it from any already-saved content on read.'],
                    ],
                ]),
            ],

            'featured_deals' => [
                self::field('featured_deals.enabled', 'Visible', 'bool_string', default: '1'),
                self::field('featured_deals.eyebrow', 'Eyebrow', 'string', default: 'Editorial picks'),
                self::field('featured_deals.title', 'Heading', 'string', default: 'Featured deals'),
                self::field('featured_deals.subtitle', 'Subtitle', 'text', default: 'Hand-picked sample fares for inspiration — prices shown are editorial examples, not live quotes.'),
                self::field('featured_deals.cta_text', 'CTA label', 'string'),
                self::field('featured_deals.cta_url', 'CTA URL', 'url'),
                self::field('featured_deals.card_count', 'Card count', 'int', default: 3, validation: 'min:1,max:6'),
                self::field('featured_deals.items', 'Deal cards', 'repeating', repeating: [
                    'item_fields' => ['airline', 'from', 'to', 'depart', 'arrive', 'dur', 'stops', 'price', 'enabled', 'sort_order'],
                    'max_items_config' => 'jetpk_homepage.max_featured_deals',
                ], note: 'Editorial CMS items — no supplier calls on homepage render.'),
            ],

            'routes' => [
                self::field('routes.enabled', 'Section enabled', 'bool_string', default: '1'),
                self::field('routes.eyebrow', 'Eyebrow', 'string', default: 'Trending routes'),
                self::field('routes.title', 'Heading', 'string', default: 'Where Pakistan is flying.'),
                self::field('routes.subtitle', 'Subtitle', 'text'),
                self::field('routes.cta_text', 'CTA label', 'string', default: 'Search routes'),
                self::field('routes.cta_url', 'CTA URL', 'url'),
                self::field('routes.items', 'Route cards', 'repeating', repeating: [
                    'item_fields' => ['from', 'to', 'trip_type', 'return_stay_days', 'manual_fallback_price', 'badge', 'enabled', 'dynamic_fare_enabled', 'adults', 'cabin', 'sort_order'],
                    'max_items_config' => 'jetpk_homepage.max_routes',
                ], note: 'Reference-quality section (Task 2/3/4) — validated server-side, live fare refresh wired correctly. No changes recommended.'),
            ],

            'destinations' => [
                self::field('destinations.enabled', 'Section enabled', 'bool_string', default: '1'),
                self::field('destinations.eyebrow', 'Eyebrow', 'string', default: 'Worth the trip'),
                self::field('destinations.title', 'Heading', 'string', default: 'Destinations on the rise.'),
                self::field('destinations.subtitle', 'Subtitle', 'text'),
                self::field('destinations.cta_text', 'Section CTA label', 'string', default: 'Explore fares'),
                self::field('destinations.cta_url', 'Section CTA URL', 'url'),
                self::field('destinations.items', 'Destination cards', 'repeating', repeating: [
                    'item_fields' => ['code', 'title', 'country', 'manual_fallback_price', 'link', 'alt', 'enabled', 'sort_order', 'image_asset_key'],
                    'max_items_config' => 'jetpk_homepage.max_destinations',
                    'field_status' => [
                        'badge' => ['status' => 'connected', 'note' => 'Task 9: badge slot added to x-jp.dest-card, matching the sibling group-card component.'],
                        'text' => ['status' => 'connected', 'note' => 'Task 9: description line added to x-jp.dest-card.'],
                    ],
                ]),
            ],

            'why_book' => [
                self::field('why_book.enabled', 'Section enabled', 'bool_string', default: '1'),
                self::field('why_book.eyebrow', 'Eyebrow', 'string', default: 'The JetPakistan difference'),
                self::field('why_book.title', 'Heading', 'string', default: 'Built for how Pakistan books.'),
                self::field('why_book.subtitle', 'Subtitle', 'text'),
                self::field('why_book.cards', 'Benefit cards', 'repeating', repeating: ['item_fields' => ['num', 'title', 'icon', 'text', 'enabled'], 'fixed_count' => 4], note: 'Clean, no issues found.'),
            ],

            'support_cta' => [
                self::field('support_cta.enabled', 'Section enabled', 'bool_string', default: '1'),
                self::field('support_cta.eyebrow', 'Eyebrow', 'string', default: 'We pick up'),
                self::field('support_cta.title', 'Heading', 'string'),
                self::field('support_cta.subtitle', 'Body', 'text'),
                self::field('support_cta.call_enabled', 'Call button enabled', 'bool_string', default: '1'),
                self::field('support_cta.call_label', 'Call Support label', 'string', default: 'Call support'),
                self::field('support_cta.phone_value', 'Call Support phone', 'phone', validation: 'digits, spaces, +, -, () only'),
                self::field('support_cta.call_url', 'Call Support URL (optional)', 'url', validation: 'relative or https://', note: 'Takes precedence over the tel: link built from phone_value if both are set.'),
                self::field('support_cta.chat_enabled', 'Chat button enabled', 'bool_string', default: '1'),
                self::field('support_cta.chat_label', 'Live Chat label', 'string', default: 'Live chat'),
                self::field('support_cta.chat_url', 'Live Chat URL', 'url', default: '/support', validation: 'relative or https://'),
                self::field('support_cta.background_mode', 'Background mode', 'enum', default: 'gradient', validation: 'gradient|uploaded|uploaded_overlay'),
                self::field('support_cta.overlay_strength', 'Overlay strength', 'enum', default: 'medium', validation: 'light|medium|strong'),
                self::field('support_cta.text_alignment', 'Text alignment', 'enum', default: 'left', validation: 'left|center'),
                self::field('support_cta.phone_label', 'phone_label (legacy)', 'string', status: 'deprecated_alias', note: 'Superseded by call_label. No Admin field. Kept only as a fallback rung for old saved payloads — see migrationAliases().'),
                self::field('support_cta.cta_label', 'cta_label (legacy)', 'string', status: 'deprecated_alias', note: 'Superseded by chat_label.'),
                self::field('support_cta.cta_link', 'cta_link (legacy)', 'string', status: 'deprecated_alias', note: 'Superseded by chat_url.'),
            ],
        ];
    }

    /**
     * Media (asset) keys — a separate namespace from content_json entirely,
     * backed by client_page_assets, not schema-validated dot-paths.
     *
     * @return list<array{key: string, section: string, desktop: bool, mobile_key: string|null, alt_source: string|null}>
     */
    public static function mediaKeys(): array
    {
        return [
            ['key' => 'hero_background', 'section' => 'hero', 'desktop' => true, 'mobile_key' => null, 'alt_source' => null],
            ['key' => 'group_card_{1,2,3}', 'section' => 'group_cards', 'desktop' => true, 'mobile_key' => null, 'alt_source' => null], // Task 9: alt field removed entirely (see fields())
            ['key' => 'destination_{id}', 'section' => 'destinations', 'desktop' => true, 'mobile_key' => null, 'alt_source' => 'destinations.items.{i}.alt (connected)'],
            ['key' => 'support_cta_background', 'section' => 'support_cta', 'desktop' => true, 'mobile_key' => 'support_cta_background_mobile', 'alt_source' => null],
        ];
    }

    /**
     * Homepage-specific SEO metadata does not exist as a concept today — only
     * a site-wide fallback exists under the separate `global` page
     * (`global.seo.{title,description,og_image}`). Recorded here as an
     * explicit gap rather than silently absent, per Task 6's requirement to
     * separate out an SEO metadata category even if it's currently empty.
     *
     * @return list<string>
     */
    public static function seoMetadataGap(): array
    {
        return [
            'No per-page SEO override exists for the homepage specifically.',
            'global.seo.title / global.seo.description / global.seo.og_image are the only SEO fields in the whole Page Settings system, and (per Task 2) the entire `global` page has zero frontend consumer anyway.',
            'Not fixed here — this is a product decision (does the homepage need its own SEO fields, distinct from a site default?) rather than a schema bug.',
        ];
    }

    /**
     * @param array{item_fields?: list<string>, fixed_count?: int, max_items_config?: string, field_status?: array<string, array{status: string, note?: string}>}|null $repeating
     */
    private static function field(
        string $path,
        string $label,
        string $type,
        ?string $default = null,
        string $emptyBehavior = 'show_empty',
        bool $nullable = false,
        ?string $validation = null,
        string $status = 'connected',
        ?string $note = null,
        ?array $repeating = null,
    ): array {
        return [
            'path' => $path,
            'label' => $label,
            'type' => $type,
            'category' => str_contains($path, '.items') || $repeating !== null ? self::CATEGORY_REPEATING : self::CATEGORY_EDITORIAL,
            'default' => $default,
            'empty_behavior' => $emptyBehavior, // hide | show_empty | show_default
            'nullable' => $nullable,
            'validation' => $validation,
            'status' => $status,
            'note' => $note,
            'repeating' => $repeating,
        ];
    }
}
