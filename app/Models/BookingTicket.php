<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'agency_id',
    'booking_id',
    'supplier_booking_id',
    'passenger_id',
    'ticket_number',
    'pnr',
    'provider',
    'airline_code',
    'status',
    'void_status',
    'issued_by',
    'issued_at',
    'voided_at',
    'raw_summary',
    'meta',
])]
class BookingTicket extends Model
{
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'raw_summary' => 'array',
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

    /** @return BelongsTo<SupplierBooking, $this> */
    public function supplierBooking(): BelongsTo
    {
        return $this->belongsTo(SupplierBooking::class);
    }

    /** @return BelongsTo<BookingPassenger, $this> */
    public function passenger(): BelongsTo
    {
        return $this->belongsTo(BookingPassenger::class, 'passenger_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return HasMany<BookingDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(BookingDocument::class);
    }

    /** @return HasMany<AgentCommissionEntry, $this> */
    public function commissionEntries(): HasMany
    {
        return $this->hasMany(AgentCommissionEntry::class);
    }
}
