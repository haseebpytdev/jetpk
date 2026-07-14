<?php

namespace App\Services\Agencies;

use App\Models\AgencyHomepageSection;
use App\Models\AgencySetting;
use App\Support\Branding\BrandDisplayResolver;
use Illuminate\Validation\ValidationException;

/**
 * Normalizes agency homepage section records for public rendering and admin saves.
 */
class HomepageSectionPresenter
{
    public const HERO = 'hero';

    public const TRUST_METRICS = 'trust_metrics';

    public const FEATURE_CARDS = 'feature_cards';

    public const POPULAR_ROUTES = 'popular_routes';

    public const WHY_CHOOSE_US = 'why_choose_us';

    /**
     * @var list<string>
     */
    public const STRUCTURED_SECTION_KEYS = [
        self::TRUST_METRICS,
        self::FEATURE_CARDS,
        self::POPULAR_ROUTES,
        self::WHY_CHOOSE_US,
    ];

    private const HERO_BODY_ALLOWED_TAGS = '<p><br><strong><b><em><i><ul><ol><li>';

    /**
     * @var array<string, string>
     */
    public const ICON_CLASSES = [
        'check-circle' => 'fa-check-circle',
        'users' => 'fa-users',
        'line-chart' => 'fa-line-chart',
        'plug' => 'fa-plug',
        'shield' => 'fa-shield',
        'tags' => 'fa-tags',
        'bolt' => 'fa-bolt',
        'headphones' => 'fa-headphones',
        'plane' => 'fa-plane',
        'star' => 'fa-star',
    ];

    public function isStructuredSection(string $sectionKey): bool
    {
        return in_array($sectionKey, self::STRUCTURED_SECTION_KEYS, true);
    }

    public function isHeroSection(string $sectionKey): bool
    {
        return $sectionKey === self::HERO;
    }

    /**
     * @param  array<string, mixed>  $brandFallback
     * @return array<string, mixed>
     */
    public function presentHero(?AgencyHomepageSection $section, ?object $agencySettings, array $brandFallback = []): array
    {
        $defaults = $this->defaultHeroPresentation($brandFallback, $agencySettings);
        if ($section === null) {
            return $defaults;
        }

        $content = is_array($section->content) ? $section->content : [];

        $subtitle = $this->textOrDefault($section->subtitle, $defaults['subtitle']);

        return [
            'enabled' => $section->is_enabled ?? $defaults['enabled'],
            'title' => $this->textOrDefault($section->title, $defaults['title']),
            'subtitle' => $subtitle,
            'body_html' => $this->formatHeroBodyForDisplay($subtitle),
            'badge' => $this->textOrDefault($content['badge'] ?? null, $defaults['badge']),
            'background_url' => $this->resolveHeroBackgroundUrl($section, $agencySettings),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentHeroForAdmin(?AgencyHomepageSection $section, ?object $agencySettings, array $brandFallback = []): array
    {
        return $this->presentHero($section, $agencySettings, $brandFallback);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildHeroContentFromRequest(array $input): array
    {
        return [
            'badge' => (string) ($input['badge'] ?? ''),
        ];
    }

    public function sanitizeHeroBodyForStorage(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $raw = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $raw) ?? $raw;

        if ($raw === strip_tags($raw)) {
            return $raw;
        }

        return strip_tags($raw, self::HERO_BODY_ALLOWED_TAGS);
    }

    public function formatHeroBodyForDisplay(?string $stored): string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return '';
        }

        $stored = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $stored) ?? $stored;

        if ($stored === strip_tags($stored)) {
            return nl2br(e($stored), false);
        }

        return strip_tags($stored, self::HERO_BODY_ALLOWED_TAGS);
    }

    /**
     * @return array{enabled: bool, title: string, subtitle: string, items: list<array<string, mixed>>}
     */
    public function present(?AgencyHomepageSection $section, string $sectionKey): array
    {
        $payload = $this->presentMerged($section, $sectionKey);

        return [
            ...$payload,
            'items' => $this->filterAndSortItems($payload['items']),
        ];
    }

    /**
     * @return array{enabled: bool, title: string, subtitle: string, items: list<array<string, mixed>>}
     */
    public function presentForAdmin(?AgencyHomepageSection $section, string $sectionKey): array
    {
        $payload = $this->presentMerged($section, $sectionKey);

        return [
            ...$payload,
            'items' => $this->sortItems($payload['items']),
        ];
    }

    /**
     * @return array{enabled: bool, title: string, subtitle: string, items: list<array<string, mixed>>}
     */
    protected function presentMerged(?AgencyHomepageSection $section, string $sectionKey): array
    {
        $defaults = $this->defaultPresentation($sectionKey);
        if ($section === null) {
            return $defaults;
        }

        $contentKey = $this->contentItemsKey($sectionKey);
        $storedItems = is_array($section->content[$contentKey] ?? null)
            ? $section->content[$contentKey]
            : [];

        return [
            'enabled' => $section->is_enabled ?? $defaults['enabled'],
            'title' => $this->textOrDefault($section->title, $defaults['title']),
            'subtitle' => $this->textOrDefault($section->subtitle, $defaults['subtitle']),
            'items' => $this->mergeItems($defaults['items'], $storedItems, $sectionKey),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildContentFromRequest(string $sectionKey, array $input): array
    {
        return match ($sectionKey) {
            self::TRUST_METRICS => [
                'metrics' => $this->normalizeTrustMetrics((array) ($input['items'] ?? [])),
            ],
            self::FEATURE_CARDS => [
                'cards' => [],
            ],
            self::POPULAR_ROUTES => [
                'routes' => $this->normalizePopularRoutes((array) ($input['items'] ?? [])),
            ],
            self::WHY_CHOOSE_US => [
                'bullets' => $this->normalizeWhyBullets((array) ($input['items'] ?? [])),
            ],
            default => throw ValidationException::withMessages(['section' => 'Unsupported structured section.']),
        };
    }

    /**
     * @param  array<string, mixed>  $card
     */
    public function resolveButtonUrl(?string $buttonUrl, array $card): string
    {
        $searchParams = [
            'from' => strtoupper((string) ($card['from'] ?? 'LHE')),
            'to' => strtoupper((string) ($card['to'] ?? 'DXB')),
            'depart' => (string) ($card['depart'] ?? now()->addDays(14)->toDateString()),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];

        return $this->sanitizeButtonUrl($buttonUrl, $searchParams);
    }

    /**
     * @param  array<string, mixed>  $searchParams
     */
    public function resolveButtonUrlOrDefault(?string $buttonUrl, array $searchParams): string
    {
        try {
            return $this->sanitizeButtonUrl($buttonUrl, $searchParams);
        } catch (ValidationException) {
            return route('flights.results', $searchParams);
        }
    }

    /**
     * @param  array<string, mixed>  $searchParams
     */
    public function sanitizeButtonUrl(?string $buttonUrl, array $searchParams = []): string
    {
        $url = trim((string) $buttonUrl);
        if ($url === '') {
            return route('flights.results', $searchParams);
        }

        if (preg_match('/^\s*(javascript|data|vbscript):/i', $url) !== 0) {
            throw ValidationException::withMessages([
                'items' => 'Button URL must be a safe relative path or same-site link.',
            ]);
        }

        if (str_starts_with($url, '#')) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $host = parse_url($url, PHP_URL_HOST);
            if ($host !== null && $appHost !== null && strcasecmp($host, $appHost) !== 0) {
                throw ValidationException::withMessages([
                    'items' => 'External button URLs are not allowed.',
                ]);
            }

            return $url;
        }

        throw ValidationException::withMessages([
            'items' => 'Button URL must start with / or be a valid same-site URL.',
        ]);
    }

    public function iconClass(?string $iconKey): string
    {
        $key = strtolower(trim((string) $iconKey));

        return self::ICON_CLASSES[$key] ?? self::ICON_CLASSES['check-circle'];
    }

    /**
     * @return array{enabled: bool, title: string, subtitle: string, items: list<array<string, mixed>>}
     */
    protected function defaultPresentation(string $sectionKey): array
    {
        return match ($sectionKey) {
            self::TRUST_METRICS => [
                'enabled' => true,
                'title' => '',
                'subtitle' => '',
                'items' => [
                    ['item_key' => 'default-0', 'value' => '24/7', 'label' => '24/7 travel support', 'icon' => 'check-circle', 'icon_class' => 'fa-check-circle', 'is_enabled' => true, 'sort_order' => 10],
                    ['item_key' => 'default-1', 'value' => 'Clear', 'label' => 'Transparent booking process', 'icon' => 'users', 'icon_class' => 'fa-users', 'is_enabled' => true, 'sort_order' => 20],
                    ['item_key' => 'default-2', 'value' => 'Flexible', 'label' => 'Flexible travel assistance', 'icon' => 'line-chart', 'icon_class' => 'fa-line-chart', 'is_enabled' => true, 'sort_order' => 30],
                    ['item_key' => 'default-3', 'value' => 'Trusted', 'label' => 'Trusted fare review', 'icon' => 'plug', 'icon_class' => 'fa-plug', 'is_enabled' => true, 'sort_order' => 40],
                ],
            ],
            self::FEATURE_CARDS => [
                'enabled' => true,
                'title' => 'Search your route to view available fares',
                'subtitle' => 'View recently searched fares when available, or browse featured sample routes.',
                'items' => $this->defaultFeatureCards(),
            ],
            self::POPULAR_ROUTES => [
                'enabled' => true,
                'title' => 'Popular corridors',
                'subtitle' => 'Quick links to search popular routes — final fare shown in PKR after you choose dates.',
                'items' => array_map(
                    fn (array $route, int $index): array => [
                        'item_key' => 'default-'.$index,
                        'is_enabled' => true,
                        'from' => $route['from'],
                        'to' => $route['to'],
                        'label' => $route['label'],
                        'button_url' => '',
                        'sort_order' => (int) ($route['sort_order'] ?? (($index + 1) * 10)),
                    ],
                    $this->defaultPopularRoutes(),
                    array_keys($this->defaultPopularRoutes()),
                ),
            ],
            self::WHY_CHOOSE_US => [
                'enabled' => true,
                'title' => 'Travel booking made simple with '.BrandDisplayResolver::displayName(),
                'subtitle' => 'Search fares, submit booking requests, and get support from a team that understands your travel needs.',
                'items' => [
                    ['item_key' => 'default-0', 'title' => 'Reliable booking support', 'text' => 'Get help from search to confirmation with clear guidance for fares, payments, and ticketing.', 'icon' => 'shield', 'icon_class' => 'fa-shield', 'is_enabled' => true, 'sort_order' => 10],
                    ['item_key' => 'default-1', 'title' => 'Clear fare details', 'text' => 'Review routes, baggage, refund rules, and PKR pricing before sending a booking request.', 'icon' => 'tags', 'icon_class' => 'fa-tags', 'is_enabled' => true, 'sort_order' => 20],
                    ['item_key' => 'default-2', 'title' => 'Fast booking updates', 'text' => 'Submit passenger details, track your request, and receive updates as your booking moves forward.', 'icon' => 'bolt', 'icon_class' => 'fa-bolt', 'is_enabled' => true, 'sort_order' => 30],
                    ['item_key' => 'default-3', 'title' => 'Built for travelers and agents', 'text' => 'Direct customers and partner agents can manage bookings through dedicated, secure portals.', 'icon' => 'users', 'icon_class' => 'fa-users', 'is_enabled' => true, 'sort_order' => 40],
                ],
            ],
            default => [
                'enabled' => true,
                'title' => '',
                'subtitle' => '',
                'items' => [],
            ],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function defaultFeatureCards(): array
    {
        $samples = [
            ['from' => 'LHE', 'to' => 'DXB', 'depart' => now()->addDays(14)->toDateString(), 'price' => 174135, 'sort_order' => 10],
            ['from' => 'KHI', 'to' => 'JED', 'depart' => now()->addDays(18)->toDateString(), 'price' => 188900, 'sort_order' => 20],
            ['from' => 'ISB', 'to' => 'IST', 'depart' => now()->addDays(22)->toDateString(), 'price' => 214500, 'sort_order' => 30],
        ];
        $offers = array_values((array) config('ota-flights.offers', []));

        return array_map(function (array $sample, int $index) use ($offers): array {
            $offer = is_array($offers[$index] ?? null) ? $offers[$index] : [];

            return array_merge($sample, [
                'item_key' => 'default-'.$index,
                'is_enabled' => true,
                'airline' => (string) ($offer['airline_name'] ?? 'Sample Airline'),
                'airline_code' => (string) ($offer['airline_code'] ?? ''),
                'baggage' => (string) ($offer['baggage'] ?? '20 kg checked + 7 kg cabin'),
                'refundable' => (bool) ($offer['refundable'] ?? false),
                'badge' => (bool) ($offer['refundable'] ?? false) ? 'Refundable' : 'Non-refundable',
                'button_label' => 'View fares',
                'button_url' => '',
                'currency' => 'PKR',
            ]);
        }, $samples, array_keys($samples));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function defaultPopularRoutes(): array
    {
        $routes = (array) config('ota-routes.popular', []);
        $items = [];
        foreach (array_values($routes) as $index => $route) {
            if (! is_array($route)) {
                continue;
            }
            $items[] = [
                'from' => (string) ($route['from'] ?? 'LHE'),
                'to' => (string) ($route['to'] ?? 'DXB'),
                'label' => (string) ($route['label'] ?? ''),
                'sort_order' => ($index + 1) * 10,
            ];
        }

        return $items;
    }

    protected function contentItemsKey(string $sectionKey): string
    {
        return match ($sectionKey) {
            self::TRUST_METRICS => 'metrics',
            self::FEATURE_CARDS => 'cards',
            self::POPULAR_ROUTES => 'routes',
            self::WHY_CHOOSE_US => 'bullets',
            default => 'items',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $defaults
     * @param  list<array<string, mixed>>  $stored
     * @return list<array<string, mixed>>
     */
    protected function mergeItems(array $defaults, array $stored, string $sectionKey): array
    {
        if ($stored === []) {
            return $this->filterAndSortItems($defaults);
        }

        $defaultsByKey = [];
        foreach ($defaults as $index => $defaultRow) {
            $defaultsByKey[(string) ($defaultRow['item_key'] ?? 'default-'.$index)] = $defaultRow;
        }

        $merged = [];
        foreach (array_values($stored) as $index => $storedRow) {
            if (! is_array($storedRow)) {
                continue;
            }
            $itemKey = (string) ($storedRow['item_key'] ?? 'default-'.$index);
            $defaultRow = $defaultsByKey[$itemKey] ?? [];
            $merged[] = $this->mergeItemRow($defaultRow, $storedRow, $sectionKey, $index);
        }

        return $this->filterAndSortItems($merged);
    }

    /**
     * @param  array<string, mixed>  $defaultRow
     * @param  array<string, mixed>  $storedRow
     * @return array<string, mixed>
     */
    protected function mergeItemRow(array $defaultRow, array $storedRow, string $sectionKey, int $index): array
    {
        $row = array_merge($defaultRow, $storedRow);
        $row['item_key'] = (string) ($row['item_key'] ?? 'default-'.$index);
        $row['is_enabled'] = filter_var($row['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $row['sort_order'] = (int) ($row['sort_order'] ?? (($index + 1) * 10));

        if ($sectionKey === self::TRUST_METRICS || $sectionKey === self::WHY_CHOOSE_US) {
            $icon = (string) ($row['icon'] ?? 'check-circle');
            $row['icon'] = array_key_exists($icon, self::ICON_CLASSES) ? $icon : 'check-circle';
            $row['icon_class'] = $this->iconClass($row['icon']);
        }

        if ($sectionKey === self::FEATURE_CARDS) {
            $row['from'] = strtoupper(substr((string) ($row['from'] ?? 'LHE'), 0, 3));
            $row['to'] = strtoupper(substr((string) ($row['to'] ?? 'DXB'), 0, 3));
            $row['price'] = (float) ($row['price'] ?? 0);
            $row['refundable'] = filter_var($row['refundable'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $row['button_label'] = (string) ($row['button_label'] ?? 'View fares');
            $row['button_url'] = $this->resolveButtonUrlOrDefault(
                isset($row['button_url']) ? (string) $row['button_url'] : null,
                $row,
            );
            $row['badge'] = (string) ($row['badge'] ?? ($row['refundable'] ? 'Refundable' : 'Non-refundable'));
        }

        if ($sectionKey === self::POPULAR_ROUTES) {
            $row['from'] = strtoupper(substr((string) ($row['from'] ?? 'LHE'), 0, 3));
            $row['to'] = strtoupper(substr((string) ($row['to'] ?? 'DXB'), 0, 3));
            $row['label'] = (string) ($row['label'] ?? $row['from'].' → '.$row['to']);
            $row['button_url'] = $this->resolveButtonUrlOrDefault(
                isset($row['button_url']) ? (string) $row['button_url'] : null,
                [
                    'from' => $row['from'],
                    'to' => $row['to'],
                    'depart' => now()->addDays(14)->toDateString(),
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            );
        }

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function filterAndSortItems(array $items): array
    {
        $enabled = array_values(array_filter(
            $items,
            fn (array $item): bool => filter_var($item['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ));

        return $this->sortItems($enabled);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function sortItems(array $items): array
    {
        usort($items, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        return array_values($items);
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>
     */
    protected function normalizeTrustMetrics(array $items): array
    {
        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $icon = strtolower((string) ($item['icon'] ?? 'check-circle'));
            if (! array_key_exists($icon, self::ICON_CLASSES)) {
                throw ValidationException::withMessages([
                    "items.{$index}.icon" => 'Invalid icon key.',
                ]);
            }
            $normalized[] = [
                'item_key' => (string) ($item['item_key'] ?? 'default-'.$index),
                'value' => (string) ($item['value'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'icon' => $icon,
                'is_enabled' => isset($item['is_enabled']) && filter_var($item['is_enabled'], FILTER_VALIDATE_BOOLEAN),
                'sort_order' => (int) ($item['sort_order'] ?? (($index + 1) * 10)),
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>
     */
    protected function normalizeFeatureCards(array $items): array
    {
        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $row = [
                'item_key' => (string) ($item['item_key'] ?? 'default-'.$index),
                'from' => strtoupper(substr((string) ($item['from'] ?? ''), 0, 3)),
                'to' => strtoupper(substr((string) ($item['to'] ?? ''), 0, 3)),
                'depart' => (string) ($item['depart'] ?? now()->addDays(14)->toDateString()),
                'airline' => (string) ($item['airline'] ?? ''),
                'airline_code' => (string) ($item['airline_code'] ?? ''),
                'baggage' => (string) ($item['baggage'] ?? ''),
                'badge' => (string) ($item['badge'] ?? ''),
                'price' => (float) ($item['price'] ?? 0),
                'currency' => 'PKR',
                'refundable' => isset($item['refundable']) && filter_var($item['refundable'], FILTER_VALIDATE_BOOLEAN),
                'button_label' => (string) ($item['button_label'] ?? 'View fares'),
                'is_enabled' => isset($item['is_enabled']) && filter_var($item['is_enabled'], FILTER_VALIDATE_BOOLEAN),
                'sort_order' => (int) ($item['sort_order'] ?? (($index + 1) * 10)),
            ];
            $row['button_url'] = $this->sanitizeButtonUrl(
                isset($item['button_url']) ? (string) $item['button_url'] : null,
                [
                    'from' => $row['from'],
                    'to' => $row['to'],
                    'depart' => $row['depart'],
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            );
            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>
     */
    protected function normalizePopularRoutes(array $items): array
    {
        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $from = strtoupper(substr((string) ($item['from'] ?? ''), 0, 3));
            $to = strtoupper(substr((string) ($item['to'] ?? ''), 0, 3));
            $row = [
                'item_key' => (string) ($item['item_key'] ?? 'default-'.$index),
                'from' => $from,
                'to' => $to,
                'label' => (string) ($item['label'] ?? $from.' → '.$to),
                'is_enabled' => isset($item['is_enabled']) && filter_var($item['is_enabled'], FILTER_VALIDATE_BOOLEAN),
                'sort_order' => (int) ($item['sort_order'] ?? (($index + 1) * 10)),
            ];
            $row['button_url'] = $this->sanitizeButtonUrl(
                isset($item['button_url']) ? (string) $item['button_url'] : null,
                [
                    'from' => $from,
                    'to' => $to,
                    'depart' => now()->addDays(14)->toDateString(),
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            );
            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>
     */
    protected function normalizeWhyBullets(array $items): array
    {
        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $icon = strtolower((string) ($item['icon'] ?? 'shield'));
            if (! array_key_exists($icon, self::ICON_CLASSES)) {
                throw ValidationException::withMessages([
                    "items.{$index}.icon" => 'Invalid icon key.',
                ]);
            }
            $normalized[] = [
                'item_key' => (string) ($item['item_key'] ?? 'default-'.$index),
                'title' => (string) ($item['title'] ?? ''),
                'text' => (string) ($item['text'] ?? ''),
                'icon' => $icon,
                'is_enabled' => isset($item['is_enabled']) && filter_var($item['is_enabled'], FILTER_VALIDATE_BOOLEAN),
                'sort_order' => (int) ($item['sort_order'] ?? (($index + 1) * 10)),
            ];
        }

        return $normalized;
    }

    protected function textOrDefault(?string $value, string $default): string
    {
        if ($value === null) {
            return $default;
        }

        $decoded = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(strip_tags($decoded));
    }

    /**
     * @param  array<string, mixed>  $brandFallback
     * @return array<string, mixed>
     */
    protected function defaultHeroPresentation(array $brandFallback, ?object $agencySettings): array
    {
        return [
            'enabled' => true,
            'title' => (string) ($brandFallback['homepage_headline'] ?? 'Book Flights With Confidence'),
            'subtitle' => (string) ($brandFallback['homepage_subheadline'] ?? ''),
            'badge' => BrandDisplayResolver::displayName($agencySettings instanceof AgencySetting ? $agencySettings : null)
                ?: (string) ($brandFallback['name'] ?? BrandDisplayResolver::displayName()),
            'background_url' => $this->resolveHeroBackgroundUrl(null, $agencySettings),
        ];
    }

    protected function resolveHeroBackgroundUrl(?AgencyHomepageSection $section, ?object $agencySettings): ?string
    {
        if ($section?->image_path) {
            return asset('storage/'.$section->image_path);
        }

        if ($agencySettings?->hero_image_path ?? null) {
            return asset('storage/'.$agencySettings->hero_image_path);
        }

        return null;
    }

    protected function resolveHeroCtaUrl(string $stored, string $default): string
    {
        $url = trim($stored);

        return $this->resolveButtonUrlOrDefault($url !== '' ? $url : $default, []);
    }
}
