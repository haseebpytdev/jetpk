@php
    use App\Enums\LedgerTransactionStatus;
    use App\Enums\LedgerTransactionType;

    $indexRoute = ($routePrefix ?? 'admin.accounting.ledger').'.index';
@endphp

<form method="get" action="{{ route($indexRoute) }}" class="card mb-3" data-testid="accounting-ledger-filters">
    <div class="card-body">
        <div class="row g-2">
            @if (($scope ?? 'platform') === 'platform' && ($agencies ?? collect())->isNotEmpty())
                <div class="col-md-3">
                    <label class="form-label">Agency</label>
                    <select name="agency_id" class="form-select form-select-sm">
                        <option value="">All agencies</option>
                        @foreach ($agencies as $agency)
                            <option value="{{ $agency->id }}" @selected(($filters['agency_id'] ?? '') === (string) $agency->id)>{{ $agency->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2">
                <label class="form-label">Posted from</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">Posted to</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="transaction_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach (LedgerTransactionType::cases() as $case)
                        <option value="{{ $case->value }}" @selected(($filters['transaction_type'] ?? '') === $case->value)>{{ str_replace('_', ' ', $case->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach (LedgerTransactionStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected(($filters['status'] ?? '') === $case->value)>{{ $case->value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Posted filter</label>
                <select name="posted_filter" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    <option value="posted_only" @selected(($filters['posted_filter'] ?? '') === 'posted_only')>Posted only</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Balanced</label>
                <select name="balanced" class="form-select form-select-sm">
                    <option value="">Any</option>
                    <option value="yes" @selected(($filters['balanced'] ?? '') === 'yes')>Balanced</option>
                    <option value="no" @selected(($filters['balanced'] ?? '') === 'no')>Unbalanced</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Amount min</label>
                <input type="number" step="0.01" name="amount_min" value="{{ $filters['amount_min'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">Amount max</label>
                <input type="number" step="0.01" name="amount_max" value="{{ $filters['amount_max'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label">Transaction ref</label>
                <input type="search" name="transaction_ref" value="{{ $filters['transaction_ref'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label">Booking reference</label>
                <input type="search" name="booking_ref" value="{{ $filters['booking_ref'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label">Source type</label>
                <input type="search" name="source_type" value="{{ $filters['source_type'] ?? '' }}" class="form-control form-control-sm" placeholder="e.g. App\Models\BookingPayment">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sort</label>
                <select name="sort" class="form-select form-select-sm">
                    <option value="">Posted date</option>
                    <option value="amount_total" @selected(($filters['sort'] ?? '') === 'amount_total')>Amount</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Direction</label>
                <select name="direction_sort" class="form-select form-select-sm">
                    <option value="desc" @selected(($filters['direction_sort'] ?? 'desc') === 'desc')>Newest first</option>
                    <option value="asc" @selected(($filters['direction_sort'] ?? '') === 'asc')>Oldest first</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Per page</label>
                <select name="per_page" class="form-select form-select-sm">
                    @foreach ($perPageOptions ?? [25, 50] as $opt)
                        <option value="{{ $opt }}" @selected(($perPage ?? 25) === $opt)>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route($indexRoute) }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                @if (($scope ?? 'platform') === 'platform' && Route::has(($routePrefix ?? 'admin.accounting.ledger').'.export'))
                    <a href="{{ route(($routePrefix ?? 'admin.accounting.ledger').'.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm" data-testid="accounting-ledger-export">Export CSV</a>
                @elseif (($scope ?? 'platform') === 'platform' && Route::has($indexRoute))
                    <a href="{{ route($indexRoute, array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-secondary btn-sm" data-testid="accounting-ledger-export">Export CSV</a>
                @endif
            </div>
        </div>
    </div>
</form>
