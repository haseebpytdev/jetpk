<?php

namespace App\Services\Client;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\ClientPageSettingRevision;
use App\Models\ClientProfile;
use App\Services\Homepage\JetpkHomepageContentValidator;
use App\Support\Client\ClientPageKeys;
use Illuminate\Support\Facades\DB;

/**
 * JETPK-HOMEPAGE-CMS Task 13: reset content back to the active saved default
 * (Task 12), safely.
 *
 * Both public methods return a plain result array — {success, error?,
 * message, ...} — rather than throwing for the expected failure paths
 * (missing default, unsafe/invalid default content), because those are
 * ordinary, anticipated outcomes the Admin controller needs to show as a
 * clear message, not exceptional program states. ValidationException is
 * still allowed to propagate for genuinely unexpected schema violations
 * that "reject if unsafe" calls for — see resolveValidatedDefaultContent().
 */
final class ClientPageResetService
{
    public function __construct(
        private readonly ClientPageContentResolver $contentResolver,
        private readonly ClientPageSettingRevisionService $revisions,
        private readonly ClientPageSettingDefaultService $defaults,
        private readonly JetpkHomepageContentValidator $homepageValidator,
    ) {}

    /**
     * Preview what a reset would apply, without writing anything. Read-only
     * — no revision, no draft/published change. Used by the Admin UI's
     * "Preview Reset to Default" action, which the spec explicitly asks for
     * as distinct from actually performing the reset.
     *
     * @return array{success: bool, error?: string, message: string, content?: array<string, mixed>, missing_media?: list<string>}
     */
    public function previewReset(ClientProfile $profile, string $pageKey): array
    {
        $resolution = $this->resolveValidatedDefaultContent($profile, $pageKey);
        if (! $resolution['success']) {
            return $resolution;
        }

        return [
            'success' => true,
            'message' => 'This is what Draft would become if reset — nothing has been written yet.',
            'content' => $resolution['content'],
            'missing_media' => $resolution['missing_media'],
            'default_label' => $resolution['default_label'],
        ];
    }

    /**
     * Reset Draft only. Published is completely untouched. Creates a
     * before_reset revision of the current Draft first (if one exists with
     * content — nothing to preserve if it doesn't).
     *
     * @return array{success: bool, error?: string, message: string, missing_media?: list<string>}
     */
    public function resetDraftToDefault(ClientProfile $profile, string $pageKey, ?int $userId = null): array
    {
        $resolution = $this->resolveValidatedDefaultContent($profile, $pageKey);
        if (! $resolution['success']) {
            return $resolution;
        }

        DB::transaction(function () use ($profile, $pageKey, $resolution, $userId): void {
            $currentDraft = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->where('status', ClientPageSettingStatus::Draft)
                ->first();

            if ($currentDraft !== null) {
                $this->revisions->snapshot($currentDraft, ClientPageSettingRevision::REASON_BEFORE_RESET, $userId);
            }

            $this->contentResolver->saveDraft($profile, $pageKey, $resolution['content'], $userId);
        });

        return [
            'success' => true,
            'message' => 'Draft reset to "'.($resolution['default_label'] ?: 'the saved default').'". Not published — review and publish when ready.',
            'missing_media' => $resolution['missing_media'],
        ];
    }

    /**
     * Reset AND publish, atomically. This is the more destructive of the
     * two actions — the spec requires it to be a genuinely separate action
     * with its own explicit confirmation, not a checkbox tacked onto
     * resetDraftToDefault(). That separation is enforced at the Admin
     * controller layer (two distinct routes, two distinct confirmation
     * copy/checkboxes) — this method itself doesn't gate on a confirmation
     * flag the way ClientPageSettingDefaultService::saveCurrentPublishedAsDefault()
     * does, because by the time a request reaches this service method, the
     * controller has already enforced that separation.
     *
     * @return array{success: bool, error?: string, message: string, missing_media?: list<string>}
     */
    public function resetAndPublish(ClientProfile $profile, string $pageKey, ?int $userId = null): array
    {
        $resolution = $this->resolveValidatedDefaultContent($profile, $pageKey);
        if (! $resolution['success']) {
            return $resolution;
        }

        DB::transaction(function () use ($profile, $pageKey, $resolution, $userId): void {
            $currentDraft = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->where('status', ClientPageSettingStatus::Draft)
                ->first();

            if ($currentDraft !== null) {
                $this->revisions->snapshot($currentDraft, ClientPageSettingRevision::REASON_BEFORE_RESET, $userId);
            }

            $draft = $this->contentResolver->saveDraft($profile, $pageKey, $resolution['content'], $userId);

            // Deliberately calls promoteToPublished() directly with
            // REASON_BEFORE_RESET, not the generic publish() method — publish()
            // would snapshot the existing Published row again under
            // REASON_BEFORE_PUBLISH, producing a second, redundant revision of
            // the exact same prior content under the wrong label (see Task 13
            // notes in ClientPageContentResolver::promoteToPublished()).
            $this->contentResolver->promoteToPublished(
                $profile,
                $pageKey,
                $draft,
                $resolution['content'],
                $userId,
                ClientPageSettingRevision::REASON_BEFORE_RESET,
            );
        });

        return [
            'success' => true,
            'message' => 'Draft and Published both reset to "'.($resolution['default_label'] ?: 'the saved default').'".',
            'missing_media' => $resolution['missing_media'],
        ];
    }

    /**
     * Shared resolution: load the active default, validate/normalize it
     * against the CURRENT schema (not whatever schema existed when it was
     * saved), and detect missing media references — without writing
     * anything. Every public method above starts here.
     *
     * @return array{success: bool, error?: string, message: string, content?: array<string, mixed>, missing_media?: list<string>, default_label?: string|null}
     */
    private function resolveValidatedDefaultContent(ClientProfile $profile, string $pageKey): array
    {
        $default = $this->defaults->getActive($profile, $pageKey);

        // "Missing default: show clear error, do not fall back silently to code defaults."
        if ($default === null) {
            return [
                'success' => false,
                'error' => 'missing_default',
                'message' => 'No saved default exists for this page yet. Save one first (Save Current Published State as Default) before resetting to it.',
            ];
        }

        $rawContent = is_array($default->content_json) ? $default->content_json : [];

        // "Schema mismatch: migrate through normalizer, reject if unsafe, preserve revision."
        // validateAndNormalize() both migrates known-safe shape drift (via the
        // homepage normalizer wired into contentFor()) and throws
        // ValidationException for genuinely unsafe content (e.g. a route with a
        // since-deactivated IATA code). That exception is allowed to propagate
        // rather than being caught into a generic error array, matching Task 8's
        // established pattern (Laravel's handler turns it into a clear
        // redirect-back-with-errors response) — "reject if unsafe" means reject
        // outright, not degrade into a vague message. No revision has been
        // created yet at this point (validation happens before any snapshot or
        // write), so there is nothing to roll back — "preserve revision" is
        // trivially satisfied by never having touched anything.
        $validated = $this->homepageValidator->validateAndNormalize($pageKey, $rawContent);

        $missingMedia = $pageKey === ClientPageKeys::HOME
            ? $this->findMissingHomepageMediaReferences($profile, $validated)
            : [];

        return [
            'success' => true,
            'message' => 'Default resolved successfully.',
            'content' => $validated,
            'missing_media' => $missingMedia,
            'default_label' => $default->label,
        ];
    }

    /**
     * "Missing media: report missing media, do not silently remove
     * references, allow controlled remediation."
     *
     * Scoped deliberately to only the media references explicitly embedded
     * IN the content itself (destinations.items[].image_asset_key) — the
     * fixed system media slots (hero_background, group_card_N,
     * support_cta_background) are optional-by-design (JetpkHomepageSectionData::assetUrl()
     * already falls back gracefully to a placeholder/branding image when
     * absent), so their absence is expected, normal behavior, not a defect
     * worth flagging as "missing." Flagging those too would produce a wall
     * of false-positive warnings for every perfectly ordinary default that
     * simply doesn't use custom uploaded images.
     *
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    private function findMissingHomepageMediaReferences(ClientProfile $profile, array $content): array
    {
        $referencedKeys = [];
        foreach (data_get($content, 'destinations.items', []) as $item) {
            if (is_array($item) && ! empty($item['image_asset_key'])) {
                $referencedKeys[] = (string) $item['image_asset_key'];
            }
        }

        if ($referencedKeys === []) {
            return [];
        }

        $existingKeys = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->whereIn('asset_key', array_unique($referencedKeys))
            ->pluck('asset_key')
            ->all();

        return array_values(array_diff(array_unique($referencedKeys), $existingKeys));
    }
}
