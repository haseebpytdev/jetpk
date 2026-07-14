<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agency_id',
    'provider',
    'api_endpoint',
    'api_key',
    'timeout_seconds',
    'max_source_bytes',
    'max_source_pixels',
    'is_enabled',
    'default_for_logos',
    'meta',
])]
#[Hidden(['api_key'])]
class BackgroundRemovalSetting extends Model
{
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
            'default_for_logos' => 'boolean',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function maskedApiKey(): ?string
    {
        return filled($this->api_key) ? '********' : null;
    }
}
