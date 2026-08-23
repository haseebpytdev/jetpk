<?php

namespace App\Services\PublicContent;

use App\Services\Client\ClientPageContentResolver;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\JetpkHomepageSectionData;
use App\Support\Media\PublicMediaUrl;

/**
 * Shapes published homepage CMS content for the Next.js public frontend.
 */
final class HomepagePublicContentPresenter
{
    public function __construct(
        private readonly JetpkHomepageSectionData $homepage,
        private readonly ClientPageContentResolver $contentResolver,
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
        $mobileImageUrl = $this->homepage->assetUrl('hero_background_mobile');
        $alt = trim((string) ($hero['image_alt'] ?? 'JetPakistan flights'));

        return [
            'eyebrow' => $hasCms ? trim((string) ($hero['eyebrow'] ?? '')) : '',
            'headline' => $hasCms ? trim((string) ($hero['headline'] ?? '')) : '',
            'headline_highlight' => $hasCms ? trim((string) ($hero['headline_highlight'] ?? '')) : '',
            'subtitle' => $hasCms ? trim((string) ($hero['subtitle'] ?? '')) : '',
            'search_visible' => ($hero['search_visible'] ?? '1') !== '0',
            'focal_point' => trim((string) ($hero['focal_point'] ?? 'center')) ?: 'center',
            'overlay_strength' => trim((string) ($hero['overlay_strength'] ?? 'medium')) ?: 'medium',
            'image' => $imageUrl !== null && $imageUrl !== ''
                ? ['url' => PublicMediaUrl::normalize($imageUrl), 'alt' => $alt]
                : null,
            'image_mobile' => $mobileImageUrl !== null && $mobileImageUrl !== ''
                ? ['url' => PublicMediaUrl::normalize($mobileImageUrl), 'alt' => $alt]
                : null,
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
            'items' => array_map(static function (array $item): array {
                return [
                    'id' => md5(($item['from'] ?? '').($item['to'] ?? '').($item['airline'] ?? '')),
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
                ];
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
            'image' => PublicMediaUrl::normalize($this->homepage->assetUrl('support_cta_background')),
        ];
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
        $rawUrl = trim($rawUrl);
        $lower = strtolower($rawUrl);

        if ($rawUrl !== '' && $rawUrl !== '#' && ! str_starts_with($lower, 'javascript:')) {
            if (str_starts_with($lower, 'tel:') || str_starts_with($lower, 'mailto:')) {
                return $rawUrl;
            }

            if (str_starts_with($rawUrl, '/')) {
                return $rawUrl;
            }

            if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://')) {
                $parts = parse_url($rawUrl);
                $host = strtolower((string) ($parts['host'] ?? ''));
                $isPrivateHost = $host === '127.0.0.1'
                    || $host === 'localhost'
                    || str_ends_with($host, '.local');

                if ($isPrivateHost) {
                    $path = (string) ($parts['path'] ?? '/');
                    $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
                    $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

                    // Preserve mis-joined scheme targets such as /tel:+92...
                    if (str_starts_with(strtolower($path), '/tel:') || str_starts_with(strtolower($path), '/mailto:')) {
                        return ltrim($path, '/').$query.$fragment;
                    }

                    return ($path === '' ? '/' : $path).$query.$fragment;
                }

                return $rawUrl;
            }

            // Relative non-root path: keep public-origin relative (do not absolute via APP_URL).
            return '/'.ltrim($rawUrl, '/');
        }

        if ($phoneValue !== '') {
            $tel = preg_replace('/\s+/', '', $phoneValue);

            return $tel !== '' ? 'tel:'.$tel : null;
        }

        return null;
    }
}
