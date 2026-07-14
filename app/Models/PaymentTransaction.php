<?php

namespace App\Models;

use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Gateway payment attempt with redacted encrypted payload snapshots.
 */
#[Fillable([
    'uuid',
    'booking_id',
    'group_booking_id',
    'user_id',
    'gateway',
    'environment',
    'amount',
    'currency',
    'client_transaction_id',
    'gateway_order_id',
    'gateway_session_id',
    'gateway_payment_url',
    'status',
    'gateway_status',
    'gateway_code',
    'gateway_message',
    'request_payload_json',
    'response_payload_json',
    'callback_payload_json',
    'verified_at',
    'paid_at',
    'failed_at',
])]
#[Hidden([
    'request_payload_json',
    'response_payload_json',
    'callback_payload_json',
])]
class PaymentTransaction extends Model
{
    protected static function booted(): void
    {
        static::creating(static function (PaymentTransaction $transaction): void {
            if (! filled($transaction->uuid)) {
                $transaction->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentTransactionStatus::class,
            'amount' => 'decimal:2',
            'request_payload_json' => 'encrypted:array',
            'response_payload_json' => 'encrypted:array',
            'callback_payload_json' => 'encrypted:array',
            'verified_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<GroupBooking, $this> */
    public function groupBooking(): BelongsTo
    {
        return $this->belongsTo(GroupBooking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentTransactionStatus::Paid;
    }

    public function canBeMarkedPaid(): bool
    {
        return ! $this->isPaid();
    }
}
