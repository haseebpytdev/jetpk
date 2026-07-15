@php
    $jpOffer = is_array($jpOffer ?? null) ? $jpOffer : (is_array($offer ?? null) ? $offer : null);
    $jpCriteria = is_array($jpCriteria ?? null) ? $jpCriteria : (is_array($criteria ?? null) ? $criteria : []);
    $jpJourneys = is_array($jpJourneys ?? null) ? $jpJourneys : [];
    $jpPresentation = is_array($jpPresentation ?? null) ? $jpPresentation : [];
    $jpTripTypeLabel = (string) ($jpTripTypeLabel ?? '');
    $jpRouteLabel = (string) ($jpRouteLabel ?? '');
    $jpAirlineLogo = $jpAirlineLogo ?? ($airlineLogo ?? null);
    $jpFareRules = is_array($jpFareRules ?? null) ? $jpFareRules : [];
    $jpCabinLabel = (string) ($jpCabinLabel ?? 'Economy');
    $jpSelectedFareFamily = is_array($jpSelectedFareFamily ?? null) ? $jpSelectedFareFamily : null;
    $jpShowFareBreakdown = (bool) ($jpShowFareBreakdown ?? false);
    $jpFareBreakdown = is_array($jpFareBreakdown ?? null) ? $jpFareBreakdown : [];
    $jpUseSelectedFareEstimate = (bool) ($jpUseSelectedFareEstimate ?? false);
    $jpSelectedFareEstimate = is_array($jpSelectedFareEstimate ?? null) ? $jpSelectedFareEstimate : null;
    $jpPassengerCountSummary = is_array($jpPassengerCountSummary ?? null) ? $jpPassengerCountSummary : null;
    $jpSelectedFareEstimateDrift = (bool) ($jpSelectedFareEstimateDrift ?? false);
    $jpCardTitle = (string) ($jpCardTitle ?? 'Flight summary');
    $jpReturnSplitSummary = is_array($jpReturnSplitSummary ?? null) ? $jpReturnSplitSummary : [];
    $jpIsReturnSplit = ! empty($jpReturnSplitSummary['is_return_split']);
@endphp

@if ($jpOffer)
<article class="jp-checkout-card jp-checkout-card--trip" data-jp-trip-summary>
    <header class="jp-checkout-card__head">
        <h2 class="jp-checkout-card__title">{{ $jpCardTitle }}</h2>
        @if ($jpTripTypeLabel !== '')
            <span class="jp-checkout-pill">{{ $jpTripTypeLabel }}</span>
        @endif
    </header>

    <p class="jp-trip-route">{{ $jpRouteLabel }}</p>

    @if ($jpIsReturnSplit)
        <x-bookings.return-split-checkout-summary :summary="$jpReturnSplitSummary" />
    @else
        <div class="jp-trip-legs">
            @if ($jpJourneys !== [])
                @foreach ($jpJourneys as $journey)
                    @if (is_array($journey))
                        <div class="jp-trip-leg">
                            <div class="jp-trip-leg__meta-row">
                                <span class="jp-trip-leg__label">{{ $journey['label'] ?? 'Flight' }}</span>
                                <span class="jp-trip-leg__meta">{{ $journey['stops_display'] ?? '' }}@if (!empty($journey['duration_display'])) · {{ $journey['duration_display'] }}@endif</span>
                            </div>
                            <p class="jp-trip-leg__route">
                                {{ $journey['origin'] ?? '' }}@if (!empty($journey['origin_city'])) ({{ $journey['origin_city'] }})@endif
                                <span aria-hidden="true">→</span>
                                {{ $journey['destination'] ?? '' }}@if (!empty($journey['destination_city'])) ({{ $journey['destination_city'] }})@endif
                            </p>
                            <div class="jp-trip-leg__times">
                                <span>{{ $journey['departure_time_display'] ?? '' }}</span>
                                <span class="jp-trip-leg__dash" aria-hidden="true">—</span>
                                <span>{{ $journey['arrival_time_display'] ?? '' }}@if (!empty($journey['arrival_day_offset'])) <em class="jp-trip-leg__offset">{{ $journey['arrival_day_offset'] }}</em>@endif</span>
                            </div>
                            <p class="jp-trip-leg__dates">
                                {{ $journey['departure_date_display'] ?? '' }}@if (!empty($journey['arrival_date_display'])) · {{ $journey['arrival_date_display'] }}@endif
                            </p>
                            <x-bookings.checkout-journey-layovers :journey="$journey" />
                        </div>
                    @endif
                @endforeach
            @else
                <div class="jp-trip-leg">
                    <div class="jp-trip-leg__meta-row">
                        <span class="jp-trip-leg__label">Outbound</span>
                        <span class="jp-trip-leg__meta">{{ $jpPresentation['stops_display'] ?? '' }}@if (!empty($jpPresentation['itinerary_duration_display'])) · {{ $jpPresentation['itinerary_duration_display'] }}@endif</span>
                    </div>
                    <p class="jp-trip-leg__route">{{ $jpCriteria['origin'] ?? '' }} <span aria-hidden="true">→</span> {{ $jpCriteria['destination'] ?? '' }}</p>
                    <div class="jp-trip-leg__times">
                        <span>{{ $jpPresentation['departure_time_display'] ?? '' }}</span>
                        <span class="jp-trip-leg__dash" aria-hidden="true">—</span>
                        <span>{{ $jpPresentation['arrival_time_display'] ?? '' }}</span>
                    </div>
                    <p class="jp-trip-leg__dates">
                        {{ $jpPresentation['departure_date_display'] ?? '' }}
                        @if (!empty($jpPresentation['arrival_date_display']))
                            · {{ $jpPresentation['arrival_date_display'] }}
                        @endif
                    </p>
                    <x-bookings.checkout-journey-layovers :journey="[
                        'origin' => $jpPresentation['origin'] ?? ($jpCriteria['origin'] ?? ''),
                        'destination' => $jpPresentation['destination'] ?? ($jpCriteria['destination'] ?? ''),
                        'stops_display' => $jpPresentation['stops_display'] ?? '',
                        'stops_count' => $jpPresentation['stops_count'] ?? null,
                        'duration_display' => $jpPresentation['itinerary_duration_display'] ?? '',
                        'segments_display' => $jpPresentation['segments_display'] ?? [],
                        'layovers_display' => $jpPresentation['layovers_display'] ?? [],
                        'layover_summary' => $jpPresentation['layover_summary'] ?? [],
                        'connection_details_unavailable' => $jpPresentation['connection_details_unavailable'] ?? false,
                    ]" />
                </div>
            @endif
        </div>

        <div class="jp-trip-airline">
            @if (!empty($jpAirlineLogo))
                <img src="{{ $jpAirlineLogo }}" alt="" class="jp-trip-airline__logo" width="36" height="36">
            @endif
            <div>
                <p class="jp-trip-airline__name">{{ $jpOffer['airline_name'] ?? '' }}</p>
                <p class="jp-trip-airline__flight">{{ $jpOffer['carrier_code'] ?? '' }}{{ $jpOffer['flight_number'] ?? '' }}</p>
            </div>
        </div>

        <ul class="jp-trip-chips" aria-label="Fare highlights">
            <li class="jp-trip-chip"><i class="fa fa-suitcase" aria-hidden="true"></i> {{ $jpFareRules['baggage_display'] ?? ($jpOffer['baggage'] ?? 'Baggage per fare rules') }}</li>
            <li class="jp-trip-chip">{{ $jpCabinLabel }}</li>
            <li class="jp-trip-chip">
                @if (!empty($jpOffer['refundable']))
                    <span class="jp-trip-chip__badge jp-trip-chip__badge--ok">Refundable</span>
                @else
                    <span class="jp-trip-chip__badge">Non-refundable</span>
                @endif
            </li>
        </ul>

        @if ($jpSelectedFareFamily)
            @include('themes.frontend.jetpakistan.frontend.booking.partials.jp-fare-family-panel', ['checkout' => $jpSelectedFareFamily])
        @endif

        @if ($jpShowFareBreakdown)
            <div class="jp-fare-breakdown-block">
                <h3 class="jp-fare-breakdown-block__title">Fare details</h3>
                <x-bookings.checkout-fare-breakdown
                    :breakdown="$jpFareBreakdown"
                    :use-selected-fare-estimate="$jpUseSelectedFareEstimate"
                    :selected-fare-estimate="$jpSelectedFareEstimate"
                    :selected-fare-estimate-drift-detected="$jpSelectedFareEstimateDrift"
                    :passenger-count-summary="$jpPassengerCountSummary"
                />
            </div>
        @endif

        <p class="jp-checkout-fare-notice">Final fare and price will be confirmed during airline price validation.</p>
    @endif
</article>
@endif
