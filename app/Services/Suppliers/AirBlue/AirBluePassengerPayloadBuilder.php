<?php

namespace App\Services\Suppliers\AirBlue;

use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use App\Support\Phone\SupplierContactFormatter;

/**
 * Builds passenger and contact payloads for AirBlue Zapways OTA booking requests.
 */
class AirBluePassengerPayloadBuilder
{
    /**
     * @return list<array<string, mixed>>
     */
    public function buildPassengersFromBooking(Booking $booking): array
    {
        $booking->loadMissing('passengers');
        $passengers = [];
        $counter = ['ADT' => 1, 'CHD' => 1, 'INF' => 1];
        $adultRphs = [];
        $rph = 1;
        foreach ($booking->passengers as $passenger) {
            $ptc = $this->resolvePassengerTypeCode($passenger);
            $idx = $counter[$ptc]++;
            $genderRaw = strtoupper(trim((string) ($passenger->gender ?? '')));
            $gender = match (true) {
                in_array($genderRaw, ['M', 'MALE'], true) => 'M',
                in_array($genderRaw, ['F', 'FEMALE'], true) => 'F',
                default => throw new \InvalidArgumentException(
                    'Passenger gender is required before creating an AirBlue order.',
                ),
            };
            $birthdate = $passenger->date_of_birth?->format('Y-m-d') ?? trim((string) ($passenger->date_of_birth ?? ''));
            $passportExpiry = $passenger->passport_expiry_date?->format('Y-m-d') ?? trim((string) ($passenger->passport_expiry_date ?? ''));
            $row = [
                'pax_id' => 'PAX-'.$ptc.$idx,
                'ptc' => $ptc,
                'rph' => $rph,
                'title' => strtoupper(trim((string) ($passenger->title ?? ''))),
                'given_name' => (string) ($passenger->first_name ?? ''),
                'surname' => (string) ($passenger->last_name ?? ''),
                'gender' => $gender,
                'birthdate' => $birthdate,
                'nationality' => strtoupper(trim((string) ($passenger->nationality ?? ''))),
                'document_number' => trim((string) ($passenger->passport_number ?? '')),
                'document_issuing_country' => strtoupper(trim((string) ($passenger->passport_issuing_country ?? ''))),
                'document_expiry' => $passportExpiry,
                'contact_info_ref_id' => 'Contact-1',
            ];
            if ($ptc === 'ADT') {
                $adultRphs[] = $rph;
            }
            $passengers[] = $row;
            $rph++;
        }

        $infantCount = count(array_filter($passengers, fn (array $p): bool => ($p['ptc'] ?? '') === 'INF'));
        $adultCount = count($adultRphs);
        if ($infantCount > $adultCount) {
            throw new AirBlueValidationException(
                'invalid_infant_count',
                422,
                'AirBlue OTA booking requires infants not to exceed adults.',
            );
        }

        $adultCursor = 0;
        foreach ($passengers as $index => $passenger) {
            if (($passenger['ptc'] ?? '') !== 'INF') {
                continue;
            }
            $passengers[$index]['associated_adult_rph'] = $adultRphs[$adultCursor] ?? null;
            $adultCursor++;
        }

        return $passengers;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContactFromBooking(Booking $booking): array
    {
        $booking->loadMissing('contact');
        $contact = $booking->contact;
        $formatted = SupplierContactFormatter::fromBooking($booking);
        $xml = SupplierContactFormatter::toXmlContact($formatted);

        return [
            'contact_info_id' => 'Contact-1',
            'email' => (string) ($contact->email ?? $booking->contact_email ?? ''),
            'phone_country' => $xml['phone_country'],
            'phone_area' => $xml['phone_area'],
            'phone_number' => $xml['phone_number'],
            'ctcm_text' => $xml['ctcm_text'],
            'ctcb_text' => $xml['ctcb_text'],
        ];
    }

    /**
     * Map BookingPassenger.passenger_type (adult/child/infant) or legacy PTC codes to Zapways PTC.
     */
    private function resolvePassengerTypeCode(BookingPassenger $passenger): string
    {
        $raw = strtoupper(trim((string) ($passenger->passenger_type ?? $passenger->type ?? 'ADT')));

        return match ($raw) {
            'ADT', 'ADULT' => 'ADT',
            'CHD', 'CHILD' => 'CHD',
            'INF', 'INFANT' => 'INF',
            default => 'ADT',
        };
    }
}
