@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Wallet')

@section('account_title', 'Wallet')
@section('account_subtitle', 'Prepaid balance and deposit history for your agent account.')

@section('account_actions')
    @if ($canUploadPayments ?? false)
        <a href="{{ route('agent.deposits.create') }}" class="ota-account-btn ota-account-btn--primary" data-testid="agent-wallet-request-deposit">Request deposit</a>
    @endif
    @if (($canViewLedger ?? false) && Route::has('agent.ledger.index'))
        <a href="{{ route('agent.ledger.index') }}" class="ota-account-btn ota-account-btn--secondary" data-testid="agent-wallet-view-ledger">View ledger</a>
    @endif
@endsection

@section('account_content')
    @php
        $ws = $summary ?? [];
        $currency = (string) ($ws['currency'] ?? 'PKR');
        $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
    @endphp

    <div class="ota-account-grid ota-account-grid--kpis ota-agent-kpi-strip ota-agent-wallet-kpis mb-4" data-testid="agent-wallet-kpis">
        <div class="ota-account-kpi ota-account-kpi--emerald">
            <div class="ota-account-kpi__label">Wallet balance</div>
            <div class="ota-account-kpi__value">{{ $moneyPrefix }}{{ number_format((float) ($ws['balance'] ?? 0), 2) }}</div>
        </div>
        <div class="ota-account-kpi ota-account-kpi--amber">
            <div class="ota-account-kpi__label">Pending deposits</div>
            <div class="ota-account-kpi__value">{{ $moneyPrefix }}{{ number_format((float) ($ws['pending_deposits'] ?? 0), 2) }}</div>
        </div>
        <div class="ota-account-kpi ota-account-kpi--violet">
            <div class="ota-account-kpi__label">Credit limit</div>
            <div class="ota-account-kpi__value">
                @if ($ws['credit_enabled'] ?? false)
                    {{ $moneyPrefix }}{{ number_format((float) $ws['credit_limit'], 2) }}
                @else
                    Not enabled
                @endif
            </div>
        </div>
        <div class="ota-account-kpi">
            <div class="ota-account-kpi__label">Available balance</div>
            <div class="ota-account-kpi__value">{{ $moneyPrefix }}{{ number_format((float) ($ws['available_balance'] ?? 0), 2) }}</div>
        </div>
    </div>

    <div class="ota-account-note mb-4" data-testid="agent-wallet-credit-notice">
        Credit limit is display-only. Your agency admin assigns credit; you cannot change it here.
        Booking credit enforcement is not enabled yet.
    </div>

    <div class="ota-account-grid ota-account-grid--2 ota-agent-wallet-layout mb-4">
        <div class="ota-account-card h-100">
            <div class="ota-account-card__head">
                <div>
                    <h2 class="ota-account-card__title">Pending deposits</h2>
                    <p class="ota-account-card__lead">Awaiting finance review.</p>
                </div>
            </div>
            <div class="ota-account-card__body">
                @forelse($pendingDeposits as $deposit)
                    <div class="ota-account-list-row ota-agent-wallet-deposit-row">
                        <div>
                            <span class="fw-semibold ota-agent-money">{{ $moneyPrefix }}{{ number_format((float) $deposit->amount, 2) }}</span>
                            @if ($deposit->reference)
                                <span class="d-block small text-secondary ota-agent-cell-ref">Ref: {{ $deposit->reference }}</span>
                            @endif
                        </div>
                        <x-dashboard.status-badge status="pending" />
                    </div>
                @empty
                    <div class="ota-account-empty ota-account-empty--compact">
                        <div class="ota-account-empty-icon" aria-hidden="true"><i class="ti ti-cash"></i></div>
                        <p class="ota-account-empty-title">No pending deposits</p>
                        <p class="ota-account-empty-help">Submit a deposit request when you transfer funds to the agency.</p>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="ota-account-grid ota-account-grid--quick">
            @if ($canUploadPayments ?? false)
                <a href="{{ route('agent.deposits.create') }}" class="ota-account-quick" data-testid="agent-wallet-deposit-quick">
                    <div class="ota-account-quick__card">
                        <div class="ota-account-quick__icon"><i class="ti ti-upload"></i></div>
                        <div class="ota-account-quick__title">Request deposit</div>
                        <div class="ota-account-quick__help">Upload proof of bank transfer or wallet payment for finance review.</div>
                    </div>
                </a>
            @endif
            @if (Route::has('agent.deposits.index'))
                <a href="{{ route('agent.deposits.index') }}" class="ota-account-quick">
                    <div class="ota-account-quick__card">
                        <div class="ota-account-quick__icon"><i class="ti ti-list"></i></div>
                        <div class="ota-account-quick__title">Deposit history</div>
                        <div class="ota-account-quick__help">View submitted, approved, and rejected deposit requests.</div>
                    </div>
                </a>
            @endif
            @if (($canViewLedger ?? false) && Route::has('agent.ledger.index'))
                <a href="{{ route('agent.ledger.index') }}" class="ota-account-quick" data-testid="agent-wallet-ledger-quick">
                    <div class="ota-account-quick__card">
                        <div class="ota-account-quick__icon"><i class="ti ti-list-details"></i></div>
                        <div class="ota-account-quick__title">Full ledger</div>
                        <div class="ota-account-quick__help">Paginated transaction history with filters.</div>
                    </div>
                </a>
            @endif
        </div>
    </div>

    <div class="ota-account-card">
        <div class="ota-account-card__head">
            <div>
                <h2 class="ota-account-card__title">Recent transactions</h2>
                <p class="ota-account-card__lead">Latest wallet ledger entries.</p>
            </div>
            @if (($canViewLedger ?? false) && Route::has('agent.ledger.index'))
                <a href="{{ route('agent.ledger.index') }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">View all</a>
            @endif
        </div>
        <div class="ota-account-card__body ota-account-card__body--flush">
            <div class="ota-account-table-wrap">
                <table class="ota-account-table ota-agent-finance-table mb-0" data-testid="agent-wallet-recent-transactions">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Balance after</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions as $tx)
                            <tr>
                                <td class="text-nowrap">{{ $tx->created_at?->format('j M y, H:i') ?? '—' }}</td>
                                <td class="text-capitalize">{{ str_replace('_', ' ', $tx->type->value) }}</td>
                                <td><x-dashboard.status-badge :status="$tx->status->value" /></td>
                                <td class="text-end ota-agent-money">{{ $moneyPrefix }}{{ number_format((float) $tx->amount, 2) }}</td>
                                <td class="text-end ota-agent-money">{{ $moneyPrefix }}{{ number_format((float) ($tx->balance_after ?? $tx->balance_before ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="ota-account-empty ota-account-empty--compact"><p class="ota-account-empty-title">No transactions yet.</p><p class="ota-account-empty-help">Wallet ledger activity will appear here.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
