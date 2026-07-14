<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_profile_id',
    'source_logo_path',
    'primary',
    'secondary',
    'accent',
    'background',
    'surface',
    'text',
    'muted',
    'success',
    'warning',
    'danger',
    'palette_json',
    'generated_at',
    'approved_at',
    'approved_by',
])]
class ClientThemePalette extends Model
{
    protected function casts(): array
    {
        return [
            'palette_json' => 'array',
            'generated_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ClientProfile, $this> */
    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }
}
