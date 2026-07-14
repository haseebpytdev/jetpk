<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'first_name',
    'last_name',
    'email',
    'mobile',
    'company_name',
    'business_type',
    'city',
    'country',
    'office_address',
    'website',
    'cnic',
    'ntn',
    'iata_number',
    'years_in_business',
    'expected_booking_volume',
    'services_interested',
    'notes',
    'status',
    'reviewed_by',
    'reviewed_at',
    'internal_note',
])]
class AgentApplication extends Model
{
    protected function casts(): array
    {
        return [
            'services_interested' => 'array',
            'reviewed_at' => 'datetime',
            'years_in_business' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
