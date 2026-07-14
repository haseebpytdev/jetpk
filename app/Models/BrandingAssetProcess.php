<?php

namespace App\Models;

use App\Enums\BrandingAssetProcessStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'agency_id',
    'user_id',
    'asset_type',
    'provider',
    'source_path',
    'result_path',
    'status',
    'source_checksum',
    'result_checksum',
    'source_mime',
    'result_mime',
    'width',
    'height',
    'source_size',
    'result_size',
    'transparent_ratio',
    'opaque_ratio',
    'provider_request_id',
    'error_code',
    'error_message_safe',
    'processing_ms',
    'warnings',
    'accepted_at',
    'discarded_at',
    'expires_at',
])]
class BrandingAssetProcess extends Model
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'status' => BrandingAssetProcessStatus::class,
            'warnings' => 'array',
            'accepted_at' => 'datetime',
            'discarded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
