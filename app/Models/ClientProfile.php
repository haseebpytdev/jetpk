<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Deployment-level client profile stored in Dev CP DB (MC-2).
 *
 * Runtime still reads config/ota_client.php via App\Support\Client\ClientProfile until wired.
 */
#[Fillable([
    'uuid',
    'name',
    'slug',
    'domain',
    'preview_path',
    'environment',
    'active_frontend_theme',
    'active_admin_theme',
    'active_staff_theme',
    'asset_profile',
    'default_locale',
    'timezone',
    'currency',
    'is_master_profile',
    'is_active',
])]
class ClientProfile extends Model
{
    protected static function booted(): void
    {
        static::creating(function (ClientProfile $profile): void {
            if ($profile->uuid === null || $profile->uuid === '') {
                $profile->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_master_profile' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ClientProfileModule, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(ClientProfileModule::class);
    }

    /** @return HasMany<ClientProfileSupplier, $this> */
    public function suppliers(): HasMany
    {
        return $this->hasMany(ClientProfileSupplier::class);
    }

    /** @return HasOne<ClientProfileBranding, $this> */
    public function branding(): HasOne
    {
        return $this->hasOne(ClientProfileBranding::class);
    }
}
