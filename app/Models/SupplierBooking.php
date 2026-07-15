<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'agency_id',
    'booking_id',
    'supplier_connection_id',
    'provider',
    'supplier_reference',
    'supplier_api_booking_id',
    'pnr',
    'status',
    'raw_summary',
    'created_by',
    'created_at_supplier',
])]
class SupplierBooking extends Model
{
    protected function casts(): array
    {
        return [
            'raw_summary' => 'array',
            'created_at_supplier' => 'datetime',
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

    /** @return BelongsTo<SupplierConnection, $this> */
    public function supplierConnection(): BelongsTo
    {
        return $this->belongsTo(SupplierConnection::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<BookingTicket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(BookingTicket::class);
    }

    /** @return HasMany<TicketingAttempt, $this> */
    public function ticketingAttempts(): HasMany
    {
        return $this->hasMany(TicketingAttempt::class);
    }
}
