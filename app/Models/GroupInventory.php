<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Synced group ticketing inventory (Al-Haider or future suppliers).
 */
#[Fillable([
    'supplier',
    'supplier_package_id',
    'public_id',
    'group_category_id',
    'title',
    'sector',
    'airline_id',
    'airline_name',
    'package_type',
    'departure_date',
    'return_date',
    'total_seats',
    'held_seats',
    'sold_seats',
    'price',
    'price_child',
    'price_infant',
    'currency',
    'baggage',
    'refund_change_notes',
    'snapshot',
    'is_active',
    'synced_at',
])]
class GroupInventory extends Model
{
    protected function casts(): array
    {
        return [
            'group_category_id' => 'integer',
            'airline_id' => 'integer',
            'departure_date' => 'date',
            'return_date' => 'date',
            'total_seats' => 'integer',
            'held_seats' => 'integer',
            'sold_seats' => 'integer',
            'price' => 'decimal:2',
            'price_child' => 'decimal:2',
            'price_infant' => 'decimal:2',
            'snapshot' => 'array',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function availableSeats(): int
    {
        return max(0, $this->total_seats - $this->held_seats - $this->sold_seats);
    }

    public function hasAvailability(int $requested = 1): bool
    {
        return $this->is_active && $this->availableSeats() >= max(1, $requested);
    }

    /** @return BelongsTo<GroupCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GroupCategory::class, 'group_category_id');
    }

    /** @return HasMany<GroupBooking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(GroupBooking::class);
    }
}
