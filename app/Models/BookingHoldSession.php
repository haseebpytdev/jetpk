<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'agency_id',
    'booking_id',
    'search_id',
    'offer_id',
    'supplier_provider',
    'supplier_connection_id',
    'supplier_offer_id',
    'supplier_order_id',
    'supplier_order_reference',
    'hold_status',
    'requires_instant_payment',
    'price_guarantee_expires_at',
    'payment_required_by',
    'local_checkout_expires_at',
    'hold_expires_at',
    'validated_total_amount',
    'validated_total_currency',
    'converted_total_pkr',
    'markup_snapshot',
    'passenger_counts',
    'passenger_pricing',
    'passenger_pricing_available',
    'validated_offer_snapshot',
    'hold_order_snapshot',
    'safe_error',
    'last_error_safe',
    'meta',
    'expires_at',
    'created_by_user_id',
])]
class BookingHoldSession extends Model
{
    protected function casts(): array
    {
        return [
            'price_guarantee_expires_at' => 'datetime',
            'payment_required_by' => 'datetime',
            'local_checkout_expires_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'expires_at' => 'datetime',
            'requires_instant_payment' => 'boolean',
            'validated_total_amount' => 'decimal:2',
            'converted_total_pkr' => 'decimal:2',
            'markup_snapshot' => 'array',
            'passenger_counts' => 'array',
            'passenger_pricing' => 'array',
            'passenger_pricing_available' => 'boolean',
            'validated_offer_snapshot' => 'array',
            'hold_order_snapshot' => 'array',
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

    /** @return BelongsTo<SupplierConnection, $this> */
    public function supplierConnection(): BelongsTo
    {
        return $this->belongsTo(SupplierConnection::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasOne<Booking, $this> */
    public function linkedBooking(): HasOne
    {
        return $this->hasOne(Booking::class, 'hold_session_id');
    }
}
