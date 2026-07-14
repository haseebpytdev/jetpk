@extends(client_layout('agent-portal', 'agent'))

@section('account_title', $pageTitle ?? 'Accounting Ledger')

@section('account_content')
    @php
        $s = $summary ?? [];
    @endphp

    @if (! empty($s))
        <div class="ota-account-card-grid ota-account-card-grid--3 mb-3" data-testid="agent-accounting-summary">
            <div class="ota-account-card ota-account-card--compact">
                <div class="ota-account-card__label">Ledger liability</div>
                <div class="ota-account-card__value">PKR {{ number_format((float) ($s['ledger_liability'] ?? 0), 2) }}</div>
            </div>
            <div class="ota-account-card ota-account-card--compact">
                <div class="ota-account-card__label">Wallet balance</div>
                <div class="ota-account-card__value">PKR {{ number_format((float) ($s['wallet_balance'] ?? 0), 2) }}</div>
            </div>
            <div class="ota-account-card ota-account-card--compact">
                <div class="ota-account-card__label">Difference</div>
                <div class="ota-account-card__value">PKR {{ number_format((float) ($s['difference'] ?? 0), 2) }}</div>
            </div>
            <div class="ota-account-card ota-account-card--compact">
                <div class="ota-account-card__label">Posted transactions</div>
                <div class="ota-account-card__value">{{ $s['posted_transaction_count'] ?? 0 }}</div>
            </div>
            <div class="ota-account-card ota-account-card--compact">
                <div class="ota-account-card__label">Last posted</div>
                <div class="ota-account-card__value small">{{ isset($s['last_posted_at']) && $s['last_posted_at'] ? $s['last_posted_at']->format('Y-m-d H:i') : '—' }}</div>
            </div>
        </div>
    @endif

    <div class="ota-account-card">
        @include('dashboard.accounting.ledger._filters')
        @include('dashboard.accounting.ledger._table')
    </div>
@endsection
