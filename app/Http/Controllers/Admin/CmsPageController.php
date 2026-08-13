<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCmsPageRequest;
use App\Http\Requests\Admin\UpdateCmsPageRequest;
use App\Http\Resources\Dashboard\DashboardCmsPageResource;
use App\Models\CmsPage;
use App\Services\Cms\CmsPageContentSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CmsPageController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected CmsPageContentSanitizer $contentSanitizer,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CmsPage::class);

        $query = CmsPage::query()->with(['creator', 'updater']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($q) use ($term): void {
                $q->where('title', 'like', $term)->orWhere('slug', 'like', $term);
            });
        }

        $pages = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();

        return view('dashboard.admin.cms-pages.index', [
            'pages' => $pages,
            'filters' => $request->only(['status', 'q']),
            'statuses' => [
                CmsPage::STATUS_DRAFT,
                CmsPage::STATUS_ACTIVE,
                CmsPage::STATUS_ARCHIVED,
            ],
            'footerGroups' => CmsPage::FOOTER_GROUPS,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', CmsPage::class);

        return view('dashboard.admin.cms-pages.create', [
            'cmsPage' => new CmsPage,
            'method' => 'POST',
            'action' => route('admin.cms-pages.store'),
            'statuses' => $this->statusOptions(),
            'robotsOptions' => $this->robotsOptions(),
            'footerGroups' => CmsPage::FOOTER_GROUPS,
        ]);
    }

    public function store(StoreCmsPageRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', CmsPage::class);

        $page = CmsPage::query()->create($this->payload($request, $request->user()?->id));

        if ($request->hasFile('featured_image')) {
            $page->update([
                'featured_image_path' => $this->storeFeaturedImage($request->file('featured_image'), $page),
            ]);
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'CMS page created.',
                'page' => DashboardCmsPageResource::detail($page->fresh()),
            ]);
        }

        return redirect()->route('admin.cms-pages.index')->with('status', 'cms-page-created');
    }

    public function edit(CmsPage $cmsPage): View
    {
        Gate::authorize('view', $cmsPage);
        $cmsPage->load(['creator', 'updater']);

        return view('dashboard.admin.cms-pages.edit', [
            'cmsPage' => $cmsPage,
            'method' => 'PATCH',
            'action' => route('admin.cms-pages.update', $cmsPage),
            'statuses' => $this->statusOptions(),
            'robotsOptions' => $this->robotsOptions(),
            'footerGroups' => CmsPage::FOOTER_GROUPS,
        ]);
    }

    public function update(UpdateCmsPageRequest $request, CmsPage $cmsPage): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $cmsPage);

        $payload = $this->payload($request, $request->user()?->id, $cmsPage);

        if ($request->hasFile('featured_image')) {
            $payload['featured_image_path'] = $this->storeFeaturedImage($request->file('featured_image'), $cmsPage);
        }

        $cmsPage->update($payload);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'CMS page updated.',
                'page' => DashboardCmsPageResource::detail($cmsPage->fresh()),
            ]);
        }

        return redirect()->route('admin.cms-pages.index')->with('status', 'cms-page-updated');
    }

    public function duplicate(CmsPage $cmsPage): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', CmsPage::class);

        $copy = $cmsPage->replicate(['published_at']);
        $copy->title = $cmsPage->title.' (copy)';
        $copy->slug = $this->uniqueCopySlug((string) $cmsPage->slug);
        $copy->status = CmsPage::STATUS_DRAFT;
        $copy->published_at = null;
        $copy->created_by = auth()->id();
        $copy->updated_by = auth()->id();
        $copy->save();

        if ($this->wantsBackOfficeJson(request())) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'CMS page duplicated as a draft.',
                'page' => DashboardCmsPageResource::detail($copy->fresh()),
            ]);
        }

        return redirect()->route('admin.cms-pages.index')->with('status', 'cms-page-duplicated');
    }

    public function archive(CmsPage $cmsPage): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $cmsPage);

        $cmsPage->update([
            'status' => CmsPage::STATUS_ARCHIVED,
            'updated_by' => auth()->id(),
        ]);

        if ($this->wantsBackOfficeJson(request())) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'CMS page archived.',
                'page' => DashboardCmsPageResource::detail($cmsPage->fresh()),
            ]);
        }

        return back()->with('status', 'cms-page-archived');
    }

    public function destroy(CmsPage $cmsPage): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $cmsPage);

        $cmsPage->delete();

        if ($this->wantsBackOfficeJson(request())) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'CMS page removed.',
            ]);
        }

        return redirect()->route('admin.cms-pages.index')->with('status', 'cms-page-deleted');
    }

    public function preview(Request $request, CmsPage $cmsPage): View
    {
        Gate::authorize('view', $cmsPage);

        $page = $this->applyDraftOverlay($request, $cmsPage);

        return view('frontend.cms-pages.show', $this->viewData($page, true) + [
            'previewTheme' => $request->string('theme')->toString() === 'night' ? 'night' : 'day',
            'previewViewport' => $request->string('viewport')->toString(),
        ]);
    }

    public function previewDraft(Request $request, CmsPage $cmsPage): JsonResponse
    {
        Gate::authorize('view', $cmsPage);

        $overlay = [
            'title' => $request->filled('title') ? $request->string('title')->toString() : $cmsPage->title,
            'excerpt' => $request->exists('excerpt') ? $request->input('excerpt') : $cmsPage->excerpt,
            'content' => $request->exists('content')
                ? $this->contentSanitizer->sanitizeForStorage($request->input('content'))
                : $cmsPage->content,
        ];
        $request->session()->put($this->draftOverlayKey($cmsPage), $overlay);

        $theme = $request->string('theme')->toString() === 'night' ? 'night' : 'day';
        $viewport = $request->string('viewport')->toString();

        return $this->backOfficeJson([
            'ok' => true,
            'previewUrl' => route('admin.cms-pages.preview', $cmsPage).'?theme='.urlencode($theme).'&viewport='.urlencode($viewport).'&draft=1',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function applyDraftOverlay(Request $request, CmsPage $cmsPage): CmsPage
    {
        $overlay = $request->session()->get($this->draftOverlayKey($cmsPage));
        if (! is_array($overlay) || ! $request->boolean('draft')) {
            return $cmsPage;
        }

        $clone = $cmsPage->replicate();
        $clone->id = $cmsPage->id;
        $clone->exists = true;
        if (isset($overlay['title'])) {
            $clone->title = (string) $overlay['title'];
        }
        if (array_key_exists('excerpt', $overlay)) {
            $clone->excerpt = $overlay['excerpt'];
        }
        if (isset($overlay['content'])) {
            $clone->content = (string) $overlay['content'];
        }

        return $clone;
    }

    protected function draftOverlayKey(CmsPage $cmsPage): string
    {
        return 'cms_draft_overlay.'.$cmsPage->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Request $request, ?int $userId, ?CmsPage $existing = null): array
    {
        $status = $request->string('status')->toString();
        $publishedAt = $existing?->published_at;

        if ($status === CmsPage::STATUS_ACTIVE && $publishedAt === null) {
            $publishedAt = now();
        }

        return [
            'title' => $request->string('title')->toString(),
            'slug' => $request->string('slug')->toString(),
            'content' => $this->contentSanitizer->sanitizeForStorage($request->input('content')),
            'excerpt' => $request->filled('excerpt') ? $request->string('excerpt')->toString() : null,
            'seo_title' => $request->filled('seo_title') ? $request->string('seo_title')->toString() : null,
            'seo_description' => $request->filled('seo_description') ? $request->string('seo_description')->toString() : null,
            'canonical_url' => $request->filled('canonical_url') ? $request->string('canonical_url')->toString() : null,
            'robots' => $request->string('robots')->toString(),
            'status' => $status,
            'show_in_footer' => $request->boolean('show_in_footer'),
            'footer_group' => $request->filled('footer_group') ? $request->string('footer_group')->toString() : null,
            'footer_label' => $request->filled('footer_label') ? $request->string('footer_label')->toString() : null,
            'footer_sort_order' => (int) $request->input('footer_sort_order', 0),
            'open_in_new_tab' => $request->boolean('open_in_new_tab'),
            'published_at' => $publishedAt,
            'created_by' => $existing?->created_by ?? $userId,
            'updated_by' => $userId,
        ];
    }

    protected function uniqueCopySlug(string $base): string
    {
        $root = preg_replace('/-copy(?:-\d+)?$/', '', $base) ?: $base;
        $candidate = $root.'-copy';
        $i = 2;
        while (CmsPage::query()->withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $root.'-copy-'.$i;
            $i++;
        }

        return $candidate;
    }

    protected function storeFeaturedImage(UploadedFile $file, CmsPage $page): string
    {
        return $file->store('cms-pages/'.$page->id, 'public');
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(CmsPage $page, bool $isPreview): array
    {
        $metaTitle = $page->seo_title ?: $page->title;
        $metaDescription = $page->seo_description ?: ($page->excerpt ?? '');
        $canonicalUrl = $page->canonical_url ?: $page->route_url;

        return [
            'page' => $page,
            'bodyHtml' => $this->contentSanitizer->formatForPreviewDisplay($page->content),
            'isPreview' => $isPreview,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
            'robots' => $page->robots,
        ];
    }

    /**
     * @return list<string>
     */
    protected function statusOptions(): array
    {
        return [
            CmsPage::STATUS_DRAFT,
            CmsPage::STATUS_ACTIVE,
            CmsPage::STATUS_ARCHIVED,
        ];
    }

    /**
     * @return list<string>
     */
    protected function robotsOptions(): array
    {
        return [
            CmsPage::ROBOTS_INDEX,
            CmsPage::ROBOTS_NOINDEX,
        ];
    }
}
