<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Services\Agencies\AboutUsContentPresenter;
use Illuminate\View\View;

class CmsPageController extends Controller
{
    public function __construct(
        protected AboutUsContentPresenter $contentPresenter,
    ) {}

    public function show(string $slug): View
    {
        $page = CmsPage::query()->where('slug', $slug)->firstOrFail();

        if (! $page->isActive()) {
            abort(404);
        }

        return view('frontend.cms-pages.show', $this->viewData($page));
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(CmsPage $page, bool $isPreview = false): array
    {
        $metaTitle = $page->seo_title ?: $page->title;
        $metaDescription = $page->seo_description ?: ($page->excerpt ?? '');
        $canonicalUrl = $page->canonical_url ?: $page->route_url;

        return [
            'page' => $page,
            'bodyHtml' => $this->contentPresenter->formatHtmlOverrideForDisplay($page->content),
            'isPreview' => $isPreview,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
            'robots' => $page->robots,
        ];
    }
}
