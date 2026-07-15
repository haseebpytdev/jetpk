<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_profile_id',
    'page_key',
    'asset_key',
    'disk',
    'path',
    'public_url',
    'alt_text',
    'meta_json',
    'created_by',
])]
class ClientPageAsset extends Model
{
    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
        ];
    }

    /** @return BelongsTo<ClientProfile, $this> */
    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }
}
