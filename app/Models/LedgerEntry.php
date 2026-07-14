<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

#[Fillable([
    'ledger_transaction_id',
    'ledger_account_id',
    'agency_id',
    'booking_id',
    'debit',
    'credit',
    'currency',
    'description',
])]
class LedgerEntry extends Model
{
    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (LedgerEntry $entry): void {
            $debit = (float) $entry->debit;
            $credit = (float) $entry->credit;

            if ($debit > 0 && $credit > 0) {
                throw new RuntimeException('Ledger entry cannot have both debit and credit positive.');
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new RuntimeException('Ledger entry must have a positive debit or credit.');
            }
        });

        static::updating(function (LedgerEntry $entry): void {
            $transaction = $entry->transaction ?? LedgerTransaction::query()->find($entry->ledger_transaction_id);
            if ($transaction?->isPosted()) {
                throw new RuntimeException('Posted ledger entries cannot be modified.');
            }
        });

        static::deleting(function (LedgerEntry $entry): bool {
            $transaction = $entry->transaction ?? LedgerTransaction::query()->find($entry->ledger_transaction_id);
            if ($transaction?->isPosted()) {
                return false;
            }

            return true;
        });
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
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
}
