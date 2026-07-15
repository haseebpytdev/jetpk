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
                    <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 3.8c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z" stroke="none" fill="currentColor"/></svg>
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
                    <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
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
                <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                Search groups
            </button>
        </div>
    </form>
</div>
