<?php

namespace App\Models;

use App\Support\Ai\Embed\EmbedTenantCapability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiEmbedTenant extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'public_id',
        'slug',
        'display_name',
        'assistant_name',
        'status',
        'embed_enabled',
        'knowledge_namespace',
        'allowed_origins',
        'capabilities',
        'theme',
        'branding',
        'settings',
    ];

    protected static function booted(): void
    {
        static::creating(static function (AiEmbedTenant $tenant): void {
            if (! filled($tenant->public_id)) {
                $tenant->public_id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embed_enabled' => 'boolean',
            'allowed_origins' => 'array',
            'capabilities' => 'array',
            'theme' => 'array',
            'branding' => 'array',
            'settings' => 'array',
        ];
    }

    /** @return HasMany<AiEmbedTenantKey, $this> */
    public function keys(): HasMany
    {
        return $this->hasMany(AiEmbedTenantKey::class);
    }

    public function isOperational(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->embed_enabled
            && $this->keys()->where('status', AiEmbedTenantKey::STATUS_ACTIVE)->exists();
    }

    public function hasCapability(string $capability): bool
    {
        $caps = $this->capabilities;
        if (! is_array($caps)) {
            return false;
        }

        return in_array($capability, $caps, true);
    }

    /**
     * @return list<string>
     */
    public function normalizedAllowedOrigins(): array
    {
        $origins = $this->allowed_origins;
        if (! is_array($origins)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($origin): string => trim((string) $origin),
            $origins
        )));
    }

    /**
     * @return list<string>
     */
    public static function defaultCapabilities(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public static function jetPakistanCapabilities(): array
    {
        return EmbedTenantCapability::all();
    }
}
