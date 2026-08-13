<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'agency_id',
    'scope_key',
    'name',
    'slug',
    'description',
    'is_system',
    'is_protected',
    'created_by',
])]
class Role extends Model
{
    public const SCOPE_PLATFORM = 'platform';

    protected static function booted(): void
    {
        static::saving(static function (Role $role): void {
            $role->scope_key = $role->agency_id === null
                ? self::SCOPE_PLATFORM
                : (string) $role->agency_id;
        });
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_protected' => 'boolean',
            'agency_id' => 'integer',
        ];
    }

    public function isPlatformScoped(): bool
    {
        return $this->agency_id === null;
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<RolePermission, $this> */
    public function permissionRows(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * @return list<string>
     */
    public function grantedPermissionKeys(): array
    {
        return $this->permissionRows
            ->where('granted', true)
            ->pluck('permission_key')
            ->map(fn ($key): string => (string) $key)
            ->values()
            ->all();
    }
}
