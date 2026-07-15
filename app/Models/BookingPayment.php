<?php

namespace App\Models;

use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'agency_id',
    'booking_id',
    'payer_user_id',
    'received_by',
    'payment_reference',
    'method',
    'status',
    'amount',
    'currency',
    'proof_path',
    'notes',
    'submitted_at',
    'verified_at',
    'rejected_at',
    'meta',
])]
class BookingPayment extends Model
{
    protected function casts(): array
    {
        return [
            'method' => BookingPaymentMethod::class,
            'status' => BookingPaymentStatus::class,
            'amount' => 'decimal:2',
            'meta' => 'array',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
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
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<BookingDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(BookingDocument::class);
    }

    /** @return HasMany<BookingRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class);
    }
}
