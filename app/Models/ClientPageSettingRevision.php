<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JETPK-HOMEPAGE-CMS Task 11: immutable page-content revision.
 *
 * A revision is a point-in-time snapshot of a ClientPageSetting row's
 * content, taken before a destructive write (publish, reset). It is never
 * updated after creation — only ever inserted or read. The boot() guard
 * below is a real enforcement mechanism, not just a comment: any attempt to
 * update an existing revision throws, so a bug elsewhere in the codebase
 * can't silently corrupt audit history.
 */
#[Fillable([
    'client_profile_id',
    'page_key',
    'source_status',
    'schema_version',
    'content_json',
    'settings_json',
    'checksum',
    'revision_reason',
    'created_by',
])]
class ClientPageSettingRevision extends Model
{
    public const REASON_BEFORE_PUBLISH = 'before_publish';

    public const REASON_BEFORE_RESET = 'before_reset';

    public const REASON_MANUAL_SNAPSHOT = 'manual_snapshot';

    public const REASON_MIGRATION = 'migration';

    public const REASON_RESTORE = 'restore';

    /**
     * No updated_at column exists on this table at all — revisions are
     * created once and never touched again.
     */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'settings_json' => 'array',
            'schema_version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $revision): void {
            throw new \LogicException(
                'ClientPageSettingRevision rows are immutable and must never be updated after creation. '
                .'If you need to correct a mistake, create a new revision instead.'
            );
        });

        static::deleting(function (self $revision): void {
            throw new \LogicException(
                'ClientPageSettingRevision rows must not be deleted individually. '
                .'Use an explicit, audited retention/pruning job if old revisions need to be purged.'
            );
        });
    }

    /** @return BelongsTo<ClientProfile, $this> */
    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
