<?php

namespace App\Http\Resources\Dashboard;

use App\Models\CmsPage;
use App\Support\Dashboard\CmsContentSanitizer;

final class DashboardCmsSectionResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromPage(CmsPage $page, int $order = 1): array
    {
        return [
            'id' => 'JP-CMS-SC-'.str_pad((string) $page->id, 3, '0', STR_PAD_LEFT).'-'.$order,
            'pageId' => DashboardCmsPageResource::publicId($page),
            'sectionType' => 'content.richText',
            'variant' => 'default',
            'ordering' => $order,
            'validationState' => CmsContentSanitizer::isSafe($page->content) ? 'valid' : 'blocked',
            'themeMode' => 'automatic',
            'structuredContent' => CmsContentSanitizer::structuredContent($page->content, $page->excerpt),
            'previewMetadata' => [
                'previewOnly' => true,
                'approvedComponentType' => 'content.richText',
                'approvedVariant' => 'default',
            ],
            'reviewFlags' => ['needsReview' => $page->status !== CmsPage::STATUS_ACTIVE],
            'updatedAt' => $page->updated_at?->toIso8601String(),
        ];
    }
}
