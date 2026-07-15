@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Booking confirmation')

@section('content')
    @php
        use App\Enums\BookingStatus;
        use App\Enums\SupplierProvider;
        use App\Support\Bookings\SabrePreCheckoutSellabilityPresentation;
        use App\Support\FlightSearch\FlightOfferDisplayPresenter;

        $d = $draft;
        $o = is_array($offer ?? null) ? $offer : null;
        $cr = $criteria;
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $isSabreBooking = strtolower((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')) === SupplierProvider::Sabre->value;
        $preCheckoutPresentation = $isSabreBooking
            ? SabrePreCheckoutSellabilityPresentation::resolveForBooking($booking)
            : null;
        $preCheckoutConfirmNote = is_array($preCheckoutPresentation)
            ? SabrePreCheckoutSellabilityPresentation::confirmationNote($preCheckoutPresentation)
            : null;
        $ref = $d['booking_reference'] ?? ($booking->booking_reference ?? null);
        $pnr = trim((string) ($booking->pnr ?? ''));
        $hasPnr = $pnr !== '';
        $allPassengers = $booking->passengers->sortBy('passenger_index')->values();
        $paxCount = $allPassengers->count();
        $fare = $booking->fareBreakdown ?? null;
        $statusBadge = match ($booking->status) {
            BookingStatus::Confirmed, BookingStatus::Paid, BookingStatus::Ticketed => [
                'label' => 'Booking confirmed',
                'tone' => 'success',
            ],
            BookingStatus::FareReview => [
                'label' => 'Under review',
                'tone' => 'review',
            ],
            default => [
                'label' => 'Booking request received',
                'tone' => 'pending',
            ],
        };
        $isFullyConfirmed = in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Paid, BookingStatus::Ticketed], true);
        $tripTypeLabel = FlightOfferDisplayPresenter::formatCriteriaTripTypeLabel((string) ($cr['trip_type'] ?? 'one_way'));
        $routeLabel = FlightOfferDisplayPresenter::formatCriteriaRouteLabel($cr);
        if ($routeLabel === '') {
            $routeLabel = ($cr['origin'] ?? '').' → '.($cr['destination'] ?? '');
        }
        $cabinLabel = ucfirst(str_replace('_', ' ', (string) (is_array($o) ? ($o['cabin'] ?? $cr['cabin'] ?? 'economy') : ($cr['cabin'] ?? 'economy'))));
        $selectedFareFamilyOption = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : null;
        $selectedFareFamilyCheckout = FlightOfferDisplayPresenter::buildSelectedFareFamilyCheckoutView($selectedFareFamilyOption);
        $selectedFareEstimate = FlightOfferDisplayPresenter::buildCheckoutSelectedFareEstimatePresentation($selectedFareFamilyOption);
        $checkoutFareRules = FlightOfferDisplayPresenter::buildCheckoutFareRulesSidebar($o, $selectedFareFamilyOption);
        $useSelectedFareEstimate = is_array($selectedFareEstimate) && ! empty($selectedFareEstimate['has_checkout_estimate']);
        $confirmPresentation = [];
        $confirmJourneys = [];
        if (is_array($o)) {
            $displayOffer = FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($o, $cr);
            $confirmCityMap = FlightOfferDisplayPresenter::airportCityMap(
                FlightOfferDisplayPresenter::collectIataCodes($displayOffer)
            );
            $confirmPresentation = FlightOfferDisplayPresenter::buildPresentation($displayOffer, $cr, $confirmCityMap);
            $confirmJourneys = is_array($confirmPresentation['journeys_display'] ?? null)
                ? $confirmPresentation['journeys_display']
                : [];
            $cabinLabel = ucfirst(str_replace('_', ' ', (string) ($checkoutFareRules['cabin_display'] ?? $o['cabin'] ?? $cr['cabin'] ?? 'economy')));
        }
        $dateLine = '';
        if (! empty($cr['depart_date'])) {
            try {
                $dateLine = \Illuminate\Support\Carbon::parse((string) $cr['depart_date'])->format('j M');
            } catch (\Throwable) {
                $dateLine = (string) $cr['depart_date'];
            }
        }
        if (! empty($cr['return_date'])) {
            try {
                $dateLine .= ' - '.\Illuminate\Support\Carbon::parse((string) $cr['return_date'])->format('j M, Y');
            } catch (\Throwable) {
                $dateLine .= ' - '.(string) $cr['return_date'];
            }
        } elseif ($dateLine !== '') {
            try {
                $dateLine = \Illuminate\Support\Carbon::parse((string) $cr['depart_date'])->format('j M, Y');
            } catch (\Throwable) {
                // keep partial
            }
        }
        $travelerLabel = $paxCount === 1 ? '1 Traveler' : $paxCount.' Travelers';
    @endphp

    <div class="ota-mobile-booking ota-mobile-booking--confirmation" data-testid="ota-mobile-confirmation" data-mobile-booking-confirmation>
        <div class="ota-mobile-booking__confirm-hero">
            <div class="ota-mobile-booking__confirm-icon ota-mobile-booking__confirm-icon--{{ $statusBadge['tone'] }}" aria-hidden="true">
                @if ($isFullyConfirmed)
                    <svg viewBox="0 0 24 24" width="36" height="36" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                @else
                    <svg viewBox="0 0 24 24" width="36" height="36" fill="currentColor"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                @endif
            </div>
            <h1 class="ota-mobile-booking__confirm-title">{{ $statusBadge['label'] }}</h1>
            <p class="ota-mobile-booking__confirm-sub">
                @if ($isFullyConfirmed && $hasPnr)
                    Your booking is confirmed and your ticket details are being prepared.
                @elseif ($hasPnr)
                    Your booking reference has been saved. Ticketing may still be pending.
                @else
                    Your booking request has been saved. Our team will review your details and contact you shortly.
                @endif
            </p>
            @if (!empty($supplierConfirmationNotice['notice'] ?? ''))
                <p class="ota-mobile-booking__confirm-note">{{ $supplierConfirmationNotice['notice'] }}</p>
            @endif
            @if (!empty($preCheckoutConfirmNote) && ($supplierProvider ?? '') === \App\Enums\SupplierProvider::Sabre->value)
                <p class="ota-mobile-booking__confirm-note">{{ $preCheckoutConfirmNote }}</p>
            @endif
        </div>

        <article class="ota-mobile-booking__card ota-mobile-booking__pnr-card">
            <div class="ota-mobile-booking__pnr-row">
                <div>
                    <p class="ota-mobile-booking__pnr-label">Booking reference</p>
                    <p class="ota-mobile-booking__pnr-value" data-mobile-booking-ref>{{ $ref ?? '—' }}</p>
                </div>
                @if ($ref)
                    <button type="button" class="ota-mobile-booking__copy-btn" data-mobile-copy-ref="{{ $ref }}" aria-label="Copy booking reference">Copy</button>
                @endif
            </div>
            <div class="ota-mobile-booking__pnr-row">
                <div>
                    <p class="ota-mobile-booking__pnr-label">PNR</p>
                    <p class="ota-mobile-booking__pnr-value">{{ $hasPnr ? $pnr : 'Under review' }}</p>
                </div>
                @if ($hasPnr)
                    <button type="button" class="ota-mobile-booking__copy-btn" data-mobile-copy-ref="{{ $pnr }}" aria-label="Copy PNR">Copy</button>
                @endif
            </div>
        </article>

        <article class="ota-mobile-booking__card">
            <h2 class="ota-mobile-booking__card-title">Trip summary</h2>
            <p class="ota-mobile-booking__route ota-mobile-booking__route--large">{{ $routeLabel }}</p>
            @if ($dateLine !== '')
                <p class="ota-mobile-booking__muted">{{ $dateLine }}</p>
            @endif
            <p class="ota-mobile-booking__muted">{{ $travelerLabel }}, {{ $cabinLabel }}</p>
            @if ($o)
                <div class="ota-mobile-booking__confirm-airline">
                    @if (! empty($airlineLogo))
                        <img src="{{ $airlineLogo }}" alt="" width="28" height="28" class="ota-mobile-booking__flight-logo">
                    @endif
                    <span>{{ $o['airline_name'] ?? '' }}</span>
                </div>
                @if ($confirmJourneys !== [])
                    @foreach ($confirmJourneys as $journey)
                        @if (is_array($journey))
                            <p class="ota-mobile-booking__leg-meta">
                                {{ $journey['departure_time_display'] ?? '' }} → {{ $journey['arrival_time_display'] ?? '' }}
                                @if (! empty($journey['departure_date_display'])) · {{ $journey['departure_date_display'] }}@endif
                            </p>
                        @endif
                    @endforeach
                @elseif (! empty($confirmPresentation['departure_time_display']))
                    <p class="ota-mobile-booking__leg-meta">
                        {{ $confirmPresentation['departure_time_display'] }} → {{ $confirmPresentation['arrival_time_display'] ?? '' }}
                    </p>
                @endif
                @if (! empty($checkoutFareRules['baggage_display']) && ! $selectedFareFamilyCheckout)
                    <p class="ota-mobile-booking__muted">{{ $checkoutFareRules['baggage_display'] }}</p>
                @endif
                @if ($selectedFareFamilyCheckout)
                    <x-bookings.selected-fare-family-block :checkout="$selectedFareFamilyCheckout" variant="mobile" />
                @endif
            @endif
            @if ($useSelectedFareEstimate)
                <p class="ota-mobile-booking__total-value ota-mobile-booking__total-value--inline">
                    @if (!empty($selectedFareEstimate['price_is_approximate']))
                        <span class="ota-mobile-booking__tag-note">Approx.</span>
                    @endif
                    {{ preg_replace('/^Approx\.\s*/i', '', (string) ($selectedFareEstimate['price_display'] ?? '')) }}
                </p>
                <p class="ota-mobile-booking__tag-note">{{ $selectedFareEstimate['validation_note'] ?? FlightOfferDisplayPresenter::SELECTED_FARE_VALIDATION_NOTE }}</p>
            @elseif ($fare)
                <p class="ota-mobile-booking__total-value ota-mobile-booking__total-value--inline">Rs {{ number_format((float) $fare->total, 0) }}</p>
            @endif
        </article>

        <div class="ota-mobile-booking__confirm-actions">
            <a href="{{ route('booking.lookup') }}" class="ota-mobile-booking__cta ota-mobile-booking__cta--link">View booking</a>
            <a href="{{ route('home') }}" class="ota-mobile-booking__cta ota-mobile-booking__cta--secondary">Back to home</a>
            <a href="{{ route('support') }}" class="ota-mobile-booking__text-link">Contact support</a>
        </div>
    </div>
@endsection
