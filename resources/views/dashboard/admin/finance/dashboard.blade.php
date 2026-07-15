@extends(client_layout('dashboard', 'admin'))

@section('title', $pageTitle ?? 'Finance Dashboard')

@section('page-header')
    <x-dashboard.section-header :title="$pageTitle ?? 'Finance Dashboard'" :subtitle="$pageSubtitle ?? ''">
        <x-slot name="actions">
            <a href="{{ route('admin.finance.dashboard.export') }}" class="jp-btn jp-btn--outline btn-sm" data-testid="finance-dashboard-export-csv">
                <i class="ti ti-download me-1"></i> Export Dashboard CSV
            </a>
            <a href="{{ route('admin.accounting.reconciliation.index') }}" class="jp-btn jp-btn--ghost btn-sm">Reconciliation</a>
            <a href="{{ route('admin.accounting.ledger.index') }}" class="jp-btn jp-btn--ghost btn-sm">Accounting Ledger</a>
        </x-slot>
    </x-dashboard.section-header>
@endsection

@section('content')
    @php
        $d = $dashboard ?? [];
        $summary = $d['summary'] ?? [];
        $mtd = $d['mtd'] ?? [];
        $recon = $d['reconciliation'] ?? [];
        $currency = $d['currency'] ?? 'PKR';
        $reconStatus = (string) ($summary['reconciliation_status'] ?? 'matched');
        $reconBadge = match ($reconStatus) {
            'matched' => 'bg-success-lt',
            'mismatch' => 'bg-danger-lt',
            default => 'bg-secondary-lt',
        };
    @endphp

    <div class="jp-alert jp-alert--info py-2 mb-3" data-testid="finance-dashboard-readonly-notice">
        <i class="ti ti-lock me-1"></i>
        Read-only monitoring. Wallet and ledger changes use Manual Adjustments or existing finance workflows.
    </div>

    {{-- Top summary --}}
    <div class="row row-cards mb-4 g-3" data-testid="finance-dashboard-summary-cards">
        @foreach ([
            ['label' => 'Agency wallet total', 'value' => $summary['wallet_balance_total'] ?? 0, 'format' => 'money', 'testid' => 'finance-dashboard-wallet-total'],
            ['label' => 'Ledger wallet liability', 'value' => $summary['ledger_liability_total'] ?? 0, 'format' => 'money', 'testid' => 'finance-dashboard-ledger-total'],
            ['label' => 'Difference', 'value' => $summary['difference'] ?? 0, 'format' => 'money', 'testid' => 'finance-dashboard-difference'],
            ['label' => 'Reconciliation', 'value' => ucfirst(str_replace('_', ' ', $reconStatus)), 'format' => 'raw', 'testid' => 'finance-dashboard-reconciliation-status', 'badge' => $reconBadge],
            ['label' => 'Posted ledger transactions', 'value' => $summary['posted_transactions'] ?? 0, 'format' => 'number'],
            ['label' => 'Unbalanced ledger transactions', 'value' => $summary['unbalanced_transactions'] ?? 0, 'format' => 'number'],
            ['label' => 'Manual adjustments (MTD)', 'value' => $summary['manual_adjustments_mtd'] ?? 0, 'format' => 'number', 'testid' => 'finance-dashboard-manual-adjustments-mtd'],
            ['label' => 'Deposits approved (MTD)', 'value' => $summary['deposits_mtd'] ?? 0, 'format' => 'number', 'testid' => 'finance-dashboard-deposits-mtd'],
        ] as $card)
            <div class="col-6 col-md-4 col-xl-3">
                <div class="card card-sm h-100" @if (! empty($card['testid'])) data-testid="{{ $card['testid'] }}" @endif>
                    <div class="jp-card__body">
                        <div class="text-secondary small">{{ $card['label'] }}</div>
                        <div class="h3 mb-0">
                            @if (($card['format'] ?? '') === 'money')
                                {{ $currency }} {{ number_format((float) $card['value'], 2) }}
                            @elseif (($card['format'] ?? '') === 'raw' && ! empty($card['badge']))
                                <span class="badge {{ $card['badge'] }}">{{ $card['value'] }}</span>
                            @else
                                {{ number_format((int) $card['value']) }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- MTD --}}
    <h3 class="mb-2">Month to date</h3>
    <div class="row row-cards mb-4 g-3" data-testid="finance-dashboard-mtd-cards">
        @foreach ([
            ['label' => 'Deposits approved', 'value' => $mtd['deposits_approved'] ?? 0],
            ['label' => 'Manual credits', 'value' => $mtd['manual_credits'] ?? 0],
            ['label' => 'Manual debits', 'value' => $mtd['manual_debits'] ?? 0],
            ['label' => 'Reversals', 'value' => $mtd['reversals'] ?? 0],
            ['label' => 'Booking payments (ledger)', 'value' => $mtd['booking_payments'] ?? null],
            ['label' => 'Refunds (ledger)', 'value' => $mtd['refunds'] ?? null],
            ['label' => 'Commission earned (ledger)', 'value' => $mtd['commission'] ?? null],
            ['label' => 'Markup revenue (ledger)', 'value' => $mtd['markup_revenue'] ?? null],
        ] as $card)
            <div class="col-6 col-md-4 col-xl-3">
                <div class="card card-sm h-100">
                    <div class="jp-card__body">
                        <div class="text-secondary small">{{ $card['label'] }}</div>
                        <div class="h3 mb-0">
                            {{ $currency }} {{ number_format((float) $card['value'], 2) }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Reconciliation alert --}}
    <div class="jp-card" data-testid="finance-dashboard-reconciliation-panel">
        <div class="jp-card__head">
            <h3 class="jp-card__title">Reconciliation overview</h3>
        </div>
        <div class="jp-card__body">
            <div class="d-flex flex-wrap gap-3 mb-3">
                <span class="badge bg-success-lt" data-testid="finance-dashboard-matched-count">{{ (int) ($recon['matched_count'] ?? 0) }} matched</span>
                <span class="badge bg-danger-lt" data-testid="finance-dashboard-mismatch-count">{{ (int) ($recon['mismatch_count'] ?? 0) }} mismatched</span>
                <span class="badge bg-secondary-lt">{{ (int) ($recon['no_ledger_data_count'] ?? 0) }} no ledger data</span>
            </div>
            @if (! empty($recon['top_mismatches']))
                <div class="table-responsive">
                    <table class="jp-table mb-0" data-testid="finance-dashboard-top-mismatches">
                        <thead>
                            <tr>
                                <th>Agency</th>
                                <th class="text-end">Wallet</th>
                                <th class="text-end">Ledger liability</th>
                                <th class="text-end">Difference</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recon['top_mismatches'] as $row)
                                <tr>
                                    <td>{{ $row['agency_name'] ?? '—' }}</td>
                                    <td class="text-end">{{ $currency }} {{ number_format((float) ($row['wallet_balance'] ?? 0), 2) }}</td>
                                    <td class="text-end">{{ $currency }} {{ number_format((float) ($row['ledger_liability'] ?? 0), 2) }}</td>
                                    <td class="text-end">{{ $currency }} {{ number_format((float) ($row['difference'] ?? 0), 2) }}</td>
                                    <td><span class="badge bg-danger-lt">mismatch</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.finance.statements.show', $row['agency_id']) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Statement</a>
                                        <a href="{{ route('admin.accounting.ledger.index', ['agency_id' => $row['agency_id']]) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Ledger</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-secondary mb-0">No agency mismatches detected.</p>
            @endif
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- Recent ledger --}}
        <div class="col-lg-6">
            <div class="card h-100" data-testid="finance-dashboard-recent-ledger">
                <div class="jp-card__head">
                    <h3 class="jp-card__title">Recent ledger activity</h3>
                </div>
                <div class="table-responsive">
                    <table class="jp-table mb-0">
                        <thead>
                            <tr>
                                <th>Ref</th>
                                <th>Type</th>
                                <th>Agency</th>
                                <th class="text-end">Amount</th>
                                <th>Balanced</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($d['recent_ledger'] ?? [] as $row)
                                <tr data-testid="finance-dashboard-ledger-row">
                                    <td>
                                        <a href="{{ route('admin.accounting.ledger.show', $row['id']) }}">{{ $row['transaction_ref'] }}</a>
                                    </td>
                                    <td class="small">{{ str_replace('_', ' ', $row['transaction_type']) }}</td>
                                    <td>{{ $row['agency_name'] ?? '—' }}</td>
                                    <td class="text-end">{{ $currency }} {{ number_format((float) $row['amount'], 2) }}</td>
                                    <td>
                                        @if ($row['is_balanced'])
                                            <span class="badge bg-success-lt">Yes</span>
                                        @else
                                            <span class="badge bg-danger-lt">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-secondary">No posted ledger transactions yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Recent adjustments --}}
        <div class="col-lg-6">
            <div class="card h-100" data-testid="finance-dashboard-recent-adjustments">
                <div class="jp-card__head">
                    <h3 class="jp-card__title">Recent manual adjustments</h3>
                </div>
                <div class="table-responsive">
                    <table class="jp-table mb-0">
                        <thead>
                            <tr>
                                <th>Ref</th>
                                <th>Agency</th>
                                <th>Type</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($d['recent_adjustments'] ?? [] as $row)
                                <tr data-testid="finance-dashboard-adjustment-row">
                                    <td>
                                        <a href="{{ route('admin.finance.adjustments.show', $row['id']) }}">{{ $row['reference'] ?? '#'.$row['id'] }}</a>
                                    </td>
                                    <td>{{ $row['agency_name'] ?? '—' }}</td>
                                    <td>
                                        {{ str_replace('_', ' ', $row['type']) }}
                                        @if ($row['is_reversal'])
                                            <span class="badge bg-azure-lt">Reversal</span>
                                        @elseif ($row['is_reversed'])
                                            <span class="badge bg-yellow-lt">Reversed</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $currency }} {{ number_format((float) $row['amount'], 2) }}</td>
                                    <td class="small text-secondary">{{ $row['created_by'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-secondary">No manual adjustments yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent deposits --}}
    <div class="jp-card" data-testid="finance-dashboard-recent-deposits">
        <div class="jp-card__head">
            <h3 class="jp-card__title">Recent deposit requests</h3>
        </div>
        <div class="table-responsive">
            <table class="jp-table mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Agency</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <th>Reviewed</th>
                        <th>Wallet tx</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($d['recent_deposits'] ?? [] as $row)
                        <tr data-testid="finance-dashboard-deposit-row">
                            <td>
                                <a href="{{ route('admin.agent-deposits.show', $row['id']) }}">{{ $row['reference'] ?? '#'.$row['id'] }}</a>
                            </td>
                            <td>{{ $row['agency_name'] ?? '—' }}</td>
                            <td class="text-end">{{ $currency }} {{ number_format((float) $row['amount'], 2) }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td>{{ $row['reviewed_at']?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td>
                                @if (! empty($row['wallet_transaction_id']))
                                    #{{ $row['wallet_transaction_id'] }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No deposit requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Agency exposure --}}
    <div class="jp-card" data-testid="finance-dashboard-agency-exposure">
        <div class="jp-card__head">
            <h3 class="jp-card__title">Agency exposure</h3>
        </div>
        <div class="table-responsive">
            <table class="jp-table mb-0">
                <thead>
                    <tr>
                        <th>Agency</th>
                        <th class="text-end">Wallet</th>
                        <th class="text-end">Ledger liability</th>
                        <th class="text-end">Difference</th>
                        <th>Status</th>
                        <th>Last wallet</th>
                        <th>Last ledger</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($d['agency_exposure'] ?? [] as $row)
                        <tr data-testid="finance-dashboard-agency-row">
                            <td>{{ $row['agency_name'] ?? '—' }}</td>
                            <td class="text-end">{{ $currency }} {{ number_format((float) ($row['wallet_balance'] ?? 0), 2) }}</td>
                            <td class="text-end">{{ $currency }} {{ number_format((float) ($row['ledger_liability'] ?? 0), 2) }}</td>
                            <td class="text-end">{{ $currency }} {{ number_format((float) ($row['difference'] ?? 0), 2) }}</td>
                            <td>
                                @php $st = (string) ($row['status'] ?? ''); @endphp
                                <span class="badge {{ $st === 'matched' ? 'bg-success-lt' : ($st === 'mismatch' ? 'bg-danger-lt' : 'bg-secondary-lt') }}">
                                    {{ str_replace('-', ' ', $st) }}
                                </span>
                            </td>
                            <td class="small text-secondary">{{ $row['last_wallet_movement_at'] ? \Illuminate\Support\Carbon::parse($row['last_wallet_movement_at'])->format('Y-m-d H:i') : '—' }}</td>
                            <td class="small text-secondary">{{ $row['last_ledger_movement_at'] ? \Illuminate\Support\Carbon::parse($row['last_ledger_movement_at'])->format('Y-m-d H:i') : '—' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.finance.statements.show', $row['agency_id']) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Statement</a>
                                <a href="{{ route('admin.accounting.ledger.index', ['agency_id' => $row['agency_id']]) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Ledger</a>
                                <a href="{{ route('admin.finance.adjustments.create', ['agency_id' => $row['agency_id']]) }}" class="jp-btn jp-btn--sm jp-btn--outline">Adjust</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-secondary">No agencies with wallet or ledger activity.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
