<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'group_booking_id',
    'title',
    'first_name',
    'last_name',
    'gender',
    'date_of_birth',
    'passport_number',
    'passport_issue_date',
    'passport_expiry',
    'nationality',
    'document_type',
    'passenger_type',
    'sort_order',
])]
class GroupBookingPassenger extends Model
{
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function fullName(): string
    {
        return trim(($this->title ? $this->title.' ' : '').$this->first_name.' '.$this->last_name);
    }

    /** @return BelongsTo<GroupBooking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(GroupBooking::class, 'group_booking_id');
    }
}
