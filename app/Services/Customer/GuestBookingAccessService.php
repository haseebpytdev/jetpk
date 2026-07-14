<?php

namespace App\Services\Customer;

use App\Models\Booking;
use App\Models\GuestBookingAccessToken;
use App\Support\Branding\PlatformBrandingResolver;
use Illuminate\Support\Str;

class GuestBookingAccessService
{
    public function createTokenForBooking(Booking $booking, ?string $email, ?string $phone): string
    {
        $raw = Str::random(64);
        GuestBookingAccessToken::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'token_hash' => hash('sha256', $raw),
            'contact_email' => $email,
            'contact_phone' => $phone,
            'expires_at' => now()->addMinutes((int) config('ota.guest_lookup_token_minutes', 30)),
        ]);

        return $raw;
    }

    public function validateToken(Booking $booking, string $token): bool
    {
        $hash = hash('sha256', $token);
        $record = GuestBookingAccessToken::query()
            ->where('booking_id', $booking->id)
            ->where('agency_id', $booking->agency_id)
            ->where('token_hash', $hash)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();

        if ($record === null) {
            return false;
        }

        $record->forceFill(['last_used_at' => now()])->save();

        return true;
    }

    public function findBookingForLookup(string $reference, ?string $email, ?string $phone): ?Booking
    {
        if ($email === null && $phone === null) {
            return null;
        }

        $candidates = PlatformBrandingResolver::lookupReferenceCandidates($reference);

        return Booking::query()
            ->whereIn('booking_reference', $candidates)
            ->whereHas('contact', function ($query) use ($email, $phone): void {
                if ($email !== null) {
                    $query->where('email', $email);
                }
                if ($phone !== null) {
                    $email !== null
                        ? $query->orWhere('phone', $phone)
                        : $query->where('phone', $phone);
                }
            })
            ->first();
    }
}
