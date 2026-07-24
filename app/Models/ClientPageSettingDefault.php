<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JETPK-HOMEPAGE-CMS Task 12: a saved, page-specific, tenant-specific
 * default that Admin can explicitly reset content back to.
 *
 * Content is immutable once created — same philosophy as
 * ClientPageSettingRevision, and for the same reason: "replacing" a default
 * (spec action 6) means deactivating the old row and creating a new one,
 * which naturally satisfies "preserve prior default as history" (spec
 * action 7) by just... not deleting the old row. label/note/is_active ARE
 * editable, since those are metadata about the snapshot, not the snapshot
 * itself.
 */
#[Fillable([
    'client_profile_id',
    'page_key',
    'schema_version',
    'content_json',
    'settings_json',
    'checksum',
    'label',
    'note',
    'is_active',
    'created_by',
    'updated_by',
])]
class ClientPageSettingDefault extends Model
{
    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'settings_json' => 'array',
            'schema_version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $default): void {
            $immutableFields = ['client_profile_id', 'page_key', 'content_json', 'settings_json', 'checksum', 'schema_version'];
            $changedImmutableFields = array_intersect($immutableFields, array_keys($default->getDirty()));

            if ($changedImmutableFields !== []) {
                throw new \LogicException(
                    'ClientPageSettingDefault content is immutable once created — attempted to change: '
                    .implode(', ', $changedImmutableFields)
                    .'. To change the saved content, create a new default and deactivate this one instead.'
                );
            }
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

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
