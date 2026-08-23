<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClientPageSettingStatus;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\AgencyMedia;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\ClientThemePalette;
use App\Services\Branding\ClientThemePaletteService;
use App\Services\Client\ClientPageAdminContentResolver;
use App\Services\Client\ClientPageAssetService;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Client\ClientPageSettingDefaultService;
use App\Services\Client\ClientPageResetService;
use App\Services\Homepage\JetpkHomepageAssetService;
use App\Services\Homepage\JetpkHomepageContentMergeService;
use App\Services\Homepage\JetpkHomepageContentValidator;
use App\Services\Homepage\JetpkHomepageRouteFareRefreshService;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientPageSectionSchema;
use App\Services\Client\CurrentClientContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * JetPK client-scoped page settings editor with draft/publish and live preview.
 */
class ClientPageSettingsController extends Controller
{
    use RespondsWithBackOfficeJson;
    public function __construct(
        private readonly CurrentClientContext $clientContext,
        private readonly ClientPageContentResolver $contentResolver,
        private readonly ClientPageAdminContentResolver $adminContentResolver,
        private readonly ClientPageAssetService $assetService,
        private readonly ClientThemePaletteService $paletteService,
        private readonly JetpkHomepageContentValidator $homepageValidator,
        private readonly JetpkHomepageContentMergeService $homepageMergeService,
        private readonly JetpkHomepageAssetService $homepageAssetService,
        private readonly JetpkHomepageRouteFareRefreshService $routeFareRefreshService,
        private readonly ClientPageSettingDefaultService $defaultService,
        private readonly ClientPageResetService $resetService,
    ) {}

    public function index(): View
    {
        Gate::authorize('client.page-settings.manage');
        $profile = $this->requireProfile();

        if (! Schema::hasTable('client_page_settings')) {
            return view(client_view('page-settings.index', 'admin'), [
                'pages' => collect(),
                'profile' => $profile,
                'migrationRequired' => true,
            ]);
        }

        $pages = collect(ClientPageKeys::labels())->map(function (string $label, string $key) use ($profile): array {
            $draft = $this->settingRow($profile->id, $key, ClientPageSettingStatus::Draft);
            $published = $this->settingRow($profile->id, $key, ClientPageSettingStatus::Published);

            return [
                'key' => $key,
                'label' => $label,
                'has_draft' => $draft !== null,
                'has_published' => $published !== null,
                'published_at' => $published?->published_at?->diffForHumans(),
            ];
        })->values();

        return view(client_view('page-settings.index', 'admin'), [
            'pages' => $pages,
            'profile' => $profile,
        ]);
    }

    public function edit(Request $request, string $pageKey): View|JsonResponse|RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        if ($this->wantsBackOfficeJson($request)) {
            $profile = $this->requireProfile();
            $previewRoute = ClientPageKeys::previewRoutes()[$pageKey] ?? 'home';
            $assets = ClientPageAsset::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->orderBy('asset_key')
                ->get()
                ->map(static fn (ClientPageAsset $asset): array => [
                    'id' => $asset->id,
                    'asset_key' => $asset->asset_key,
                    'alt_text' => $asset->alt_text,
                    'url' => $asset->public_url,
                    'public_url' => $asset->public_url,
                    'meta' => $asset->meta_json,
                ])
                ->values()
                ->all();

            $publishedRow = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->where('status', ClientPageSettingStatus::Published)
                ->first();
            $draftRow = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->where('status', ClientPageSettingStatus::Draft)
                ->first();
            $draftSettings = is_array($draftRow?->settings_json) ? $draftRow->settings_json : [];
            $archived = (bool) ($draftSettings['archived'] ?? false);
            $content = $this->adminContentResolver->formContentFor($profile, $pageKey);

            return $this->backOfficeJson([
                'ok' => true,
                'page_key' => $pageKey,
                'pageKey' => $pageKey,
                'pageLabel' => ClientPageKeys::labels()[$pageKey] ?? $pageKey,
                'content' => $content,
                'draft' => $draftRow !== null ? $content : null,
                'published' => $publishedRow !== null
                    ? (is_array($publishedRow->content_json) ? $publishedRow->content_json : null)
                    : null,
                'editorMeta' => $this->adminContentResolver->editorMeta($profile, $pageKey),
                'sections' => ClientPageSectionSchema::sectionsFor($pageKey),
                'assets' => $assets,
                'publishing' => [
                    'has_draft' => $draftRow !== null,
                    'has_published' => $publishedRow !== null,
                    'archived' => $archived,
                    'can_unpublish' => $pageKey !== ClientPageKeys::HOME && $publishedRow !== null,
                    'status' => $publishedRow !== null
                        ? ($draftRow !== null ? 'draft_ahead' : 'published')
                        : ($draftRow !== null ? 'draft' : 'empty'),
                ],
                'previewUrl' => client_route($previewRoute),
                'preview_url' => client_route($previewRoute),
            ]);
        }

        return redirect()->to('/admin/dashboard/cms/pages');
    }

    /**
     * Blade editor surface retained for operational/regression coverage while GET redirects to Next CMS.
     */
    public function editView(string $pageKey): View
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $profile = $this->requireProfile();
        $previewRoute = ClientPageKeys::previewRoutes()[$pageKey] ?? 'home';

        return view(client_view('page-settings.edit', 'admin'), [
            'pageKey' => $pageKey,
            'pageLabel' => ClientPageKeys::labels()[$pageKey] ?? $pageKey,
            'content' => $this->adminContentResolver->formContentFor($profile, $pageKey),
            'editorMeta' => $this->adminContentResolver->editorMeta($profile, $pageKey),
            'previewUrl' => client_route($previewRoute),
            'assets' => ClientPageAsset::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->orderBy('asset_key')
                ->get(),
            'palette' => ClientThemePalette::query()->where('client_profile_id', $profile->id)->first(),
            'activeDefault' => Schema::hasTable('client_page_setting_defaults')
                ? $this->defaultService->getActive($profile, $pageKey)
                : null,
        ]);
    }

    public function update(Request $request, string $pageKey): RedirectResponse|JsonResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $profile = $this->requireProfile();
        $validated = $request->validate([
            'content' => ['required', 'array'],
            'destination_files' => ['nullable', 'array'],
            'destination_files.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'destination_remove' => ['nullable', 'array'],
            'route_files' => ['nullable', 'array'],
            'route_files.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'route_remove' => ['nullable', 'array'],
            'support_cta_background_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'support_cta_background_mobile_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'submitted_sections' => ['nullable', 'array'],
            'submitted_sections.*' => ['string', 'max:64'],
        ]);

        $content = $this->preserveIntentionalEmptyScalars($validated['content']);
        $existing = $this->adminContentResolver->formContentFor($profile, $pageKey);
        if ($pageKey === ClientPageKeys::HOME) {
            $panels = array_values(array_filter((array) $request->input('submitted_sections', [])));
            $content = $this->homepageMergeService->mergeOnSave($existing, $content, $panels);
        }
        $content = $this->homepageValidator->validateAndNormalize($pageKey, $content);

        if ($pageKey === ClientPageKeys::HOME) {
            $content = $this->processHomeMediaUploads($request, $profile, $content);
        }

        $this->contentResolver->saveDraft(
            $profile,
            $pageKey,
            $content,
            auth()->id(),
        );

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Draft saved.',
                'pageKey' => $pageKey,
                'content' => $content,
            ]);
        }

        return redirect()
            ->to(client_route('admin.page-settings.edit', ['pageKey' => $pageKey]))
            ->with('status', 'Draft saved.');
    }

    public function refreshHomeRouteFares(): RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        $profile = $this->requireProfile();
        $summary = $this->routeFareRefreshService->refreshProfile($profile, true);

        return redirect()
            ->to(client_route('admin.page-settings.edit', ['pageKey' => ClientPageKeys::HOME]).'#section-routes')
            ->with('status', sprintf(
                'Route fare refresh complete: %d refreshed, %d success, %d failed, %d skipped.',
                $summary['refreshed'],
                $summary['success'],
                $summary['failed'],
                $summary['skipped'],
            ));
    }

    public function publish(Request $request, string $pageKey): RedirectResponse|JsonResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $profile = $this->requireProfile();
        $published = $this->contentResolver->publish($profile, $pageKey, auth()->id());

        if ($published === null) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJson(['ok' => false, 'message' => 'No draft found to publish.'], 422);
            }

            return back()->withErrors(['publish' => 'No draft found to publish.']);
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson(['ok' => true, 'message' => 'Page published.', 'pageKey' => $pageKey]);
        }

        return redirect()
            ->to(client_route('admin.page-settings.edit', ['pageKey' => $pageKey]))
            ->with('status', 'Page published.');
    }

    public function saveCurrentAsDefault(Request $request, string $pageKey): RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $validated = $request->validate([
            'visual_approval_confirmed' => ['required', 'accepted'],
            'label' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = $this->requireProfile();

        try {
            $this->defaultService->saveCurrentPublishedAsDefault(
                $profile,
                $pageKey,
                visualApprovalConfirmed: true,
                label: $validated['label'] ?? null,
                note: $validated['note'] ?? null,
                userId: auth()->id(),
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['default' => $exception->getMessage()]);
        }

        return redirect()
            ->to(client_route('admin.page-settings.edit', ['pageKey' => $pageKey]))
            ->with('status', 'Current published content saved as the new default.');
    }

    public function previewReset(string $pageKey): RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $profile = $this->requireProfile();
        $result = $this->resetService->previewReset($profile, $pageKey);

        if (! $result['success']) {
            return back()->withErrors(['reset' => $result['message']]);
        }

        $status = $result['message'];
        if (! empty($result['missing_media'])) {
            $status .= ' Warning — referenced media not found: '.implode(', ', $result['missing_media']).'.';
        }

        return back()->with('status', $status);
    }

    public function resetDraft(string $pageKey): RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $profile = $this->requireProfile();
        $result = $this->resetService->resetDraftToDefault($profile, $pageKey, auth()->id());

        if (! $result['success']) {
            return back()->withErrors(['reset' => $result['message']]);
        }

        $status = $result['message'];
        if (! empty($result['missing_media'])) {
            $status .= ' Warning — referenced media not found: '.implode(', ', $result['missing_media']).'.';
        }

        return redirect()
            ->to(client_route('admin.page-settings.edit', ['pageKey' => $pageKey]))
            ->with('status', $status);
    }

    public function resetAndPublish(Request $request, string $pageKey): RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $request->validate([
            'reset_and_publish_confirmed' => ['required', 'accepted'],
        ]);

        $profile = $this->requireProfile();
        $result = $this->resetService->resetAndPublish($profile, $pageKey, auth()->id());

        if (! $result['success']) {
            return back()->withErrors(['reset' => $result['message']]);
        }

        $status = $result['message'];
        if (! empty($result['missing_media'])) {
            $status .= ' Warning — referenced media not found: '.implode(', ', $result['missing_media']).'.';
        }

        return redirect()
            ->to(client_route('admin.page-settings.edit', ['pageKey' => $pageKey]))
            ->with('status', $status);
    }

    public function beginPreview(Request $request, string $pageKey): RedirectResponse|JsonResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $this->contentResolver->beginDraftPreview($pageKey);
        $route = ClientPageKeys::previewRoutes()[$pageKey] ?? 'home';
        $previewUrl = client_route($route);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'previewUrl' => $previewUrl,
                'message' => 'Draft preview session started.',
            ]);
        }

        return redirect()->to($previewUrl);
    }

    public function catalog(Request $request): JsonResponse
    {
        Gate::authorize('client.page-settings.manage');
        $profile = $this->requireProfile();

        $managed = [
            ClientPageKeys::HOME,
            ClientPageKeys::ABOUT,
            ClientPageKeys::FAQ,
            ClientPageKeys::SUPPORT,
            ClientPageKeys::TERMS,
            ClientPageKeys::PRIVACY,
        ];

        $pages = [];
        foreach ($managed as $key) {
            $published = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $key)
                ->where('status', ClientPageSettingStatus::Published)
                ->exists();
            $draftRow = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $key)
                ->where('status', ClientPageSettingStatus::Draft)
                ->first();
            $draft = $draftRow !== null;
            $draftSettings = is_array($draftRow?->settings_json) ? $draftRow->settings_json : [];
            $archived = (bool) ($draftSettings['archived'] ?? false);
            $previewRoute = ClientPageKeys::previewRoutes()[$key] ?? 'home';
            $updatedAt = $draftRow?->updated_at?->toIso8601String()
                ?? ClientPageSetting::query()
                    ->where('client_profile_id', $profile->id)
                    ->where('page_key', $key)
                    ->where('status', ClientPageSettingStatus::Published)
                    ->value('updated_at');
            $pages[] = [
                'key' => $key,
                'label' => ClientPageKeys::labels()[$key] ?? $key,
                'type' => 'page_settings',
                'route' => client_route($previewRoute),
                'public_path' => client_route($previewRoute),
                'published' => $published && ! $archived,
                'draft' => $draft,
                'archived' => $archived,
                'status' => $archived
                    ? 'archived'
                    : ($published ? ($draft ? 'draft_ahead' : 'published') : ($draft ? 'draft' : 'empty')),
                'updated_at' => is_string($updatedAt) ? $updatedAt : ($updatedAt?->toIso8601String() ?? null),
                'sections' => ClientPageSectionSchema::sectionsFor($key),
            ];
        }

        if (Schema::hasTable('client_pages')) {
            $custom = \App\Models\ClientPage::query()
                ->where('client_profile_id', $profile->id)
                ->orderBy('public_title')
                ->get();
            foreach ($custom as $page) {
                $pageKey = $page->pageKey();
                $published = ClientPageSetting::query()
                    ->where('client_profile_id', $profile->id)
                    ->where('page_key', $pageKey)
                    ->where('status', ClientPageSettingStatus::Published)
                    ->exists();
                $draft = ClientPageSetting::query()
                    ->where('client_profile_id', $profile->id)
                    ->where('page_key', $pageKey)
                    ->where('status', ClientPageSettingStatus::Draft)
                    ->exists();
                $pages[] = [
                    'key' => $pageKey,
                    'label' => (string) $page->public_title,
                    'type' => 'custom_page',
                    'route' => '/pages/'.$page->slug,
                    'public_path' => '/pages/'.$page->slug,
                    'published' => $published && (bool) $page->enabled,
                    'draft' => $draft,
                    'archived' => ! (bool) $page->enabled,
                    'status' => ! (bool) $page->enabled
                        ? 'archived'
                        : ($published ? 'published' : ($draft ? 'draft' : 'empty')),
                    'updated_at' => $page->updated_at?->toIso8601String(),
                    'sections' => ClientPageSectionSchema::sectionsFor($pageKey),
                ];
            }
        }

        return $this->backOfficeJson([
            'ok' => true,
            'pages' => $pages,
        ]);
    }

    public function duplicate(Request $request, string $pageKey): JsonResponse|RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);
        abort_if($pageKey === ClientPageKeys::HOME, 422, 'Homepage cannot be duplicated into another canonical route.');

        $profile = $this->requireProfile();
        $sourceContent = $this->adminContentResolver->formContentFor($profile, $pageKey);

        $baseSlug = ClientPageKeys::isCustom($pageKey)
            ? ClientPageKeys::customSlug($pageKey)
            : $pageKey;
        $slug = $baseSlug.'-copy';
        $i = 2;
        while (
            \App\Models\ClientPage::query()
                ->where('client_profile_id', $profile->id)
                ->where('slug', $slug)
                ->exists()
            || \App\Support\Client\ClientManagedPageReservedSlugs::isReserved($slug)
        ) {
            $slug = $baseSlug.'-copy-'.$i;
            $i++;
            if ($i > 50) {
                abort(422, 'Unable to allocate a unique slug for the duplicate.');
            }
        }

        $label = ClientPageKeys::labels()[$pageKey] ?? $pageKey;
        $title = $label.' (copy)';

        $page = \App\Models\ClientPage::query()->create([
            'client_profile_id' => $profile->id,
            'slug' => $slug,
            'internal_name' => $title,
            'public_title' => $title,
            'nav_label' => $title,
            'enabled' => true,
            'show_header' => true,
            'show_footer' => true,
        ]);

        $newKey = ClientPageKeys::customKey($slug);
        $content = $sourceContent;
        $content['identity'] = [
            'title' => $title,
            'slug' => $slug,
            'nav_label' => $title,
        ];
        if (! isset($content['seo']) || ! is_array($content['seo'])) {
            $content['seo'] = [];
        }
        $content['seo']['title'] = $title;
        $content['seo']['robots'] = 'noindex,nofollow';

        $this->contentResolver->saveDraft($profile, $newKey, $content, auth()->id());

        // Copy ClientPageAsset rows by re-attaching from existing public disk paths via meta agency_media when present.
        $sourceAssets = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->get();
        foreach ($sourceAssets as $asset) {
            $meta = is_array($asset->meta_json) ? $asset->meta_json : [];
            $agencyMediaId = isset($meta['agency_media_id']) ? (int) $meta['agency_media_id'] : 0;
            if ($agencyMediaId > 0) {
                $media = AgencyMedia::query()->find($agencyMediaId);
                if ($media !== null) {
                    try {
                        $this->assetService->attachFromAgencyMedia(
                            $profile,
                            $newKey,
                            (string) $asset->asset_key,
                            $media,
                            auth()->id(),
                            $asset->alt_text,
                        );
                    } catch (Throwable) {
                        // Non-fatal — content duplicate still succeeds without media.
                    }
                }
            }
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Page duplicated as draft.',
                'page_key' => $newKey,
                'slug' => $slug,
                'status' => 'draft',
                'page' => [
                    'id' => $page->id,
                    'key' => $newKey,
                    'slug' => $slug,
                    'title' => $title,
                ],
            ]);
        }

        return redirect()
            ->to(client_route('admin.page-settings.edit', ['pageKey' => $newKey]))
            ->with('status', 'Page duplicated as draft.');
    }

    public function unpublish(Request $request, string $pageKey): RedirectResponse|JsonResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        // Never destroy homepage published content via unpublish — use archive semantics of removing public authority only for non-home pages.
        if ($pageKey === ClientPageKeys::HOME) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJson([
                    'ok' => false,
                    'message' => 'Homepage cannot be unpublished from this action. Use draft/preview/publish safely.',
                ], 422);
            }

            return back()->withErrors(['unpublish' => 'Homepage cannot be unpublished from this action.']);
        }

        $profile = $this->requireProfile();
        $deleted = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->where('status', ClientPageSettingStatus::Published)
            ->delete();

        // Soft archive flag on draft settings_json when draft exists.
        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();
        if ($draft !== null) {
            $settings = is_array($draft->settings_json) ? $draft->settings_json : [];
            $settings['archived'] = true;
            $settings['archived_at'] = now()->toIso8601String();
            $draft->settings_json = $settings;
            $draft->save();
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Page unpublished (archived from public). Draft retained.',
                'removed_published' => (int) $deleted,
            ]);
        }

        return back()->with('status', 'Page unpublished.');
    }

    public function attachAsset(Request $request, string $pageKey): JsonResponse|RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $validated = $request->validate([
            'asset_key' => ['required', 'string', 'max:64'],
            'agency_media_id' => ['required', 'integer', 'exists:agency_media,id'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $profile = $this->requireProfile();
        $media = AgencyMedia::query()->findOrFail((int) $validated['agency_media_id']);

        try {
            $asset = $this->assetService->attachFromAgencyMedia(
                $profile,
                $pageKey,
                $validated['asset_key'],
                $media,
                auth()->id(),
                $validated['alt_text'] ?? null,
            );
        } catch (ValidationException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJson(['ok' => false, 'message' => 'Attach failed.', 'errors' => $e->errors()], 422);
            }
            throw $e;
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Media attached from library.',
                'asset' => [
                    'id' => $asset->id,
                    'asset_key' => $asset->asset_key,
                    'alt_text' => $asset->alt_text,
                    'url' => $asset->public_url,
                    'agency_media_id' => $media->id,
                ],
            ]);
        }

        return redirect()
            ->to($this->mediaTabEditUrl($pageKey))
            ->with('status', 'Media attached from library.');
    }

    public function storeAsset(Request $request, string $pageKey): RedirectResponse|JsonResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $mediaEditUrl = $this->mediaTabEditUrl($pageKey);

        $validator = Validator::make($request->all(), [
            'asset_key' => ['required', 'string', 'max:64'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJson(['ok' => false, 'message' => 'Asset validation failed.', 'errors' => $validator->errors()], 422);
            }

            return redirect()
                ->to($mediaEditUrl)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $profile = $this->requireProfile();

        try {
            $asset = $this->assetService->store(
                $profile,
                $pageKey,
                $validated['asset_key'],
                $validated['file'],
                auth()->id(),
                $validated['alt_text'] ?? null,
            );
        } catch (ValidationException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJson(['ok' => false, 'message' => 'Asset validation failed.', 'errors' => $e->errors()], 422);
            }
            $e->redirectTo($mediaEditUrl);
            throw $e;
        } catch (Throwable $e) {
            report($e);

            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJson(['ok' => false, 'message' => 'Upload failed. Please try again.'], 500);
            }

            return redirect()
                ->to($mediaEditUrl)
                ->withErrors(['file' => 'Upload failed. Please try again.']);
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Asset uploaded.',
                'asset' => [
                    'id' => $asset->id,
                    'asset_key' => $asset->asset_key,
                    'alt_text' => $asset->alt_text,
                    'url' => $asset->public_url ?? $asset->url ?? null,
                ],
            ]);
        }

        $warning = is_array($asset->meta_json) ? ($asset->meta_json['hero_lcp_warning'] ?? null) : null;
        $redirect = redirect()
            ->to($mediaEditUrl)
            ->with('status', 'Asset uploaded.');

        if (is_string($warning) && $warning !== '') {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function destroyAsset(string $pageKey, ClientPageAsset $asset, Request $request): RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);
        abort_unless($asset->page_key === $pageKey, 404);
        $profile = $this->requireProfile();
        abort_unless($asset->client_profile_id === $profile->id, 404);

        if (! $request->boolean('force') && $this->assetIsStillReferenced($profile, $pageKey, $asset->asset_key)) {
            return back()->withErrors([
                'asset' => "\"{$asset->asset_key}\" is still referenced in this page's Draft or Published content. "
                    .'Remove the reference first, or resubmit with force=1 to delete anyway and leave a broken image reference.',
            ]);
        }

        $this->assetService->destroy($asset);

        return back()->with('status', 'Asset removed.');
    }

    private function assetIsStillReferenced(\App\Models\ClientProfile $profile, string $pageKey, string $assetKey): bool
    {
        $rows = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->whereIn('status', [ClientPageSettingStatus::Draft, ClientPageSettingStatus::Published])
            ->get();

        foreach ($rows as $row) {
            if (is_array($row->content_json) && $this->arrayContainsValue($row->content_json, $assetKey)) {
                return true;
            }
        }

        return false;
    }

    private function arrayContainsValue(array $data, string $needle): bool
    {
        foreach ($data as $value) {
            if (is_array($value)) {
                if ($this->arrayContainsValue($value, $needle)) {
                    return true;
                }

                continue;
            }
            if (is_string($value) && $value === $needle) {
                return true;
            }
        }

        return false;
    }

    public function palette(): View
    {
        Gate::authorize('client.page-settings.manage');
        $profile = $this->requireProfile();

        return view(client_view('page-settings.palette', 'admin'), [
            'palette' => ClientThemePalette::query()->where('client_profile_id', $profile->id)->first(),
            'logoPath' => 'client-assets/'.($profile->asset_profile ?: $profile->slug).'/logo/logo.svg',
        ]);
    }

    public function generatePalette(Request $request): RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        $profile = $this->requireProfile();
        $logo = trim((string) $request->input('logo_path', ''));

        $this->paletteService->generateForProfile($profile, $logo !== '' ? $logo : null);

        return redirect()
            ->to(client_route('admin.page-settings.palette'))
            ->with('status', 'Palette generated from logo (draft — approve to apply).');
    }

    public function applyPalette(): RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        $profile = $this->requireProfile();
        $this->paletteService->approveDraft($profile, (int) auth()->id());

        return redirect()
            ->to(client_route('admin.page-settings.palette'))
            ->with('status', 'Palette approved and saved to client branding.');
    }

    private function mediaTabEditUrl(string $pageKey): string
    {
        return client_route('admin.page-settings.edit', ['pageKey' => $pageKey]).'#media';
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function processHomeMediaUploads(Request $request, \App\Models\ClientProfile $profile, array $content): array
    {
        $items = is_array($content['destinations']['items'] ?? null) ? $content['destinations']['items'] : [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $itemId = (string) ($item['id'] ?? 'dest-'.$index);
            if ($request->hasFile('destination_files.'.$itemId)) {
                $asset = $this->homepageAssetService->storeDestinationImage(
                    $profile,
                    $itemId,
                    $request->file('destination_files.'.$itemId),
                    auth()->id(),
                    $item['alt'] ?? null,
                );
                $content['destinations']['items'][$index]['image_asset_key'] = $asset->asset_key;
            }

            if ($request->boolean('destination_remove.'.$itemId)) {
                $assetKey = (string) ($item['image_asset_key'] ?? JetpkHomepageAssetService::destinationAssetKey($itemId));
                $existing = ClientPageAsset::query()
                    ->where('client_profile_id', $profile->id)
                    ->where('page_key', ClientPageKeys::HOME)
                    ->where('asset_key', $assetKey)
                    ->first();
                if ($existing !== null) {
                    $this->homepageAssetService->destroyAsset($existing);
                }
            }
        }

        if ($request->hasFile('support_cta_background_file')) {
            $this->homepageAssetService->storeSupportCtaImage(
                $profile,
                'desktop',
                $request->file('support_cta_background_file'),
                auth()->id(),
            );
            $content = $this->ensureSupportCtaUploadedBackgroundMode($content);
        }

        if ($request->hasFile('support_cta_background_mobile_file')) {
            $this->homepageAssetService->storeSupportCtaImage(
                $profile,
                'mobile',
                $request->file('support_cta_background_mobile_file'),
                auth()->id(),
            );
            $content = $this->ensureSupportCtaUploadedBackgroundMode($content);
        }

        if ($request->boolean('support_cta_background_remove')) {
            $this->destroyHomeAssetByKey($profile, 'support_cta_background');
        }

        if ($request->boolean('support_cta_background_mobile_remove')) {
            $this->destroyHomeAssetByKey($profile, 'support_cta_background_mobile');
        }

        $routeItems = is_array($content['routes']['items'] ?? null) ? $content['routes']['items'] : [];
        foreach ($routeItems as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $itemId = (string) ($item['id'] ?? 'route-'.$index);
            if ($request->hasFile('route_files.'.$itemId)) {
                $asset = $this->homepageAssetService->storeRouteImage(
                    $profile,
                    $itemId,
                    $request->file('route_files.'.$itemId),
                    auth()->id(),
                    $item['image_alt'] ?? ($item['alt'] ?? null),
                );
                $content['routes']['items'][$index]['image_asset_key'] = $asset->asset_key;
            }

            if ($request->boolean('route_remove.'.$itemId)) {
                $assetKey = (string) ($item['image_asset_key'] ?? JetpkHomepageAssetService::routeAssetKey($itemId));
                $existing = ClientPageAsset::query()
                    ->where('client_profile_id', $profile->id)
                    ->where('page_key', ClientPageKeys::HOME)
                    ->where('asset_key', $assetKey)
                    ->first();
                if ($existing !== null) {
                    $this->homepageAssetService->destroyAsset($existing);
                }
            }
        }

        return $content;
    }

    private function destroyHomeAssetByKey(\App\Models\ClientProfile $profile, string $assetKey): void
    {
        $existing = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('asset_key', $assetKey)
            ->first();

        if ($existing !== null) {
            $this->homepageAssetService->destroyAsset($existing);
        }
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function ensureSupportCtaUploadedBackgroundMode(array $content): array
    {
        $support = is_array($content['support_cta'] ?? null) ? $content['support_cta'] : [];
        $mode = (string) ($support['background_mode'] ?? 'gradient');
        if ($mode === 'gradient') {
            $support['background_mode'] = 'uploaded_overlay';
            $content['support_cta'] = $support;
        }

        return $content;
    }

    private function requireProfile(): \App\Models\ClientProfile
    {
        $profile = $this->clientContext->get();
        abort_if($profile === null, 404, 'Client profile not available in this context.');

        return $profile;
    }

    private function settingRow(int $profileId, string $pageKey, ClientPageSettingStatus $status): ?ClientPageSetting
    {
        return ClientPageSetting::query()
            ->where('client_profile_id', $profileId)
            ->where('page_key', $pageKey)
            ->where('status', $status)
            ->first();
    }

    /**
     * Laravel's ConvertEmptyStringsToNull turns cleared fields into null; store explicit empties for CMS parity.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function preserveIntentionalEmptyScalars(array $content): array
    {
        foreach ($content as $key => $value) {
            if (is_array($value)) {
                $content[$key] = $this->preserveIntentionalEmptyScalars($value);

                continue;
            }

            if ($value === null) {
                $content[$key] = '';
            }
        }

        return $content;
    }
}
