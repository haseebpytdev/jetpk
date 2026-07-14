@php
    $o = is_array($offer ?? null) ? $offer : null;
    $cr = is_array($criteria ?? null) ? $criteria : [];
    $presentation = is_array($checkoutPresentation ?? null) ? $checkoutPresentation : (is_array($reviewPresentation ?? null) ? $reviewPresentation : []);
    $journeys = is_array($presentation['journeys_display'] ?? null) ? $presentation['journeys_display'] : [];
    $routeLabel = \App\Support\FlightSearch\FlightOfferDisplayPresenter::formatCriteriaRouteLabel($cr);
    if ($routeLabel === '') {
        $routeLabel = ($cr['origin'] ?? '').' → '.($cr['destination'] ?? '');
    }
    $tripTypeLabel = \App\Support\FlightSearch\FlightOfferDisplayPresenter::formatCriteriaTripTypeLabel((string) ($cr['trip_type'] ?? 'one_way'));
    $cabinLabel = ucfirst(str_replace('_', ' ', (string) ($o['cabin'] ?? $cr['cabin'] ?? 'economy')));
    $selectedFareFamilyCheckout = \App\Support\FlightSearch\FlightOfferDisplayPresenter::buildSelectedFareFamilyCheckoutView(
        is_array($selectedFareFamilyOption ?? null) ? $selectedFareFamilyOption : null
    );
    $checkoutFareRules = \App\Support\FlightSearch\FlightOfferDisplayPresenter::buildCheckoutFareRulesSidebar($o, is_array($selectedFareFamilyOption ?? null) ? $selectedFareFamilyOption : null);
    $selectedFareFamilyLabel = trim((string) ($selectedFareFamilyCheckout['name'] ?? ''));
    $totalPayable = (float) ($totalPayable ?? ($o['total'] ?? $o['final_customer_price'] ?? 0));
    $selectedFareEstimate = is_array($selectedFareEstimate ?? null) ? $selectedFareEstimate : null;
    $useSelectedFareEstimate = (bool) ($useSelectedFareEstimate ?? false) && is_array($selectedFareEstimate) && ! empty($selectedFareEstimate['has_checkout_estimate']);
    $displayTotalLabel = $useSelectedFareEstimate ? ($selectedFareEstimate['label'] ?? 'Estimated selected fare') : 'Total';
    $displayTotalAmount = $useSelectedFareEstimate
        ? (string) preg_replace('/^Approx\.\s*/i', '', (string) ($selectedFareEstimate['price_display'] ?? ''))
        : 'Rs '.number_format($totalPayable, 0);
    $displayTotalApproximate = $useSelectedFareEstimate && ! empty($selectedFareEstimate['price_is_approximate']);
@endphp
@if ($o)
    <article class="ota-mobile-booking__card ota-mobile-booking__flight-card">
        <header class="ota-mobile-booking__flight-head">
            <div class="ota-mobile-booking__flight-brand">
                @if (! empty($airlineLogo))
                    <img src="{{ $airlineLogo }}" alt="" class="ota-mobile-booking__flight-logo" width="32" height="32">
                @endif
                <div>
                    <p class="ota-mobile-booking__flight-airline">{{ $o['airline_name'] ?? '' }}</p>
                    <p class="ota-mobile-booking__flight-no">{{ $o['carrier_code'] ?? '' }}{{ $o['flight_number'] ?? '' }}</p>
                </div>
            </div>
            <span class="ota-mobile-booking__pill">{{ $tripTypeLabel }}</span>
        </header>
        <p class="ota-mobile-booking__route">{{ $routeLabel }}</p>
        @if ($journeys !== [])
            @foreach ($journeys as $journey)
                @if (is_array($journey))
                    <div class="ota-mobile-booking__leg">
                        <div class="ota-mobile-booking__leg-row">
                            <span class="ota-mobile-booking__leg-time">{{ $journey['departure_time_display'] ?? '' }}</span>
                            <span class="ota-mobile-booking__leg-arrow" aria-hidden="true">→</span>
                            <span class="ota-mobile-booking__leg-time">{{ $journey['arrival_time_display'] ?? '' }}</span>
                        </div>
                        <p class="ota-mobile-booking__leg-meta">
                            {{ $journey['label'] ?? 'Flight' }}
                            @if (! empty($journey['stops_display'])) · {{ $journey['stops_display'] }}@endif
                            @if (! empty($journey['duration_display'])) · {{ $journey['duration_display'] }}@endif
                        </p>
                        @if (! empty($journey['departure_date_display']))
                            <p class="ota-mobile-booking__leg-date">{{ $journey['departure_date_display'] }}</p>
                        @endif
                        <x-bookings.checkout-journey-layovers :journey="$journey" />
                    </div>
                @endif
            @endforeach
        @else
            <div class="ota-mobile-booking__leg">
                <div class="ota-mobile-booking__leg-row">
                    <span class="ota-mobile-booking__leg-time">{{ $presentation['departure_time_display'] ?? '' }}</span>
                    <span class="ota-mobile-booking__leg-arrow" aria-hidden="true">→</span>
                    <span class="ota-mobile-booking__leg-time">{{ $presentation['arrival_time_display'] ?? '' }}</span>
                </div>
                <p class="ota-mobile-booking__leg-meta">
                    {{ $presentation['stops_display'] ?? '' }}
                    @if (! empty($presentation['itinerary_duration_display'])) · {{ $presentation['itinerary_duration_display'] }}@endif
                </p>
                @if (! empty($presentation['departure_date_display']))
                    <p class="ota-mobile-booking__leg-date">{{ $presentation['departure_date_display'] }}</p>
                @endif
                <x-bookings.checkout-journey-layovers :journey="[
                    'origin' => $presentation['origin'] ?? ($cr['origin'] ?? ''),
                    'destination' => $presentation['destination'] ?? ($cr['destination'] ?? ''),
                    'stops_display' => $presentation['stops_display'] ?? '',
                    'stops_count' => $presentation['stops_count'] ?? null,
                    'duration_display' => $presentation['itinerary_duration_display'] ?? '',
                    'segments_display' => $presentation['segments_display'] ?? [],
                    'layovers_display' => $presentation['layovers_display'] ?? [],
                    'layover_summary' => $presentation['layover_summary'] ?? [],
                    'connection_details_unavailable' => $presentation['connection_details_unavailable'] ?? false,
                ]" />
            </div>
        @endif
        <ul class="ota-mobile-booking__tags">
            @if (!empty($checkoutFareRules['baggage_display']))
                <li>{{ $checkoutFareRules['baggage_display'] }}</li>
            @endif
            @if (!empty($checkoutFareRules['cabin_display']))
                <li>{{ $checkoutFareRules['cabin_display'] }}</li>
            @elseif (!empty($cabinLabel))
                <li>{{ $cabinLabel }}</li>
            @endif
        </ul>
        @if ($selectedFareFamilyCheckout)
            <x-bookings.selected-fare-family-block :checkout="$selectedFareFamilyCheckout" variant="mobile" />
        @endif
        @if ($useSelectedFareEstimate || $totalPayable > 0)
            <p class="ota-mobile-booking__flight-price">
                @if ($useSelectedFareEstimate)
                    <span class="ota-mobile-booking__flight-price-label">{{ $displayTotalLabel }}</span>
                    @if ($displayTotalApproximate)
                        <span class="ota-mobile-booking__tag-note">Approx.</span>
                    @endif
                    {{ $displayTotalAmount }}
                @else
                    Rs {{ number_format($totalPayable, 0) }}
                @endif
            </p>
            @if ($useSelectedFareEstimate)
                <p class="ota-mobile-booking__tag-note">{{ $selectedFareEstimate['validation_note'] ?? \App\Support\FlightSearch\FlightOfferDisplayPresenter::SELECTED_FARE_VALIDATION_NOTE }}</p>
            @endif
        @endif
    </article>
@endif
