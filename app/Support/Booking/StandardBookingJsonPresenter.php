<?php

namespace App\Support\Booking;

use App\Enums\BookingCancellationStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Support\Bookings\BookingPaymentSummaryPresenter;
use App\Support\Bookings\CheckoutFareBreakdownPresenter;
use App\Support\Bookings\PublicCheckoutFareChangeState;
use App\Support\Branding\PublicAgencyContactResolver;
use App\Support\Payments\PublicAbhiPayCheckoutPresenter;
use App\Support\PublicBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;

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
            'progress_step' => 'skipped',
        ];
    }

    /**
     * @param  array<string, mixed>  $viewData
     * @return array<string, mixed>
     */
    public function presentReviewContext(array $viewData, Request $request): array
    {
        $booking = $viewData['booking'] ?? null;
        if (! $booking instanceof Booking) {
            return $this->presentError('missing_session', __('Please search for a flight before continuing to checkout.'), '/');
        }

        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $criteria = is_array($viewData['criteria'] ?? null) ? $viewData['criteria'] : [];
        $offer = is_array($viewData['offer'] ?? null) ? $viewData['offer'] : null;
        $draft = is_array($viewData['draft'] ?? null) ? $viewData['draft'] : [];
        $checkoutFareBreakdown = is_array($viewData['checkoutFareBreakdown'] ?? null) ? $viewData['checkoutFareBreakdown'] : [];
        $abhiPay = is_array($viewData['abhiPayCheckout'] ?? null) ? $viewData['abhiPayCheckout'] : [];
        $searchId = trim((string) data_get($meta, 'checkout_search_id', ''));
        $offerId = trim((string) ($booking->flight_offer_id ?? ($offer['id'] ?? '')));
        $holdSessionId = (int) data_get($meta, 'hold_session_id', 0);
        $expiresAt = (string) ($viewData['fareSessionExpiresAt'] ?? data_get($meta, 'checkout_lock_expires_at', ''));

        return [
            'ok' => true,
            'booking_session' => [
                'id' => $this->opaqueSessionId($searchId, $offerId, $holdSessionId),
                'status' => 'review',
                'expires_at' => $expiresAt !== '' ? $expiresAt : null,
                'server_time' => now()->toIso8601String(),
                'next_url' => null,
                'previous_url' => '/booking/passengers',
                'progress' => $this->progressStateForCheckout('review', $this->seatExtrasCapability($offer)['progress_step'] ?? 'skipped'),
            ],
            'booking_reference' => $booking->booking_reference,
            'itinerary' => $this->presentItineraryFromReview($viewData, $offer, $criteria),
            'passengers' => $this->presentPassengersSummary($booking),
            'contact' => $this->presentContactSummary($booking),
            'documents' => $this->presentDocumentSummary($booking),
            'pricing' => $this->presentAuthoritativePricing($checkoutFareBreakdown, $booking),
            'payment_methods' => $this->presentPaymentMethods($abhiPay),
            'terms' => [
                'required' => false,
                'terms_url' => '/terms',
                'privacy_url' => '/privacy',
            ],
            'fare_change' => $this->presentFareChangeState($booking, $viewData),
            'submit_blocked' => (bool) ($viewData['sabreCheckoutSubmitDisabled'] ?? false) || (bool) ($viewData['offerRefreshPending'] ?? false),
            'submit_blocked_reason' => $this->reviewSubmitBlockedReason($viewData),
            'notices' => $this->reviewNotices($viewData),
            'next_actions' => [
                'edit_passengers_url' => '/booking/passengers',
                'accept_fare_url' => filled($booking->booking_reference)
                    ? '/booking/'.$booking->id.'/accept-updated-fare'
                    : null,
                'decline_fare_url' => filled($booking->booking_reference)
                    ? '/booking/'.$booking->id.'/decline-updated-fare'
                    : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentReviewSubmitSuccess(?Booking $booking, Request $request): array
    {
        $meta = is_array($booking?->meta) ? $booking->meta : [];
        $method = (string) ($meta['booking_method'] ?? 'pay_later_booking_request');
        $isCard = $method === 'online_card';

        $nextPath = $isCard ? '/booking/payment/card' : '/booking/payment/manual';

        return [
            'ok' => true,
            'status' => 'submitted',
            'booking_method' => $method,
            'payment_method_code' => $isCard ? 'card' : 'manual',
            'next_url' => $nextPath,
            'confirmation_handoff_url' => '/booking/confirmation',
            'progress' => $this->progressStateForCheckout($isCard ? 'payment' : 'payment'),
            'guest_abhipay_token' => $request->session()->get('guest_abhipay_token'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentFareChangeRequired(?Booking $booking, ?ViewErrorBag $errors = null): array
    {
        $fareChange = $booking !== null ? $this->presentFareChangeState($booking, []) : null;

        return [
            'ok' => false,
            'status' => 'fare_changed',
            'message' => (string) ($errors?->getBag('default')->first('booking') ?? __('The fare has changed. Please accept the updated fare before continuing.')),
            'fare_change' => $fareChange,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentReviewBlocked(?Booking $booking, string $message, string $redirectPath): array
    {
        return [
            'ok' => false,
            'status' => 'review_blocked',
            'message' => $message,
            'redirect_url' => $redirectPath,
            'fare_change' => $booking !== null ? $this->presentFareChangeState($booking, []) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $viewData
     * @return array<string, mixed>
     */
    public function presentCheckoutState(array $viewData, Request $request): array
    {
        $booking = $viewData['booking'] ?? null;
        if (! $booking instanceof Booking) {
            return $this->presentError('missing_session', __('Booking session not found.'), '/');
        }

        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown', 'payments']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $draft = is_array($viewData['draft'] ?? null) ? $viewData['draft'] : [];
        $abhiPay = is_array($viewData['abhiPayCheckout'] ?? null) ? $viewData['abhiPayCheckout'] : [];
        $criteria = is_array($viewData['criteria'] ?? null) ? $viewData['criteria'] : [];
        $offer = is_array($viewData['offer'] ?? null) ? $viewData['offer'] : null;
        $method = (string) ($meta['booking_method'] ?? $draft['booking_method'] ?? 'pay_later');
        $isCard = $method === 'online_card';
        $checkoutFareBreakdown = CheckoutFareBreakdownPresenter::present(
            $offer,
            $booking->fareBreakdown,
            [
                'adults' => (int) $booking->passengers->where('passenger_type', 'adult')->count(),
                'children' => (int) $booking->passengers->where('passenger_type', 'child')->count(),
                'infants' => (int) $booking->passengers->where('passenger_type', 'infant')->count(),
            ],
        );

        $paymentStatus = $this->mapPaymentStatus($abhiPay);
        $bookingStatus = $this->mapBookingStatus($booking);
        $guestToken = $viewData['guestAbhiPayToken'] ?? $request->session()->get('guest_abhipay_token');

        return [
            'ok' => true,
            'booking_session' => [
                'id' => $this->opaqueSessionId(
                    (string) data_get($meta, 'checkout_search_id', ''),
                    (string) ($booking->flight_offer_id ?? ''),
                    (int) data_get($meta, 'hold_session_id', 0),
                ),
                'status' => $isCard ? 'payment' : 'awaiting_payment',
                'server_time' => now()->toIso8601String(),
                'progress' => $this->progressStateForCheckout('payment'),
            ],
            'booking_reference' => $booking->booking_reference,
            'booking_method' => $method,
            'payment_method_code' => $isCard ? 'card' : 'manual',
            'booking_status' => $bookingStatus,
            'payment_status' => $paymentStatus,
            'pnr' => filled($booking->pnr) ? strtoupper((string) $booking->pnr) : null,
            'ticketing_status' => (string) ($booking->ticketing_status ?? ''),
            'pricing' => $this->presentAuthoritativePricing($checkoutFareBreakdown, $booking),
            'manual_payment' => $isCard ? null : $this->presentManualPaymentState($booking, $abhiPay),
            'card_payment' => $isCard ? $this->presentCardPaymentState($booking, $abhiPay, $guestToken) : null,
            'supplier_notice' => is_array($viewData['supplierConfirmationNotice'] ?? null)
                ? ($viewData['supplierConfirmationNotice']['notice'] ?? null)
                : null,
            'itinerary' => $this->presentItineraryFromReview($viewData, $offer, $criteria),
            'passengers' => $this->presentPassengersSummary($booking),
            'contact' => $this->presentContactSummary($booking),
            'documents_portal' => $this->presentDocumentsPortalSafe($booking, 'customer'),
            'support' => $this->supportContacts(),
            'confirmation_handoff_url' => $bookingStatus['terminal'] ? '/booking/confirmation' : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $viewData
     * @return array<string, mixed>
     */
    public function presentConfirmation(array $viewData, Request $request): array
    {
        $base = $this->presentCheckoutState($viewData, $request);
        if (($base['ok'] ?? false) !== true) {
            return $base;
        }

        $booking = $viewData['booking'] ?? null;
        if (! $booking instanceof Booking) {
            return $this->presentError('missing_session', __('Booking session not found.'), '/');
        }

        $booking->loadMissing(['tickets.passenger', 'supplierBookings', 'cancellationRequests', 'refunds']);
        $paymentStatus = is_array($base['payment_status'] ?? null) ? $base['payment_status'] : [];
        $bookingStatus = is_array($base['booking_status'] ?? null) ? $base['booking_status'] : [];
        $method = (string) ($base['booking_method'] ?? 'pay_later');
        $ticketingStatus = $this->presentTicketingStatus($booking);

        $base['booking_session']['status'] = 'confirmation';
        $base['booking_session']['progress'] = $this->progressStateForCheckout(
            'confirmation',
            $this->seatExtrasCapability(is_array($viewData['offer'] ?? null) ? $viewData['offer'] : null)['progress_step'] ?? 'skipped',
        );
        $base['presentation'] = $this->presentSuccessPresentation($booking, $paymentStatus, $bookingStatus, $method, $ticketingStatus);
        $base['pnr_details'] = $this->presentPnrDetails($booking);
        $base['ticketing_status'] = $ticketingStatus;
        $base['tickets'] = $this->presentTicketsSummary($booking);
        $base['actions'] = $this->presentPostBookingActions($booking, $request);
        $base['poll'] = $this->presentBookingPollConfig($booking, $paymentStatus, $bookingStatus, $ticketingStatus);
        $base['cancellation'] = $this->presentCancellationEligibility($booking);
        $base['refund'] = $this->presentRefundStatus($booking);

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPaymentStatus(?Booking $booking, array $abhiPayCheckout, ?string $transactionReference = null): array
    {
        if ($booking === null) {
            return $this->presentError('missing_session', __('Booking not found.'), '/');
        }

        $paymentStatus = $this->mapPaymentStatus($abhiPayCheckout);
        $terminal = in_array($paymentStatus['code'], ['succeeded', 'failed', 'cancelled', 'duplicate'], true);

        $bookingStatus = $this->mapBookingStatus($booking);

        return [
            'ok' => true,
            'booking_reference' => $booking->booking_reference,
            'payment_status' => $paymentStatus,
            'booking_status' => $bookingStatus,
            'ticketing_status' => $this->presentTicketingStatus($booking),
            'transaction_reference' => $transactionReference ?? ($abhiPayCheckout['latest_transaction_reference'] ?? null),
            'confirmation_url' => '/booking/confirmation',
            'invoice_url' => '/booking/invoice',
            'poll' => [
                'should_poll' => ! $terminal && in_array($paymentStatus['code'], ['pending', 'processing', 'unknown'], true),
                'interval_ms' => 3000,
                'max_attempts' => 40,
            ],
            'booking_poll' => $this->presentBookingPollConfig($booking, $paymentStatus, $bookingStatus, $this->presentTicketingStatus($booking)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentInvoice(?Booking $booking): array
    {
        if ($booking === null) {
            return $this->presentError('unauthorized', __('Invoice not available.'), '/');
        }

        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $offer = is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : null;
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $checkoutFareBreakdown = CheckoutFareBreakdownPresenter::present(
            $offer,
            $booking->fareBreakdown,
            [
                'adults' => (int) $booking->passengers->where('passenger_type', 'adult')->count(),
                'children' => (int) $booking->passengers->where('passenger_type', 'child')->count(),
                'infants' => (int) $booking->passengers->where('passenger_type', 'infant')->count(),
            ],
        );
        $contact = PublicAgencyContactResolver::resolve(null);
        $documents = $this->presentDocumentsPortalSafe($booking, 'customer');
        $invoiceDoc = collect($documents)->firstWhere('key', 'invoice');

        return [
            'ok' => true,
            'invoice_number' => $invoiceDoc !== null
                ? ($invoiceDoc['document_number'] ?? $booking->booking_reference)
                : null,
            'booking_reference' => $booking->booking_reference,
            'issue_date' => now()->format('Y-m-d'),
            'customer' => $this->presentContactSummary($booking),
            'itinerary_summary' => [
                'route' => (string) (($criteria['origin'] ?? '').' → '.($criteria['destination'] ?? '')),
                'depart_date' => (string) ($criteria['depart_date'] ?? ''),
                'return_date' => (string) ($criteria['return_date'] ?? ''),
            ],
            'passenger_count' => $booking->passengers->count(),
            'line_items' => $checkoutFareBreakdown['rows'] ?? [],
            'pricing' => $this->presentAuthoritativePricing($checkoutFareBreakdown, $booking),
            'payment_method' => (string) ($meta['booking_method'] ?? ''),
            'payment_status' => $this->mapPaymentStatus(app(PublicAbhiPayCheckoutPresenter::class)->forBooking($booking, afterSubmission: true)),
            'booking_status' => $this->mapBookingStatus($booking),
            'company' => [
                'name' => trim(client_branding()->companyName()),
                'phone' => $contact->phone,
                'email' => $contact->email,
                'address' => trim(client_branding()->address()) !== '' ? trim(client_branding()->address()) : $contact->address,
            ],
            'pdf_available' => $invoiceDoc !== null && filled($invoiceDoc['download_path'] ?? null),
            'pdf_download_path' => $invoiceDoc['download_path'] ?? null,
            'documents' => $documents,
        ];
    }

    /**
     * @param  array<string, mixed>  $abhiPay
     * @return list<array<string, mixed>>
     */
    private function presentPaymentMethods(array $abhiPay): array
    {
        $methods = [
            [
                'code' => 'manual',
                'canonical' => 'pay_later',
                'label' => 'Manual Payment',
                'description' => 'Submit your booking request and pay using the instructions on the confirmation page.',
                'available' => true,
                'fee' => null,
                'currency' => (string) ($abhiPay['currency'] ?? 'PKR'),
            ],
        ];

        if (($abhiPay['show_review_option'] ?? false) === true) {
            $methods[] = [
                'code' => 'card',
                'canonical' => 'online_card',
                'label' => 'Pay by Card',
                'description' => 'Pay securely online by debit or credit card after submitting your booking.',
                'available' => true,
                'fee' => null,
                'currency' => (string) ($abhiPay['currency'] ?? 'PKR'),
            ];
        }

        return $methods;
    }

    /**
     * @param  array<string, mixed>  $checkoutFareBreakdown
     * @return array<string, mixed>
     */
    private function presentAuthoritativePricing(array $checkoutFareBreakdown, Booking $booking): array
    {
        $currency = (string) ($checkoutFareBreakdown['currency'] ?? $booking->currency ?? 'PKR');
        $total = (float) ($checkoutFareBreakdown['total'] ?? $booking->fareBreakdown?->total ?? 0);

        return [
            'currency' => $currency,
            'base_fare' => (float) ($booking->fareBreakdown?->base_fare ?? 0),
            'taxes' => (float) ($booking->fareBreakdown?->taxes ?? 0),
            'service_charges' => (float) ($booking->fareBreakdown?->service_fee ?? 0),
            'total' => $total,
            'formatted_total' => 'Rs. '.number_format($total, 0),
            'rows' => $checkoutFareBreakdown['rows'] ?? [],
            'passenger_mix' => $checkoutFareBreakdown['passenger_mix'] ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentPassengersSummary(Booking $booking): array
    {
        return $booking->passengers
            ->sortBy('passenger_index')
            ->values()
            ->map(fn ($passenger): array => [
                'passenger_type' => (string) $passenger->passenger_type,
                'title' => (string) $passenger->title,
                'first_name' => (string) $passenger->first_name,
                'last_name' => (string) $passenger->last_name,
                'gender' => (string) $passenger->gender,
                'date_of_birth' => $passenger->date_of_birth?->format('Y-m-d'),
                'nationality' => (string) $passenger->nationality,
                'document_type' => (string) $passenger->document_type,
                'passport_number_masked' => $this->maskDocumentNumber((string) $passenger->passport_number),
                'national_id_masked' => $this->maskDocumentNumber((string) $passenger->national_id_number),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentContactSummary(Booking $booking): array
    {
        $contact = $booking->contact;

        return [
            'name' => trim((string) ($contact?->name ?? '')),
            'email' => (string) ($contact?->email ?? ''),
            'phone' => (string) ($contact?->phone ?? ''),
            'country' => (string) ($contact?->country ?? ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentDocumentSummary(Booking $booking): array
    {
        return $booking->passengers
            ->sortBy('passenger_index')
            ->values()
            ->map(fn ($passenger): array => [
                'passenger_label' => trim((string) $passenger->first_name.' '.(string) $passenger->last_name),
                'document_type' => (string) $passenger->document_type,
                'passport_number_masked' => $this->maskDocumentNumber((string) $passenger->passport_number),
                'national_id_masked' => $this->maskDocumentNumber((string) $passenger->national_id_number),
                'passport_expiry_date' => $passenger->passport_expiry_date?->format('Y-m-d'),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $viewData
     * @return array<string, mixed>|null
     */
    private function presentFareChangeState(Booking $booking, array $viewData): ?array
    {
        $fareChangeState = app(PublicCheckoutFareChangeState::class);
        if (! $fareChangeState->requiresCustomerAcceptance($booking) && ! $fareChangeState->persistedFareChanged($booking)) {
            return null;
        }

        $display = $viewData['offerRefreshDisplay'] ?? null;
        if (! is_array($display)) {
            $display = $fareChangeState->customerModalDisplay($booking);
        }

        if ($display === null) {
            return ['fare_changed' => true, 'requires_acceptance' => true];
        }

        return array_merge($display, [
            'requires_acceptance' => $fareChangeState->requiresCustomerAcceptance($booking),
            'accept_url' => '/booking/'.$booking->id.'/accept-updated-fare',
            'decline_url' => '/booking/'.$booking->id.'/decline-updated-fare',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $offer
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $viewData
     * @return array<string, mixed>
     */
    private function presentItineraryFromReview(array $viewData, ?array $offer, array $criteria): array
    {
        $presentation = is_array($viewData['reviewPresentation'] ?? null)
            ? $viewData['reviewPresentation']
            : (is_array($viewData['checkoutPresentation'] ?? null) ? $viewData['checkoutPresentation'] : []);

        return $this->presentItinerary(
            array_merge($viewData, ['checkoutPresentation' => $presentation, 'checkoutFareBreakdown' => $viewData['checkoutFareBreakdown'] ?? []]),
            $offer,
            $criteria,
        );
    }

    /**
     * @param  array<string, mixed>  $abhiPay
     * @return array<string, mixed>
     */
    private function presentManualPaymentState(Booking $booking, array $abhiPay): array
    {
        return [
            'amount_due' => (float) ($abhiPay['payable_amount'] ?? 0),
            'currency' => (string) ($abhiPay['currency'] ?? 'PKR'),
            'formatted_amount' => 'Rs. '.number_format((float) ($abhiPay['payable_amount'] ?? 0), 0),
            'instructions' => [
                'Our team will contact you to confirm availability, fare, and payment instructions.',
                'Include your booking reference in any bank transfer note.',
                'Ticketing is completed after verification and payment where applicable.',
            ],
            'payment_status_label' => (string) ($abhiPay['payment_status_label'] ?? 'Unpaid'),
            'proof_upload_supported' => false,
            'payment_reference_supported' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $abhiPay
     * @return array<string, mixed>
     */
    private function presentCardPaymentState(Booking $booking, array $abhiPay, ?string $guestToken): array
    {
        $startPath = Auth::check()
            ? '/payments/abhipay/start/'.$booking->id
            : ($guestToken !== null && $guestToken !== ''
                ? '/guest/bookings/'.$booking->id.'/access/'.$guestToken.'/abhipay/start'
                : null);

        return [
            'can_start' => (bool) ($abhiPay['can_start'] ?? false),
            'show_pay_button' => (bool) ($abhiPay['show_pay_button'] ?? false),
            'payable_amount' => (float) ($abhiPay['payable_amount'] ?? 0),
            'currency' => (string) ($abhiPay['currency'] ?? 'PKR'),
            'formatted_amount' => 'Rs. '.number_format((float) ($abhiPay['payable_amount'] ?? 0), 0),
            'payment_status_label' => (string) ($abhiPay['payment_status_label'] ?? 'Unpaid'),
            'blocked_message' => $abhiPay['blocked_message'] ?? null,
            'ticketing_note' => (string) ($abhiPay['ticketing_note'] ?? 'Ticketing will happen after payment verification.'),
            'start_endpoint' => $startPath,
            'latest_transaction_reference' => $abhiPay['latest_transaction_reference'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $abhiPay
     * @return array<string, mixed>
     */
    private function mapPaymentStatus(array $abhiPay): array
    {
        $label = (string) ($abhiPay['payment_status_label'] ?? 'Unpaid');
        $code = match ($label) {
            'Paid' => 'succeeded',
            'Payment pending' => 'pending',
            'Payment failed' => 'failed',
            default => 'not_started',
        };

        return [
            'code' => $code,
            'label' => $label,
            'terminal' => in_array($code, ['succeeded', 'failed', 'cancelled'], true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBookingStatus(Booking $booking): array
    {
        $ticketed = $booking->status === BookingStatus::Ticketed
            || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true)
            || $booking->ticketed_at !== null;

        $label = match (true) {
            $ticketed => 'Confirmed',
            filled($booking->pnr) => 'Pending ticketing',
            $booking->submitted_at !== null => 'Pending',
            default => 'Draft',
        };

        return [
            'code' => (string) ($booking->status?->value ?? 'draft'),
            'label' => $label,
            'terminal' => $ticketed,
        ];
    }

    /**
     * @return list<array{key: string, label: string, state: string, href: ?string}>
     */
    public function progressStateForCheckout(string $activeStep, string $seatExtrasState = 'skipped'): array
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
        $completedThrough = match ($activeStep) {
            'review' => 'passenger_details',
            'payment' => 'review',
            'confirmation' => 'payment',
            default => $activeIndex !== false && $activeIndex > 0 ? $order[$activeIndex - 1] : null,
        };
        $completedIndex = $completedThrough !== null ? array_search($completedThrough, $order, true) : -1;

        return array_map(function (array $step) use ($activeStep, $completedIndex, $order, $seatExtrasState): array {
            $index = array_search($step['key'], $order, true);
            $state = 'upcoming';

            if ($step['key'] === $activeStep) {
                $state = 'current';
            } elseif ($step['key'] === 'seat_extras' && $seatExtrasState === 'skipped') {
                $state = 'skipped';
            } elseif ($completedIndex !== false && $index !== false && $index <= $completedIndex) {
                $state = 'completed';
            }

            $href = null;
            if ($step['key'] === 'passenger_details' && $state === 'completed') {
                $href = '/booking/passengers';
            }

            return [
                'key' => $step['key'],
                'label' => $step['label'],
                'state' => $state,
                'href' => $href,
            ];
        }, $steps);
    }

    /**
     * @param  array<string, mixed>  $viewData
     * @return list<string>
     */
    private function reviewNotices(array $viewData): array
    {
        $notices = [];
        if ((bool) ($viewData['complexItineraryNotice'] ?? false)) {
            $notices[] = (string) __('Your booking request will require staff confirmation before airline hold/PNR.');
        }
        if ((bool) ($viewData['timelineSnapshotInvalid'] ?? false)) {
            $notices[] = (string) __('Selected itinerary timing could not be verified. Please choose another fare.');
        }
        if ((bool) ($viewData['recheckRequired'] ?? false)) {
            $notices[] = (string) __('Your airline hold has expired. Please recheck the fare before continuing.');
        }

        return $notices;
    }

    /**
     * @param  array<string, mixed>  $viewData
     */
    private function reviewSubmitBlockedReason(array $viewData): ?string
    {
        if ((bool) ($viewData['offerRefreshPending'] ?? false)) {
            return (string) __('An airline fare update must be accepted before you can continue.');
        }
        if ((bool) ($viewData['sabreCheckoutSubmitDisabled'] ?? false)) {
            return (string) __('Online airline confirmation is not available yet.');
        }

        return null;
    }

    private function maskDocumentNumber(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        if (strlen($trimmed) <= 4) {
            return str_repeat('•', strlen($trimmed));
        }

        return str_repeat('•', max(0, strlen($trimmed) - 4)).substr($trimmed, -4);
    }

    /**
     * @return array<string, mixed>
     */
    private function supportContacts(): array
    {
        $contact = PublicAgencyContactResolver::resolve(null);

        return [
            'phone' => $contact->phone,
            'email' => $contact->email,
            'whatsapp_url' => $contact->whatsappUrl(),
            'support_url' => '/support',
            'lookup_url' => '/lookup-booking',
        ];
    }

    /**
     * @return array{code: string, label: string, terminal: bool}
     */
    private function presentTicketingStatus(Booking $booking): array
    {
        $status = strtolower(trim((string) ($booking->ticketing_status ?? 'not_started')));
        $hasTickets = $booking->relationLoaded('tickets')
            ? $booking->tickets->isNotEmpty()
            : $booking->tickets()->exists();
        $ticketed = $booking->status === BookingStatus::Ticketed
            || in_array($status, ['ticketed', 'issued'], true)
            || $booking->ticketed_at !== null
            || $hasTickets;

        $label = match (true) {
            $ticketed => 'Ticketed',
            in_array($status, ['pending', 'ready'], true) => 'Pending',
            $status === 'failed' => 'Failed',
            $status === 'not_supported' => 'Not supported',
            default => 'Not started',
        };

        return [
            'code' => $ticketed ? 'ticketed' : ($status !== '' ? $status : 'not_started'),
            'label' => $label,
            'terminal' => $ticketed || in_array($status, ['failed', 'not_supported', 'voided'], true),
        ];
    }

    /**
     * @param  array<string, mixed>  $paymentStatus
     * @param  array<string, mixed>  $bookingStatus
     * @return array<string, mixed>
     */
    private function presentSuccessPresentation(
        Booking $booking,
        array $paymentStatus,
        array $bookingStatus,
        string $method,
        array $ticketingStatus,
    ): array {
        $paymentCode = (string) ($paymentStatus['code'] ?? 'not_started');
        $ticketed = (bool) ($ticketingStatus['terminal'] ?? false) && ($ticketingStatus['code'] ?? '') === 'ticketed';
        $cancelled = $booking->status === BookingStatus::Cancelled;
        $failed = $booking->status === BookingStatus::Failed;
        $hasPnr = filled($booking->pnr);
        $isManual = $method !== 'online_card';

        if ($cancelled) {
            return [
                'heading' => 'Booking cancelled',
                'subtitle' => 'This booking is no longer active.',
                'tone' => 'neutral',
                'show_celebration' => false,
            ];
        }

        if ($failed) {
            return [
                'heading' => 'Booking requires attention',
                'subtitle' => 'We could not complete this booking automatically. Our team will follow up.',
                'tone' => 'warning',
                'show_celebration' => false,
            ];
        }

        if ($ticketed) {
            return [
                'heading' => 'Booking complete',
                'subtitle' => 'Your tickets have been issued.',
                'tone' => 'success',
                'show_celebration' => true,
            ];
        }

        if ($hasPnr) {
            return [
                'heading' => 'Booking confirmed',
                'subtitle' => 'Your reservation is confirmed. Ticketing may still be in progress.',
                'tone' => 'success',
                'show_celebration' => false,
            ];
        }

        if ($paymentCode === 'succeeded') {
            return [
                'heading' => 'Payment received',
                'subtitle' => 'We are processing your booking with the airline.',
                'tone' => 'processing',
                'show_celebration' => false,
            ];
        }

        if ($isManual && in_array($paymentCode, ['not_started', 'pending'], true)) {
            return [
                'heading' => 'Booking request received',
                'subtitle' => 'Complete manual payment using the instructions provided.',
                'tone' => 'pending',
                'show_celebration' => false,
            ];
        }

        return [
            'heading' => 'Booking request received',
            'subtitle' => (string) ($bookingStatus['label'] ?? 'We are processing your request.'),
            'tone' => 'pending',
            'show_celebration' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPnrDetails(Booking $booking): array
    {
        $airlineLocator = null;
        if ($booking->relationLoaded('supplierBookings')) {
            $airlineLocator = $booking->supplierBookings
                ->pluck('supplier_reference')
                ->filter(fn ($value) => filled($value))
                ->first();
        }

        return [
            'booking_reference' => filled($booking->pnr) ? strtoupper((string) $booking->pnr) : null,
            'airline_locator' => filled($airlineLocator) ? strtoupper((string) $airlineLocator) : null,
            'available' => filled($booking->pnr) || filled($airlineLocator),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentTicketsSummary(Booking $booking): array
    {
        if (! $booking->relationLoaded('tickets')) {
            return [];
        }

        return $booking->tickets
            ->sortBy('id')
            ->values()
            ->map(function ($ticket): array {
                $passenger = $ticket->passenger;

                return [
                    'ticket_number' => filled($ticket->ticket_number) ? (string) $ticket->ticket_number : null,
                    'passenger_name' => $passenger !== null
                        ? trim((string) $passenger->first_name.' '.(string) $passenger->last_name)
                        : null,
                    'issued_at' => $ticket->issued_at?->toIso8601String(),
                    'status' => (string) ($ticket->status ?? ''),
                ];
            })
            ->filter(fn (array $row): bool => filled($row['ticket_number']))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentPostBookingActions(Booking $booking, Request $request): array
    {
        $documents = $this->presentDocumentsPortalSafe($booking, 'customer');
        $actions = [
            [
                'code' => 'view_confirmation',
                'label' => 'View confirmation',
                'available' => true,
                'url' => '/booking/confirmation',
            ],
            [
                'code' => 'view_invoice',
                'label' => 'View invoice',
                'available' => true,
                'url' => '/booking/invoice',
            ],
            [
                'code' => 'contact_support',
                'label' => 'Contact support',
                'available' => true,
                'url' => '/support',
            ],
        ];

        foreach ($documents as $document) {
            if (! ($document['available'] ?? false) || ! filled($document['download_path'] ?? null)) {
                continue;
            }

            $actions[] = [
                'code' => 'download_'.$document['key'],
                'label' => 'Download '.$document['label'],
                'available' => true,
                'url' => (string) $document['download_path'],
            ];
        }

        if (Auth::check()) {
            $actions[] = [
                'code' => 'my_bookings',
                'label' => 'My bookings',
                'available' => true,
                'url' => '/customer/bookings',
            ];
        } else {
            $actions[] = [
                'code' => 'lookup_booking',
                'label' => 'Lookup booking later',
                'available' => true,
                'url' => '/lookup-booking',
            ];
        }

        if ($booking->status !== BookingStatus::Cancelled) {
            $openCancellation = $booking->relationLoaded('cancellationRequests')
                ? $booking->cancellationRequests->contains(
                    fn ($requestRow) => in_array($requestRow->status->value, [
                        BookingCancellationStatus::Requested->value,
                        BookingCancellationStatus::Approved->value,
                    ], true),
                )
                : false;

            $actions[] = [
                'code' => 'request_cancellation',
                'label' => 'Request cancellation',
                'available' => Auth::check() && ! $openCancellation,
                'url' => Auth::check() ? '/customer/bookings/'.$booking->id : null,
                'reason_unavailable' => Auth::check()
                    ? ($openCancellation ? 'A cancellation request is already in progress.' : null)
                    : 'Sign in or use booking lookup to manage cancellation.',
            ];
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $paymentStatus
     * @param  array<string, mixed>  $bookingStatus
     * @param  array<string, mixed>  $ticketingStatus
     * @return array<string, mixed>
     */
    private function presentBookingPollConfig(
        Booking $booking,
        array $paymentStatus,
        array $bookingStatus,
        array $ticketingStatus,
    ): array {
        $paymentTerminal = (bool) ($paymentStatus['terminal'] ?? false);
        $bookingTerminal = (bool) ($bookingStatus['terminal'] ?? false)
            || $booking->status === BookingStatus::Cancelled
            || $booking->status === BookingStatus::Failed;
        $ticketingTerminal = (bool) ($ticketingStatus['terminal'] ?? false);
        $paymentProcessing = in_array((string) ($paymentStatus['code'] ?? ''), ['pending', 'processing'], true);
        $bookingProcessing = $booking->submitted_at !== null
            && ! $bookingTerminal
            && ! filled($booking->pnr)
            && ($paymentStatus['code'] ?? '') === 'succeeded';

        $shouldPoll = ($paymentProcessing && ! $paymentTerminal)
            || ($bookingProcessing && ! $ticketingTerminal && ! $bookingTerminal);

        return [
            'should_poll' => $shouldPoll,
            'interval_ms' => 4000,
            'max_attempts' => 45,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCancellationEligibility(Booking $booking): array
    {
        $openCancellation = $booking->relationLoaded('cancellationRequests')
            ? $booking->cancellationRequests->contains(
                fn ($requestRow) => in_array($requestRow->status->value, [
                    BookingCancellationStatus::Requested->value,
                    BookingCancellationStatus::Approved->value,
                ], true),
            )
            : false;

        return [
            'eligible' => false,
            'request_pending' => $openCancellation,
            'already_cancelled' => $booking->status === BookingStatus::Cancelled,
            'message' => $booking->status === BookingStatus::Cancelled
                ? 'This booking has been cancelled.'
                : ($openCancellation
                    ? 'A cancellation request is already in progress.'
                    : 'Use your customer account or booking lookup to request cancellation.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRefundStatus(Booking $booking): array
    {
        $latestRefund = $booking->relationLoaded('refunds')
            ? $booking->refunds->sortByDesc('id')->first()
            : null;

        if ($latestRefund === null) {
            return [
                'available' => false,
                'status' => null,
                'label' => null,
            ];
        }

        return [
            'available' => true,
            'status' => (string) ($latestRefund->status->value ?? ''),
            'label' => str_replace('_', ' ', ucfirst((string) ($latestRefund->status->value ?? ''))),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentDocumentsPortalSafe(Booking $booking, string $audience = 'customer'): array
    {
        return collect(BookingPaymentSummaryPresenter::documentsForPortal($booking, $audience))
            ->map(function (array $row) use ($audience): array {
                /** @var BookingDocument|null $document */
                $document = $row['document'] ?? null;

                return [
                    'key' => (string) ($row['key'] ?? ''),
                    'label' => (string) ($row['label'] ?? ''),
                    'status' => (string) ($row['status'] ?? 'unavailable'),
                    'available' => (bool) ($row['available'] ?? false),
                    'document_number' => $document?->document_number,
                    'download_path' => $this->documentDownloadPath($document, $audience),
                    'unavailable_message' => (string) ($row['unavailable_message'] ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    private function documentDownloadPath(?BookingDocument $document, string $audience): ?string
    {
        if ($document === null || $document->file_path === null) {
            return null;
        }

        if ($audience === 'customer' && Auth::check()) {
            return '/customer/documents/'.$document->id.'/download';
        }

        return null;
    }
}
