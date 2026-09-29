<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\ClientPage;
use App\Models\CmsPage;
use App\Services\Seo\SeoAuditService;
use App\Services\Seo\SeoManagementService;
use App\Services\Seo\SeoVerificationResolver;
use App\Support\Seo\SeoManagedPageCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SeoManagementController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        private readonly SeoManagementService $seo,
        private readonly SeoAuditService $audit,
        private readonly SeoVerificationResolver $verification,
    ) {}

    public function overview(Request $request): View|JsonResponse
    {
        Gate::authorize('seo.manage');

        $stats = $this->seo->overviewStats();
        $pages = $this->seo->listPages();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'stats' => $stats,
                'pages' => $pages,
            ]);
        }

        return view('dashboard.admin.seo.overview', [
            'stats' => $stats,
            'pages' => $pages,
        ]);
    }

    public function pagesIndex(Request $request): View|JsonResponse
    {
        Gate::authorize('seo.manage');

        $pages = $this->seo->listPages();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'pages' => $pages,
            ]);
        }

        return view('dashboard.admin.seo.pages-index', [
            'pages' => $pages,
        ]);
    }

    public function pagesEdit(Request $request, string $sourceType, string $sourceId): View|JsonResponse
    {
        Gate::authorize('seo.manage');
        $page = $this->seo->getPage($sourceType, $sourceId);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'page' => $page,
            ]);
        }

        return view('dashboard.admin.seo.pages-edit', [
            'page' => $page,
        ]);
    }

    public function pagesUpdate(Request $request, string $sourceType, string $sourceId): RedirectResponse|JsonResponse
    {
        Gate::authorize('seo.manage');
        $payload = $this->validatedSeoPayload($request);

        if ($sourceType === SeoManagedPageCatalog::SOURCE_MANAGED) {
            $this->seo->saveManagedDraft($sourceId, $payload, auth()->id());
        } elseif ($sourceType === SeoManagedPageCatalog::SOURCE_CMS) {
            $cmsPage = CmsPage::query()->findOrFail((int) $sourceId);
            Gate::authorize('update', $cmsPage);
            $this->seo->updateCmsSeo($cmsPage, $payload, auth()->id());

            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJson([
                    'ok' => true,
                    'message' => 'CMS SEO updated.',
                    'page' => $this->seo->getPage($sourceType, $sourceId),
                ]);
            }

            return redirect()
                ->route('admin.seo.pages.edit', compact('sourceType', 'sourceId'))
                ->with('status', 'CMS SEO updated.');
        } elseif ($sourceType === SeoManagedPageCatalog::SOURCE_CUSTOM) {
            $clientPage = ClientPage::query()->findOrFail((int) $sourceId);
            $this->seo->saveCustomDraft($clientPage, $payload, auth()->id());
        } else {
            abort(404);
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Draft saved. Publish to update the live site.',
                'page' => $this->seo->getPage($sourceType, $sourceId),
            ]);
        }

        return redirect()
            ->route('admin.seo.pages.edit', compact('sourceType', 'sourceId'))
            ->with('status', 'Draft saved. Publish to update the live site.');
    }

    public function pagesPublish(Request $request, string $sourceType, string $sourceId): RedirectResponse|JsonResponse
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

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'SEO published to the live site.',
                'page' => $this->seo->getPage($sourceType, $sourceId),
            ]);
        }

        return redirect()
            ->route('admin.seo.pages.edit', compact('sourceType', 'sourceId'))
            ->with('status', 'SEO published to the live site.');
    }

    public function globalSettings(Request $request): View|JsonResponse
    {
        Gate::authorize('seo.manage');

        $settings = $this->seo->globalSettings();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'settings' => $settings,
            ]);
        }

        return view('dashboard.admin.seo.global', [
            'settings' => $settings,
        ]);
    }

    public function globalUpdate(Request $request): RedirectResponse|JsonResponse
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

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Global SEO draft saved.',
                'settings' => $this->seo->globalSettings(),
            ]);
        }

        return redirect()->route('admin.seo.global')->with('status', 'Global SEO draft saved.');
    }

    public function globalPublish(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('seo.manage');
        $this->seo->publishGlobal(auth()->id());

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Global SEO published.',
                'settings' => $this->seo->globalSettings(),
            ]);
        }

        return redirect()->route('admin.seo.global')->with('status', 'Global SEO published.');
    }

    public function social(Request $request): View|JsonResponse
    {
        Gate::authorize('seo.manage');

        $social = $this->seo->socialSettings();
        $global = $this->seo->globalSettings();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'social' => $social,
                'global' => $global,
            ]);
        }

        return view('dashboard.admin.seo.social', [
            'social' => $social,
            'global' => $global,
        ]);
    }

    public function socialUpdate(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('seo.manage');
        $validated = $request->validate([
            'og_title' => ['nullable', 'string', 'max:180'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:255'],
        ]);

        $this->seo->saveGlobalDraft($validated, auth()->id());

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Social defaults draft saved.',
                'social' => $this->seo->socialSettings(),
                'global' => $this->seo->globalSettings(),
            ]);
        }

        return redirect()->route('admin.seo.social')->with('status', 'Social defaults draft saved.');
    }

    public function schema(Request $request): View|JsonResponse
    {
        Gate::authorize('seo.manage');

        $schema = $this->seo->schemaBusinessData();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'schema' => $schema,
            ]);
        }

        return view('dashboard.admin.seo.schema', [
            'schema' => $schema,
        ]);
    }

    public function sitemap(Request $request): View|JsonResponse
    {
        Gate::authorize('seo.manage');

        $meta = $this->seo->sitemapMeta();
        $entries = $this->seo->sitemapEntries();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'meta' => $meta,
                'entries' => $entries,
                'protected' => SeoManagedPageCatalog::PROTECTED_ROUTES,
            ]);
        }

        return view('dashboard.admin.seo.sitemap', [
            'meta' => $meta,
            'entries' => $entries,
            'protected' => SeoManagedPageCatalog::PROTECTED_ROUTES,
        ]);
    }

    public function verification(Request $request): View|JsonResponse
    {
        Gate::authorize('seo.manage');

        $verification = $this->verification->adminView();
        $global = $this->seo->globalSettings();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'verification' => $verification,
                'global' => $global,
            ]);
        }

        return view('dashboard.admin.seo.verification', [
            'verification' => $verification,
            'global' => $global,
        ]);
    }

    public function verificationUpdate(Request $request): RedirectResponse|JsonResponse
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

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Verification draft saved.',
                'verification' => $this->verification->adminView(),
                'global' => $this->seo->globalSettings(),
            ]);
        }

        return redirect()->route('admin.seo.verification')->with('status', 'Verification draft saved.');
    }

    public function verificationPublish(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('seo.manage');
        $this->seo->publishGlobal(auth()->id());

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Verification settings published.',
                'verification' => $this->verification->adminView(),
                'global' => $this->seo->globalSettings(),
            ]);
        }

        return redirect()->route('admin.seo.verification')->with('status', 'Verification settings published.');
    }

    public function audit(Request $request): View|JsonResponse
    {
        Gate::authorize('seo.manage');

        $findings = $this->audit->runAudit();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'findings' => $findings,
            ]);
        }

        return view('dashboard.admin.seo.audit', [
            'findings' => $findings,
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
