<?php

namespace App\Models;

use App\Enums\BookingDocumentStatus;
use App\Enums\BookingDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agency_id',
    'booking_id',
    'booking_payment_id',
    'booking_ticket_id',
    'document_type',
    'document_number',
    'title',
    'file_path',
    'status',
    'generated_by',
    'generated_at',
    'meta',
])]
class BookingDocument extends Model
{
    protected function casts(): array
    {
        return [
            'document_type' => BookingDocumentType::class,
            'status' => BookingDocumentStatus::class,
            'generated_at' => 'datetime',
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

    /** @return BelongsTo<BookingTicket, $this> */
    public function bookingTicket(): BelongsTo
    {
        return $this->belongsTo(BookingTicket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
