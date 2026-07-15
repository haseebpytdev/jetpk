<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Support\FlightSearch\TravellerCountRules;
use Carbon\Carbon;

class BookingOperationalPrecheckService
{
    /**
     * @return array<int, string>
     */
    public function validatePassengerReadiness(Booking $booking): array
    {
        $booking->loadMissing(['passengers']);
        $errors = [];
        $passengers = $booking->passengers->values();
        $metaPassengerCounts = is_array($booking->meta['passenger_counts'] ?? null) ? $booking->meta['passenger_counts'] : null;
        $expectedTotal = is_array($metaPassengerCounts) ? (int) ($metaPassengerCounts['total'] ?? 0) : 0;

        if ($passengers->isEmpty() && $expectedTotal > 0) {
            return ['No passengers are attached to this booking.'];
        }
        if ($passengers->isEmpty()) {
            // Legacy/admin-seeded records may not include passenger manifests.
            return [];
        }

        $adults = 0;
        $infants = 0;
        $leadCount = 0;

        $criteria = is_array($booking->meta['search_criteria'] ?? null) ? $booking->meta['search_criteria'] : [];
        $origin = (string) ($criteria['origin'] ?? '');
        $destination = (string) ($criteria['destination'] ?? '');
        $isInternational = app(InternationalRouteDetector::class)->isInternational($origin, $destination);
        $passportGate = (bool) config('ota.passport_required_for_international', true) && $isInternational;

        $travelDate = $booking->travel_date instanceof Carbon
            ? $booking->travel_date->copy()->startOfDay()
            : (isset($criteria['depart_date']) && (string) $criteria['depart_date'] !== ''
                ? Carbon::parse((string) $criteria['depart_date'])->startOfDay()
                : null);

        $ageRules = (array) config('ota.passenger_age_rules', []);
        $adultMin = (int) ($ageRules['adult_min_years'] ?? 12);
        $childMin = (int) ($ageRules['child_min_years'] ?? 2);
        $childMax = (int) ($ageRules['child_max_years'] ?? 11);
        $infantMax = (int) ($ageRules['infant_max_years'] ?? 1);

        foreach ($passengers as $idx => $pax) {
            $n = $idx + 1;
            $type = strtolower((string) ($pax->passenger_type ?? 'adult'));
            $first = trim((string) ($pax->first_name ?? ''));
            $last = trim((string) ($pax->last_name ?? ''));

            if ($first === '' || $last === '') {
                $errors[] = "Passenger {$n}: first and last name are required.";
            }

            if ($type === 'adult') {
                $adults++;
            } elseif ($type === 'infant') {
                $infants++;
            }

            if ((bool) $pax->is_lead_passenger) {
                $leadCount++;
                if ($type !== 'adult') {
                    $errors[] = "Passenger {$n}: lead passenger must be adult.";
                }
            }

            if ($travelDate !== null && $pax->date_of_birth !== null) {
                try {
                    $dob = Carbon::parse((string) $pax->date_of_birth)->startOfDay();
                    $age = $dob->diffInYears($travelDate);
                    if ($type === 'adult' && $age < $adultMin) {
                        $errors[] = "Passenger {$n}: adult DOB is invalid for passenger type.";
                    }
                    if ($type === 'child' && ($age < $childMin || $age > $childMax)) {
                        $errors[] = "Passenger {$n}: child DOB is invalid for passenger type.";
                    }
                    if ($type === 'infant' && $age > $infantMax) {
                        $errors[] = "Passenger {$n}: infant DOB is invalid for passenger type.";
                    }
                } catch (\Throwable) {
                    $errors[] = "Passenger {$n}: DOB format is invalid.";
                }
            }

            if ($passportGate) {
                $passportNumber = trim((string) ($pax->passport_number ?? ''));
                if ($passportNumber === '') {
                    $errors[] = "Passenger {$n}: passport is required for international route.";
                }
                if ($pax->passport_expiry_date === null) {
                    $errors[] = "Passenger {$n}: passport expiry date is required.";
                } else {
                    try {
                        $expiry = Carbon::parse((string) $pax->passport_expiry_date)->startOfDay();
                        if ($expiry->lte(now()->startOfDay())) {
                            $errors[] = "Passenger {$n}: passport expiry is not valid.";
                        }
                        if ($travelDate !== null && $expiry->lte($travelDate)) {
                            $errors[] = "Passenger {$n}: passport expires on/before travel date.";
                        }
                    } catch (\Throwable) {
                        $errors[] = "Passenger {$n}: passport expiry format is invalid.";
                    }
                }
            }
        }

        if (TravellerCountRules::infantsExceedAdults($adults, $infants)) {
            $errors[] = TravellerCountRules::INFANTS_EXCEED_ADULTS_MESSAGE;
        }
        if ($leadCount > 1) {
            $errors[] = 'Exactly one lead passenger is required, and must be adult.';
        } elseif ($leadCount === 0 && $adults < 1) {
            $errors[] = 'Lead passenger must be adult.';
        }

        return array_values(array_unique($errors));
    }
}
