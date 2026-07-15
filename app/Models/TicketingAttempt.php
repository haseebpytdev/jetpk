<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agency_id',
    'booking_id',
    'supplier_booking_id',
    'provider',
    'status',
    'request_payload',
    'response_payload',
    'safe_summary',
    'error_code',
    'error_message',
    'attempted_by',
    'attempted_at',
    'completed_at',
])]
class TicketingAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'safe_summary' => 'array',
            'attempted_at' => 'datetime',
            'completed_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function attemptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attempted_by');
    }
}
