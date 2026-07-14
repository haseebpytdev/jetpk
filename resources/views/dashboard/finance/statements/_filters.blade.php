@php
    $routePrefix = $routePrefix ?? 'admin.finance.statements';
    $isAgentPortal = str_starts_with($routePrefix, 'agent.finance.statement');
    $showRoute = $isAgentPortal ? $routePrefix.'.show' : $routePrefix.'.show';
    $exportRoute = $isAgentPortal ? $routePrefix.'.export' : $routePrefix.'.export';
    $agencyParam = $agency ?? null;
    $filterAction = $isAgentPortal
        ? route($showRoute)
        : ($agencyParam ? route($showRoute, $agencyParam) : route($routePrefix.'.index'));
    $exportUrl = $agencyParam && Route::has($exportRoute)
        ? ($isAgentPortal
            ? route($exportRoute, request()->only(['date_from', 'date_to']))
            : route($exportRoute, array_merge([$agencyParam], request()->only(['date_from', 'date_to']))))
        : null;
@endphp
<form method="get" action="{{ $filterAction }}" class="card mb-3" data-testid="finance-statement-filters">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label" for="date_from">From</label>
                <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="{{ request('date_from', $statement['period']['from'] ?? '') }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="date_to">To</label>
                <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="{{ request('date_to', $statement['period']['to'] ?? '') }}">
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                @if ($exportUrl)
                    <a href="{{ $exportUrl }}" class="btn btn-outline-secondary btn-sm" data-testid="finance-statement-export">Export CSV</a>
                @endif
            </div>
        </div>
        @if ($errors->any())
            <div class="text-danger small mt-2">{{ $errors->first() }}</div>
        @endif
    </div>
</form>
