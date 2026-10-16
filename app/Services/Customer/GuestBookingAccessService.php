<?php

namespace App\Services\Customer;

use App\Models\Booking;
use App\Models\GuestBookingAccessToken;
use App\Support\Branding\PlatformBrandingResolver;
use App\Support\Phone\PhoneNumberNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestBookingAccessService
{
    public function sessionKey(Booking $booking): string
    {
        return 'guest_booking_access.'.$booking->id;
    }

    /**
     * Bind the raw guest token into the encrypted session for cookie-based auth.
     * Also mirrors the legacy AbhiPay session key so confirmation/card flows keep working.
     */
    public function bindSession(Request $request, Booking $booking, string $rawToken): void
    {
        $request->session()->put($this->sessionKey($booking), $rawToken);
        $request->session()->put('guest_abhipay_token_booking_'.$booking->id, $rawToken);
    }

    public function clearSession(Request $request, Booking $booking): void
    {
        $request->session()->forget($this->sessionKey($booking));
        $request->session()->forget('guest_abhipay_token_booking_'.$booking->id);
    }

    public function rawTokenFromSession(Request $request, Booking $booking): string
    {
        $token = (string) $request->session()->get($this->sessionKey($booking), '');
        if ($token !== '') {
            return $token;
        }

        return (string) $request->session()->get('guest_abhipay_token_booking_'.$booking->id, '');
    }

    /**
     * Authorize guest access via session (preferred) or route/body token (compat).
     */
    public function authorizeGuestAccess(Request $request, Booking $booking, ?string $routeToken = null): bool
    {
        $sessionToken = $this->rawTokenFromSession($request, $booking);
        if ($sessionToken !== '' && $this->validateToken($booking, $sessionToken)) {
            return true;
        }

        $token = (string) ($routeToken ?? '');
        if ($token === '') {
            $token = (string) $request->input('guest_token', '');
        }
        if ($token === '') {
            $token = (string) $request->query('token', '');
        }

        if ($token !== '' && $this->validateToken($booking, $token)) {
            $this->bindSession($request, $booking, $token);

            return true;
        }

        return false;
    }

    public function createTokenForBooking(Booking $booking, ?string $email, ?string $phone): string
    {
        return DB::transaction(function () use ($booking, $email, $phone): string {
            // Lock existing active rows for this booking/agency so concurrent creates cannot leave two valids.
            GuestBookingAccessToken::query()
                ->where('booking_id', $booking->id)
                ->where('agency_id', $booking->agency_id)
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->get()
                ->each(function (GuestBookingAccessToken $row): void {
                    $row->forceFill(['expires_at' => now()])->save();
                });

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
        });
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
        $normalizedEmail = is_string($email) ? strtolower(trim($email)) : null;
        $normalizedPhone = is_string($phone) ? $this->normalizeLookupPhone($phone) : null;

        $bookings = Booking::query()
            ->whereIn('booking_reference', $candidates)
            ->with('contact')
            ->get();

        foreach ($bookings as $booking) {
            $contact = $booking->contact;
            if ($contact === null) {
                continue;
            }

            $emailMatch = $normalizedEmail !== null
                && strtolower(trim((string) $contact->email)) === $normalizedEmail;
            $phoneMatch = $normalizedPhone !== null
                && $this->normalizeLookupPhone((string) $contact->phone) === $normalizedPhone;

            // When both factors are supplied, require AND match so one correct
            // factor cannot compensate for an incorrect second factor (OPS-DEF-0005).
            if ($normalizedEmail !== null && $normalizedPhone !== null && $emailMatch && $phoneMatch) {
                return $booking;
            }
            if ($normalizedEmail !== null && $normalizedPhone === null && $emailMatch) {
                return $booking;
            }
            if ($normalizedPhone !== null && $normalizedEmail === null && $phoneMatch) {
                return $booking;
            }
        }

        return null;
    }

    private function normalizeLookupPhone(string $phone): string
    {
        $parts = PhoneNumberNormalizer::splitForSupplierDialing($phone, '92');

        return $parts['e164'] !== '' ? $parts['e164'] : '';
    }
}
