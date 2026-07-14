@php
    $d = $dashboard ?? [];
    $currency = 'PKR';
@endphp

<div class="row row-cards mb-4 g-3" data-testid="accounting-reconciliation-cards">
    @foreach ([
        ['label' => 'Ledger transactions', 'value' => $d['total_transactions'] ?? 0, 'format' => 'number'],
        ['label' => 'Posted transactions', 'value' => $d['posted_transactions'] ?? 0, 'format' => 'number'],
        ['label' => 'Failed / draft / reversed', 'value' => ($d['failed_count'] ?? 0).' / '.($d['draft_count'] ?? 0).' / '.($d['reversed_count'] ?? 0), 'format' => 'raw'],
        ['label' => 'Unbalanced transactions', 'value' => $d['unbalanced_count'] ?? 0, 'format' => 'number'],
        ['label' => 'Ledger entries', 'value' => $d['total_entries'] ?? 0, 'format' => 'number'],
        ['label' => 'Platform exposure (ledger)', 'value' => $d['platform_exposure'] ?? 0, 'format' => 'money'],
        ['label' => 'Agency wallet liability (ledger)', 'value' => $d['agency_wallet_liability_total'] ?? 0, 'format' => 'money'],
        ['label' => 'Wallet balance (source of truth)', 'value' => $d['wallet_balance_total'] ?? 0, 'format' => 'money'],
        ['label' => 'Wallet vs ledger difference', 'value' => $d['wallet_ledger_difference'] ?? 0, 'format' => 'money'],
        ['label' => 'Duplicate source postings', 'value' => $d['duplicate_source_count'] ?? 0, 'format' => 'number'],
        ['label' => 'Orphan wallet transactions', 'value' => $d['orphan_wallet_count'] ?? 0, 'format' => 'number'],
    ] as $card)
        <div class="col-6 col-md-4 col-xl-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary small">{{ $card['label'] }}</div>
                    <div class="h3 mb-0">
                        @if ($card['format'] === 'money')
                            {{ $currency }} {{ number_format((float) $card['value'], 2) }}
                        @elseif ($card['format'] === 'raw')
                            {{ $card['value'] }}
                        @else
                            {{ number_format((int) $card['value']) }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row mb-4 g-3">
    <div class="col-md-6">
        <div class="card card-sm">
            <div class="card-body">
                <div class="text-secondary small">Last posted transaction</div>
                @if (! empty($d['last_posted_transaction']))
                    <div class="fw-medium">{{ $d['last_posted_transaction']->transaction_ref }}</div>
                    <div class="text-secondary small">{{ $d['last_posted_transaction']->posted_at?->toDayDateTimeString() ?? '—' }}</div>
                @else
                    <div class="text-secondary">None yet</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-sm">
            <div class="card-body">
                <div class="text-secondary small">Integrity check summary</div>
                @if ($d['integrity_passed'] ?? true)
                    <span class="badge bg-success-lt">Passed</span>
                @else
                    <span class="badge bg-danger-lt">{{ $d['integrity_issue_count'] ?? 0 }} issue(s)</span>
                @endif
            </div>
        </div>
    </div>
</div>

@if (! empty($d['agency_breakdown']))
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Agency breakdown</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table mb-0" data-testid="accounting-reconciliation-agencies">
                <thead>
                    <tr>
                        <th>Agency</th>
                        <th class="text-end">Wallet balance</th>
                        <th class="text-end">Ledger liability</th>
                        <th class="text-end">Difference</th>
                        <th class="text-end">Posted txns</th>
                        <th>Last posted</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($d['agency_breakdown'] as $row)
                        <tr data-testid="reconciliation-agency-{{ $row['agency_id'] }}">
                            <td>
                                <div>{{ $row['agency_name'] }}</div>
                                @if ($row['agency_code'])
                                    <div class="text-secondary small">{{ $row['agency_code'] }}</div>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((float) $row['wallet_balance'], 2) }}</td>
                            <td class="text-end">{{ number_format((float) $row['ledger_liability'], 2) }}</td>
                            <td class="text-end">{{ number_format((float) $row['difference'], 2) }}</td>
                            <td class="text-end">{{ $row['posted_transaction_count'] }}</td>
                            <td>{{ $row['last_posted_at'] ? \Illuminate\Support\Carbon::parse($row['last_posted_at'])->format('Y-m-d H:i') : '—' }}</td>
                            <td>
                                @if ($row['status'] === 'matched')
                                    <span class="badge bg-success-lt">Matched</span>
                                @elseif ($row['status'] === 'mismatch')
                                    <span class="badge bg-danger-lt">Mismatch</span>
                                @else
                                    <span class="badge bg-secondary-lt">No ledger data</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
