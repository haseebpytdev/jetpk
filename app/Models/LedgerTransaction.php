<?php

namespace App\Models;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

#[Fillable([
    'transaction_ref',
    'source_type',
    'source_id',
    'agency_id',
    'booking_id',
    'customer_id',
    'guest_key',
    'actor_user_id',
    'actor_identifier',
    'transaction_type',
    'status',
    'currency',
    'amount_total',
    'description',
    'occurred_at',
    'posted_at',
    'reversed_at',
    'reversal_of_id',
    'properties',
])]
class LedgerTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'transaction_type' => LedgerTransactionType::class,
            'status' => LedgerTransactionStatus::class,
            'amount_total' => 'decimal:2',
            'occurred_at' => 'datetime',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
            'properties' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (LedgerTransaction $transaction): void {
            $originalStatus = $transaction->getRawOriginal('status');
            if ($originalStatus === LedgerTransactionStatus::Posted->value) {
                $dirty = array_keys($transaction->getDirty());
                $allowed = ['reversed_at', 'status', 'updated_at'];
                if (array_diff($dirty, $allowed) !== []) {
                    throw new RuntimeException('Posted ledger transactions cannot be modified.');
                }
            }
        });

        static::deleting(function (LedgerTransaction $transaction): bool {
            if ($transaction->status === LedgerTransactionStatus::Posted) {
                return false;
            }

            return true;
        });
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return HasMany<LedgerTransaction, $this> */
    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function isPosted(): bool
    {
        return $this->status === LedgerTransactionStatus::Posted;
    }
}
