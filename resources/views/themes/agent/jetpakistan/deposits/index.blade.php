{{-- JP-PORTAL-3 TASK 5 · Agent / Agent Staff deposits — index (JetPK theme)
     Resolved by client_view('deposits.index', 'agent'); dashboard.agent.deposits.index remains the
     fallback for standalone mode is off\.
     Route gate: agent.permission:WalletView + platform.module:agent_deposits.
     Controller also enforces Gate::authorize('viewWallet', $agent).
     Mobile branch: mobile.agent.deposits.index — see Task 12.

     PRESERVED EXACTLY:
       • controller vars: $deposits (paginator), $summary
       • $ws = $summary ?? []
       • CURRENCY: legacy HARDCODES 'Rs ' on this page (unlike wallet.blade.php, which derives a
         prefix from $summary['currency']). That inconsistency is REPRODUCED, not corrected:
         "preserve currency formatting exactly" outranks internal consistency, and changing it
         here would alter displayed financial values for any non-PKR agency. Flagged in the
         contract matrix as a pre-existing legacy inconsistency for a separate decision.
       • in-view permission gates, reproduced with the identical expressions:
           PaymentsUpload -> "New deposit request"
           WalletView     -> "Wallet" link
       • flash: session('status') === 'deposit-submitted' + exact copy + data-testid agent-deposit-flash
       • KPI set and order: Wallet balance, Pending deposits, Credit limit  (only THREE here —
         wallet's fourth "Available balance" KPI is deliberately absent in legacy on this page)
       • credit limit branch: $ws['credit_enabled'] ? number_format($ws['credit_limit']) : 'Not enabled'
       • columns: Submitted, Amount, Method, Reference, Status
       • date format 'j M Y, g:i A' ?? '—'; method/reference '—' fallbacks
       • <x-dashboard.status-badge :status="$deposit->status->value"> (canonical — reused)
       • pagination: $deposits->links() gated by hasPages()
       • data-testids: agent-deposits-create-link, agent-deposits-summary, agent-deposits-table
     Mobile cards carry all five fields — no column dropped.
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Deposits')

@section('account_title', 'Deposits')
@section('account_subtitle', 'Your deposit requests and review status.')

@section('account_actions')
    @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::PaymentsUpload))
        <a href="{{ route('agent.deposits.create') }}" class="jp-btn jp-btn--primary" data-testid="agent-deposits-create-link">New deposit request</a>
    @endif
    @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::WalletView))
        <a href="{{ route('agent.wallet.show') }}" class="jp-btn jp-btn--ghost">Wallet</a>
    @endif
@endsection

@section('account_content')
    @php $ws = $summary ?? []; @endphp

    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Deposits'],
    ]" />

    @if (session('status') === 'deposit-submitted')
        <x-jp.alert variant="success" data-testid="agent-deposit-flash">Deposit request submitted. Finance will review your proof.</x-jp.alert>
    @endif

    <div class="jp-kpi-grid" data-testid="agent-deposits-summary">
        <div class="jp-kpi jp-kpi--emerald">
            <p class="jp-kpi__label">Wallet balance</p>
            <p class="jp-kpi__value jp-money">Rs {{ number_format((float) ($ws['balance'] ?? 0), 2) }}</p>
        </div>
        <div class="jp-kpi jp-kpi--amber">
            <p class="jp-kpi__label">Pending deposits</p>
            <p class="jp-kpi__value jp-money">Rs {{ number_format((float) ($ws['pending_deposits'] ?? 0), 2) }}</p>
        </div>
        <div class="jp-kpi">
            <p class="jp-kpi__label">Credit limit</p>
            <p class="jp-kpi__value jp-money">
                @if ($ws['credit_enabled'] ?? false)
                    Rs {{ number_format((float) $ws['credit_limit'], 2) }}
                @else
                    Not enabled
                @endif
            </p>
        </div>
    </div>

    <x-jp.card class="jp-portal__panel jp-portal__panel--flush">
        <div class="jp-table-wrap jp-table-wrap--desktop">
            <table class="jp-table jp-table--finance" data-testid="agent-deposits-table">
                <thead>
                    <tr>
                        <th scope="col">Submitted</th>
                        <th scope="col" class="jp-table__cell--end">Amount</th>
                        <th scope="col">Method</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deposits as $deposit)
                        <tr>
                            <td data-label="Submitted" class="jp-table__cell--nowrap">{{ $deposit->created_at?->format('j M Y, g:i A') ?? '—' }}</td>
                            <td data-label="Amount" class="jp-table__cell--end jp-money">Rs {{ number_format((float) $deposit->amount, 2) }}</td>
                            <td data-label="Method">{{ $deposit->payment_method ?? '—' }}</td>
                            <td data-label="Reference">{{ $deposit->reference ?? '—' }}</td>
                            <td data-label="Status"><x-dashboard.status-badge :status="$deposit->status->value" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="jp-empty">
                                    <span class="jp-empty__icon" aria-hidden="true"><x-jp.icon name="wallet" /></span>
                                    <p class="jp-empty__title">No deposits yet</p>
                                    <p class="jp-empty__help">Request a deposit after transferring funds to the agency account.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="jp-portal__list jp-portal__list--mobile">
            @forelse ($deposits as $deposit)
                <article class="jp-portal__list-card">
                    <div class="jp-portal__list-card-head">
                        <span class="jp-portal__list-card-ref jp-money">Rs {{ number_format((float) $deposit->amount, 2) }}</span>
                        <x-dashboard.status-badge :status="$deposit->status->value" />
                    </div>
                    <div class="jp-portal__list-card-meta">
                        <span>{{ $deposit->created_at?->format('j M Y, g:i A') ?? '—' }}</span>
                        <span>Method: {{ $deposit->payment_method ?? '—' }}</span>
                        <span>Reference: {{ $deposit->reference ?? '—' }}</span>
                    </div>
                </article>
            @empty
                <div class="jp-empty">
                    <p class="jp-empty__title">No deposits yet</p>
                    <p class="jp-empty__help">Request a deposit after transferring funds to the agency account.</p>
                </div>
            @endforelse
        </div>

        @if ($deposits->hasPages())
            <div class="jp-portal__pagination">{{ $deposits->links() }}</div>
        @endif
    </x-jp.card>
@endsection
