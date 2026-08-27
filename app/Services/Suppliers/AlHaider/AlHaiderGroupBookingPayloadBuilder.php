<?php

namespace App\Services\Suppliers\AlHaider;

use App\Models\GroupBooking;
use App\Models\GroupBookingPassenger;
use App\Models\GroupInventory;

/**
 * Builds official Al-Haider POST /api/create/booking payloads from local group bookings.
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
            throw new \InvalidArgumentException('Supplier package id is required for Al-Haider booking.');
        }

        $counts = $this->passengerCounts($booking);
        $contactEmail = trim((string) ($booking->contact_email ?? ''));
        $contactPhone = trim((string) ($booking->contact_phone ?? ''));

        return [
            'group_id' => $groupId,
            'agency_info' => [
                'group_id' => $groupId,
                'agent_name' => (string) config('suppliers.al_haider.booking_agent_name', 'JetPakistan'),
                'agency_name' => (string) config('suppliers.al_haider.booking_agency_name', 'JetPakistan'),
                'email' => $contactEmail !== ''
                    ? $contactEmail
                    : (string) config('suppliers.al_haider.booking_contact_email', 'groups@jetpakistan.pk'),
                'mobile' => $contactPhone !== ''
                    ? $contactPhone
                    : (string) config('suppliers.al_haider.booking_contact_mobile', '03000000000'),
                'adults' => $counts['adults'],
                'child' => $counts['children'],
                'infant' => $counts['infants'],
                'agent_notes' => $booking->reference,
            ],
            'booking_details' => $this->bookingDetails($booking),
        ];
    }

    /**
     * @return array{adults: int, children: int, infants: int}
     */
    private function passengerCounts(GroupBooking $booking): array
    {
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

        if ($adults + $children + $infants === 0) {
            $adults = max(1, (int) $booking->seat_count);
        }

        return compact('adults', 'children', 'infants');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bookingDetails(GroupBooking $booking): array
    {
        $rows = [];
        foreach ($booking->passengers as $passenger) {
            $rows[] = $this->mapPassenger($passenger);
        }

        if ($rows === []) {
            $rows[] = [
                'type' => 'Adult',
                'surname' => 'Passenger',
                'given_name' => 'Guest',
                'title' => 'MR',
                'passport_no' => 'QA'.str_pad((string) $booking->id, 7, '0', STR_PAD_LEFT),
                'dob' => '1990-01-01',
                'doe' => '2030-01-01',
            ];
        }

        return $rows;
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

        return [
            'type' => $mappedType,
            'surname' => trim((string) $passenger->last_name) ?: 'Passenger',
            'given_name' => trim((string) $passenger->first_name) ?: 'Guest',
            'title' => $this->mapTitle($passenger, $mappedType),
            'passport_no' => trim((string) $passenger->passport_number) ?: 'QA'.str_pad((string) $passenger->id, 7, '0', STR_PAD_LEFT),
            'dob' => optional($passenger->date_of_birth)->format('Y-m-d') ?? '1990-01-01',
            'doe' => optional($passenger->passport_expiry)->format('Y-m-d') ?? '2030-01-01',
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
