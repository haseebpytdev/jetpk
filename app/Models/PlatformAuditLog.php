<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only Dev CP / platform-owner audit log.
 */
class PlatformAuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'action',
        'subject_type',
        'subject_id',
        'developer_user_id',
        'agency_id',
        'properties',
        'ip_address',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): bool {
            return false;
        });

        static::deleting(static function (): bool {
            return false;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<DeveloperUser, $this> */
    public function developerUser(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class);
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
