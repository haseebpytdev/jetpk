<?php

namespace App\Services\Client;

use App\Models\ClientPageSetting;
use App\Models\ClientPageSettingRevision;
use App\Models\ClientProfile;
use App\Support\Client\Homepage\HomepageContentNormalizer;
use Illuminate\Database\Eloquent\Collection;

/**
 * JETPK-HOMEPAGE-CMS Task 11: creates and reads immutable page-content
 * revisions. Never updates or deletes an existing revision — see
 * ClientPageSettingRevision's own boot() guard for the hard enforcement of
 * that beyond just this class's discipline.
 *
 * Tenant/page scoping: every method that reads revisions requires an
 * explicit ClientProfile and page_key and filters strictly by both — there
 * is no method that can accidentally return another tenant's revisions.
 */
final class ClientPageSettingRevisionService
{
    /**
     * Snapshot an existing ClientPageSetting row's current content into an
     * immutable revision, before the caller goes on to overwrite that row.
     * Returns null (does not throw) if the source row has no content to
     * snapshot — there's nothing meaningful to preserve, and callers
     * (publish(), reset()) should not fail just because a Draft row hadn't
     * been created yet.
     */
    public function snapshot(ClientPageSetting $source, string $reason, ?int $userId = null): ?ClientPageSettingRevision
    {
        if (! in_array($reason, $this->validReasons(), true)) {
            throw new \InvalidArgumentException("Invalid revision_reason: {$reason}");
        }

        $content = is_array($source->content_json) ? $source->content_json : null;
        if ($content === null) {
            return null;
        }

        return ClientPageSettingRevision::query()->create([
            'client_profile_id' => $source->client_profile_id,
            'page_key' => $source->page_key,
            'source_status' => $source->status instanceof \BackedEnum ? $source->status->value : (string) $source->status,
            'schema_version' => $source->page_key === \App\Support\Client\ClientPageKeys::HOME ? HomepageContentNormalizer::SCHEMA_VERSION : null,
            'content_json' => $content,
            'settings_json' => is_array($source->settings_json) ? $source->settings_json : null,
            'checksum' => $this->checksumFor($content),
            'revision_reason' => $reason,
            'created_by' => $userId,
        ]);
    }

    /**
     * sha256 of the content array's JSON encoding. This is a tamper-evidence
     * / integrity checksum, not a deduplication key — two logically
     * identical arrays with different key insertion order will produce
     * different checksums, since this does not canonicalize/sort keys
     * before hashing. That's a deliberate simplification: the goal is
     * detecting corruption of a specific stored payload, not detecting
     * whether two different payloads are semantically equivalent.
     *
     * @param  array<string, mixed>  $content
     */
    public function checksumFor(array $content): string
    {
        return hash('sha256', json_encode($content, JSON_THROW_ON_ERROR));
    }

    /**
     * @return Collection<int, ClientPageSettingRevision>
     */
    public function listFor(ClientProfile $profile, string $pageKey, int $limit = 20): Collection
    {
        return ClientPageSettingRevision::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function find(ClientProfile $profile, string $pageKey, int $revisionId): ?ClientPageSettingRevision
    {
        return ClientPageSettingRevision::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $pageKey)
            ->where('id', $revisionId)
            ->first();
    }

    /**
     * Verifies a revision's stored checksum still matches its stored
     * content — a cheap way to detect accidental corruption (e.g. a raw SQL
     * edit) independent of the immutability guard on the model itself.
     */
    public function verifyIntegrity(ClientPageSettingRevision $revision): bool
    {
        if (! is_array($revision->content_json)) {
            return false;
        }

        return hash_equals($revision->checksum, $this->checksumFor($revision->content_json));
    }

    /**
     * @return list<string>
     */
    private function validReasons(): array
    {
        return [
            ClientPageSettingRevision::REASON_BEFORE_PUBLISH,
            ClientPageSettingRevision::REASON_BEFORE_RESET,
            ClientPageSettingRevision::REASON_MANUAL_SNAPSHOT,
            ClientPageSettingRevision::REASON_MIGRATION,
            ClientPageSettingRevision::REASON_RESTORE,
        ];
    }
}
