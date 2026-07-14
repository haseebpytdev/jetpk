<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'booking_id',
    'base_fare',
    'taxes',
    'fees',
    'markup',
    'discount',
    'total',
    'currency',
    'breakdown',
])]
class BookingFareBreakdown extends Model
{
    protected function casts(): array
    {
        return [
            'base_fare' => 'decimal:2',
            'taxes' => 'decimal:2',
            'fees' => 'decimal:2',
            'markup' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
