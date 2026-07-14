@php
    $timelineSegments = is_array($segments ?? null) ? $segments : [];
    $timelineAirlineCode = strtoupper(trim((string) ($airlineCode ?? '')));
    $suppressLayoverAfterIndex = $suppressLayoverAfterIndex ?? null;
    $showSubhead = (bool) ($showSubhead ?? false);
    $subheadLabel = trim((string) ($subheadLabel ?? 'Itinerary'));
@endphp
@if ($timelineSegments !== [])
    <div class="ota-mobile-flight-details__timeline" aria-label="Flight segments">
        @if ($showSubhead && $subheadLabel !== '')
            <h3 class="ota-mobile-flight-details__subhead">{{ $subheadLabel }}</h3>
        @endif
        @foreach ($timelineSegments as $segmentIndex => $segment)
            @if (is_array($segment))
                @php
                    $segOrigin = strtoupper(trim((string) ($segment['origin'] ?? '')));
                    $segDest = strtoupper(trim((string) ($segment['destination'] ?? '')));
                    $segOriginCity = trim((string) ($segment['origin_city'] ?? ''));
                    $segDestCity = trim((string) ($segment['destination_city'] ?? ''));
                    $segAirline = strtoupper(trim((string) ($segment['airline_code'] ?? $timelineAirlineCode)));
                    $segFlightNo = trim((string) ($segment['flight_number'] ?? ''));
                    $segDuration = trim((string) ($segment['duration_display'] ?? ''));
                    $layoverAfter = trim((string) ($segment['layover_after_display'] ?? ''));
                    if ($suppressLayoverAfterIndex !== null && (int) $segmentIndex === (int) $suppressLayoverAfterIndex) {
                        $layoverAfter = '';
                    }
                @endphp
                <div class="ota-mobile-flight-details__timeline-segment">
                    <div class="ota-mobile-flight-details__timeline-row">
                        <div class="ota-mobile-flight-details__timeline-track" aria-hidden="true">
                            <span class="ota-mobile-flight-details__timeline-dot"></span>
                            <span class="ota-mobile-flight-details__timeline-line"></span>
                        </div>
                        <div class="ota-mobile-flight-details__timeline-content">
                            <div class="ota-mobile-flight-details__timeline-point">
                                <span class="ota-mobile-flight-details__time">{{ $segment['departure_time_display'] ?? '' }}</span>
                                <span class="ota-mobile-flight-details__code">{{ $segOrigin }}</span>
                                @if ($segOriginCity !== '')
                                    <span class="ota-mobile-flight-details__city">{{ $segOriginCity }}</span>
                                @endif
                                @if (! empty($segment['departure_date_display']))
                                    <span class="ota-mobile-flight-details__date">{{ $segment['departure_date_display'] }}</span>
                                @endif
                            </div>
                            <div class="ota-mobile-flight-details__timeline-flight">
                                @if ($segAirline !== '' && $segFlightNo !== '')
                                    <span class="ota-mobile-flight-details__timeline-flight-no">{{ $segAirline }}{{ $segFlightNo }}</span>
                                @endif
                                @if ($segDuration !== '')
                                    <span class="ota-mobile-flight-details__timeline-duration">{{ $segDuration }}</span>
                                @endif
                            </div>
                            <div class="ota-mobile-flight-details__timeline-point">
                                <span class="ota-mobile-flight-details__time">{{ $segment['arrival_time_display'] ?? '' }}</span>
                                <span class="ota-mobile-flight-details__code">{{ $segDest }}</span>
                                @if ($segDestCity !== '')
                                    <span class="ota-mobile-flight-details__city">{{ $segDestCity }}</span>
                                @endif
                                @if (! empty($segment['arrival_date_display']))
                                    <span class="ota-mobile-flight-details__date">{{ $segment['arrival_date_display'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if ($layoverAfter !== '')
                        <p class="ota-mobile-flight-details__layover">{{ $layoverAfter }}</p>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
@endif
