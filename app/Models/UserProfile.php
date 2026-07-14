<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'phone',
    'whatsapp',
    'country_code',
    'city',
    'date_of_birth',
    'gender',
    'nationality',
    'passport_number',
    'passport_issuing_country',
    'passport_expiry_date',
    'national_id',
    'emergency_contact_name',
    'emergency_contact_phone',
    'profile_photo_path',
])]
class UserProfile extends Model
{
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_expiry_date' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
