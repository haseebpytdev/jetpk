<?php

namespace App\Support\Client;

use App\Services\Client\ClientPageContentResolver;
use App\Services\Homepage\JetpkHomepageAssetService;
use App\Support\Client\Homepage\JetpkHomepageHeroSizing;
use App\Support\Media\PublicMediaUrl;
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
        return $this->resolver->defaultHomeContent();
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
            $cmsImage = PublicMediaUrl::normalize($this->routeImageUrl($item, $routeId));
            $image = $cmsImage ?? '';

            $routes[] = array_merge($item, [
                'from' => $from,
                'to' => $to,
                'price_label' => $priceLabel,
                'price' => $fare['label'] ?? '',
                'airlines' => $fare['label'] ?? JetpkHomepageFareDisplay::neutralAvailabilityLabel(),
                'fare_source' => $fare['source'] ?? 'none',
                'search_url' => $this->routeSearchUrl($item),
                'image' => $image !== '' ? $image : null,
                'image_alt' => trim((string) ($item['image_alt'] ?? $item['alt'] ?? "{$from} to {$to}")),
                'media_source' => $image !== '' ? 'cms' : 'none',
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
            $cmsImage = PublicMediaUrl::normalize($this->destinationCmsImageUrl($item, $index));
            $image = $cmsImage ?? '';
            $mediaSource = 'cms';
            if ($image === '') {
                $image = $this->destinationFallbackImageUrl();
                $mediaSource = 'fallback';
            }
            $link = trim((string) ($item['link'] ?? $item['cta_url'] ?? ''));
            $imageAlt = trim((string) ($item['image_alt'] ?? $item['alt'] ?? $title));

            $destinations[] = array_merge($item, [
                'code' => $code,
                'title' => $title !== '' ? $title : $code,
                'image' => $image,
                'image_alt' => $imageAlt,
                'media_source' => $mediaSource,
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
                $itemId = trim((string) ($item['id'] ?? ''));
                $image = PublicMediaUrl::normalize($this->featuredDealImageUrl($item, $itemId)) ?? '';
                $imageAlt = trim((string) ($item['image_alt'] ?? $item['alt'] ?? ''));
                if ($imageAlt === '') {
                    $imageAlt = trim((string) ($item['title'] ?? '')).($from !== '' && $to !== '' ? " {$from} to {$to}" : '');
                }

                $deals[] = [
                    'id' => $itemId !== '' ? $itemId : null,
                    'airline' => trim((string) ($item['airline'] ?? '')),
                    'from' => $from,
                    'to' => $to,
                    'depart' => trim((string) ($item['depart'] ?? '')),
                    'arrive' => trim((string) ($item['arrive'] ?? '')),
                    'dur' => trim((string) ($item['dur'] ?? '')),
                    'stops' => (int) ($item['stops'] ?? 0),
                    'price' => (int) ($item['price'] ?? 0),
                    'title' => trim((string) ($item['title'] ?? '')),
                    'badge' => trim((string) ($item['badge'] ?? '')),
                    'description' => trim((string) ($item['description'] ?? $item['text'] ?? '')),
                    'image' => $image !== '' ? $image : null,
                    'image_alt' => $imageAlt,
                    'image_asset_key' => trim((string) ($item['image_asset_key'] ?? '')),
                    'media_source' => $image !== '' ? 'cms' : 'none',
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
            return $this->sortedEnabledItems($items);
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
    private function featuredDealImageUrl(array $item, string $itemId): ?string
    {
        $candidates = [];

        $assetKey = trim((string) ($item['image_asset_key'] ?? ''));
        if ($assetKey !== '') {
            $candidates[] = $assetKey;
        }

        if ($itemId !== '') {
            $candidates[] = JetpkHomepageAssetService::featuredDealAssetKey($itemId);
        }

        foreach (array_unique($candidates) as $key) {
            $url = $this->assetUrl($key);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function routeImageUrl(array $item, string $routeId): ?string
    {
        $candidates = [];

        $assetKey = trim((string) ($item['image_asset_key'] ?? ''));
        if ($assetKey !== '') {
            $candidates[] = $assetKey;
        }

        if ($routeId !== '') {
            $candidates[] = JetpkHomepageAssetService::routeAssetKey($routeId);
        }

        foreach (array_unique($candidates) as $key) {
            $url = $this->assetUrl($key);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function destinationCmsImageUrl(array $item, int $index): ?string
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

        return null;
    }

    private function destinationFallbackImageUrl(): string
    {
        $fallback = (string) config('jetpk_homepage.destination_fallback_image', '');
        if ($fallback !== '' && is_file(public_path($fallback))) {
            return PublicMediaUrl::normalize(asset($fallback)) ?? '/'.ltrim($fallback, '/');
        }

        return '/themes/frontend/jetpakistan/images/homepage-destination-fallback.svg';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function destinationImageUrl(array $item, int $index): string
    {
        return $this->destinationCmsImageUrl($item, $index) ?? $this->destinationFallbackImageUrl();
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
