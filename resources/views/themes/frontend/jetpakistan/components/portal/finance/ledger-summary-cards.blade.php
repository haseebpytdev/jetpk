@php
    $s = $summary ?? [];
@endphp
@if (! empty($s))
    <div class="jp-kpi-grid jp-kpi-grid--5" data-testid="agent-accounting-summary">
        <div class="jp-kpi">
            <p class="jp-kpi__label">Ledger liability</p>
            <p class="jp-kpi__value jp-money">PKR {{ number_format((float) ($s['ledger_liability'] ?? 0), 2) }}</p>
        </div>
        <div class="jp-kpi">
            <p class="jp-kpi__label">Wallet balance</p>
            <p class="jp-kpi__value jp-money">PKR {{ number_format((float) ($s['wallet_balance'] ?? 0), 2) }}</p>
        </div>
        <div class="jp-kpi">
            <p class="jp-kpi__label">Difference</p>
            <p class="jp-kpi__value jp-money">PKR {{ number_format((float) ($s['difference'] ?? 0), 2) }}</p>
        </div>
        <div class="jp-kpi">
            <p class="jp-kpi__label">Posted transactions</p>
            <p class="jp-kpi__value">{{ $s['posted_transaction_count'] ?? 0 }}</p>
        </div>
        <div class="jp-kpi">
            <p class="jp-kpi__label">Last posted</p>
            <p class="jp-kpi__value jp-kpi__value--sm">{{ isset($s['last_posted_at']) && $s['last_posted_at'] ? $s['last_posted_at']->format('Y-m-d H:i') : '—' }}</p>
        </div>
    </div>
@endif
