<?php

namespace App\Services\PublicContent;

use App\Enums\SupportTicketCategory;
use App\Models\ClientPage;
use App\Models\CmsPage;
use App\Services\Agencies\AboutUsContentPresenter;
use App\Services\Client\ClientGlobalContactResolver;
use App\Services\Client\ClientPageRenderer;
use App\Services\Client\ClientPageSeoResolver;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientSafeHtmlSanitizer;

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
}
