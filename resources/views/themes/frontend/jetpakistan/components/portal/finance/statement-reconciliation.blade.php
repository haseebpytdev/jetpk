@php
    $recon = $statement['reconciliation'] ?? [];
    $ledgerSummary = $statement['ledger_summary'] ?? [];
    $ledgerTransactions = $ledgerSummary['transactions'] ?? collect();
    $currency = (string) ($statement['currency'] ?? 'PKR');
    $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
@endphp
<div class="jp-finance-recon-grid">
    <div class="jp-card" data-testid="finance-statement-reconciliation">
        <div class="jp-panel__header">
            <h2 class="jp-panel__title">Wallet vs ledger</h2>
        </div>
        <dl class="jp-dl">
            <dt>Wallet balance (source of truth)</dt>
            <dd class="jp-money">{{ $moneyPrefix }}{{ number_format((float) ($recon['wallet_balance'] ?? 0), 2) }}</dd>
            <dt>Ledger liability</dt>
            <dd class="jp-money">{{ $moneyPrefix }}{{ number_format((float) ($recon['ledger_liability'] ?? 0), 2) }}</dd>
            <dt>Difference</dt>
            <dd class="jp-money">{{ $moneyPrefix }}{{ number_format((float) ($recon['difference'] ?? 0), 2) }}</dd>
        </dl>
        <p class="jp-help">Double-entry ledger is shown for transparency; wallet transactions remain the statement source of truth.</p>
    </div>
    <div class="jp-card" data-testid="finance-statement-ledger-period">
        <div class="jp-panel__header">
            <h2 class="jp-panel__title">Ledger transactions (period)</h2>
        </div>
        @if ($ledgerTransactions->isEmpty())
            <p class="jp-empty">No posted ledger transactions in this period.</p>
        @else
            <div class="jp-table-wrap">
                <table class="jp-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Ref</th>
                            <th>Type</th>
                            <th class="jp-table__num">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ledgerTransactions as $tx)
                            <tr>
                                <td class="jp-nowrap">{{ $tx->occurred_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $tx->transaction_ref }}</td>
                                <td>{{ str_replace('_', ' ', $tx->transaction_type->value ?? '') }}</td>
                                <td class="jp-table__num jp-money">{{ $moneyPrefix }}{{ number_format((float) $tx->amount_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
