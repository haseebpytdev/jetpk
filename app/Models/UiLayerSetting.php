<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-layer enable override for Developer CP (missing row = config/env default).
 */
class UiLayerSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'layer_key',
        'enabled',
        'notes',
        'updated_by_developer_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<DeveloperUser, $this> */
    public function updatedByDeveloper(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class, 'updated_by_developer_user_id');
    }
}
