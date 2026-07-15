@php
    $currency = (string) ($statement['currency'] ?? 'PKR');
    $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
    $recon = $statement['reconciliation'] ?? [];
    $reconStatus = (string) ($recon['status'] ?? 'no_ledger_data');
    $badgeClass = match ($reconStatus) {
        'matched' => 'bg-success',
        'mismatch' => 'bg-danger',
        default => 'bg-secondary',
    };
    $badgeLabel = match ($reconStatus) {
        'matched' => 'Matched',
        'mismatch' => 'Mismatch',
        default => 'No ledger data',
    };
@endphp
<div class="row row-cards mb-4 g-3" data-testid="finance-statement-summary">
    @foreach ([
        ['label' => 'Opening Balance', 'key' => 'opening_balance'],
        ['label' => 'Total Credits', 'key' => 'total_credits'],
        ['label' => 'Total Debits', 'key' => 'total_debits'],
        ['label' => 'Closing Balance', 'key' => 'closing_balance'],
    ] as $card)
        <div class="col-6 col-md-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary small">{{ $card['label'] }}</div>
                    <div class="h3 mb-0">{{ $moneyPrefix }}{{ number_format((float) ($statement[$card['key']] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
    @endforeach
    <div class="col-6 col-md-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="text-secondary small">Ledger Liability</div>
                <div class="h3 mb-0">{{ $moneyPrefix }}{{ number_format((float) ($recon['ledger_liability'] ?? 0), 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="text-secondary small">Reconciliation</div>
                <div class="mt-1">
                    <span class="badge {{ $badgeClass }}" data-testid="finance-statement-recon-status">{{ $badgeLabel }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
