<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'event', 'channel', 'subject', 'body', 'is_enabled', 'variables', 'meta'])]
class AgencyMessageTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'variables' => 'array',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
