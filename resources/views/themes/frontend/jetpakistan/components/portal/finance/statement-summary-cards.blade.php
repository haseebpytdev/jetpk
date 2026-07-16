@php
    $currency = (string) ($statement['currency'] ?? 'PKR');
    $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
    $recon = $statement['reconciliation'] ?? [];
    $reconStatus = (string) ($recon['status'] ?? 'no_ledger_data');
    $badgeClass = match ($reconStatus) {
        'matched' => 'jp-badge--success',
        'mismatch' => 'jp-badge--danger',
        default => 'jp-badge--muted',
    };
    $badgeLabel = match ($reconStatus) {
        'matched' => 'Matched',
        'mismatch' => 'Mismatch',
        default => 'No ledger data',
    };
@endphp
<div class="jp-kpi-grid jp-kpi-grid--3" data-testid="finance-statement-summary">
    @foreach ([
        ['label' => 'Opening Balance', 'key' => 'opening_balance'],
        ['label' => 'Total Credits', 'key' => 'total_credits'],
        ['label' => 'Total Debits', 'key' => 'total_debits'],
        ['label' => 'Closing Balance', 'key' => 'closing_balance'],
    ] as $card)
        <div class="jp-kpi">
            <p class="jp-kpi__label">{{ $card['label'] }}</p>
            <p class="jp-kpi__value jp-money">{{ $moneyPrefix }}{{ number_format((float) ($statement[$card['key']] ?? 0), 2) }}</p>
        </div>
    @endforeach
    <div class="jp-kpi">
        <p class="jp-kpi__label">Ledger Liability</p>
        <p class="jp-kpi__value jp-money">{{ $moneyPrefix }}{{ number_format((float) ($recon['ledger_liability'] ?? 0), 2) }}</p>
    </div>
    <div class="jp-kpi">
        <p class="jp-kpi__label">Reconciliation</p>
        <p class="jp-kpi__value">
            <span class="jp-badge {{ $badgeClass }}" data-testid="finance-statement-recon-status">{{ $badgeLabel }}</span>
        </p>
    </div>
</div>
