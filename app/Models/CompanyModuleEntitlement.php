<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-agency (company) module entitlement override.
 */
class CompanyModuleEntitlement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'agency_id',
        'module_key',
        'enabled',
        'expires_at',
        'source',
        'assigned_by_developer_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<DeveloperUser, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class, 'assigned_by_developer_user_id');
    }
}
