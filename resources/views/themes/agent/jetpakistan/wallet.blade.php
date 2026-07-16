{{-- JP-PORTAL-3 TASK 5 · Agent / Agent Staff wallet (JetPK theme)
     Resolved by client_view('wallet', 'agent'); dashboard.agent.wallet remains the fallback for
     default/Parwaaz clients and is NOT modified.
     Route gate: agent.permission:WalletView + platform.module:agent_wallet.
     Controller also enforces Gate::authorize('viewWallet', $agent).
     NOTE: AgentWalletController branches to mobile.agent.wallet.show BEFORE this view when
     MobileViewPreference::shouldUseMobileShell() is true — see Task 12.

     PRESERVED EXACTLY (financial contract — nothing here may be "tidied"):
       • controller vars: $summary, $pendingDeposits, $recentTransactions, $canViewLedger,
         $canUploadPayments
       • $ws = $summary ?? []  and the currency-derived money prefix:
           $currency   = (string) ($ws['currency'] ?? 'PKR')
           $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' '
         (wallet is currency-aware — do NOT hardcode 'Rs ' here; deposits/index legitimately does,
          see that file's note)
       • KPI set and order: Wallet balance, Pending deposits, Credit limit, Available balance
       • every KPI value: number_format((float) ($ws[key] ?? 0), 2) — 2dp always, never rounded,
         never abbreviated, never clipped
       • credit limit branch: $ws['credit_enabled'] ? number_format($ws['credit_limit']) : 'Not enabled'
       • the credit notice copy, verbatim
       • $canUploadPayments gates: header "Request deposit" + the deposit quick-action
       • ($canViewLedger && Route::has('agent.ledger.index')) gates: header "View ledger",
         the ledger quick-action, and the "View all" link — Route::has() guard retained
       • Route::has('agent.deposits.index') guard on deposit-history quick action
       • pendingDeposits rows: amount + optional Ref + <x-dashboard.status-badge status="pending">
         (the badge is HARDCODED to "pending" in legacy because the query already filters
          status='submitted' — reproduced exactly, NOT changed to $deposit->status)
       • recentTransactions columns: Date, Type, Status, Amount, Balance after
       • date format 'j M y, H:i' ?? '—'; type str_replace('_',' ', $tx->type->value) capitalised
       • balance after: $tx->balance_after ?? $tx->balance_before ?? 0  (exact fallback chain)
       • <x-dashboard.status-badge :status="$tx->status->value"> (canonical — reused)
       • data-testids: agent-wallet-request-deposit, agent-wallet-view-ledger, agent-wallet-kpis,
         agent-wallet-credit-notice, agent-wallet-deposit-quick, agent-wallet-ledger-quick,
         agent-wallet-recent-transactions
     Amount columns are right-aligned and non-wrapping, as legacy. Mobile card rows carry the SAME
     five fields as the desktop table — no financial column is dropped at any width.
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Wallet')

@section('account_title', 'Wallet')
@section('account_subtitle', 'Prepaid balance and deposit history for your agent account.')

@section('account_actions')
    @if ($canUploadPayments ?? false)
        <a href="{{ route('agent.deposits.create') }}" class="jp-btn jp-btn--primary" data-testid="agent-wallet-request-deposit">Request deposit</a>
    @endif
    @if (($canViewLedger ?? false) && Route::has('agent.ledger.index'))
        <a href="{{ route('agent.ledger.index') }}" class="jp-btn jp-btn--ghost" data-testid="agent-wallet-view-ledger">View ledger</a>
    @endif
@endsection

@section('account_content')
    @php
        $ws = $summary ?? [];
        $currency = (string) ($ws['currency'] ?? 'PKR');
        $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
    @endphp

    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Wallet'],
    ]" />

    <div class="jp-kpi-grid" data-testid="agent-wallet-kpis">
        <div class="jp-kpi jp-kpi--emerald">
            <p class="jp-kpi__label">Wallet balance</p>
            <p class="jp-kpi__value jp-money">{{ $moneyPrefix }}{{ number_format((float) ($ws['balance'] ?? 0), 2) }}</p>
        </div>
        <div class="jp-kpi jp-kpi--amber">
            <p class="jp-kpi__label">Pending deposits</p>
            <p class="jp-kpi__value jp-money">{{ $moneyPrefix }}{{ number_format((float) ($ws['pending_deposits'] ?? 0), 2) }}</p>
        </div>
        <div class="jp-kpi jp-kpi--violet">
            <p class="jp-kpi__label">Credit limit</p>
            <p class="jp-kpi__value jp-money">
                @if ($ws['credit_enabled'] ?? false)
                    {{ $moneyPrefix }}{{ number_format((float) $ws['credit_limit'], 2) }}
                @else
                    Not enabled
                @endif
            </p>
        </div>
        <div class="jp-kpi">
            <p class="jp-kpi__label">Available balance</p>
            <p class="jp-kpi__value jp-money">{{ $moneyPrefix }}{{ number_format((float) ($ws['available_balance'] ?? 0), 2) }}</p>
        </div>
    </div>

    <x-jp.alert variant="info" data-testid="agent-wallet-credit-notice">
        Credit limit is display-only. Your agency admin assigns credit; you cannot change it here.
        Booking credit enforcement is not enabled yet.
    </x-jp.alert>

    <div class="jp-portal__split">
        <x-jp.card class="jp-portal__panel">
            <div class="jp-portal__panel-head">
                <div>
                    <h2 class="jp-portal__panel-title">Pending deposits</h2>
                    <p class="jp-portal__panel-lead">Awaiting finance review.</p>
                </div>
            </div>
            @forelse ($pendingDeposits as $deposit)
                <div class="jp-portal__row">
                    <div>
                        <span class="jp-money jp-money--strong">{{ $moneyPrefix }}{{ number_format((float) $deposit->amount, 2) }}</span>
                        @if ($deposit->reference)
                            <span class="jp-portal__row-ref">Ref: {{ $deposit->reference }}</span>
                        @endif
                    </div>
                    <x-dashboard.status-badge status="pending" />
                </div>
            @empty
                <div class="jp-empty">
                    <span class="jp-empty__icon" aria-hidden="true"><x-jp.icon name="wallet" /></span>
                    <p class="jp-empty__title">No pending deposits</p>
                    <p class="jp-empty__help">Submit a deposit request when you transfer funds to the agency.</p>
                </div>
            @endforelse
        </x-jp.card>

        <div class="jp-portal__quick-grid">
            @if ($canUploadPayments ?? false)
                <a href="{{ route('agent.deposits.create') }}" class="jp-quick" data-testid="agent-wallet-deposit-quick">
                    <span class="jp-quick__icon" aria-hidden="true"><x-jp.icon name="upload" /></span>
                    <span class="jp-quick__title">Request deposit</span>
                    <span class="jp-quick__help">Upload proof of bank transfer or wallet payment for finance review.</span>
                </a>
            @endif
            @if (Route::has('agent.deposits.index'))
                <a href="{{ route('agent.deposits.index') }}" class="jp-quick">
                    <span class="jp-quick__icon" aria-hidden="true"><x-jp.icon name="list" /></span>
                    <span class="jp-quick__title">Deposit history</span>
                    <span class="jp-quick__help">View submitted, approved, and rejected deposit requests.</span>
                </a>
            @endif
            @if (($canViewLedger ?? false) && Route::has('agent.ledger.index'))
                <a href="{{ route('agent.ledger.index') }}" class="jp-quick" data-testid="agent-wallet-ledger-quick">
                    <span class="jp-quick__icon" aria-hidden="true"><x-jp.icon name="list-details" /></span>
                    <span class="jp-quick__title">Full ledger</span>
                    <span class="jp-quick__help">Paginated transaction history with filters.</span>
                </a>
            @endif
        </div>
    </div>

    <x-jp.card class="jp-portal__panel jp-portal__panel--flush">
        <div class="jp-portal__panel-head">
            <div>
                <h2 class="jp-portal__panel-title">Recent transactions</h2>
                <p class="jp-portal__panel-lead">Latest wallet ledger entries.</p>
            </div>
            @if (($canViewLedger ?? false) && Route::has('agent.ledger.index'))
                <a href="{{ route('agent.ledger.index') }}" class="jp-btn jp-btn--ghost jp-btn--sm">View all</a>
            @endif
        </div>

        {{-- Desktop: full table. --}}
        <div class="jp-table-wrap jp-table-wrap--desktop">
            <table class="jp-table jp-table--finance" data-testid="agent-wallet-recent-transactions">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Type</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="jp-table__cell--end">Amount</th>
                        <th scope="col" class="jp-table__cell--end">Balance after</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentTransactions as $tx)
                        <tr>
                            <td data-label="Date" class="jp-table__cell--nowrap">{{ $tx->created_at?->format('j M y, H:i') ?? '—' }}</td>
                            <td data-label="Type" class="jp-table__cell--capitalize">{{ str_replace('_', ' ', $tx->type->value) }}</td>
                            <td data-label="Status"><x-dashboard.status-badge :status="$tx->status->value" /></td>
                            <td data-label="Amount" class="jp-table__cell--end jp-money">{{ $moneyPrefix }}{{ number_format((float) $tx->amount, 2) }}</td>
                            <td data-label="Balance after" class="jp-table__cell--end jp-money">{{ $moneyPrefix }}{{ number_format((float) ($tx->balance_after ?? $tx->balance_before ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="jp-empty">
                                    <p class="jp-empty__title">No transactions yet.</p>
                                    <p class="jp-empty__help">Wallet ledger activity will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile: identical five fields as cards — no financial value hidden. --}}
        <div class="jp-portal__list jp-portal__list--mobile">
            @forelse ($recentTransactions as $tx)
                <article class="jp-portal__list-card">
                    <div class="jp-portal__list-card-head">
                        <span class="jp-portal__list-card-ref jp-table__cell--capitalize">{{ str_replace('_', ' ', $tx->type->value) }}</span>
                        <x-dashboard.status-badge :status="$tx->status->value" />
                    </div>
                    <div class="jp-portal__list-card-meta">
                        <span>{{ $tx->created_at?->format('j M y, H:i') ?? '—' }}</span>
                        <span>Amount: <span class="jp-money">{{ $moneyPrefix }}{{ number_format((float) $tx->amount, 2) }}</span></span>
                        <span>Balance after: <span class="jp-money">{{ $moneyPrefix }}{{ number_format((float) ($tx->balance_after ?? $tx->balance_before ?? 0), 2) }}</span></span>
                    </div>
                </article>
            @empty
                <div class="jp-empty">
                    <p class="jp-empty__title">No transactions yet.</p>
                    <p class="jp-empty__help">Wallet ledger activity will appear here.</p>
                </div>
            @endforelse
        </div>
    </x-jp.card>
@endsection
