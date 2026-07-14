<?php

namespace App\Models;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerNormalBalance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'account_type',
    'normal_balance',
    'agency_id',
    'currency',
    'is_system',
    'is_active',
    'properties',
])]
class LedgerAccount extends Model
{
    protected function casts(): array
    {
        return [
            'account_type' => LedgerAccountType::class,
            'normal_balance' => LedgerNormalBalance::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'properties' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (LedgerAccount $account): bool {
            if (LedgerEntry::query()->where('ledger_account_id', $account->id)->exists()) {
                return false;
            }

            return true;
        });
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
