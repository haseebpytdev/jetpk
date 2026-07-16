@php
    $routePrefix = $routePrefix ?? 'agent.finance.statement';
    $isAgentPortal = str_starts_with($routePrefix, 'agent.finance.statement');
    $showRoute = $routePrefix.'.show';
    $exportRoute = $routePrefix.'.export';
    $agencyParam = $agency ?? null;
    $filterAction = route($showRoute);
    $exportUrl = Route::has($exportRoute)
        ? route($exportRoute, request()->only(['date_from', 'date_to']))
        : null;
@endphp
<form method="get" action="{{ $filterAction }}" class="jp-panel jp-panel--filters" data-testid="finance-statement-filters">
    <div class="jp-field-grid jp-field-grid--filters">
        <div class="jp-field">
            <label class="jp-label" for="date_from">From</label>
            <input type="date" name="date_from" id="date_from" class="jp-input" value="{{ request('date_from', $statement['period']['from'] ?? '') }}">
        </div>
        <div class="jp-field">
            <label class="jp-label" for="date_to">To</label>
            <input type="date" name="date_to" id="date_to" class="jp-input" value="{{ request('date_to', $statement['period']['to'] ?? '') }}">
        </div>
        <div class="jp-field jp-field--actions">
            <div class="jp-action-bar">
                <button type="submit" class="jp-btn jp-btn--primary">Apply</button>
                @if ($exportUrl)
                    <a href="{{ $exportUrl }}" class="jp-btn jp-btn--ghost" data-testid="finance-statement-export">Export CSV</a>
                @endif
            </div>
        </div>
    </div>
    @if ($errors->any())
        <p class="jp-alert jp-alert--danger">{{ $errors->first() }}</p>
    @endif
</form>
