@extends(client_layout('dashboard', 'admin'))

@section('title', $pageTitle ?? 'Master Ledger')

@section('page-header')
    <x-dashboard.section-header
        :title="$pageTitle ?? 'Master Ledger'"
        :subtitle="$pageSubtitle ?? 'Platform-wide agency wallet transactions, deposits, and adjustments.'"
    />
@endsection

@section('content')
    @php
        use App\Enums\AgentWalletTransactionStatus;
        use App\Enums\AgentWalletTransactionType;

        $indexRoute = ($routePrefix ?? 'admin.ledger').'.index';
        $currency = (string) ($summary['currency'] ?? 'PKR');
        $moneyPrefix = $currency === 'PKR' ? 'Rs ' : $currency.' ';
    @endphp

    <div class="jp-module-compat">
        <div class="row g-3 mb-4" data-testid="master-ledger-summary">
            @foreach ([
                ['label' => 'Total credits', 'key' => 'total_credits'],
                ['label' => 'Total debits', 'key' => 'total_debits'],
                ['label' => 'Net (filtered)', 'key' => 'net_balance'],
                ['label' => 'Pending deposits', 'key' => 'pending_deposits'],
                ['label' => 'Approved deposits', 'key' => 'approved_deposits'],
                ['label' => 'Wallet exposure', 'key' => 'agency_wallet_exposure'],
            ] as $card)
                <div class="col-6 col-md-4 col-xl-2">
                    <x-dashboard.kpi-stat :label="$card['label']" :value="$moneyPrefix.number_format((float) ($summary[$card['key']] ?? 0), 2)" />
                </div>
            @endforeach
        </div>

        <form method="get" action="{{ route($indexRoute) }}" class="card mb-3 jp-form-shell" data-testid="master-ledger-filters">
            <div class="jp-card__body">
                <div class="jp-form-grid jp-form-grid--2">
                    @if (($scope ?? 'platform') === 'platform' && $agencies->isNotEmpty())
                        <div class="jp-field">
                            <label class="jp-label jp-label">Agency</label>
                            <select name="agency_id" class="jp-control jp-control">
                                <option value="">All agencies</option>
                                @foreach ($agencies as $agency)
                                    <option value="{{ $agency->id }}" @selected(($filters['agency_id'] ?? '') === (string) $agency->id)>{{ $agency->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="jp-field">
                        <label class="jp-label jp-label">From</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="jp-control jp-control">
                    </div>
                    <div class="jp-field">
                        <label class="jp-label jp-label">To</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="jp-control jp-control">
                    </div>
                    <div class="jp-field">
                        <label class="jp-label jp-label">Type</label>
                        <select name="type" class="jp-control jp-control">
                            <option value="">All</option>
                            @foreach (AgentWalletTransactionType::cases() as $case)
                                <option value="{{ $case->value }}" @selected(($filters['type'] ?? '') === $case->value)>{{ str_replace('_', ' ', $case->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="jp-field">
                        <label class="jp-label jp-label">Status</label>
                        <select name="status" class="jp-control jp-control">
                            <option value="">All</option>
                            @foreach (AgentWalletTransactionStatus::cases() as $case)
                                <option value="{{ $case->value }}" @selected(($filters['status'] ?? '') === $case->value)>{{ $case->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="jp-field">
                        <label class="jp-label jp-label">Direction</label>
                        <select name="direction" class="jp-control jp-control">
                            <option value="">All</option>
                            <option value="credit" @selected(($filters['direction'] ?? '') === 'credit')>Credit</option>
                            <option value="debit" @selected(($filters['direction'] ?? '') === 'debit')>Debit</option>
                        </select>
                    </div>
                    <div class="jp-field">
                        <label class="jp-label jp-label">Booking reference</label>
                        <input type="search" name="booking_ref" value="{{ $filters['booking_ref'] ?? '' }}" class="jp-control jp-control">
                    </div>
                    <div class="jp-field">
                        <label class="jp-label jp-label">{{ \App\Support\Identity\IdentityDisplay::labelPerformedBy() }} / user</label>
                        <input type="search" name="actor" value="{{ $filters['actor'] ?? '' }}" class="jp-control jp-control">
                    </div>
                    <div class="jp-field jp-field--full">
                        <label class="jp-label jp-label">Search</label>
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="jp-control jp-control" placeholder="Reference or description">
                    </div>
                </div>
                <div class="jp-action-bar" style="border-top:none;padding-top:12px;margin-top:8px;">
                    <a href="{{ route($indexRoute) }}" class="jp-btn jp-btn--ghost btn-sm">Clear</a>
                    <button type="submit" class="jp-btn jp-btn--primary btn-sm">Filter</button>
                </div>
            </div>
        </form>

        <div class="jp-card">
            <div class="table-responsive">
                <table class="jp-table mb-0" data-testid="master-ledger-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Agency</th>
                            <th>{{ \App\Support\Identity\IdentityDisplay::labelPerformedBy() }}</th>
                            <th>Reference</th>
                            <th>Booking</th>
                            <th>Type</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $tx)
                            @include('dashboard.admin.ledger._row', ['tx' => $tx, 'routePrefix' => $routePrefix ?? 'admin.ledger'])
                        @empty
                            <tr>
                                <td colspan="10">
                                    <x-dashboard.empty-state title="No ledger transactions" help="No ledger transactions match your filters." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($transactions->hasPages())
                <div class="card-footer">{{ $transactions->links() }}</div>
            @endif
        </div>
    </div>
@endsection
