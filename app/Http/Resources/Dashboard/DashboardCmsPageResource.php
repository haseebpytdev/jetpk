<?php

namespace App\Http\Resources\Dashboard;

use App\Models\CmsPage;
use App\Support\Dashboard\CmsContentSanitizer;

final class DashboardCmsPageResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(CmsPage $page, int $sectionCount = 1): array
    {
        $status = match ($page->status) {
            CmsPage::STATUS_ACTIVE => 'published',
            CmsPage::STATUS_DRAFT => 'draft',
            CmsPage::STATUS_ARCHIVED => 'archived',
            default => 'draft',
        };

        return [
            'id' => self::publicId($page),
            'internalId' => (string) $page->id,
            'title' => (string) $page->title,
            'slug' => (string) $page->slug,
            'pageType' => self::pageType($page),
            'status' => $status,
            'content' => (string) ($page->content ?? ''),
            'excerpt' => $page->excerpt,
            'seoTitle' => (string) ($page->seo_title ?: $page->title),
            'seoDescription' => (string) ($page->seo_description ?? ''),
            'canonicalUrl' => (string) ($page->canonical_url ?? ''),
            'robots' => (string) ($page->robots ?: 'index'),
            'showInFooter' => (bool) $page->show_in_footer,
            'footerGroup' => $page->footer_group,
            'footerLabel' => $page->footer_label,
            'validationState' => CmsContentSanitizer::isSafe($page->content) ? 'valid' : 'blocked',
            'themeMode' => 'automatic',
            'brand' => ['id' => 'jetpakistan', 'label' => 'JetPakistan'],
            'locale' => 'en-PK',
            'sectionCount' => $sectionCount,
            'previewMetadata' => [
                'routeUrl' => '/pages/'.$page->slug,
                'seoTitle' => (string) ($page->seo_title ?: $page->title),
                'robots' => (string) ($page->robots ?: 'index'),
                'previewOnly' => false,
            ],
            'reviewFlags' => [
                'needsReview' => $page->status === CmsPage::STATUS_DRAFT,
                'blockingIssues' => CmsContentSanitizer::isSafe($page->content) ? 0 : 1,
            ],
            'createdAt' => $page->created_at?->toIso8601String(),
            'updatedAt' => $page->updated_at?->toIso8601String(),
            'publishedAt' => $page->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(CmsPage $page): array
    {
        return self::fromModel($page);
    }

    public static function publicId(CmsPage $page): string
    {
        return 'JP-CMS-PG-'.str_pad((string) $page->id, 3, '0', STR_PAD_LEFT);
    }

    private static function pageType(CmsPage $page): string
    {
        $slug = (string) $page->slug;
        if (in_array($slug, ['privacy-policy', 'privacy'], true)) {
            return 'privacy';
        }
        if (str_contains($slug, 'terms')) {
            return 'terms';
        }
        if (str_contains($slug, 'refund')) {
            return 'refund';
        }
        if (str_contains($slug, 'faq')) {
            return 'faq';
        }
        if (str_contains($slug, 'contact')) {
            return 'contact';
        }
        if (str_contains($slug, 'about')) {
            return 'about';
        }

        return 'support';
    }
}
