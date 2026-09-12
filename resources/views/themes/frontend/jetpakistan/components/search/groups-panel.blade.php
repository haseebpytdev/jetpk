@php
    $facets = $groupFacets ?? ['sectors' => [], 'airlines' => [], 'departure_dates' => [], 'categories' => []];
    $filters = $groupSearchFilters ?? [];
    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
@endphp

<div data-jp-panel="groups" @if(($activeProduct ?? 'flights') !== 'groups') hidden @endif>
    <form method="get" action="{{ client_route('group-ticketing.search') }}" class="jp-groups-form" data-jp-group-form>
        <div class="fields jp-group-fields">
            <div class="field">
                <label for="{{ $widgetId }}-group-airline">Airline</label>
                <div class="jp-field-value-row">
                    <x-jp.icon name="plane" class="icon" />
                    <select id="{{ $widgetId }}-group-airline" name="airline" class="jp-select-input @if(($filters['airline'] ?? '') === '') is-placeholder @endif">
                        <option value="">Any airline</option>
                        @foreach ($facets['airlines'] ?? [] as $airline)
                            <option value="{{ e($airline['name']) }}" @selected(($filters['airline'] ?? '') === $airline['name'])>{{ e($airline['name']) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="{{ $widgetId }}-group-sector">Sector</label>
                <div class="jp-field-value-row">
                    <x-jp.icon name="map-pin" class="icon" />
                    <select id="{{ $widgetId }}-group-sector" name="sector" class="jp-select-input @if(($filters['sector'] ?? '') === '') is-placeholder @endif">
                        <option value="">Any sector</option>
                        @foreach ($facets['sectors'] ?? [] as $sector)
                            <option value="{{ e($sector) }}" @selected(($filters['sector'] ?? '') === $sector)>{{ e($sector) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @include('themes.frontend.jetpakistan.components.search.date-field', [
                'id' => $widgetId.'-group-date-from',
                'label' => 'From date',
                'name' => 'date_from',
                'value' => $dateFrom,
                'min' => $minDate,
                'role' => 'group_from',
                'extraClass' => 'dep',
            ])
            @include('themes.frontend.jetpakistan.components.search.date-field', [
                'id' => $widgetId.'-group-date-to',
                'label' => 'To date',
                'name' => 'date_to',
                'value' => $dateTo,
                'min' => $dateFrom !== '' ? $dateFrom : $minDate,
                'role' => 'group_to',
                'extraClass' => 'ret',
            ])
        </div>
        <div class="search-bottom">
            <a href="{{ client_route('group-ticketing.search') }}" class="btn btn-ghost">Clear filters</a>
            <button type="submit" class="btn btn-primary btn-search">
                <x-jp.icon name="search" class="icon" />
                Search groups
            </button>
        </div>
    </form>
</div>
