@php
    $facets = $groupFacets ?? ['sectors' => [], 'airlines' => [], 'departure_dates' => [], 'categories' => []];
    $filters = $groupSearchFilters ?? [];
    $minDate = $minDate ?? now()->format('Y-m-d');
    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
@endphp
<form method="get" action="{{ client_route('group-ticketing.search') }}" class="ota-hero-group-search-form">
    <div class="ota-hero-group-search-row">
        <div class="ota-hero-search-field ota-hero-group-search-field--airline">
            <label class="ota-hero-search-label" for="{{ $widgetId }}-group-airline">Airline</label>
            <select id="{{ $widgetId }}-group-airline" name="airline" class="ota-hero-search-control ota-hero-search-control--select">
                <option value="">Any airline</option>
                @foreach ($facets['airlines'] ?? [] as $airline)
                    <option value="{{ e($airline['name']) }}" @selected(($filters['airline'] ?? '') === $airline['name'])>{{ e($airline['name']) }}</option>
                @endforeach
            </select>
        </div>

        <div class="ota-hero-search-field ota-hero-group-search-field--sector">
            <label class="ota-hero-search-label" for="{{ $widgetId }}-group-sector">Sector</label>
            <select id="{{ $widgetId }}-group-sector" name="sector" class="ota-hero-search-control ota-hero-search-control--select">
                <option value="">Any sector</option>
                @foreach ($facets['sectors'] ?? [] as $sector)
                    <option value="{{ e($sector) }}" @selected(($filters['sector'] ?? '') === $sector)>{{ e($sector) }}</option>
                @endforeach
            </select>
        </div>

        <div class="ota-hero-search-field ota-hero-group-search-field--date-from">
            <label class="ota-hero-search-label" for="{{ $widgetId }}-group-date-from">From Date</label>
            <div class="ota-hero-search-input ota-hero-search-input--date">
                <input
                    class="ota-hero-search-control ota-hero-search-control--date"
                    id="{{ $widgetId }}-group-date-from"
                    name="date_from"
                    type="date"
                    value="{{ $dateFrom }}"
                    min="{{ $minDate }}"
                    autocomplete="off"
                >
                <span class="ota-hero-search-input__icon" aria-hidden="true"><i class="fa fa-calendar"></i></span>
            </div>
        </div>

        <div class="ota-hero-search-field ota-hero-group-search-field--date-to">
            <label class="ota-hero-search-label" for="{{ $widgetId }}-group-date-to">To Date</label>
            <div class="ota-hero-search-input ota-hero-search-input--date">
                <input
                    class="ota-hero-search-control ota-hero-search-control--date"
                    id="{{ $widgetId }}-group-date-to"
                    name="date_to"
                    type="date"
                    value="{{ $dateTo }}"
                    min="{{ $dateFrom !== '' ? $dateFrom : $minDate }}"
                    autocomplete="off"
                >
                <span class="ota-hero-search-input__icon" aria-hidden="true"><i class="fa fa-calendar"></i></span>
            </div>
        </div>

        <div class="ota-hero-search-field ota-hero-search-field--submit ota-hero-group-search-field--submit">
            <span class="ota-hero-search-label ota-hero-search-label--sr">Search Groups</span>
            <button type="submit" class="ota-hero-search-submit">
                <i class="fa fa-search" aria-hidden="true"></i>
                <span>Search Groups</span>
            </button>
        </div>

        <div class="ota-hero-search-field ota-hero-group-search-field--clear">
            <span class="ota-hero-search-label ota-hero-search-label--sr">Clear</span>
            <a href="{{ client_route('group-ticketing.search') }}" class="ota-hero-group-search-clear">Clear</a>
        </div>
    </div>
</form>
