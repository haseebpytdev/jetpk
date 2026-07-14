@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Deposits')

@section('account_title', 'Deposits')
@section('account_subtitle', 'Your deposit requests and review status.')

@section('account_actions')
    @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::PaymentsUpload))
        <a href="{{ route('agent.deposits.create') }}" class="ota-account-btn ota-account-btn--primary" data-testid="agent-deposits-create-link">New deposit request</a>
    @endif
    @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::WalletView))
        <a href="{{ route('agent.wallet.show') }}" class="ota-account-btn ota-account-btn--secondary">Wallet</a>
    @endif
@endsection

@section('account_content')
    @php $ws = $summary ?? []; @endphp

    @if (session('status') === 'deposit-submitted')
        <div class="ota-account-alert ota-account-alert--success" data-testid="agent-deposit-flash">Deposit request submitted. Finance will review your proof.</div>
    @endif

    <div class="ota-account-grid ota-account-grid--kpis mb-4" data-testid="agent-deposits-summary">
        <div class="ota-account-kpi ota-account-kpi--emerald">
            <div class="ota-account-kpi__label">Wallet balance</div>
            <div class="ota-account-kpi__value">Rs {{ number_format((float) ($ws['balance'] ?? 0), 2) }}</div>
        </div>
        <div class="ota-account-kpi ota-account-kpi--amber">
            <div class="ota-account-kpi__label">Pending deposits</div>
            <div class="ota-account-kpi__value">Rs {{ number_format((float) ($ws['pending_deposits'] ?? 0), 2) }}</div>
        </div>
        <div class="ota-account-kpi">
            <div class="ota-account-kpi__label">Credit limit</div>
            <div class="ota-account-kpi__value">
                @if ($ws['credit_enabled'] ?? false)
                    Rs {{ number_format((float) $ws['credit_limit'], 2) }}
                @else
                    Not enabled
                @endif
            </div>
        </div>
    </div>

    <div class="ota-account-card">
        <div class="ota-account-card__body ota-account-card__body--flush">
            <div class="ota-account-table-wrap">
                <table class="ota-account-table mb-0" data-testid="agent-deposits-table">
                    <thead>
                        <tr>
                            <th>Submitted</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deposits as $deposit)
                            <tr>
                                <td>{{ $deposit->created_at?->format('j M Y, g:i A') ?? '—' }}</td>
                                <td>Rs {{ number_format((float) $deposit->amount, 2) }}</td>
                                <td>{{ $deposit->payment_method ?? '—' }}</td>
                                <td>{{ $deposit->reference ?? '—' }}</td>
                                <td><x-dashboard.status-badge :status="$deposit->status->value" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="ota-account-empty ota-account-empty--compact">
                                        <div class="ota-account-empty-icon" aria-hidden="true"><i class="ti ti-cash"></i></div>
                                        <p class="ota-account-empty-title">No deposits yet</p>
                                        <p class="ota-account-empty-help">Request a deposit after transferring funds to the agency account.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($deposits->hasPages())
                <div class="ota-account-card__footer">{{ $deposits->links() }}</div>
            @endif
        </div>
    </div>
@endsection
