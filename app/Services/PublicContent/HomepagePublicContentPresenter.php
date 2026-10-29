<?php

namespace App\Services\PublicContent;

use App\Models\ClientPageAsset;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Client\ClientPageSeoResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Homepage\JetpkHomepageAssetService;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\JetpkHomepageSectionData;
use App\Support\Homepage\JetpkHeroLcpPresenter;
use App\Support\Homepage\JetpkResponsiveMediaPresenter;

/**
 * Shapes published homepage CMS content for the Next.js public frontend.
 */
final class HomepagePublicContentPresenter
{
    private const CARD_SIZES = '(max-width: 640px) 100vw, (max-width: 900px) 50vw, (max-width: 1180px) 33vw, 25vw';

    private const SUPPORT_SIZES = '(max-width: 1024px) 100vw, 16rem';

    public function __construct(
        private readonly JetpkHomepageSectionData $homepage,
        private readonly ClientPageContentResolver $contentResolver,
        private readonly ClientPageSeoResolver $seoResolver,
        private readonly CurrentClientContext $clientContext,
        private readonly JetpkHeroLcpPresenter $heroLcpPresenter,
        private readonly JetpkResponsiveMediaPresenter $responsiveMediaPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(): array
    {
        $published = $this->contentResolver->contentFor(ClientPageKeys::HOME);
        $hasCms = $published !== [];

        return [
            'source' => $hasCms ? 'cms' : 'empty',
            'seo' => $this->seoResolver->forPage(
                ClientPageKeys::HOME,
                'JetPakistan | Affordable Flights, Umrah Packages & Tours',
                'Search and compare domestic and international flights from Pakistan, explore Umrah packages, and plan travel with JetPakistan.',
            ),
            'hero' => $this->presentHero($hasCms),
            'trust_chips' => $this->presentTrustChips($hasCms),
            'routes' => $this->presentSection('routes', fn (): array => $this->homepage->routesForDisplay()),
            'destinations' => $this->presentSection('destinations', fn (): array => $this->homepage->destinationsForDisplay()),
            'featured_deals' => $this->presentFeaturedDeals(),
            'why_book' => $this->presentWhyBook(),
            'support_cta' => $this->presentSupportCta(),
            'feature_board' => $this->presentFeatureBoard(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentHero(bool $hasCms): array
    {
        $hero = $this->homepage->field('hero', []);
        if (! is_array($hero)) {
            $hero = [];
        }

        $imageUrl = $this->homepage->assetUrl('hero_background');
        $alt = trim((string) ($hero['image_alt'] ?? 'JetPakistan flights'));
        $responsive = null;
        if ($imageUrl !== null && $imageUrl !== '') {
            $manifest = $this->manifestForAssetKey('hero_background', $imageUrl);
            $presented = $this->heroLcpPresenter->present($imageUrl, $manifest, $alt !== '' ? $alt : 'JetPakistan flights');
            if (is_array($presented)) {
                $responsive = [
                    'fallback_url' => $presented['fallback_url'],
                    'width' => $presented['width'],
                    'height' => $presented['height'],
                    'sources' => $presented['sources'],
                    'preloads' => $presented['preloads'],
                    'has_responsive_variants' => $presented['has_responsive_variants'],
                    'fingerprint' => $presented['manifest_fingerprint'] ?? null,
                ];
            }
        }

        return [
            'eyebrow' => $hasCms ? trim((string) ($hero['eyebrow'] ?? '')) : '',
            'headline' => $hasCms ? trim((string) ($hero['headline'] ?? '')) : '',
            'headline_highlight' => $hasCms ? trim((string) ($hero['headline_highlight'] ?? '')) : '',
            'subtitle' => $hasCms ? trim((string) ($hero['subtitle'] ?? '')) : '',
            'search_visible' => ($hero['search_visible'] ?? '1') !== '0',
            'image' => $imageUrl !== null && $imageUrl !== ''
                ? ['url' => $imageUrl, 'alt' => $alt !== '' ? $alt : 'JetPakistan flights']
                : null,
            'responsive' => $responsive,
        ];
    }

    /**
     * @return list<array{label: string}>
     */
    private function presentTrustChips(bool $hasCms): array
    {
        if (! $hasCms) {
            return [];
        }

        $chips = $this->homepage->field('trust_chips', []);
        if (! is_array($chips)) {
            return [];
        }

        $items = [];
        foreach ($chips as $chip) {
            if (! is_array($chip)) {
                continue;
            }
            $label = trim((string) ($chip['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $items[] = ['label' => $label];
        }

        return $items;
    }

    /**
     * @param  callable(): list<array<string, mixed>>  $itemsResolver
     * @return array<string, mixed>
     */
    private function presentSection(string $key, callable $itemsResolver): array
    {
        if (! $this->homepage->isEnabled($key)) {
            return ['enabled' => false, 'items' => []];
        }

        $items = $itemsResolver();
        if ($key === 'routes') {
            $items = array_map(fn (array $item): array => $this->attachCardResponsive($item, 'route'), $items);
        }
        if ($key === 'destinations') {
            $items = array_map(fn (array $item): array => $this->attachCardResponsive($item, 'destination'), $items);
        }

        return [
            'enabled' => $items !== [],
            'eyebrow' => trim((string) $this->homepage->field("{$key}.eyebrow", '')),
            'title' => trim((string) $this->homepage->field("{$key}.title", '')),
            'subtitle' => trim((string) $this->homepage->field("{$key}.subtitle", '')),
            'cta_text' => trim((string) $this->homepage->field("{$key}.cta_text", '')),
            'cta_url' => trim((string) $this->homepage->field("{$key}.cta_url", '')),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentFeaturedDeals(): array
    {
        if (! $this->homepage->isEnabled('featured_deals')) {
            return ['enabled' => false, 'items' => []];
        }

        $items = $this->homepage->featuredDealsForDisplay();

        return [
            'enabled' => $items !== [],
            'eyebrow' => trim((string) $this->homepage->field('featured_deals.eyebrow', '')),
            'title' => trim((string) $this->homepage->field('featured_deals.title', '')),
            'subtitle' => trim((string) $this->homepage->field('featured_deals.subtitle', '')),
            'cta_text' => trim((string) $this->homepage->field('featured_deals.cta_text', '')),
            'cta_url' => trim((string) $this->homepage->field('featured_deals.cta_url', '')),
            'items' => array_map(function (array $item): array {
                $id = trim((string) ($item['id'] ?? ''));
                if ($id === '') {
                    $id = md5(($item['from'] ?? '').($item['to'] ?? '').($item['airline'] ?? ''));
                }

                $mapped = [
                    'id' => $id,
                    'airline' => (string) ($item['airline'] ?? ''),
                    'from' => (string) ($item['from'] ?? ''),
                    'to' => (string) ($item['to'] ?? ''),
                    'depart' => (string) ($item['depart'] ?? ''),
                    'arrive' => (string) ($item['arrive'] ?? ''),
                    'duration' => (string) ($item['dur'] ?? ''),
                    'stops' => (int) ($item['stops'] ?? 0),
                    'price' => (int) ($item['price'] ?? 0),
                    'price_label' => ((int) ($item['price'] ?? 0)) > 0
                        ? 'PKR '.number_format((int) $item['price'])
                        : '',
                    'image' => isset($item['image']) && is_string($item['image']) && $item['image'] !== ''
                        ? $item['image']
                        : null,
                    'image_alt' => trim((string) ($item['image_alt'] ?? '')),
                ];

                return $this->attachCardResponsive($mapped, 'featured_deal');
            }, $items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentWhyBook(): array
    {
        if (! $this->homepage->isEnabled('why_book')) {
            return ['enabled' => false, 'cards' => []];
        }

        $cards = $this->homepage->field('why_book.cards', []);
        if (! is_array($cards)) {
            $cards = [];
        }

        $normalized = [];
        foreach ($cards as $index => $card) {
            if (! is_array($card) || ($card['enabled'] ?? '1') === '0') {
                continue;
            }
            $title = trim((string) ($card['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $normalized[] = [
                'id' => (string) ($card['id'] ?? "why-{$index}"),
                'num' => trim((string) ($card['num'] ?? '')),
                'title' => $title,
                'text' => trim((string) ($card['text'] ?? '')),
                'icon' => trim((string) ($card['icon'] ?? '')),
            ];
        }

        return [
            'enabled' => $normalized !== [],
            'eyebrow' => trim((string) $this->homepage->field('why_book.eyebrow', '')),
            'title' => trim((string) $this->homepage->field('why_book.title', '')),
            'subtitle' => trim((string) $this->homepage->field('why_book.subtitle', '')),
            'cards' => $normalized,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSupportCta(): array
    {
        if (! $this->homepage->isEnabled('support_cta')) {
            return ['enabled' => false];
        }

        $support = $this->homepage->supportCtaForDisplay();
        $title = trim((string) $this->homepage->field('support_cta.title', ''));
        $subtitle = trim((string) $this->homepage->field('support_cta.subtitle', ''));

        if ($title === '' && $subtitle === '') {
            return ['enabled' => false];
        }

        $phoneValue = trim((string) ($support['phone_value'] ?? ''));
        $callUrlRaw = trim((string) ($support['call_url'] ?? ''));
        $chatUrlRaw = trim((string) ($support['chat_url'] ?? ''));

        return [
            'enabled' => true,
            'eyebrow' => trim((string) $this->homepage->field('support_cta.eyebrow', '')),
            'title' => $title,
            'subtitle' => $subtitle,
            'call_enabled' => ($support['call_enabled'] ?? '1') === '1',
            'call_label' => trim((string) $this->homepage->field('support_cta.call_label', 'Call support')),
            'call_href' => $this->resolveActionHref($callUrlRaw, $phoneValue),
            'chat_enabled' => ($support['chat_enabled'] ?? '1') === '1',
            'chat_label' => trim((string) $this->homepage->field('support_cta.chat_label', 'Get support')),
            'chat_href' => $this->resolveActionHref($chatUrlRaw, ''),
            'image' => $this->homepage->assetUrl('support_cta_background'),
            'responsive' => $this->presentSupportResponsive(
                $this->homepage->assetUrl('support_cta_background'),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function attachCardResponsive(array $item, string $kind): array
    {
        $image = isset($item['image']) && is_string($item['image']) ? trim($item['image']) : '';
        if ($image === '' || ! str_contains($image, '/storage/')) {
            $item['responsive'] = null;

            return $item;
        }

        $assetKey = $this->guessCardAssetKey($item, $kind);
        $manifest = $assetKey !== null ? $this->manifestForAssetKey($assetKey, $image) : null;
        $item['responsive'] = $this->responsiveMediaPresenter->presentWidthSrcset(
            $image,
            $manifest,
            self::CARD_SIZES,
            640,
            480,
        );

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function guessCardAssetKey(array $item, string $kind): ?string
    {
        $id = trim((string) ($item['id'] ?? ''));
        if ($id === '') {
            return null;
        }

        return match ($kind) {
            'route' => JetpkHomepageAssetService::routeAssetKey($id),
            'destination' => JetpkHomepageAssetService::destinationAssetKey($id),
            'featured_deal' => JetpkHomepageAssetService::featuredDealAssetKey($id),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentSupportResponsive(?string $imageUrl): ?array
    {
        if ($imageUrl === null || trim($imageUrl) === '') {
            return null;
        }

        $manifest = $this->manifestForAssetKey('support_cta_background', $imageUrl);

        return $this->responsiveMediaPresenter->presentWidthSrcset(
            $imageUrl,
            $manifest,
            self::SUPPORT_SIZES,
            1280,
            720,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function manifestForAssetKey(string $assetKey, string $sourceUrl): ?array
    {
        $profile = $this->clientContext->get();
        $path = $this->storageRelativeFromUrl($sourceUrl);

        $query = ClientPageAsset::query()
            ->where('page_key', ClientPageKeys::HOME)
            ->where('asset_key', $assetKey);

        if ($profile !== null) {
            $query->where('client_profile_id', $profile->id);
        }

        if ($path !== null) {
            $query->where('path', $path);
        }

        $asset = $query->first();
        if ($asset === null) {
            return null;
        }

        $meta = is_array($asset->meta_json) ? $asset->meta_json : [];
        if ($assetKey === 'hero_background' && is_array($meta['hero_lcp'] ?? null)) {
            return $meta['hero_lcp'];
        }

        return is_array($meta['responsive_media'] ?? null) ? $meta['responsive_media'] : null;
    }

    private function storageRelativeFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = rawurldecode($path);
        if (! str_starts_with($path, '/storage/')) {
            return null;
        }

        return ltrim(substr($path, strlen('/storage/')), '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentFeatureBoard(): array
    {
        if (! $this->homepage->isEnabled('feature_board')) {
            return ['enabled' => false, 'items' => []];
        }

        $items = $this->homepage->featureBoardWithFallback();
        $normalized = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $normalized[] = [
                'id' => "stat-{$index}",
                'value' => trim((string) ($item['value'] ?? '')),
                'label' => $label,
            ];
        }

        return [
            'enabled' => $normalized !== [],
            'items' => $normalized,
        ];
    }

    private function resolveActionHref(string $rawUrl, string $phoneValue): ?string
    {
        if ($rawUrl !== '' && $rawUrl !== '#' && ! str_starts_with(strtolower($rawUrl), 'javascript:')) {
            return str_starts_with($rawUrl, 'http') ? $rawUrl : client_url($rawUrl);
        }

        if ($phoneValue !== '') {
            $tel = preg_replace('/\s+/', '', $phoneValue);

            return $tel !== '' ? 'tel:'.$tel : null;
        }

        return null;
    }
}
