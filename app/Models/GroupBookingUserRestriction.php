<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'unpaid_release_count',
    'blocked_at',
    'last_release_at',
    'reset_at',
    'reset_by',
    'reset_note',
])]
class GroupBookingUserRestriction extends Model
{
    protected function casts(): array
    {
        return [
            'unpaid_release_count' => 'integer',
            'blocked_at' => 'datetime',
            'last_release_at' => 'datetime',
            'reset_at' => 'datetime',
        ];
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null && $this->reset_at === null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resetByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reset_by');
    }
}
