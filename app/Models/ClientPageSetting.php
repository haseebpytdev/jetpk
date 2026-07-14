<?php

namespace App\Models;

use App\Enums\ClientPageSettingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_profile_id',
    'page_key',
    'status',
    'title',
    'seo_title',
    'seo_description',
    'content_json',
    'settings_json',
    'published_at',
    'created_by',
    'updated_by',
])]
class ClientPageSetting extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ClientPageSettingStatus::class,
            'content_json' => 'array',
            'settings_json' => 'array',
            'published_at' => 'datetime',
        ];
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
