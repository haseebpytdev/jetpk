<?php

namespace App\Models;

use App\Enums\PromoRedemptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'promo_code_id',
    'booking_id',
    'group_booking_id',
    'user_id',
    'session_id',
    'code',
    'original_amount',
    'discount_amount',
    'final_amount',
    'currency',
    'status',
    'applied_at',
    'redeemed_at',
])]
class PromoRedemption extends Model
{
    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'status' => PromoRedemptionStatus::class,
            'applied_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PromoCode, $this> */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
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
}
