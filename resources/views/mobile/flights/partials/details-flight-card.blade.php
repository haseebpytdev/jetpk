@php
    $o = is_array($offer ?? null) ? $offer : null;
    $cr = is_array($criteria ?? null) ? $criteria : [];
    $segments = is_array($o['segments'] ?? null) ? $o['segments'] : [];
    $journeys = is_array($o['journeys_display'] ?? null) ? $o['journeys_display'] : [];
    $tripType = (string) ($cr['trip_type'] ?? 'one_way');
    $reqOrigin = strtoupper(trim((string) ($cr['origin'] ?? '')));
    $reqDest = strtoupper(trim((string) ($cr['destination'] ?? '')));
    $hasRoundTripGrouping = $tripType === 'round_trip'
        && count($journeys) >= 2
        && empty($o['journey_grouping_unavailable']);
    $crossJourneyLayoverAfterIndex = null;
    if ($tripType === 'round_trip' && ! $hasRoundTripGrouping && count($segments) >= 2 && $reqDest !== '') {
        foreach ($segments as $idx => $seg) {
            if (! is_array($seg) || $idx >= count($segments) - 1) {
                continue;
            }
            $next = $segments[$idx + 1];
            if (! is_array($next)) {
                continue;
            }
            if (strtoupper(trim((string) ($seg['destination'] ?? ''))) === $reqDest
                && strtoupper(trim((string) ($next['origin'] ?? ''))) === $reqDest) {
                $crossJourneyLayoverAfterIndex = $idx;
                break;
            }
        }
    }
    $airlineName = trim((string) ($o['airline_name'] ?? $o['primary_display_carrier_name'] ?? ''));
    $airlineCode = strtoupper(trim((string) ($o['airline_code'] ?? $o['primary_display_carrier'] ?? '')));
    $flightNumbers = [];
    foreach ($segments as $seg) {
        if (! is_array($seg)) {
            continue;
        }
        $fn = trim((string) ($seg['flight_number'] ?? ''));
        $ac = strtoupper(trim((string) ($seg['airline_code'] ?? $airlineCode)));
        if ($fn !== '') {
            $flightNumbers[] = $ac.$fn;
        }
    }
    if ($flightNumbers === []) {
        $singleFn = trim((string) ($o['flight_number'] ?? ''));
        if ($singleFn !== '') {
            $flightNumbers[] = $airlineCode.$singleFn;
        }
    }
    $flightNumbersLabel = $flightNumbers !== [] ? implode(', ', array_unique($flightNumbers)) : '';
    $routeSummary = trim((string) ($o['route'] ?? ''));
    if ($routeSummary === '') {
        $routeSummary = strtoupper(trim((string) ($o['departure_airport_code'] ?? ''))).' → '.strtoupper(trim((string) ($o['arrival_airport_code'] ?? '')));
    }
    $dateDisplay = trim((string) ($o['departure_date_display'] ?? ''));
    $cabinLabel = ucfirst(str_replace('_', ' ', (string) ($o['cabin'] ?? '')));
    $fareFamily = trim((string) ($o['fare_family'] ?? ''));
    $stopsDisplay = trim((string) ($o['stops_display'] ?? ''));
    if ($stopsDisplay === '') {
        $stopsCount = (int) ($o['stops'] ?? 0);
        $stopsDisplay = $stopsCount === 0 ? 'Direct' : $stopsCount.' stop'.($stopsCount === 1 ? '' : 's');
    }
    $durationDisplay = trim((string) ($o['itinerary_duration_display'] ?? $o['duration'] ?? ''));
    $roundTripJourneySummaries = [];
    $returnDateDisplay = '';
    if ($hasRoundTripGrouping) {
        foreach (array_slice($journeys, 0, 2) as $journeyIndex => $journey) {
            if (! is_array($journey)) {
                continue;
            }
            $journeyLabel = trim((string) ($journey['label'] ?? ''));
            if ($journeyLabel === '') {
                $journeyLabel = $journeyIndex === 0 ? 'Outbound' : 'Return';
            }
            $journeyDuration = trim((string) ($journey['duration_display'] ?? ''));
            $journeyStops = trim((string) ($journey['stops_display'] ?? ''));
            if ($journeyStops === '' && isset($journey['stops_count'])) {
                $journeyStopCount = (int) $journey['stops_count'];
                $journeyStops = match (true) {
                    $journeyStopCount === 0 => 'Direct',
                    $journeyStopCount === 1 => '1 stop',
                    default => $journeyStopCount.' stops',
                };
            }
            $summaryParts = array_values(array_filter([$journeyDuration, $journeyStops], static fn (string $part): bool => $part !== ''));
            if ($summaryParts !== []) {
                $roundTripJourneySummaries[] = [
                    'label' => $journeyLabel,
                    'text' => implode(' • ', $summaryParts),
                ];
            }
        }
        $returnJourney = is_array($journeys[1] ?? null) ? $journeys[1] : [];
        $returnDateDisplay = trim((string) ($returnJourney['departure_date_display'] ?? ''));
    }
    $showOneWayMetaLine = $tripType !== 'round_trip'
        && ! $hasRoundTripGrouping
        && ($dateDisplay !== '' || $durationDisplay !== '' || $stopsDisplay !== '');
    $showRoundTripDateLine = $hasRoundTripGrouping && ($dateDisplay !== '' || $returnDateDisplay !== '');
    $showRoundTripFallbackDateLine = $tripType === 'round_trip' && ! $hasRoundTripGrouping && $dateDisplay !== '';
    $bagChecked = trim((string) ($o['baggage_checked_display'] ?? ''));
    $bagCabin = trim((string) ($o['baggage_cabin_display'] ?? ''));
    $bagSummary = trim((string) ($o['baggage_summary_display'] ?? $o['baggage'] ?? ''));
    $layovers = is_array($o['layovers_display'] ?? null) ? $o['layovers_display'] : [];
@endphp
@if ($o)
    <article class="ota-mobile-flight-details__card">
        <header class="ota-mobile-flight-details__airline">
            @if (! empty($o['airline_logo_url']))
                <img src="{{ $o['airline_logo_url'] }}" alt="" class="ota-mobile-flight-details__logo" width="40" height="40">
            @else
                <span class="ota-mobile-flight-details__logo-fallback">{{ $airlineCode !== '' ? $airlineCode : '—' }}</span>
            @endif
            <div class="ota-mobile-flight-details__airline-copy">
                <p class="ota-mobile-flight-details__airline-name">{{ $airlineName !== '' ? $airlineName : 'Airline' }}</p>
                @if ($flightNumbersLabel !== '')
                    <p class="ota-mobile-flight-details__flight-no">{{ $flightNumbersLabel }}</p>
                @endif
                @if ($routeSummary !== '')
                    <p class="ota-mobile-flight-details__route-summary">{{ $routeSummary }}</p>
                @endif
                @if ($showOneWayMetaLine)
                    <p class="ota-mobile-flight-details__meta-line">
                        @if ($dateDisplay !== '')
                            <span>{{ $dateDisplay }}</span>
                        @endif
                        @if ($durationDisplay !== '')
                            <span>{{ $durationDisplay }}</span>
                        @endif
                        @if ($stopsDisplay !== '')
                            <span>{{ $stopsDisplay }}</span>
                        @endif
                    </p>
                @elseif ($showRoundTripDateLine)
                    <p class="ota-mobile-flight-details__meta-line">
                        @if ($dateDisplay !== '')
                            <span>{{ $dateDisplay }}</span>
                        @endif
                        @if ($returnDateDisplay !== '')
                            <span>– {{ $returnDateDisplay }}</span>
                        @endif
                    </p>
                @elseif ($showRoundTripFallbackDateLine)
                    <p class="ota-mobile-flight-details__meta-line">
                        <span>{{ $dateDisplay }}</span>
                    </p>
                @endif
                @if ($roundTripJourneySummaries !== [])
                    <div class="ota-mobile-flight-details__journey-durations">
                        @foreach ($roundTripJourneySummaries as $journeySummary)
                            <p class="ota-mobile-flight-details__journey-duration-line">
                                <span class="ota-mobile-flight-details__journey-duration-label">{{ $journeySummary['label'] }}</span>
                                <span class="ota-mobile-flight-details__journey-duration-value">{{ $journeySummary['text'] }}</span>
                            </p>
                        @endforeach
                    </div>
                @endif
                @if ($cabinLabel !== '' || $fareFamily !== '')
                    <p class="ota-mobile-flight-details__meta-line ota-mobile-flight-details__meta-line--cabin">
                        @if ($cabinLabel !== '')
                            <span>{{ $cabinLabel }}</span>
                        @endif
                        @if ($fareFamily !== '')
                            <span>{{ $fareFamily }}</span>
                        @endif
                    </p>
                @endif
            </div>
        </header>

        @if ($hasRoundTripGrouping)
            <div class="ota-mobile-flight-details__journeys">
                @foreach ($journeys as $journeyIndex => $journey)
                    @if (! is_array($journey))
                        @continue
                    @endif
                    @if ($journeyIndex > 0)
                        @php
                            $prevJourney = is_array($journeys[$journeyIndex - 1] ?? null) ? $journeys[$journeyIndex - 1] : [];
                            $stayAirport = strtoupper(trim((string) ($journey['origin'] ?? $prevJourney['destination'] ?? $reqDest)));
                            $stayCity = trim((string) ($prevJourney['destination_city'] ?? ''));
                            $returnStartDate = trim((string) ($journey['departure_date_display'] ?? ''));
                            $stayLabel = $returnStartDate !== ''
                                ? 'Return flight starts '.$returnStartDate
                                : ($stayCity !== ''
                                    ? 'Stay in '.$stayCity.' before return flight'
                                    : ($stayAirport !== ''
                                        ? 'Stay in '.$stayAirport.' before return flight'
                                        : 'Return flight'));
                        @endphp
                        <div class="ota-mobile-flight-details__stay-gap" role="note">
                            <span class="ota-mobile-flight-details__stay-gap-label">{{ $stayLabel }}</span>
                        </div>
                    @endif

                    @php
                        $journeyLabel = trim((string) ($journey['label'] ?? ''));
                        if ($journeyLabel === '') {
                            $journeyLabel = $journeyIndex === 0 ? 'Outbound' : 'Return';
                        }
                        $journeySegments = is_array($journey['segments_display'] ?? null) ? $journey['segments_display'] : [];
                    @endphp
                    <section class="ota-mobile-flight-details__journey-section" aria-label="{{ $journeyLabel }}">
                        <h3 class="ota-mobile-flight-details__journey-heading">{{ strtoupper($journeyLabel) }}</h3>
                        @if ($journeySegments !== [])
                            @include('mobile.flights.partials.details-segment-timeline', [
                                'segments' => $journeySegments,
                                'airlineCode' => $airlineCode,
                                'suppressLayoverAfterIndex' => null,
                                'showSubhead' => false,
                            ])
                        @else
                            <div class="ota-mobile-flight-details__times ota-mobile-flight-details__times--journey">
                                <div class="ota-mobile-flight-details__point">
                                    <span class="ota-mobile-flight-details__time">{{ $journey['departure_time_display'] ?? '' }}</span>
                                    <span class="ota-mobile-flight-details__code">{{ $journey['origin'] ?? '' }}</span>
                                    @if (! empty($journey['origin_city']))
                                        <span class="ota-mobile-flight-details__city">{{ $journey['origin_city'] }}</span>
                                    @endif
                                </div>
                                <div class="ota-mobile-flight-details__mid">
                                    <span class="ota-mobile-flight-details__duration">{{ $journey['duration_display'] ?? '' }}</span>
                                    <span class="ota-mobile-flight-details__mid-line" aria-hidden="true"></span>
                                    <span class="ota-mobile-flight-details__stops">{{ $journey['stops_display'] ?? '' }}</span>
                                </div>
                                <div class="ota-mobile-flight-details__point ota-mobile-flight-details__point--arr">
                                    <span class="ota-mobile-flight-details__time">{{ $journey['arrival_time_display'] ?? '' }}</span>
                                    <span class="ota-mobile-flight-details__code">{{ $journey['destination'] ?? '' }}</span>
                                    @if (! empty($journey['destination_city']))
                                        <span class="ota-mobile-flight-details__city">{{ $journey['destination_city'] }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @elseif ($segments !== [])
            @include('mobile.flights.partials.details-segment-timeline', [
                'segments' => $segments,
                'airlineCode' => $airlineCode,
                'suppressLayoverAfterIndex' => $crossJourneyLayoverAfterIndex,
                'showSubhead' => true,
                'subheadLabel' => 'Itinerary',
            ])
        @elseif ($journeys === [])
            <div class="ota-mobile-flight-details__leg">
                <div class="ota-mobile-flight-details__times">
                    <div class="ota-mobile-flight-details__point">
                        <span class="ota-mobile-flight-details__time">{{ $o['departure_time_display'] ?? $o['departure_time'] ?? '' }}</span>
                        <span class="ota-mobile-flight-details__code">{{ $o['departure_airport_code'] ?? '' }}</span>
                        @if (! empty($o['departure_city']))
                            <span class="ota-mobile-flight-details__city">{{ $o['departure_city'] }}</span>
                        @endif
                    </div>
                    <div class="ota-mobile-flight-details__mid">
                        <span class="ota-mobile-flight-details__duration">{{ $durationDisplay }}</span>
                        <span class="ota-mobile-flight-details__mid-line" aria-hidden="true"></span>
                        <span class="ota-mobile-flight-details__stops">{{ $stopsDisplay }}</span>
                    </div>
                    <div class="ota-mobile-flight-details__point ota-mobile-flight-details__point--arr">
                        <span class="ota-mobile-flight-details__time">{{ $o['arrival_time_display'] ?? $o['arrival_time'] ?? '' }}</span>
                        <span class="ota-mobile-flight-details__code">{{ $o['arrival_airport_code'] ?? '' }}</span>
                        @if (! empty($o['arrival_city']))
                            <span class="ota-mobile-flight-details__city">{{ $o['arrival_city'] }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if ($layovers !== [] && $segments === [] && ! $hasRoundTripGrouping)
            <div class="ota-mobile-flight-details__stopovers">
                <h3 class="ota-mobile-flight-details__subhead">Stopovers</h3>
                <ul>
                    @foreach ($layovers as $layover)
                        @if (is_string($layover) && $layover !== '')
                            <li>{{ $layover }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="ota-mobile-flight-details__baggage">
            <h3 class="ota-mobile-flight-details__subhead">Baggage</h3>
            @if ($bagChecked !== '' || $bagCabin !== '' || $bagSummary !== '')
                <dl class="ota-mobile-flight-details__baggage-dl">
                    @if ($bagSummary !== '')
                        <div class="ota-mobile-flight-details__baggage-row">
                            <dt>Allowance</dt>
                            <dd>{{ $bagSummary }}</dd>
                        </div>
                    @else
                        @if ($bagChecked !== '')
                            <div class="ota-mobile-flight-details__baggage-row">
                                <dt>Checked baggage</dt>
                                <dd>{{ $bagChecked }}</dd>
                            </div>
                        @endif
                        @if ($bagCabin !== '')
                            <div class="ota-mobile-flight-details__baggage-row">
                                <dt>Cabin baggage</dt>
                                <dd>{{ $bagCabin }}</dd>
                            </div>
                        @endif
                    @endif
                </dl>
            @else
                <p class="ota-mobile-flight-details__fallback">Baggage allowance details will be confirmed during checkout.</p>
            @endif
        </div>
    </article>
@endif
