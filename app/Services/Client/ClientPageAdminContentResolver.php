<?php

namespace App\Services\Client;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientPagePublicFallbackCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves Admin Page Settings form content with explicit precedence and empty-value preservation.
 */
final class ClientPageAdminContentResolver
{
    public const SOURCE_DRAFT = 'draft';

    public const SOURCE_PUBLISHED = 'published';

    public const SOURCE_PUBLIC_FALLBACK = 'public_fallback';

    public const SOURCE_EMPTY = 'empty';

    public function __construct(
        private readonly ClientPageContentResolver $contentResolver,
    ) {}

    /**
     * Admin edit form: draft when present, else published, else JetPK public fallback, else [].
     *
     * @return array<string, mixed>
     */
    public function formContentFor(ClientProfile $profile, string $pageKey): array
    {
        if (! Schema::hasTable('client_page_settings')) {
            return ClientPagePublicFallbackCatalog::contentFor($pageKey);
        }

        $draft = $this->row($profile->id, $pageKey, ClientPageSettingStatus::Draft);
        if ($draft !== null && is_array($draft->content_json)) {
            return $draft->content_json;
        }

        $published = $this->row($profile->id, $pageKey, ClientPageSettingStatus::Published);
        if ($published !== null && is_array($published->content_json)) {
            return $published->content_json;
        }

        $fallback = ClientPagePublicFallbackCatalog::contentFor($pageKey);

        return $fallback !== [] ? $fallback : [];
    }

  /**
   * Effective public content: published row, else JetPK public fallback (no master merge).
   *
   * @return array<string, mixed>
   */
    public function effectivePublicContent(ClientProfile $profile, string $pageKey): array
    {
        if (! Schema::hasTable('client_page_settings')) {
            return ClientPagePublicFallbackCatalog::contentFor($pageKey);
        }

        $published = $this->row($profile->id, $pageKey, ClientPageSettingStatus::Published);
        if ($published !== null && is_array($published->content_json)) {
            return $published->content_json;
        }

        return ClientPagePublicFallbackCatalog::contentFor($pageKey);
    }

    public function effectiveSource(ClientProfile $profile, string $pageKey): string
    {
        if (! Schema::hasTable('client_page_settings')) {
            return ClientPagePublicFallbackCatalog::contentFor($pageKey) !== []
                ? self::SOURCE_PUBLIC_FALLBACK
                : self::SOURCE_EMPTY;
        }

        if ($this->row($profile->id, $pageKey, ClientPageSettingStatus::Published) !== null) {
            return self::SOURCE_PUBLISHED;
        }

        return ClientPagePublicFallbackCatalog::contentFor($pageKey) !== []
            ? self::SOURCE_PUBLIC_FALLBACK
            : self::SOURCE_EMPTY;
    }

    public function formSource(ClientProfile $profile, string $pageKey): string
    {
        if (! Schema::hasTable('client_page_settings')) {
            return ClientPagePublicFallbackCatalog::contentFor($pageKey) !== []
                ? self::SOURCE_PUBLIC_FALLBACK
                : self::SOURCE_EMPTY;
        }

        if ($this->row($profile->id, $pageKey, ClientPageSettingStatus::Draft) !== null) {
            return self::SOURCE_DRAFT;
        }

        if ($this->row($profile->id, $pageKey, ClientPageSettingStatus::Published) !== null) {
            return self::SOURCE_PUBLISHED;
        }

        return ClientPagePublicFallbackCatalog::contentFor($pageKey) !== []
            ? self::SOURCE_PUBLIC_FALLBACK
            : self::SOURCE_EMPTY;
    }

    /**
     * @return array{draft: bool, published: bool, form_source: string, effective_source: string, updated_at: ?string}
     */
    public function editorMeta(ClientProfile $profile, string $pageKey): array
    {
        $draft = $this->row($profile->id, $pageKey, ClientPageSettingStatus::Draft);
        $published = $this->row($profile->id, $pageKey, ClientPageSettingStatus::Published);

        return [
            'draft' => $draft !== null,
            'published' => $published !== null,
            'form_source' => $this->formSource($profile, $pageKey),
            'effective_source' => $this->effectiveSource($profile, $pageKey),
            'updated_at' => $draft?->updated_at?->toIso8601String()
                ?? $published?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Count fields intentionally empty in saved content (key present, value empty).
     *
     * @param  array<string, mixed>  $content
     */
    public function intentionalEmptyFieldCount(array $content, string $pageKey): int
    {
        $count = 0;
        foreach (ClientPagePublicFallbackCatalog::fieldPathsFor($pageKey) as $path) {
            if (! Arr::has($content, $path)) {
                continue;
            }
            $value = data_get($content, $path);
            if ($value === '' || $value === null || $value === []) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $formContent
     * @param  array<string, mixed>  $effectiveContent
     */
    public function parityStatus(array $formContent, array $effectiveContent, string $pageKey): string
    {
        foreach (ClientPagePublicFallbackCatalog::fieldPathsFor($pageKey) as $path) {
            if (! Arr::has($effectiveContent, $path)) {
                continue;
            }
            $effective = data_get($effectiveContent, $path);
            $form = Arr::has($formContent, $path) ? data_get($formContent, $path) : null;
            if ($form !== $effective) {
                return 'mismatch';
            }
        }

        return 'ok';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function backfillPayloadIfMissing(ClientProfile $profile, string $pageKey): ?array
    {
        if (! Schema::hasTable('client_page_settings')) {
            return null;
        }

        $hasDraft = $this->row($profile->id, $pageKey, ClientPageSettingStatus::Draft) !== null;
        $hasPublished = $this->row($profile->id, $pageKey, ClientPageSettingStatus::Published) !== null;
        if ($hasDraft || $hasPublished) {
            return null;
        }

        $fallback = ClientPagePublicFallbackCatalog::contentFor($pageKey);

        return $fallback !== [] ? $fallback : null;
    }

    private function row(int $profileId, string $pageKey, ClientPageSettingStatus $status): ?ClientPageSetting
    {
        return ClientPageSetting::query()
            ->where('client_profile_id', $profileId)
            ->where('page_key', $pageKey)
            ->where('status', $status)
            ->first();
    }
}
