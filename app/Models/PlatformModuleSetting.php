<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-module deployment override for Developer CP (missing row = registry default).
 */
class PlatformModuleSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'module_key',
        'enabled',
        'locked',
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
            'locked' => 'boolean',
        ];
    }

    /** @return BelongsTo<DeveloperUser, $this> */
    public function updatedByDeveloper(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class, 'updated_by_developer_user_id');
    }
}
