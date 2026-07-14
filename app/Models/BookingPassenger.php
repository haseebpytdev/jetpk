<?php

namespace App\Models;

use Database\Factories\BookingPassengerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'booking_id',
    'passenger_index',
    'passenger_type',
    'is_lead_passenger',
    'title',
    'first_name',
    'last_name',
    'date_of_birth',
    'nationality',
    'gender',
    'passport_number',
    'passport_issuing_country',
    'passport_expiry_date',
    'passport_issue_date',
    'document_type',
    'national_id_number',
    'country_of_residence',
    'place_of_birth',
    'meta',
])]
class BookingPassenger extends Model
{
    /** @use HasFactory<BookingPassengerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_lead_passenger' => 'boolean',
            'date_of_birth' => 'date',
            'passport_expiry_date' => 'date',
            'passport_issue_date' => 'date',
            'meta' => 'array',
        ];
    }

    public function isAdult(): bool
    {
        return $this->passenger_type === 'adult';
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
