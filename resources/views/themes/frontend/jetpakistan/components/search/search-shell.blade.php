@php
    $widgetId = 'jp-'.substr(md5((string) microtime(true).(string) random_int(1000, 999999)), 0, 8);
    $minDate = $minDate ?? now()->format('Y-m-d');
    $defaultTripType = old('trip_type', $defaultTripType ?? 'round_trip');
    $defaultOrigin = old('from', $defaultOrigin ?? '');
    $defaultDestination = old('to', $defaultDestination ?? '');
    $defaultOriginDisplay = old('from_display', $defaultOriginDisplay ?? $defaultOrigin);
    $defaultDestinationDisplay = old('to_display', $defaultDestinationDisplay ?? $defaultDestination);
    $defaultDepart = old('depart', $defaultDepart ?? '');
    $defaultReturnDate = old('return_date', $defaultReturnDate ?? '');
    $adults = max(1, (int) ($adults ?? 1));
    $children = max(0, (int) ($children ?? 0));
    $infants = min(max(0, (int) ($infants ?? 0)), $adults);
    $cabin = old('cabin', $cabin ?? 'economy');
    $defaultStopsDirect = (bool) ($defaultStopsDirect ?? false);
    $defaultIncludeNearby = (bool) ($defaultIncludeNearby ?? false);
    $showGroupTab = $showGroupTab ?? (
        \Illuminate\Support\Facades\Route::has('group-ticketing.search')
        || \Illuminate\Support\Facades\Route::has('client.parity.group-ticketing.search')
    );
    $activeProduct = 'flights';
@endphp

<div
    class="search hseq hseq-4"
    id="jp-flight-search"
    role="search"
    data-hero-search
    data-jp-search
    data-jp-search-ready="false"
    data-min-date="{{ $minDate }}"
    data-airports-url="{{ url('/airports/search') }}"
    data-default-trip="{{ $defaultTripType }}"
    data-min-segments="2"
    data-max-segments="6"
>
    @if ($errors->any())
        <x-jp.alert variant="danger">
            <strong>Please fix the following:</strong>
            <ul class="jp-search-errors">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </x-jp.alert>
    @endif

    @include('themes.frontend.jetpakistan.components.search.trip-tabs', [
        'showGroupTab' => $showGroupTab,
        'defaultTripType' => $defaultTripType,
    ])

    @include('themes.frontend.jetpakistan.components.search.flights-panel', [
        'widgetId' => $widgetId,
        'minDate' => $minDate,
        'defaultTripType' => $defaultTripType,
        'defaultOrigin' => $defaultOrigin,
        'defaultDestination' => $defaultDestination,
        'defaultOriginDisplay' => $defaultOriginDisplay,
        'defaultDestinationDisplay' => $defaultDestinationDisplay,
        'defaultDepart' => $defaultDepart,
        'defaultReturnDate' => $defaultReturnDate,
        'activeProduct' => $activeProduct,
        'adults' => $adults,
        'children' => $children,
        'infants' => $infants,
        'cabin' => $cabin,
        'defaultStopsDirect' => $defaultStopsDirect,
        'defaultIncludeNearby' => $defaultIncludeNearby,
    ])

    @if ($showGroupTab)
        @include('themes.frontend.jetpakistan.components.search.groups-panel', [
            'widgetId' => $widgetId,
            'minDate' => $minDate,
            'groupFacets' => $groupFacets ?? [],
            'groupSearchFilters' => $groupSearchFilters ?? [],
            'activeProduct' => $activeProduct,
        ])
    @endif
</div>
