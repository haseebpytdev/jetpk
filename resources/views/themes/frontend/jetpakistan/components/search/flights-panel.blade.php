@php
    $isRound = ($defaultTripType ?? 'round_trip') === 'round_trip';
    $adultsVal = (int) old('adults', $adults ?? 1);
    $childrenVal = (int) old('children', $children ?? 0);
    $infantsVal = (int) old('infants', $infants ?? 0);
    $cabinVal = old('cabin', $cabin ?? 'economy');
@endphp

<div data-jp-panel="flights" @if(($activeProduct ?? 'flights') !== 'flights') hidden @endif>
    <form
        method="get"
        action="{{ client_route('flights.results') }}"
        class="jp-flight-form"
        data-jp-flight-form
        novalidate
    >
        <input type="hidden" name="trip_type" value="{{ $defaultTripType ?? 'round_trip' }}" data-jp-trip-type>

        <div data-jp-simple-fields @if(($defaultTripType ?? 'round_trip') === 'multi_city') hidden @endif>
            <div class="fields jp-search-row">
                @include('themes.frontend.jetpakistan.components.search.airport-field', [
                    'id' => $widgetId.'-from',
                    'label' => 'From',
                    'displayName' => 'from_display',
                    'hiddenName' => 'from',
                    'displayValue' => $defaultOriginDisplay ?? $defaultOrigin ?? '',
                    'codeValue' => $defaultOrigin ?? '',
                    'role' => 'from',
                    'required' => true,
                ])

                <div class="swap-wrap">
                    <button type="button" class="swap" id="swapBtn" data-jp-swap aria-label="Swap origin and destination">
                        <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M7 16V4M7 4 3 8M7 4l4 4M17 8v12M17 20l4-4M17 20l-4-4"/></svg>
                    </button>
                </div>

                @include('themes.frontend.jetpakistan.components.search.airport-field', [
                    'id' => $widgetId.'-to',
                    'label' => 'To',
                    'displayName' => 'to_display',
                    'hiddenName' => 'to',
                    'displayValue' => $defaultDestinationDisplay ?? $defaultDestination ?? '',
                    'codeValue' => $defaultDestination ?? '',
                    'role' => 'to',
                    'required' => true,
                ])

                @include('themes.frontend.jetpakistan.components.search.date-field', [
                    'id' => $widgetId.'-depart',
                    'label' => 'Departure',
                    'name' => 'depart',
                    'value' => $defaultDepart ?? '',
                    'min' => $minDate,
                    'role' => 'depart',
                    'extraClass' => 'dep jp-oneway-date',
                    'hidden' => $isRound,
                ])

                @include('themes.frontend.jetpakistan.components.search.date-range-field', [
                    'id' => $widgetId.'-range',
                    'departValue' => $defaultDepart ?? '',
                    'returnValue' => $defaultReturnDate ?? '',
                    'min' => $minDate,
                    'hidden' => ! $isRound,
                ])

                <div class="jp-chrome-slot jp-pax-slot-row" data-jp-pax-slot-row></div>
                <div class="jp-chrome-slot jp-submit-slot-row" data-jp-submit-slot-row></div>
            </div>

            @include('themes.frontend.jetpakistan.components.search.search-action-row', [
                'defaultStopsDirect' => $defaultStopsDirect ?? false,
                'defaultIncludeNearby' => $defaultIncludeNearby ?? false,
            ])
        </div>

        @include('themes.frontend.jetpakistan.components.search.multi-city-segments', [
            'widgetId' => $widgetId,
            'minDate' => $minDate,
            'defaultTripType' => $defaultTripType ?? 'round_trip',
        ])

        <div class="jp-multi-footer" data-jp-multi-footer hidden>
            <div class="jp-chrome-slot jp-pax-slot-multi" data-jp-pax-slot-multi></div>
            <div class="jp-chrome-slot jp-submit-slot-multi" data-jp-submit-slot-multi></div>
        </div>

        <div class="jp-flight-chrome" data-jp-flight-chrome>
            @include('themes.frontend.jetpakistan.components.search.passenger-selector', [
                'widgetId' => $widgetId,
                'adultsVal' => $adultsVal,
                'childrenVal' => $childrenVal,
                'infantsVal' => $infantsVal,
                'cabinVal' => $cabinVal,
            ])

            <div class="field jp-submit-field" data-jp-submit-field>
                <span class="jp-field-sr-label">Search</span>
                <button type="submit" class="btn btn-primary btn-search jp-search-submit ota-hero-search-submit" data-jp-flight-submit>
                    <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <span class="jp-search-submit-text">Search</span>
                </button>
            </div>
        </div>

    </form>
</div>
