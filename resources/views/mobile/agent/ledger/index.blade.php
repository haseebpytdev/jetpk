@extends('layouts.mobile-app')

@section('title', 'Ledger')

@section('mobile_app_title', 'Ledger')

@section('mobile_app_back')
    @if (Route::has('agent.wallet.show'))
        <a href="{{ route('agent.wallet.show') }}" class="ota-mobile-app__back-btn" aria-label="Back to wallet">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
        </a>
    @endif
@endsection

@section('content')
    @php
        use App\Enums\AgentWalletTransactionStatus;
        use App\Enums\AgentWalletTransactionType;

        $ws = $summary ?? [];
        $currency = (string) ($ws['currency'] ?? 'PKR');
        $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
        $agencyBal = $agencyBalance ?? [];
    @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-ledger">
        @if (! empty($agencyBal))
            <section class="ota-mobile-agent__card" data-testid="agent-agency-balance">
                <h2 class="ota-mobile-agent__card-title">Agency balance</h2>
                <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                    <div>
                        <dt>All wallets</dt>
                        <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($agencyBal['balance'] ?? 0), 2) }}</dd>
                    </div>
                    <div>
                        <dt>Pending deposits</dt>
                        <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($agencyBal['pending_deposits'] ?? 0), 2) }}</dd>
                    </div>
                </dl>
            </section>
        @endif

        <form method="get" action="{{ route('agent.ledger.index') }}" class="ota-mobile-agent__filters ota-mobile-agent__filters--form" data-testid="agent-ledger-filters">
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="date_from">From</label>
                <input type="date" name="date_from" id="date_from" class="ota-mobile-agent__input" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="date_to">To</label>
                <input type="date" name="date_to" id="date_to" class="ota-mobile-agent__input" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="type">Type</label>
                <select name="type" id="type" class="ota-mobile-agent__input">
                    <option value="">All types</option>
                    @foreach (AgentWalletTransactionType::cases() as $case)
                        <option value="{{ $case->value }}" @selected(($filters['type'] ?? '') === $case->value)>{{ str_replace('_', ' ', $case->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="status">Status</label>
                <select name="status" id="status" class="ota-mobile-agent__input">
                    <option value="">All statuses</option>
                    @foreach (AgentWalletTransactionStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected(($filters['status'] ?? '') === $case->value)>{{ str_replace('_', ' ', $case->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="q">Search</label>
                <input type="search" name="q" id="q" class="ota-mobile-agent__input" value="{{ $filters['q'] ?? '' }}" placeholder="Reference or description">
            </div>
            <div class="ota-mobile-agent__actions">
                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">Filter</button>
                @if (array_filter($filters ?? []))
                    <a href="{{ route('agent.ledger.index') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary">Clear</a>
                @endif
            </div>
        </form>

        @if ($transactions->isEmpty())
            <div class="ota-mobile-agent__empty" data-testid="ota-mobile-agent-ledger-empty">
                <p class="ota-mobile-agent__empty-title">No ledger entries</p>
                <p class="ota-mobile-agent__empty-help">Transactions appear here when deposits are submitted or approved.</p>
            </div>
        @else
            <div class="ota-mobile-agent__list">
                @foreach ($transactions as $tx)
                    @include('mobile.agent.partials.ledger-row-card', [
                        'transaction' => $tx,
                        'moneyPrefix' => $moneyPrefix,
                        'timezone' => $timezone ?? config('app.timezone'),
                    ])
                @endforeach
            </div>
            @if ($transactions->hasPages())
                <div class="ota-mobile-agent__pagination">{{ $transactions->links() }}</div>
            @endif
        @endif
    </div>
@endsection
