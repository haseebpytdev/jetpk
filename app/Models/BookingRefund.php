<?php

namespace App\Models;

use App\Enums\BookingRefundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agency_id',
    'booking_id',
    'booking_payment_id',
    'cancellation_request_id',
    'amount',
    'currency',
    'method',
    'status',
    'reference',
    'notes',
    'approved_by',
    'approved_at',
    'paid_by',
    'paid_at',
    'meta',
])]
class BookingRefund extends Model
{
    protected function casts(): array
    {
        return [
            'status' => BookingRefundStatus::class,
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'meta' => 'array',
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

    /** @return BelongsTo<BookingPayment, $this> */
    public function bookingPayment(): BelongsTo
    {
        return $this->belongsTo(BookingPayment::class);
    }

    /** @return BelongsTo<BookingCancellationRequest, $this> */
    public function cancellationRequest(): BelongsTo
    {
        return $this->belongsTo(BookingCancellationRequest::class, 'cancellation_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
