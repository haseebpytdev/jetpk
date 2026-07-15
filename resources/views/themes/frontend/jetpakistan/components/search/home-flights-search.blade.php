@php
    use Illuminate\Support\Facades\Route;

    $context = $context ?? 'home';
    $criteria = is_array($criteria ?? null) ? $criteria : [];
    $inlineDisplay = is_array($inlineDisplay ?? null) ? $inlineDisplay : [];

    $originCode = strtoupper(trim((string) ($defaultOrigin ?? $criteria['origin'] ?? '')));
    $destinationCode = strtoupper(trim((string) ($defaultDestination ?? $criteria['destination'] ?? '')));
    $originDisplay = $defaultOriginDisplay
        ?? $inlineDisplay['origin_subtitle']
        ?? $inlineDisplay['origin_code']
        ?? $originCode;
    $destinationDisplay = $defaultDestinationDisplay
        ?? $inlineDisplay['destination_subtitle']
        ?? $inlineDisplay['destination_code']
        ?? $destinationCode;

    $tripType = old('trip_type', $defaultTripType ?? $criteria['trip_type'] ?? 'round_trip');
    $departDate = old('depart', $defaultDepart ?? $criteria['depart_date'] ?? '');
    $returnDate = old('return_date', $defaultReturnDate ?? $criteria['return_date'] ?? '');

    $adultsCount = max(1, (int) ($adults ?? $criteria['adults'] ?? 1));
    $childrenCount = max(0, (int) ($children ?? $criteria['children'] ?? 0));
    $infantsCount = min(max(0, (int) ($infants ?? $criteria['infants'] ?? 0)), $adultsCount);
    $cabinClass = old('cabin', $cabin ?? $criteria['cabin'] ?? 'economy');

    $stopsDirect = old('stops');
    if ($stopsDirect === null) {
        $stopsDirect = filter_var($criteria['direct_only'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || request()->query('stops') === 'direct'
            ? 'direct'
            : '';
    }

    $includeNearby = old('include_nearby');
    if ($includeNearby === null) {
        $includeNearby = filter_var($criteria['nearby_airports'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || request()->query('include_nearby') === '1'
            ? '1'
            : '';
    }

    if ($context === 'results') {
        $resolvedShowGroupTab = false;
    } else {
        $resolvedShowGroupTab = $showGroupTab ?? (
            Route::has('group-ticketing.search')
            || Route::has('client.parity.group-ticketing.search')
        );
    }
@endphp

@include('themes.frontend.jetpakistan.components.search.search-shell', [
    'defaultDepart' => $departDate,
    'defaultOrigin' => $originCode,
    'defaultDestination' => $destinationCode,
    'defaultOriginDisplay' => $originDisplay,
    'defaultDestinationDisplay' => $destinationDisplay,
    'defaultReturnDate' => $returnDate,
    'defaultTripType' => $tripType,
    'minDate' => $minDate ?? now()->format('Y-m-d'),
    'adults' => $adultsCount,
    'children' => $childrenCount,
    'infants' => $infantsCount,
    'cabin' => $cabinClass,
    'defaultStopsDirect' => $stopsDirect === 'direct',
    'defaultIncludeNearby' => $includeNearby === '1',
    'showGroupTab' => $resolvedShowGroupTab,
    'groupFacets' => $groupFacets ?? [],
    'groupSearchFilters' => $groupSearchFilters ?? [],
])
