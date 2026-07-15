@extends(client_layout('mobile-app', 'mobile'))

@section('title', $pageTitle ?? 'Accounting Ledger')

@section('mobile_app_title', 'Accounting Ledger')

@section('content')
    @php
        $s = $summary ?? [];
        $query = request()->query();
        $query = array_filter($query, static fn ($value) => filled($value));
    @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-accounting-ledger-index">
        @if (! empty($s))
            <section class="ota-mobile-agent__card" data-testid="agent-accounting-summary">
                <h2 class="ota-mobile-agent__card-title">Summary</h2>
                <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                    <div>
                        <dt>Ledger liability</dt>
                        <dd class="ota-mobile-agent__amount">PKR {{ number_format((float) ($s['ledger_liability'] ?? 0), 2) }}</dd>
                    </div>
                    <div>
                        <dt>Wallet balance</dt>
                        <dd class="ota-mobile-agent__amount">PKR {{ number_format((float) ($s['wallet_balance'] ?? 0), 2) }}</dd>
                    </div>
                    <div>
                        <dt>Difference</dt>
                        <dd class="ota-mobile-agent__amount">PKR {{ number_format((float) ($s['difference'] ?? 0), 2) }}</dd>
                    </div>
                    <div>
                        <dt>Posted transactions</dt>
                        <dd class="ota-mobile-agent__amount">{{ $s['posted_transaction_count'] ?? 0 }}</dd>
                    </div>
                </dl>
            </section>
        @endif

        <form method="get" action="{{ route('agent.accounting.ledger.index') }}" class="ota-mobile-agent__filters ota-mobile-agent__filters--form" data-testid="accounting-ledger-filters">
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="date_from">From</label>
                <input type="date" name="date_from" id="date_from" class="ota-mobile-agent__input" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="date_to">To</label>
                <input type="date" name="date_to" id="date_to" class="ota-mobile-agent__input" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="transaction_ref">Transaction ref</label>
                <input type="search" name="transaction_ref" id="transaction_ref" class="ota-mobile-agent__input" value="{{ $filters['transaction_ref'] ?? '' }}">
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="booking_ref">Booking ref</label>
                <input type="search" name="booking_ref" id="booking_ref" class="ota-mobile-agent__input" value="{{ $filters['booking_ref'] ?? '' }}">
            </div>
            <div class="ota-mobile-agent__actions">
                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">Filter</button>
                @if (! empty($query))
                    <a href="{{ route('agent.accounting.ledger.index') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary">Clear</a>
                @endif
            </div>
        </form>

        @if ($transactions->isEmpty())
            <div class="ota-mobile-agent__empty" data-testid="accounting-ledger-empty">
                <p class="ota-mobile-agent__empty-title">No ledger transactions yet</p>
                <p class="ota-mobile-agent__empty-help">New verified finance events will appear here.</p>
            </div>
        @else
            <div class="ota-mobile-agent__list">
                @foreach ($transactions as $tx)
                    @php
                        $debit = round((float) ($tx->debit_total ?? 0), 2);
                        $credit = round((float) ($tx->credit_total ?? 0), 2);
                        $balanced = abs($debit - $credit) < 0.01;
                    @endphp
                    <article class="ota-mobile-agent__card" data-testid="accounting-ledger-row-{{ $tx->id }}">
                        <div class="ota-mobile-agent__card-head">
                            <span class="ota-mobile-agent__ref">{{ $tx->posted_at?->format('Y-m-d H:i') ?? $tx->occurred_at?->format('Y-m-d H:i') ?? '—' }}</span>
                            @include('mobile.agent.partials.agent-status-pill', ['status' => $tx->status->value])
                        </div>
                        <p class="ota-mobile-agent__text-safe">Ref: {{ $tx->transaction_ref }}</p>
                        <p class="ota-mobile-agent__muted">Type: {{ str_replace('_', ' ', $tx->transaction_type->value) }}</p>
                        @if ($tx->booking)
                            <p class="ota-mobile-agent__text-safe">Booking: {{ $tx->booking->booking_reference }}</p>
                        @endif
                        <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                            <div>
                                <dt>Amount</dt>
                                <dd class="ota-mobile-agent__amount">{{ $tx->currency }} {{ number_format((float) $tx->amount_total, 2) }}</dd>
                            </div>
                            <div>
                                <dt>Debit</dt>
                                <dd class="ota-mobile-agent__amount">{{ number_format($debit, 2) }}</dd>
                            </div>
                            <div>
                                <dt>Credit</dt>
                                <dd class="ota-mobile-agent__amount">{{ number_format($credit, 2) }}</dd>
                            </div>
                            <div>
                                <dt>Balanced</dt>
                                <dd class="ota-mobile-agent__amount">{{ $balanced ? 'Yes' : 'No' }}</dd>
                            </div>
                        </dl>
                        <div class="ota-mobile-agent__actions">
                            <a href="{{ route('agent.accounting.ledger.show', $tx) }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">View</a>
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($transactions->hasPages())
                <div class="ota-mobile-agent__pagination">{{ $transactions->links() }}</div>
            @endif
        @endif
    </div>
@endsection
