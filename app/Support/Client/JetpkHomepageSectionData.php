<?php

namespace App\Support\Client;

use App\Services\Client\ClientPageContentResolver;
use App\Services\Homepage\JetpkHomepageAssetService;
use App\Support\Client\Homepage\JetpkHomepageHeroSizing;
use Illuminate\Support\Str;

/**
 * Resolves JetPK homepage section content with presence-aware defaults and fare/image normalization.
 */
final class JetpkHomepageSectionData
{
    public function __construct(
        private readonly ClientPageContentResolver $resolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [];
    }

    public function field(string $key, mixed $defaultWhenAbsent = ''): mixed
    {
        return $this->resolver->section(ClientPageKeys::HOME, $key, $defaultWhenAbsent, true);
    }

    public function isEnabled(string $sectionKey, bool $default = true): bool
    {
        $value = $this->field($sectionKey.'.enabled', $default ? '1' : '0');

        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }

    public function assetUrl(string $assetKey, ?string $default = null): ?string
    {
        return $this->resolver->assetUrl(ClientPageKeys::HOME, $assetKey, $default);
    }

    /**
     * @return array<string, string>
     */
    public function heroLayoutCssVariables(): array
    {
        $defaults = $this->defaults();
        $hero = $this->field('hero', data_get($defaults, 'hero', []));
        if (! is_array($hero)) {
            $hero = [];
        }

        return JetpkHomepageHeroSizing::cssVariablesFromHero($hero);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function routesForDisplay(): array
    {
        $items = $this->field('routes.items', null);
        if (! is_array($items) || $items === []) {
            $items = $this->defaultRouteItems();
        }

        $fareCache = $this->fareCacheRoutes();
        $routes = [];

        foreach ($this->sortedEnabledItems($items) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $from = strtoupper(trim((string) ($item['from'] ?? '')));
            $to = strtoupper(trim((string) ($item['to'] ?? '')));
            if ($from === '' || $to === '') {
                continue;
            }

            $routeId = (string) ($item['id'] ?? '');
            $fare = JetpkHomepageFareDisplay::resolve($item, $fareCache[$routeId] ?? null);
            $priceLabel = $fare['label'] ?? JetpkHomepageFareDisplay::neutralAvailabilityLabel();

            $routes[] = array_merge($item, [
                'from' => $from,
                'to' => $to,
                'price_label' => $priceLabel,
                'price' => $fare['label'] ?? '',
                'airlines' => $fare['label'] ?? JetpkHomepageFareDisplay::neutralAvailabilityLabel(),
                'fare_source' => $fare['source'] ?? 'none',
                'search_url' => $this->routeSearchUrl($item),
            ]);
        }

        return $routes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function destinationsForDisplay(): array
    {
        $items = $this->field('destinations.items', null);
        if (! is_array($items) || $items === []) {
            $items = $this->defaultDestinationItems();
        }

        $destinations = [];
        $index = 0;

        foreach ($this->sortedEnabledItems($items) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $code = strtoupper(trim((string) ($item['code'] ?? '')));
            $title = trim((string) ($item['title'] ?? ''));
            if ($code === '' && $title === '') {
                continue;
            }

            $fare = JetpkHomepageFareDisplay::resolve($item, null);
            $image = $this->destinationImageUrl($item, $index);
            $link = trim((string) ($item['link'] ?? $item['cta_url'] ?? ''));

            $destinations[] = array_merge($item, [
                'code' => $code,
                'title' => $title !== '' ? $title : $code,
                'image' => $image,
                'price' => $fare !== null ? (int) round($fare['amount']) : null,
                'price_label' => $fare['label'] ?? JetpkHomepageFareDisplay::neutralAvailabilityLabel(),
                'link' => $link,
                'href' => $link !== '' ? $link : null,
            ]);

            $index++;
        }

        return $destinations;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function featuredDealsForDisplay(): array
    {
        $items = $this->field('featured_deals.items', null);
        if (is_array($items) && $items !== []) {
            $deals = [];
            foreach ($this->sortedEnabledItems($items) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $from = strtoupper(trim((string) ($item['from'] ?? '')));
                $to = strtoupper(trim((string) ($item['to'] ?? '')));
                if ($from === '' && $to === '') {
                    continue;
                }
                $deals[] = [
                    'airline' => trim((string) ($item['airline'] ?? '')),
                    'from' => $from,
                    'to' => $to,
                    'depart' => trim((string) ($item['depart'] ?? '')),
                    'arrive' => trim((string) ($item['arrive'] ?? '')),
                    'dur' => trim((string) ($item['dur'] ?? '')),
                    'stops' => (int) ($item['stops'] ?? 0),
                    'price' => (int) ($item['price'] ?? 0),
                ];
            }

            if ($deals !== []) {
                return $deals;
            }
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function supportCtaForDisplay(): array
    {
        $defaults = $this->defaults();
        $raw = $this->field('support_cta', data_get($defaults, 'support_cta', []));

        return is_array($raw) ? $raw : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groupCardsWithFallback(): array
    {
        $items = $this->field('group_cards.items', null);
        if (is_array($items) && $items !== []) {
            return array_values(array_filter($items, static fn ($item) => is_array($item) && ($item['enabled'] ?? '1') !== '0'));
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function authoritativeTrustCardDefaults(): array
    {
        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function trustCardsWithFallback(): array
    {
        $items = $this->field('trust.cards', null);
        if (is_array($items) && $items !== []) {
            return array_values($items);
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function featureBoardWithFallback(): array
    {
        $items = $this->field('feature_board.items', null);
        if (is_array($items) && $items !== []) {
            return array_values($items);
        }

        return [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fareCacheRoutes(): array
    {
        $cache = $this->field('_fare_cache.routes', []);
        if (! is_array($cache)) {
            return [];
        }

        return $cache;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function sortedEnabledItems(array $items): array
    {
        $filtered = array_values(array_filter($items, static fn ($item) => is_array($item) && ($item['enabled'] ?? '1') !== '0'));
        usort($filtered, static fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function destinationImageUrl(array $item, int $index): string
    {
        $candidates = [];

        $assetKey = trim((string) ($item['image_asset_key'] ?? ''));
        if ($assetKey !== '') {
            $candidates[] = $assetKey;
        }

        $itemId = trim((string) ($item['id'] ?? ''));
        if ($itemId !== '') {
            $candidates[] = JetpkHomepageAssetService::destinationAssetKey($itemId);
            $candidates[] = 'destination_'.$itemId;
        }

        $candidates[] = 'destination_'.($index + 1);

        foreach (array_unique($candidates) as $key) {
            $url = $this->assetUrl($key);
            if ($url !== null) {
                return $url;
            }
        }

        $fallback = (string) config('jetpk_homepage.destination_fallback_image', '');
        if ($fallback !== '' && is_file(public_path($fallback))) {
            return asset($fallback);
        }

        return asset('themes/frontend/jetpakistan/images/homepage-destination-fallback.svg');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function routeSearchUrl(array $item): string
    {
        $custom = trim((string) ($item['cta_url'] ?? ''));
        if ($custom !== '') {
            return str_starts_with($custom, 'http') ? $custom : client_url($custom);
        }

        $offset = max(1, (int) config('jetpk_homepage.route_date_offset_days', 7));
        $depart = now(config('app.timezone', 'Asia/Karachi'))->addDays($offset)->toDateString();
        $tripType = (string) ($item['trip_type'] ?? 'one_way');
        $params = [
            'from' => strtoupper((string) ($item['from'] ?? '')),
            'to' => strtoupper((string) ($item['to'] ?? '')),
            'depart' => $depart,
            'trip_type' => $tripType === 'return' ? 'return' : 'one_way',
            'cabin' => (string) ($item['cabin'] ?? 'economy'),
            'adults' => max(1, (int) ($item['adults'] ?? 1)),
            'children' => 0,
            'infants' => 0,
        ];

        if ($tripType === 'return') {
            $stay = max(1, (int) ($item['return_stay_days'] ?? config('jetpk_homepage.default_return_stay_days', 7)));
            $params['return'] = now(config('app.timezone', 'Asia/Karachi'))->addDays($offset + $stay)->toDateString();
        }

        return client_route('flights.results', $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultRouteItems(): array
    {
        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultDestinationItems(): array
    {
        return [];
    }
}
