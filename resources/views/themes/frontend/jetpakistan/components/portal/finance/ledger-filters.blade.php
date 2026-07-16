@php
    use App\Enums\LedgerTransactionStatus;
    use App\Enums\LedgerTransactionType;

    $indexRoute = ($routePrefix ?? 'agent.accounting.ledger').'.index';
@endphp

<form method="get" action="{{ route($indexRoute) }}" class="jp-panel jp-panel--filters" data-testid="accounting-ledger-filters">
    <div class="jp-field-grid jp-field-grid--filters">
        @if (($scope ?? 'platform') === 'platform' && ($agencies ?? collect())->isNotEmpty())
            <div class="jp-field">
                <label class="jp-label" for="agency_id">Agency</label>
                <select name="agency_id" id="agency_id" class="jp-input">
                    <option value="">All agencies</option>
                    @foreach ($agencies as $agency)
                        <option value="{{ $agency->id }}" @selected(($filters['agency_id'] ?? '') === (string) $agency->id)>{{ $agency->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="jp-field">
            <label class="jp-label" for="date_from">Posted from</label>
            <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] ?? '' }}" class="jp-input">
        </div>
        <div class="jp-field">
            <label class="jp-label" for="date_to">Posted to</label>
            <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] ?? '' }}" class="jp-input">
        </div>
        <div class="jp-field">
            <label class="jp-label" for="transaction_type">Type</label>
            <select name="transaction_type" id="transaction_type" class="jp-input">
                <option value="">All</option>
                @foreach (LedgerTransactionType::cases() as $case)
                    <option value="{{ $case->value }}" @selected(($filters['transaction_type'] ?? '') === $case->value)>{{ str_replace('_', ' ', $case->value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="jp-field">
            <label class="jp-label" for="status">Status</label>
            <select name="status" id="status" class="jp-input">
                <option value="">All</option>
                @foreach (LedgerTransactionStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected(($filters['status'] ?? '') === $case->value)>{{ $case->value }}</option>
                @endforeach
            </select>
        </div>
        <div class="jp-field">
            <label class="jp-label" for="posted_filter">Posted filter</label>
            <select name="posted_filter" id="posted_filter" class="jp-input">
                <option value="">All statuses</option>
                <option value="posted_only" @selected(($filters['posted_filter'] ?? '') === 'posted_only')>Posted only</option>
            </select>
        </div>
        <div class="jp-field">
            <label class="jp-label" for="balanced">Balanced</label>
            <select name="balanced" id="balanced" class="jp-input">
                <option value="">Any</option>
                <option value="yes" @selected(($filters['balanced'] ?? '') === 'yes')>Balanced</option>
                <option value="no" @selected(($filters['balanced'] ?? '') === 'no')>Unbalanced</option>
            </select>
        </div>
        <div class="jp-field">
            <label class="jp-label" for="amount_min">Amount min</label>
            <input type="number" step="0.01" name="amount_min" id="amount_min" value="{{ $filters['amount_min'] ?? '' }}" class="jp-input">
        </div>
        <div class="jp-field">
            <label class="jp-label" for="amount_max">Amount max</label>
            <input type="number" step="0.01" name="amount_max" id="amount_max" value="{{ $filters['amount_max'] ?? '' }}" class="jp-input">
        </div>
        <div class="jp-field">
            <label class="jp-label" for="transaction_ref">Transaction ref</label>
            <input type="search" name="transaction_ref" id="transaction_ref" value="{{ $filters['transaction_ref'] ?? '' }}" class="jp-input">
        </div>
        <div class="jp-field">
            <label class="jp-label" for="booking_ref">Booking reference</label>
            <input type="search" name="booking_ref" id="booking_ref" value="{{ $filters['booking_ref'] ?? '' }}" class="jp-input">
        </div>
        <div class="jp-field">
            <label class="jp-label" for="source_type">Source type</label>
            <input type="search" name="source_type" id="source_type" value="{{ $filters['source_type'] ?? '' }}" class="jp-input" placeholder="e.g. App\Models\BookingPayment">
        </div>
        <div class="jp-field">
            <label class="jp-label" for="sort">Sort</label>
            <select name="sort" id="sort" class="jp-input">
                <option value="">Posted date</option>
                <option value="amount_total" @selected(($filters['sort'] ?? '') === 'amount_total')>Amount</option>
            </select>
        </div>
        <div class="jp-field">
            <label class="jp-label" for="direction_sort">Direction</label>
            <select name="direction_sort" id="direction_sort" class="jp-input">
                <option value="desc" @selected(($filters['direction_sort'] ?? 'desc') === 'desc')>Newest first</option>
                <option value="asc" @selected(($filters['direction_sort'] ?? '') === 'asc')>Oldest first</option>
            </select>
        </div>
        <div class="jp-field">
            <label class="jp-label" for="per_page">Per page</label>
            <select name="per_page" id="per_page" class="jp-input">
                @foreach ($perPageOptions ?? [25, 50] as $opt)
                    <option value="{{ $opt }}" @selected(($perPage ?? 25) === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div class="jp-field jp-field--actions">
            <div class="jp-action-bar">
                <button type="submit" class="jp-btn jp-btn--primary">Filter</button>
                <a href="{{ route($indexRoute) }}" class="jp-btn jp-btn--ghost">Clear</a>
            </div>
        </div>
    </div>
</form>
