<?php

namespace App\Models;

use App\Enums\PromoCodeAppliesTo;
use App\Enums\PromoCodeStatus;
use App\Enums\PromoCodeType;
use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'agency_id',
    'code',
    'name',
    'type',
    'value',
    'currency',
    'min_amount',
    'max_discount',
    'starts_at',
    'ends_at',
    'usage_limit',
    'used_count',
    'per_user_limit',
    'applies_to',
    'status',
    'internal_testing_only',
    'created_by',
    'updated_by',
])]
class PromoCode extends Model
{
    /** @use HasFactory<PromoCodeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => PromoCodeType::class,
            'value' => 'decimal:4',
            'min_amount' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'per_user_limit' => 'integer',
            'internal_testing_only' => 'boolean',
            'applies_to' => PromoCodeAppliesTo::class,
            'status' => PromoCodeStatus::class,
        ];
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

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<PromoRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }

    public function normalizedCode(): string
    {
        return strtoupper(trim($this->code));
    }
}
