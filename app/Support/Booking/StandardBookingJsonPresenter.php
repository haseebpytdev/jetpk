<?php

namespace App\Support\Booking;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Structured JSON payloads for Next.js standard flight passenger checkout (additive; Blade unchanged).
 */
class StandardBookingJsonPresenter
{
    /**
     * @param  array<string, mixed>  $viewData
     * @return array<string, mixed>
     */
    public function presentPassengersContext(array $viewData, Request $request): array
    {
        $draft = is_array($viewData['draft'] ?? null) ? $viewData['draft'] : [];
        $criteria = is_array($viewData['criteria'] ?? null) ? $viewData['criteria'] : [];
        $offer = is_array($viewData['offer'] ?? null) ? $viewData['offer'] : null;
        $protection = is_array($viewData['checkoutProtection'] ?? null) ? $viewData['checkoutProtection'] : [];
        $counts = is_array($viewData['passengerCountSummary'] ?? null) ? $viewData['passengerCountSummary'] : [];
        $expectedPassengers = is_array($viewData['expectedPassengers'] ?? null) ? $viewData['expectedPassengers'] : [];
        $pkDomestic = (bool) ($viewData['pkDomesticTravelDocuments'] ?? false);
        $isInternational = (bool) ($viewData['isInternationalRoute'] ?? false);
        $contactPrefill = is_array($viewData['checkoutContactPrefill'] ?? null) ? $viewData['checkoutContactPrefill'] : [];
        $contactPhone = is_array($viewData['checkoutContactPhone'] ?? null) ? $viewData['checkoutContactPhone'] : [];

        $searchId = trim((string) ($draft['search_id'] ?? ''));
        $offerId = trim((string) ($viewData['flightId'] ?? $draft['offer_id'] ?? $draft['flight_id'] ?? ''));
        $holdSessionId = (int) ($draft['hold_session_id'] ?? 0);

        $expiresAt = $protection['checkout_lock_expires_at']
            ?? $protection['offer_expires_at']
            ?? $protection['price_guarantee_expires_at']
            ?? null;

        return [
            'ok' => true,
            'booking_session' => [
                'id' => $this->opaqueSessionId($searchId, $offerId, $holdSessionId),
                'status' => 'passenger_details',
                'expires_at' => $expiresAt,
                'server_time' => now()->toIso8601String(),
                'next_url' => null,
                'previous_url' => $viewData['resultsBackUrl'] ?? null,
                'progress' => $this->progressState('passenger_details'),
            ],
            'selection' => [
                'search_id' => $searchId,
                'offer_id' => $offerId,
                'fare_option_key' => trim((string) ($draft['fare_option_key'] ?? '')),
                'return_fare_option_key' => trim((string) ($draft['return_fare_option_key'] ?? '')),
                'outbound_fare_option_key' => trim((string) ($draft['outbound_fare_option_key'] ?? '')),
                'outbound_key' => trim((string) ($draft['outbound_key'] ?? '')),
                'combo_id' => trim((string) ($draft['combo_id'] ?? '')),
                'from' => (string) ($draft['search_from'] ?? $criteria['origin'] ?? ''),
                'to' => (string) ($draft['search_to'] ?? $criteria['destination'] ?? ''),
                'depart' => (string) ($draft['search_depart'] ?? $criteria['depart_date'] ?? ''),
                'trip_type' => (string) ($draft['trip_type'] ?? $criteria['trip_type'] ?? 'one_way'),
                'return_date' => (string) ($draft['return_date'] ?? $criteria['return_date'] ?? ''),
                'cabin' => (string) ($draft['cabin'] ?? $criteria['cabin'] ?? 'economy'),
            ],
            'itinerary' => $this->presentItinerary($viewData, $offer, $criteria),
            'travellers' => [
                'adults' => (int) ($counts['adults'] ?? 1),
                'children' => (int) ($counts['children'] ?? 0),
                'infants' => (int) ($counts['infants'] ?? 0),
                'total' => (int) ($counts['total'] ?? 1),
                'expected' => $expectedPassengers,
                'lead_passenger_index' => 0,
            ],
            'passenger_requirements' => $this->passengerFieldRequirements($pkDomestic, $isInternational),
            'contact_requirements' => $this->contactFieldRequirements($viewData),
            'document_requirements' => $this->documentRequirements($pkDomestic, $isInternational),
            'existing_values' => $this->existingValues($request, $contactPrefill, $contactPhone, $expectedPassengers),
            'checkout_summary' => $this->presentCheckoutSummary($viewData),
            'seat_extras_capability' => $this->seatExtrasCapability($offer),
            'countries' => $viewData['checkoutCountries'] ?? [],
            'phone_dial_codes' => $viewData['checkoutPhoneDialCodes'] ?? [],
            'auth' => [
                'authenticated' => Auth::check(),
                'can_create_account' => ! Auth::check() && ! ($viewData['hideInlineAccount'] ?? false),
                'agent_booking_mode' => (bool) ($viewData['agentBookingMode'] ?? false),
                'agent_contact_locked' => (bool) ($viewData['agentBookingContactLocked'] ?? false),
            ],
            'validation_result' => $viewData['validationResult'] ?? null,
            'validation_alert' => $viewData['validationAlert'] ?? null,
            'fare_estimate_drift' => (bool) ($viewData['selectedFareEstimateDriftDetected'] ?? false),
            'complex_itinerary_notice' => (bool) ($viewData['complexItineraryNotice'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPassengersSuccess(string $nextUrl): array
    {
        return [
            'ok' => true,
            'status' => 'accepted',
            'next_url' => $nextUrl,
            'progress' => $this->progressState('review'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentError(string $status, string $message, ?string $redirectUrl = null): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'message' => $message,
            'redirect_url' => $redirectUrl,
        ];
    }

    private function opaqueSessionId(string $searchId, string $offerId, int $holdSessionId): string
    {
        return substr(hash('sha256', $searchId.'|'.$offerId.'|'.$holdSessionId), 0, 32);
    }

    /**
     * @param  array<string, mixed>|null  $offer
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function presentItinerary(array $viewData, ?array $offer, array $criteria): array
    {
        $presentation = is_array($viewData['checkoutPresentation'] ?? null) ? $viewData['checkoutPresentation'] : [];
        $fareBreakdown = is_array($viewData['checkoutFareBreakdown'] ?? null) ? $viewData['checkoutFareBreakdown'] : [];
        $returnSplit = is_array($viewData['returnSplitSummary'] ?? null) ? $viewData['returnSplitSummary'] : null;

        return [
            'trip_type' => (string) ($criteria['trip_type'] ?? 'one_way'),
            'route_label' => $presentation['route_label'] ?? null,
            'origin' => (string) ($criteria['origin'] ?? ''),
            'destination' => (string) ($criteria['destination'] ?? ''),
            'depart_date' => (string) ($criteria['depart_date'] ?? ''),
            'return_date' => (string) ($criteria['return_date'] ?? ''),
            'airline_name' => $offer['airline_name'] ?? null,
            'airline_code' => $offer['airline_code'] ?? ($offer['carrier_code'] ?? null),
            'airline_logo_url' => $viewData['airlineLogo'] ?? null,
            'flight_number' => $offer['flight_number'] ?? null,
            'cabin' => (string) ($criteria['cabin'] ?? 'economy'),
            'fare_family' => $offer['fare_family'] ?? ($offer['branded_fare_label'] ?? null),
            'stops' => $offer['stops'] ?? null,
            'duration' => $presentation['duration_label'] ?? null,
            'baggage' => $offer['baggage'] ?? null,
            'segments' => $presentation['segments'] ?? ($offer['segments'] ?? []),
            'return_segments' => $presentation['return_segments'] ?? [],
            'total_formatted' => $fareBreakdown['total_formatted'] ?? null,
            'currency' => $fareBreakdown['currency'] ?? ($offer['currency'] ?? 'PKR'),
            'return_split' => $returnSplit,
        ];
    }

    /**
     * @param  array<string, mixed>  $viewData
     * @return array<string, mixed>
     */
    private function presentCheckoutSummary(array $viewData): array
    {
        $fareBreakdown = is_array($viewData['checkoutFareBreakdown'] ?? null) ? $viewData['checkoutFareBreakdown'] : [];
        $counts = is_array($viewData['passengerCountSummary'] ?? null) ? $viewData['passengerCountSummary'] : [];

        return [
            'total_formatted' => $fareBreakdown['total_formatted'] ?? null,
            'currency' => $fareBreakdown['currency'] ?? 'PKR',
            'passenger_counts' => $counts,
            'lines' => $fareBreakdown['lines'] ?? [],
        ];
    }

    /**
     * @return list<array{key: string, label: string, state: string, href: ?string}>
     */
    public function progressState(string $activeStep): array
    {
        $steps = [
            ['key' => 'flight_selected', 'label' => 'Flight Selected'],
            ['key' => 'passenger_details', 'label' => 'Passenger Details'],
            ['key' => 'seat_extras', 'label' => 'Seat & Extras'],
            ['key' => 'review', 'label' => 'Review'],
            ['key' => 'payment', 'label' => 'Payment'],
            ['key' => 'confirmation', 'label' => 'Confirmation'],
        ];

        $order = array_column($steps, 'key');
        $activeIndex = array_search($activeStep, $order, true);
        $completedThrough = $activeIndex !== false && $activeIndex > 0
            ? $order[$activeIndex - 1]
            : ($activeStep === 'passenger_details' ? 'flight_selected' : null);
        $completedIndex = $completedThrough !== null ? array_search($completedThrough, $order, true) : -1;

        return array_map(function (array $step) use ($activeStep, $activeIndex, $completedIndex, $order): array {
            $index = array_search($step['key'], $order, true);
            $state = 'upcoming';
            if ($step['key'] === $activeStep) {
                $state = 'current';
            } elseif ($completedIndex !== false && $index !== false && $index <= $completedIndex) {
                $state = 'completed';
            }

            return [
                'key' => $step['key'],
                'label' => $step['label'],
                'state' => $state,
                'href' => null,
            ];
        }, $steps);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function passengerFieldRequirements(bool $pkDomestic, bool $isInternational): array
    {
        $fields = [
            ['key' => 'title', 'label' => 'Title', 'required' => true, 'input_type' => 'select', 'passenger_types' => ['adult', 'child', 'infant']],
            ['key' => 'first_name', 'label' => 'First name', 'required' => true, 'input_type' => 'text', 'passenger_types' => ['adult', 'child', 'infant']],
            ['key' => 'last_name', 'label' => 'Last name', 'required' => true, 'input_type' => 'text', 'passenger_types' => ['adult', 'child', 'infant']],
            ['key' => 'gender', 'label' => 'Gender', 'required' => true, 'input_type' => 'select', 'passenger_types' => ['adult', 'child', 'infant']],
            ['key' => 'date_of_birth', 'label' => 'Date of birth', 'required' => true, 'input_type' => 'date', 'passenger_types' => ['adult', 'child', 'infant']],
            ['key' => 'nationality', 'label' => 'Nationality', 'required' => $isInternational, 'input_type' => 'country', 'passenger_types' => ['adult', 'child', 'infant']],
            ['key' => 'document_type', 'label' => 'Document type', 'required' => true, 'input_type' => 'select', 'passenger_types' => ['adult', 'child', 'infant'], 'options' => $pkDomestic ? ['passport', 'national_id'] : ['passport']],
        ];

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $viewData
     * @return list<array<string, mixed>>
     */
    private function contactFieldRequirements(array $viewData): array
    {
        $locked = (bool) ($viewData['agentBookingContactLocked'] ?? false);

        return [
            ['key' => 'contact_name', 'label' => 'Contact name', 'required' => false, 'input_type' => 'text', 'locked' => $locked],
            ['key' => 'email', 'label' => 'Email', 'required' => true, 'input_type' => 'email', 'locked' => $locked],
            ['key' => 'phone', 'label' => 'Mobile', 'required' => true, 'input_type' => 'tel', 'locked' => $locked],
            ['key' => 'phone_country_code', 'label' => 'Country code', 'required' => false, 'input_type' => 'select', 'locked' => $locked],
            ['key' => 'phone_number', 'label' => 'Mobile number', 'required' => false, 'input_type' => 'tel', 'locked' => $locked],
            ['key' => 'country', 'label' => 'Country', 'required' => false, 'input_type' => 'text', 'locked' => $locked],
            ['key' => 'create_account', 'label' => 'Create account', 'required' => false, 'input_type' => 'checkbox', 'locked' => Auth::check()],
            ['key' => 'password', 'label' => 'Password', 'required' => false, 'input_type' => 'password', 'locked' => Auth::check()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentRequirements(bool $pkDomestic, bool $isInternational): array
    {
        return [
            'passport_required' => $isInternational,
            'national_id_allowed' => $pkDomestic,
            'passport_fields' => [
                ['key' => 'passport_number', 'label' => 'Passport number', 'required' => true],
                ['key' => 'passport_issuing_country', 'label' => 'Issuing country', 'required' => true, 'input_type' => 'country'],
                ['key' => 'passport_expiry_date', 'label' => 'Expiry date', 'required' => true, 'input_type' => 'date'],
                ['key' => 'passport_issue_date', 'label' => 'Issue date', 'required' => true, 'input_type' => 'date'],
            ],
            'national_id_fields' => $pkDomestic ? [
                ['key' => 'national_id_number', 'label' => 'CNIC / NICOP', 'required' => true],
            ] : [],
            'not_required_message' => ! $isInternational && $pkDomestic
                ? null
                : ($isInternational ? null : 'Passport is not required for this domestic itinerary when using national ID.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $contactPrefill
     * @param  array<string, mixed>  $contactPhone
     * @param  list<array<string, mixed>>  $expectedPassengers
     * @return array<string, mixed>
     */
    private function existingValues(Request $request, array $contactPrefill, array $contactPhone, array $expectedPassengers): array
    {
        $passengers = [];
        foreach ($expectedPassengers as $expected) {
            $idx = (int) ($expected['index'] ?? 0);
            $passengers[] = [
                'passenger_type' => (string) ($expected['type'] ?? 'adult'),
                'title' => old("passengers.$idx.title"),
                'first_name' => old("passengers.$idx.first_name"),
                'last_name' => old("passengers.$idx.last_name"),
                'gender' => old("passengers.$idx.gender"),
                'date_of_birth' => old("passengers.$idx.date_of_birth"),
                'nationality' => old("passengers.$idx.nationality"),
                'document_type' => old("passengers.$idx.document_type", 'passport'),
                'passport_number' => old("passengers.$idx.passport_number"),
                'passport_issuing_country' => old("passengers.$idx.passport_issuing_country"),
                'passport_expiry_date' => old("passengers.$idx.passport_expiry_date"),
                'passport_issue_date' => old("passengers.$idx.passport_issue_date"),
                'national_id_number' => old("passengers.$idx.national_id_number"),
            ];
        }

        return [
            'passengers' => $passengers,
            'contact' => [
                'contact_name' => old('contact_name', $contactPrefill['name'] ?? ''),
                'email' => old('email', $contactPrefill['email'] ?? ''),
                'phone' => old('phone', trim(($contactPhone['country_code'] ?? '').' '.($contactPhone['number'] ?? ''))),
                'phone_country_code' => old('phone_country_code', $contactPhone['country_code'] ?? ''),
                'phone_number' => old('phone_number', $contactPhone['number'] ?? ''),
                'country' => old('country', $contactPrefill['country'] ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $offer
     * @return array<string, mixed>
     */
    private function seatExtrasCapability(?array $offer): array
    {
        $provider = strtolower(trim((string) ($offer['supplier_provider'] ?? '')));
        $oneApiExtras = $provider === 'one_api';

        return [
            'seat_map_available' => false,
            'ancillaries_available' => $oneApiExtras,
            'message' => 'Seat selection and optional extras will be shown when supported for this fare.',
            'progress_step' => 'upcoming',
        ];
    }
}
