<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit of platform module setting changes (Developer CP).
 */
class PlatformModuleSettingChange extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'developer_user_id',
        'module_key',
        'old_enabled',
        'new_enabled',
        'source',
        'preset_key',
        'validation_passed',
        'validation_violations',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_enabled' => 'boolean',
            'new_enabled' => 'boolean',
            'validation_passed' => 'boolean',
            'validation_violations' => 'array',
        ];
    }

    /** @return BelongsTo<DeveloperUser, $this> */
    public function developerUser(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class);
    }
}
