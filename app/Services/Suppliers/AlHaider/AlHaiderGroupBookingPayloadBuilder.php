<?php

namespace App\Services\Suppliers\AlHaider;

use App\Models\GroupBooking;
use App\Models\GroupBookingPassenger;
use App\Models\GroupInventory;

/**
 * Builds official Al-Haider POST /api/create/booking payloads from local group bookings.
 *
 * Production path is fail-closed: no synthetic passenger/contact fallbacks.
 * Infants do not consume seats (seat_count must equal adults + children).
 */
class AlHaiderGroupBookingPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(GroupBooking $booking, GroupInventory $inventory): array
    {
        $booking->loadMissing('passengers');
        $groupId = (int) $inventory->supplier_package_id;
        if ($groupId <= 0) {
            throw new AlHaiderGroupBookingPayloadException(
                'supplier_package_id_missing',
                'This group package cannot be reserved with the supplier right now.',
            );
        }

        $contactEmail = trim((string) ($booking->contact_email ?? ''));
        $contactPhone = trim((string) ($booking->contact_phone ?? ''));
        if ($contactEmail === '' || $contactPhone === '') {
            throw new AlHaiderGroupBookingPayloadException(
                'contact_required',
                'Contact email and mobile are required before reserving seats.',
            );
        }

        $counts = $this->passengerCounts($booking);
        $this->assertSeatPassengerParity($booking, $counts);

        $details = [];
        foreach ($booking->passengers as $passenger) {
            $details[] = $this->mapPassenger($passenger);
        }

        if ($details === []) {
            throw new AlHaiderGroupBookingPayloadException(
                'passengers_required',
                'Passenger details are required before reserving seats.',
            );
        }

        if (count($details) !== ($counts['adults'] + $counts['children'] + $counts['infants'])) {
            throw new AlHaiderGroupBookingPayloadException(
                'passenger_row_count_mismatch',
                'Passenger details do not match the selected traveller counts.',
            );
        }

        return [
            'group_id' => $groupId,
            'agency_info' => [
                'group_id' => $groupId,
                'agent_name' => (string) config('suppliers.al_haider.booking_agent_name', 'JetPakistan'),
                'agency_name' => (string) config('suppliers.al_haider.booking_agency_name', 'JetPakistan'),
                'email' => $contactEmail,
                'mobile' => $contactPhone,
                'adults' => $counts['adults'],
                'child' => $counts['children'],
                'infant' => $counts['infants'],
                'agent_notes' => $booking->reference,
            ],
            'booking_details' => $details,
        ];
    }

    /**
     * @return array{adults: int, children: int, infants: int}
     */
    public function passengerCounts(GroupBooking $booking): array
    {
        $booking->loadMissing('passengers');

        $adults = 0;
        $children = 0;
        $infants = 0;

        foreach ($booking->passengers as $passenger) {
            $type = strtolower(trim((string) $passenger->passenger_type));
            if ($type === 'child') {
                $children++;
            } elseif ($type === 'infant') {
                $infants++;
            } else {
                $adults++;
            }
        }

        return compact('adults', 'children', 'infants');
    }

    /**
     * @param  array{adults: int, children: int, infants: int}  $counts
     */
    public function assertSeatPassengerParity(GroupBooking $booking, ?array $counts = null): void
    {
        $counts ??= $this->passengerCounts($booking);
        $seatCount = max(0, (int) $booking->seat_count);
        $seated = $counts['adults'] + $counts['children'];

        if ($seatCount < 1) {
            throw new AlHaiderGroupBookingPayloadException(
                'seat_count_invalid',
                'At least one seat is required to reserve this group package.',
            );
        }

        if ($counts['adults'] < 1) {
            throw new AlHaiderGroupBookingPayloadException(
                'adult_required',
                'At least one adult passenger is required.',
            );
        }

        if ($seated !== $seatCount) {
            throw new AlHaiderGroupBookingPayloadException(
                'seat_passenger_parity',
                'Seat count must match the number of adults and children (infants do not require seats).',
            );
        }

        if ($counts['infants'] > $counts['adults']) {
            throw new AlHaiderGroupBookingPayloadException(
                'infants_exceed_adults',
                'Each infant must be accompanied by an adult.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPassenger(GroupBookingPassenger $passenger): array
    {
        $type = strtolower(trim((string) $passenger->passenger_type));
        $mappedType = match ($type) {
            'child' => 'Child',
            'infant' => 'Infant',
            default => 'Adult',
        };

        $surname = trim((string) $passenger->last_name);
        $givenName = trim((string) $passenger->first_name);
        $passportNo = trim((string) $passenger->passport_number);
        $dob = optional($passenger->date_of_birth)->format('Y-m-d');
        $doe = optional($passenger->passport_expiry)->format('Y-m-d');

        if ($surname === '' || $givenName === '') {
            throw new AlHaiderGroupBookingPayloadException(
                'passenger_name_required',
                'Each passenger must have a first and last name.',
            );
        }

        if ($passportNo === '') {
            throw new AlHaiderGroupBookingPayloadException(
                'passport_required',
                'Each passenger must have a passport or document number.',
            );
        }

        if ($dob === null || $dob === '') {
            throw new AlHaiderGroupBookingPayloadException(
                'date_of_birth_required',
                'Each passenger must have a date of birth.',
            );
        }

        if ($doe === null || $doe === '') {
            throw new AlHaiderGroupBookingPayloadException(
                'passport_expiry_required',
                'Each passenger must have a passport expiry date.',
            );
        }

        return [
            'type' => $mappedType,
            'surname' => $surname,
            'given_name' => $givenName,
            'title' => $this->mapTitle($passenger, $mappedType),
            'passport_no' => $passportNo,
            'dob' => $dob,
            'doe' => $doe,
        ];
    }

    private function mapTitle(GroupBookingPassenger $passenger, string $mappedType): string
    {
        if ($mappedType === 'Child') {
            return 'CHD';
        }
        if ($mappedType === 'Infant') {
            return 'INF';
        }

        $title = strtoupper(trim((string) $passenger->title));

        return match ($title) {
            'MR', 'MRS', 'MS' => $title,
            'MISS' => 'MS',
            default => 'MR',
        };
    }
}
