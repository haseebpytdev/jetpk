@php
    $recon = $statement['reconciliation'] ?? [];
    $ledgerSummary = $statement['ledger_summary'] ?? [];
    $ledgerTransactions = $ledgerSummary['transactions'] ?? collect();
    $currency = (string) ($statement['currency'] ?? 'PKR');
    $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
@endphp
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100" data-testid="finance-statement-reconciliation">
            <div class="card-header">
                <h3 class="card-title mb-0">Wallet vs ledger</h3>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-7">Wallet balance (source of truth)</dt>
                    <dd class="col-5 text-end">{{ $moneyPrefix }}{{ number_format((float) ($recon['wallet_balance'] ?? 0), 2) }}</dd>
                    <dt class="col-7">Ledger liability</dt>
                    <dd class="col-5 text-end">{{ $moneyPrefix }}{{ number_format((float) ($recon['ledger_liability'] ?? 0), 2) }}</dd>
                    <dt class="col-7">Difference</dt>
                    <dd class="col-5 text-end">{{ $moneyPrefix }}{{ number_format((float) ($recon['difference'] ?? 0), 2) }}</dd>
                </dl>
                <p class="text-secondary small mb-0 mt-3">Double-entry ledger is shown for transparency; wallet transactions remain the statement source of truth.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100" data-testid="finance-statement-ledger-period">
            <div class="card-header">
                <h3 class="card-title mb-0">Ledger transactions (period)</h3>
            </div>
            <div class="card-body p-0">
                @if ($ledgerTransactions->isEmpty())
                    <p class="text-secondary p-4 mb-0">No posted ledger transactions in this period.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Ref</th>
                                    <th>Type</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ledgerTransactions as $tx)
                                    <tr>
                                        <td class="text-nowrap">{{ $tx->occurred_at?->format('Y-m-d H:i') }}</td>
                                        <td class="ota-r-text-safe">{{ $tx->transaction_ref }}</td>
                                        <td>{{ str_replace('_', ' ', $tx->transaction_type->value ?? '') }}</td>
                                        <td class="text-end">{{ $moneyPrefix }}{{ number_format((float) $tx->amount_total, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
