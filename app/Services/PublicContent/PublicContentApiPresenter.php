<?php

namespace App\Services\PublicContent;

use App\Enums\SupportTicketCategory;
use App\Models\ClientPage;
use App\Models\CmsPage;
use App\Models\User;
use App\Services\Agencies\AboutUsContentPresenter;
use App\Services\Ai\AiAssistantEligibility;
use App\Services\Client\ClientGlobalContactResolver;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Client\ClientPageRenderer;
use App\Services\Client\ClientPageSeoResolver;
use App\Services\Seo\SeoVerificationResolver;
use App\Support\Seo\SeoSitemapEligibility;
use App\Support\Client\ClientManagedPageReservedSlugs;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientSafeHtmlSanitizer;
use App\Support\Client\ReservedPublicPath;
use Illuminate\Support\Facades\Auth;

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
        private readonly ClientPageContentResolver $contentResolver,
        private readonly SeoVerificationResolver $verificationResolver,
        private readonly SeoSitemapEligibility $sitemapEligibility,
        private readonly AiAssistantEligibility $aiEligibility,
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
            'body_html' => ClientSafeHtmlSanitizer::sanitize(
                $this->cmsContentPresenter->formatHtmlOverrideForDisplay($page->content),
            ),
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
        $user = Auth::user();
        $aiUser = $user instanceof User ? $user : null;
        // Guests must resolve via isEligible(null) so MODE_PUBLIC enables the FAB.
        $aiEnabled = $this->aiEligibility->isEligible($aiUser);

        return [
            'brand_name' => (string) config('ota-brand.name', 'JetPakistan'),
            'domain' => (string) config('client.canonical_client.domain', 'jetpakistan.pk'),
            'app_url' => rtrim((string) config('app.url'), '/'),
            'contact' => $contact,
            'legal_paths' => [
                'terms' => '/terms',
                'privacy' => '/privacy',
            ],
            'support_path' => '/support',
            'contact_path' => '/about-us',
            'booking_lookup_path' => '/lookup-booking',
            'groups_path' => '/groups/search',
            'social_links' => $this->normalizeSocialLinks($social),
            'default_seo' => array_intersect_key(
                $this->seoResolver->forPage(
                    ClientPageKeys::HOME,
                    'JetPakistan | Affordable Flights, Umrah Packages & Tours',
                    'Search and compare domestic and international flights from Pakistan, explore Umrah packages, and plan travel with JetPakistan.',
                ),
                array_flip(['title', 'description', 'robots']),
            ),
            'site_verification' => [
                'google' => $this->verificationResolver->googleToken(),
                'bing' => $this->verificationResolver->bingToken(),
            ],
            'ai_assistant_enabled' => $aiEnabled,
            'ai_assistant_mode' => $this->aiEligibility->mode(),
            'source' => 'laravel',
        ];
    }

    /**
     * Return canonical, indexable public URLs only. Redirect aliases such as
     * /contact and /flights deliberately stay out of the sitemap.
     *
     * @return list<array{path: string, lastmod?: string}>
     */
    public function sitemapRoutes(): array
    {
        $routes = [];

        foreach ([
            ['path' => '/'],
            ['path' => '/about-us'],
            ['path' => '/support'],
            ['path' => '/faq'],
            ['path' => '/terms'],
            ['path' => '/privacy'],
        ] as $route) {
            if ($this->sitemapEligibility->isManagedPathEligible($route['path'])) {
                $routes[] = $route;
            }
        }

        CmsPage::query()
            ->active()
            ->orderBy('slug')
            ->get(['slug', 'updated_at', 'robots', 'status'])
            ->each(function (CmsPage $page) use (&$routes): void {
                if (! $this->sitemapEligibility->isCmsPageEligible($page)) {
                    return;
                }

                $slug = trim((string) $page->slug, " /\t\n\r\0\x0B");
                if ($slug === '') {
                    return;
                }

                $routes[] = [
                    'path' => '/pages/'.$slug,
                    'lastmod' => $page->updated_at?->toAtomString(),
                ];
            });

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('client_pages')) {
                ClientPage::query()
                    ->where('enabled', true)
                    ->orderBy('slug')
                    ->get(['slug', 'updated_at'])
                    ->each(function (ClientPage $page) use (&$routes): void {
                        $slug = ClientManagedPageReservedSlugs::normalize((string) $page->slug);
                        if ($slug === '' || ReservedPublicPath::isReservedFirstSegment($slug)) {
                            return;
                        }

                        if (! $this->sitemapEligibility->isCustomPageEligible($page)) {
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
            }
        } catch (\Illuminate\Database\QueryException) {
            // Partial sqlite test databases may not include client_pages yet.
        }

        return collect($routes)
            ->unique('path')
            ->values()
            ->all();
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
