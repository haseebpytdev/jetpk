<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agency_id',
    'guest_booking_enabled',
    'card_payment_enabled',
    'customer_group_booking_enabled',
])]
class CommerceCheckoutSetting extends Model
{
    protected function casts(): array
    {
        return [
            'guest_booking_enabled' => 'boolean',
            'card_payment_enabled' => 'boolean',
            'customer_group_booking_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
