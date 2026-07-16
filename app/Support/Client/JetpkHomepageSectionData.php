<?php

namespace App\Support\Client;

use App\Services\Client\ClientPageContentResolver;
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
     * @return array<string, mixed>
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

        return [
            ['variant' => 'g-jeddah', 'badge' => 'Featured', 'gold' => true, 'title' => 'Group · Jeddah', 'meta' => '10+ travellers · coordinated seats', 'price' => 285000, 'image' => null, 'link' => ''],
            ['variant' => 'g-dubai', 'badge' => 'Group fares', 'gold' => false, 'title' => 'Corporate · Dubai', 'meta' => '10+ travellers · locked group rate', 'price' => 96500, 'image' => null, 'link' => ''],
            ['variant' => 'g-uk', 'badge' => 'Family', 'gold' => false, 'title' => 'Family · London', 'meta' => 'Flexible dates · seats together', 'price' => 198000, 'image' => null, 'link' => ''],
        ];
    }

    /** @deprecated Use routesForDisplay() */
    public function routesWithFallback(): array
    {
        return $this->routesForDisplay();
    }

    /** @deprecated Use destinationsForDisplay() */
    public function destinationsWithFallback(): array
    {
        return $this->destinationsForDisplay();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function authoritativeTrustCardDefaults(): array
    {
        return [
            ['icon' => 'check-square', 'title' => 'Transparent PKR pricing', 'text' => 'No FX shock between search and checkout.', 'enabled' => '1'],
            ['icon' => 'check-square', 'title' => 'Licensed operations', 'text' => 'IATA accredited and PCAA licensed.', 'enabled' => '1'],
            ['icon' => 'check-square', 'title' => 'Human support', 'text' => 'Pakistan-based desk in Urdu and English.', 'enabled' => '1'],
        ];
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

        return [
            ['title' => 'Transparent PKR pricing', 'text' => 'No FX shock between search and checkout.'],
            ['title' => 'Licensed operations', 'text' => 'IATA accredited and PCAA licensed.'],
            ['title' => 'Human support', 'text' => 'Pakistan-based desk in Urdu and English.'],
        ];
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

        return [
            ['value' => '400+', 'label' => 'Airlines'],
            ['value' => 'Best', 'label' => 'PKR fares'],
            ['value' => 'Instant', 'label' => 'e-ticket'],
            ['value' => 'IATA', 'label' => 'accredited'],
            ['value' => 'PCAA', 'label' => 'licensed'],
        ];
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
        $assetKey = trim((string) ($item['image_asset_key'] ?? ''));
        if ($assetKey !== '') {
            $url = $this->assetUrl($assetKey);
            if ($url !== null) {
                return $url;
            }
        }

        $legacy = $this->assetUrl('destination_'.($index + 1));
        if ($legacy !== null) {
            return $legacy;
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
        return [
            ['id' => 'default-khi-dxb', 'from' => 'KHI', 'to' => 'DXB', 'enabled' => '1', 'sort_order' => 0, 'manual_fallback_price' => 42500, 'dynamic_fare_enabled' => '1'],
            ['id' => 'default-lhe-jed', 'from' => 'LHE', 'to' => 'JED', 'enabled' => '1', 'sort_order' => 1, 'manual_fallback_price' => 68900, 'dynamic_fare_enabled' => '1'],
            ['id' => 'default-isb-lhr', 'from' => 'ISB', 'to' => 'LHR', 'enabled' => '1', 'sort_order' => 2, 'manual_fallback_price' => 198000, 'dynamic_fare_enabled' => '1'],
            ['id' => 'default-khi-ruh', 'from' => 'KHI', 'to' => 'RUH', 'enabled' => '1', 'sort_order' => 3, 'manual_fallback_price' => 72000, 'dynamic_fare_enabled' => '1'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultDestinationItems(): array
    {
        return [
            ['id' => 'default-dxb', 'code' => 'DXB', 'title' => 'Dubai', 'text' => 'Daily departures', 'enabled' => '1', 'sort_order' => 0, 'manual_fallback_price' => 42500],
            ['id' => 'default-jed', 'code' => 'JED', 'title' => 'Jeddah', 'text' => 'Umrah & Hajj routes', 'enabled' => '1', 'sort_order' => 1, 'manual_fallback_price' => 68900],
            ['id' => 'default-lhr', 'code' => 'LHR', 'title' => 'London', 'text' => 'UK family travel', 'enabled' => '1', 'sort_order' => 2, 'manual_fallback_price' => 198000],
            ['id' => 'default-ist', 'code' => 'IST', 'title' => 'Istanbul', 'text' => 'Europe connections', 'enabled' => '1', 'sort_order' => 3, 'manual_fallback_price' => 85000],
        ];
    }
}
