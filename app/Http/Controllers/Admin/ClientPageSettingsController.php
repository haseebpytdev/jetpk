<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClientPageSettingStatus;
use App\Http\Controllers\Controller;
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
use App\Services\Client\CurrentClientContext;
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

    public function edit(string $pageKey): View
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $profile = $this->requireProfile();
        $content = $this->adminContentResolver->formContentFor($profile, $pageKey);
        $editorMeta = $this->adminContentResolver->editorMeta($profile, $pageKey);
        $previewRoute = ClientPageKeys::previewRoutes()[$pageKey] ?? 'home';
        $previewUrl = client_route($previewRoute);

        return view(client_view('page-settings.edit', 'admin'), [
            'pageKey' => $pageKey,
            'pageLabel' => ClientPageKeys::labels()[$pageKey] ?? $pageKey,
            'content' => $content,
            'editorMeta' => $editorMeta,
            'previewUrl' => $previewUrl,
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

    public function update(Request $request, string $pageKey): RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $profile = $this->requireProfile();
        $validated = $request->validate([
            'content' => ['required', 'array'],
            'destination_files' => ['nullable', 'array'],
            'destination_files.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'destination_remove' => ['nullable', 'array'],
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

    public function publish(string $pageKey): RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $profile = $this->requireProfile();
        $published = $this->contentResolver->publish($profile, $pageKey, auth()->id());

        if ($published === null) {
            return back()->withErrors(['publish' => 'No draft found to publish.']);
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

    public function beginPreview(string $pageKey): RedirectResponse
    {
        Gate::authorize('client.page-settings.manage');
        abort_unless(ClientPageKeys::isValid($pageKey), 404);

        $this->contentResolver->beginDraftPreview($pageKey);
        $route = ClientPageKeys::previewRoutes()[$pageKey] ?? 'home';

        return redirect()->to(client_route($route));
    }

    public function storeAsset(Request $request, string $pageKey): RedirectResponse
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
            $e->redirectTo($mediaEditUrl);
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->to($mediaEditUrl)
                ->withErrors(['file' => 'Upload failed. Please try again.']);
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
                $assetKey = (string) ($item['image_asset_key'] ?? 'destination_'.$itemId);
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
