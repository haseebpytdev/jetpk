<?php

namespace App\Services\Client;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientPageSettingDefault;
use App\Models\ClientProfile;
use App\Support\Client\Homepage\HomepageContentNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * JETPK-HOMEPAGE-CMS Task 12: saved defaults.
 *
 * Deliberately does NOT auto-create a default from
 * ClientPagePublicFallbackCatalog / defaultHomeContent() anywhere in this
 * class. getActive() returns null until an Admin has explicitly saved one —
 * that null is a meaningful, checkable state Task 13's reset flow must
 * handle ("Missing default: show clear error, do not fall back silently to
 * code defaults"), not a gap to paper over here.
 */
final class ClientPageSettingDefaultService
{
    /**
     * @throws \RuntimeException if no Published row exists to snapshot, or
     *   if $visualApprovalConfirmed is false. The spec requires "Cursor must
     *   visually approve the current JetPK homepage before the first
     *   production default snapshot is created" — this obviously cannot be
     *   verified in code (no test can confirm a human looked at a page),
     *   but requiring an explicit, separately-named boolean parameter that
     *   defaults to nothing and must be deliberately passed true means a
     *   caller cannot accidentally trigger this by wiring up a generic
     *   "save" action without a dedicated confirmation step in the UI.
     */
    public function saveCurrentPublishedAsDefault(
        ClientProfile $profile,
        string $pageKey,
        bool $visualApprovalConfirmed,
        ?string $label = null,
        ?string $note = null,
        ?int $userId = null,
    ): ClientPageSettingDefault {
        if (! $visualApprovalConfirmed) {
            throw new \RuntimeException(
                'Cannot save a default without explicit visual-approval confirmation. '
                .'Someone must have actually looked at the live page before it becomes the reset target.'
            );
        }

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        if ($published === null || ! is_array($published->content_json)) {
            throw new \RuntimeException('Cannot save a default: no Published content exists for this page yet.');
        }

        return $this->storeNewDefault($profile, $pageKey, $published->content_json, $published->settings_json, $label, $note, $userId);
    }

    /**
     * Generic version for setting arbitrary content as the new default —
     * e.g. promoting a specific past revision, or curated content that
     * didn't necessarily come from what's live right now. Does NOT require
     * visual-approval confirmation, on the theory that anything reaching
     * this method already went through its own separate approval path
     * (e.g. "restore this specific revision, then approve it, then save it
     * as default" is a longer, already-deliberate chain). If this method
     * ever gets its own direct UI entry point, reconsider that assumption.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>|null  $settings
     */
    public function replaceActiveDefault(
        ClientProfile $profile,
        string $pageKey,
        array $content,
        ?array $settings = null,
        ?string $label = null,
        ?string $note = null,
        ?int $userId = null,
    ): ClientPageSettingDefault {
        return $this->storeNewDefault($profile, $pageKey, $content, $settings, $label, $note, $userId);
    }

    public function getActive(ClientProfile $profile, string $pageKey): ?ClientPageSettingDefault
    {
        return ClientPageSettingDefault::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return Collection<int, ClientPageSettingDefault>
     */
    public function history(ClientProfile $profile, string $pageKey, int $limit = 20): Collection
    {
        return ClientPageSettingDefault::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function checksumFor(array $content): string
    {
        return hash('sha256', json_encode($content, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>|null  $settings
     */
    private function storeNewDefault(
        ClientProfile $profile,
        string $pageKey,
        array $content,
        ?array $settings,
        ?string $label,
        ?string $note,
        ?int $userId,
    ): ClientPageSettingDefault {
        return DB::transaction(function () use ($profile, $pageKey, $content, $settings, $label, $note, $userId): ClientPageSettingDefault {
            // Deactivate the current active default (metadata-only update — allowed
            // by the model's immutability guard, which only blocks content changes).
            ClientPageSettingDefault::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_by' => $userId]);

            return ClientPageSettingDefault::query()->create([
                'client_profile_id' => $profile->id,
                'page_key' => $pageKey,
                'schema_version' => $pageKey === \App\Support\Client\ClientPageKeys::HOME ? HomepageContentNormalizer::SCHEMA_VERSION : null,
                'content_json' => $content,
                'settings_json' => $settings,
                'checksum' => $this->checksumFor($content),
                'label' => $label,
                'note' => $note,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });
    }
}
