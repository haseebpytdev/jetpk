<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientPage;
use App\Models\CmsPage;
use App\Services\Seo\SeoAuditService;
use App\Services\Seo\SeoManagementService;
use App\Services\Seo\SeoVerificationResolver;
use App\Support\Seo\SeoManagedPageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SeoManagementController extends Controller
{
    public function __construct(
        private readonly SeoManagementService $seo,
        private readonly SeoAuditService $audit,
        private readonly SeoVerificationResolver $verification,
    ) {}

    public function overview(): View
    {
        Gate::authorize('seo.manage');

        return view('dashboard.admin.seo.overview', [
            'stats' => $this->seo->overviewStats(),
            'pages' => $this->seo->listPages(),
        ]);
    }

    public function pagesIndex(): View
    {
        Gate::authorize('seo.manage');

        return view('dashboard.admin.seo.pages-index', [
            'pages' => $this->seo->listPages(),
        ]);
    }

    public function pagesEdit(string $sourceType, string $sourceId): View
    {
        Gate::authorize('seo.manage');
        $page = $this->seo->getPage($sourceType, $sourceId);

        return view('dashboard.admin.seo.pages-edit', [
            'page' => $page,
        ]);
    }

    public function pagesUpdate(Request $request, string $sourceType, string $sourceId): RedirectResponse
    {
        Gate::authorize('seo.manage');
        $payload = $this->validatedSeoPayload($request);

        if ($sourceType === SeoManagedPageCatalog::SOURCE_MANAGED) {
            $this->seo->saveManagedDraft($sourceId, $payload, auth()->id());
        } elseif ($sourceType === SeoManagedPageCatalog::SOURCE_CMS) {
            $cmsPage = CmsPage::query()->findOrFail((int) $sourceId);
            Gate::authorize('update', $cmsPage);
            $this->seo->updateCmsSeo($cmsPage, $payload, auth()->id());

            return redirect()
                ->route('admin.seo.pages.edit', compact('sourceType', 'sourceId'))
                ->with('status', 'CMS SEO updated.');
        } elseif ($sourceType === SeoManagedPageCatalog::SOURCE_CUSTOM) {
            $clientPage = ClientPage::query()->findOrFail((int) $sourceId);
            $this->seo->saveCustomDraft($clientPage, $payload, auth()->id());
        } else {
            abort(404);
        }

        return redirect()
            ->route('admin.seo.pages.edit', compact('sourceType', 'sourceId'))
            ->with('status', 'Draft saved. Publish to update the live site.');
    }

    public function pagesPublish(string $sourceType, string $sourceId): RedirectResponse
    {
        Gate::authorize('seo.manage');

        if ($sourceType === SeoManagedPageCatalog::SOURCE_MANAGED) {
            $this->seo->publishManaged($sourceId, auth()->id());
        } elseif ($sourceType === SeoManagedPageCatalog::SOURCE_CUSTOM) {
            $clientPage = ClientPage::query()->findOrFail((int) $sourceId);
            $this->seo->publishCustom($clientPage, auth()->id());
        } else {
            abort(404, 'CMS pages publish through CMS status, not draft/publish.');
        }

        return redirect()
            ->route('admin.seo.pages.edit', compact('sourceType', 'sourceId'))
            ->with('status', 'SEO published to the live site.');
    }

    public function globalSettings(): View
    {
        Gate::authorize('seo.manage');

        return view('dashboard.admin.seo.global', [
            'settings' => $this->seo->globalSettings(),
        ]);
    }

    public function globalUpdate(Request $request): RedirectResponse
    {
        Gate::authorize('seo.manage');
        $validated = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:500'],
            'title_suffix' => ['nullable', 'string', 'max:80'],
            'canonical_domain' => ['nullable', 'string', 'max:255'],
            'robots' => ['nullable', 'string', 'max:40'],
            'og_title' => ['nullable', 'string', 'max:180'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:255'],
        ]);

        $this->seo->saveGlobalDraft($validated, auth()->id());

        return redirect()->route('admin.seo.global')->with('status', 'Global SEO draft saved.');
    }

    public function globalPublish(): RedirectResponse
    {
        Gate::authorize('seo.manage');
        $this->seo->publishGlobal(auth()->id());

        return redirect()->route('admin.seo.global')->with('status', 'Global SEO published.');
    }

    public function social(): View
    {
        Gate::authorize('seo.manage');

        return view('dashboard.admin.seo.social', [
            'social' => $this->seo->socialSettings(),
            'global' => $this->seo->globalSettings(),
        ]);
    }

    public function socialUpdate(Request $request): RedirectResponse
    {
        Gate::authorize('seo.manage');
        $validated = $request->validate([
            'og_title' => ['nullable', 'string', 'max:180'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:255'],
        ]);

        $this->seo->saveGlobalDraft($validated, auth()->id());

        return redirect()->route('admin.seo.social')->with('status', 'Social defaults draft saved.');
    }

    public function schema(): View
    {
        Gate::authorize('seo.manage');

        return view('dashboard.admin.seo.schema', [
            'schema' => $this->seo->schemaBusinessData(),
        ]);
    }

    public function sitemap(): View
    {
        Gate::authorize('seo.manage');

        return view('dashboard.admin.seo.sitemap', [
            'meta' => $this->seo->sitemapMeta(),
            'entries' => $this->seo->sitemapEntries(),
            'protected' => SeoManagedPageCatalog::PROTECTED_ROUTES,
        ]);
    }

    public function verification(): View
    {
        Gate::authorize('seo.manage');

        return view('dashboard.admin.seo.verification', [
            'verification' => $this->verification->adminView(),
            'global' => $this->seo->globalSettings(),
        ]);
    }

    public function verificationUpdate(Request $request): RedirectResponse
    {
        Gate::authorize('seo.manage');
        $validated = $request->validate([
            'verification_google' => ['nullable', 'string', 'max:255'],
            'verification_bing' => ['nullable', 'string', 'max:255'],
        ]);

        $this->seo->saveGlobalDraft([
            'verification_google' => $validated['verification_google'] ?? '',
            'verification_bing' => $validated['verification_bing'] ?? '',
        ], auth()->id());

        return redirect()->route('admin.seo.verification')->with('status', 'Verification draft saved.');
    }

    public function verificationPublish(): RedirectResponse
    {
        Gate::authorize('seo.manage');
        $this->seo->publishGlobal(auth()->id());

        return redirect()->route('admin.seo.verification')->with('status', 'Verification settings published.');
    }

    public function audit(): View
    {
        Gate::authorize('seo.manage');

        return view('dashboard.admin.seo.audit', [
            'findings' => $this->audit->runAudit(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSeoPayload(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:500'],
            'canonical' => ['nullable', 'string', 'max:255'],
            'index' => ['nullable', 'boolean'],
            'follow' => ['nullable', 'boolean'],
            'og_title' => ['nullable', 'string', 'max:180'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:255'],
            'sitemap_eligible' => ['nullable', 'boolean'],
        ]);

        return [
            'title' => $validated['title'] ?? '',
            'description' => $validated['description'] ?? '',
            'canonical' => $validated['canonical'] ?? '',
            'index' => $request->boolean('index', true),
            'follow' => $request->boolean('follow', true),
            'og_title' => $validated['og_title'] ?? '',
            'og_description' => $validated['og_description'] ?? '',
            'og_image' => $validated['og_image'] ?? '',
            'sitemap_eligible' => $request->boolean('sitemap_eligible', true),
        ];
    }
}
