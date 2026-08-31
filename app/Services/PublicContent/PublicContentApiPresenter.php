<?php

namespace App\Services\PublicContent;

use App\Enums\SupportTicketCategory;
use App\Models\Agency;
use App\Models\ClientPage;
use App\Models\CmsPage;
use App\Services\Agencies\AboutUsContentPresenter;
use App\Services\Cms\CmsPageContentSanitizer;
use App\Services\Client\ClientGlobalContactResolver;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Client\ClientPageRenderer;
use App\Services\Client\ClientPageSeoResolver;
use App\Support\Client\ClientManagedPageReservedSlugs;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientSafeHtmlSanitizer;
use App\Support\Client\ReservedPublicPath;
use App\Support\Branding\JetpkCompanyBrandingResolver;
use App\Support\Media\PublicMediaUrl;

/**
 * Shapes Laravel-managed public content for the Next.js public frontend.
 */
final class PublicContentApiPresenter
{
    public function __construct(
        private readonly ClientPageRenderer $pageRenderer,
        private readonly ClientPageSeoResolver $seoResolver,
        private readonly ClientGlobalContactResolver $contactResolver,
        private readonly AboutUsContentPresenter $cmsContentPresenter,
        private readonly CmsPageContentSanitizer $cmsPageSanitizer,
        private readonly ClientPageContentResolver $contentResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function managedPage(string $pageKey): array
    {
        $content = $this->pageRenderer->viewModel($pageKey);
        $published = is_array($content['content'] ?? null) ? $content['content'] : [];

        return [
            'page_key' => $pageKey,
            'source' => $published === [] ? 'empty' : 'cms',
            'content' => $published,
            'seo' => $content['seo'] ?? $this->seoResolver->forPage($pageKey),
            'contact' => $content['contact'] ?? $this->contactResolver->contact(),
            'sections_order' => $content['sectionsOrder'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function siteContact(): array
    {
        return [
            'contact' => $this->contactResolver->contact(),
            'source' => 'laravel',
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function supportCategories(): array
    {
        return array_map(
            static fn (SupportTicketCategory $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ],
            SupportTicketCategory::cases(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function cmsPage(CmsPage $page): array
    {
        $metaTitle = $page->seo_title ?: $page->title;
        $metaDescription = $page->seo_description ?: ($page->excerpt ?? '');

        return [
            'slug' => $page->slug,
            'title' => $page->title,
            'subtitle' => $page->excerpt ?? '',
            'body_html' => $this->cmsPageSanitizer->formatForPublicDisplay($page->content),
            'seo' => [
                'title' => $metaTitle,
                'description' => $metaDescription,
                'canonical' => $page->canonical_url ?: $page->route_url,
                'robots' => $page->robots,
            ],
            'source' => 'cms',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customClientPage(ClientPage $page, string $pageKey): array
    {
        $vm = $this->pageRenderer->viewModel($pageKey);
        $published = is_array($vm['content'] ?? null) ? $vm['content'] : [];

        return [
            'slug' => $page->slug,
            'title' => (string) ($page->public_title ?? ''),
            'content' => $published,
            'seo' => $vm['seo'] ?? $this->seoResolver->forPage(
                $pageKey,
                (string) ($page->public_title ?? ''),
                '',
            ),
            'source' => $published === [] ? 'empty' : 'cms',
        ];
    }

    /**
     * @return list<string>
     */
    public function allowedManagedPageKeys(): array
    {
        return [
            ClientPageKeys::ABOUT,
            ClientPageKeys::SUPPORT,
            ClientPageKeys::FAQ,
            ClientPageKeys::TERMS,
            ClientPageKeys::PRIVACY,
            ClientPageKeys::GLOBAL,
            ClientPageKeys::GROUP_SEARCH,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicConfig(): array
    {
        $contact = $this->contactResolver->contact();
        $global = $this->contentFor(ClientPageKeys::GLOBAL);
        $social = is_array($global['social'] ?? null) ? $global['social'] : [];
        $branding = app(JetpkCompanyBrandingResolver::class);

        return [
            'brand_name' => (string) config('ota-brand.name', 'JetPakistan'),
            'domain' => (string) config('client.canonical_client.domain', 'jetpakistan.pk'),
            'app_url' => rtrim((string) config('app.url'), '/'),
            'logo_url' => PublicMediaUrl::normalize($branding->logoUrl()),
            'favicon_url' => PublicMediaUrl::normalize($branding->faviconUrl()),
            'header_logo_height' => $branding->headerLogoHeight(),
            'contact' => $contact,
            'legal_paths' => [
                'terms' => '/terms',
                'privacy' => '/privacy',
            ],
            'support_path' => '/support',
            'contact_path' => '/contact',
            'booking_lookup_path' => '/lookup-booking',
            'groups_path' => '/groups/search',
            'social_links' => $this->normalizeSocialLinks($social),
            'commerce_gates' => app(\App\Services\Commerce\CommerceCheckoutSettingsService::class)->gates(
                Agency::query()->where('slug', (string) config('ota.default_agency_slug', 'asif-travels'))->value('id')
            ),
            'ai_assistant_enabled' => (bool) config('ota.ai_assistant.enabled', false),
            'default_seo' => [
                'title' => 'JetPakistan',
                'description' => 'Book flights, hotels, and travel services with JetPakistan.',
                'robots' => 'index,follow',
            ],
            'source' => 'laravel',
        ];
    }

    /**
     * @return list<array{path: string, lastmod?: string}>
     */
    public function sitemapRoutes(): array
    {
        $routes = [
            ['path' => '/'],
            ['path' => '/about-us'],
            ['path' => '/contact'],
            ['path' => '/support'],
            ['path' => '/faq'],
            ['path' => '/terms'],
            ['path' => '/privacy'],
            ['path' => '/lookup-booking'],
            ['path' => '/groups/search'],
        ];

        CmsPage::query()
            ->active()
            ->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->each(function (CmsPage $page): void {
                $routes[] = [
                    'path' => '/pages/'.$page->slug,
                    'lastmod' => $page->updated_at?->toAtomString(),
                ];
            });

        ClientPage::query()
            ->where('enabled', true)
            ->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->each(function (ClientPage $page): void {
                $slug = ClientManagedPageReservedSlugs::normalize((string) $page->slug);
                if ($slug === '' || ReservedPublicPath::isReservedFirstSegment($slug)) {
                    return;
                }

                $pageKey = ClientPageKeys::customKey($slug);
                if ($this->contentResolver->contentFor($pageKey) === []) {
                    return;
                }

                $routes[] = [
                    'path' => '/'.$slug,
                    'lastmod' => $page->updated_at?->toAtomString(),
                ];
            });

        return $routes;
    }

    /**
     * @param  array<int|string, mixed>  $social
     * @return list<array{label: string, href: string}>
     */
    private function normalizeSocialLinks(array $social): array
    {
        $links = [];

        foreach ($social as $row) {
            if (! is_array($row)) {
                continue;
            }

            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $links[] = [
                'label' => trim((string) ($row['platform'] ?? 'Social')),
                'href' => $url,
            ];
        }

        return $links;
    }

    /**
     * @return array<string, mixed>
     */
    private function contentFor(string $pageKey): array
    {
        return $this->contentResolver->contentFor($pageKey);
    }
}
