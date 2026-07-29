<?php

namespace App\Support\CustomerPortal;

use App\Models\User;
use App\Support\Geo\CountryList;
use Illuminate\Support\Facades\Storage;

/**
 * Customer profile JSON for Next.js dashboard.
 */
class CustomerPortalProfilePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        $profile = $user->profile()->firstOrCreate([]);

        return [
            'ok' => true,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'email_verified' => $user->email_verified_at !== null,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ],
            'profile' => [
                'phone' => $profile->phone,
                'whatsapp' => $profile->whatsapp,
                'country_code' => $profile->country_code,
                'city' => $profile->city,
                'date_of_birth' => $profile->date_of_birth?->toDateString(),
                'gender' => $profile->gender,
                'nationality' => $profile->nationality,
                'passport_number' => $profile->passport_number,
                'passport_issuing_country' => $profile->passport_issuing_country,
                'passport_expiry_date' => $profile->passport_expiry_date?->toDateString(),
                'national_id' => $profile->national_id,
                'emergency_contact_name' => $profile->emergency_contact_name,
                'emergency_contact_phone' => $profile->emergency_contact_phone,
                'profile_photo_url' => filled($profile->profile_photo_path)
                    ? Storage::disk('public')->url($profile->profile_photo_path)
                    : null,
            ],
            'countries' => CountryList::forSelect(),
            'update_url' => '/laravel/profile',
            'password_update_url' => '/laravel/password',
            'supported_fields' => [
                'name', 'email', 'username', 'phone', 'whatsapp', 'country_code', 'city',
                'date_of_birth', 'gender', 'nationality', 'passport_number',
                'passport_issuing_country', 'passport_expiry_date', 'national_id',
                'emergency_contact_name', 'emergency_contact_phone', 'profile_photo',
            ],
        ];
    }
}
