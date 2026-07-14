@extends(client_layout('dashboard', 'admin'))

@section('title', $pageTitle ?? 'Agent Statements')

@section('content')
    @php
        $showRoute = ($routePrefix ?? 'admin.finance.statements').'.show';
        $currency = 'PKR';
        $moneyPrefix = 'Rs ';
    @endphp

    <div class="page-header d-print-none mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="jp-page-title" data-testid="finance-statements-index-title">{{ $pageTitle ?? 'Agent Statements' }}</h2>
                <p class="text-secondary mb-0">Settlement-ready wallet statements with ledger comparison (read-only).</p>
            </div>
        </div>
    </div>

    <div class="jp-card" data-testid="finance-statements-index-table">
        <div class="table-responsive">
            <table class="jp-table mb-0">
                <thead>
                    <tr>
                        <th>Agency</th>
                        <th class="text-end">Wallet balance</th>
                        <th class="text-end">Ledger liability</th>
                        <th class="text-end">Diff</th>
                        <th>Last movement</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $agency = $row['agency'];
                            $status = (string) ($row['reconciliation_status'] ?? 'no_ledger_data');
                            $badgeClass = match ($status) {
                                'matched' => 'bg-success',
                                'mismatch' => 'bg-danger',
                                default => 'bg-secondary',
                            };
                            $badgeLabel = match ($status) {
                                'matched' => 'Matched',
                                'mismatch' => 'Mismatch',
                                default => 'No ledger data',
                            };
                        @endphp
                        <tr data-testid="finance-statement-agency-{{ $agency->id }}">
                            <td>{{ $agency->name }}</td>
                            <td class="text-end">{{ $moneyPrefix }}{{ number_format((float) $row['wallet_balance'], 2) }}</td>
                            <td class="text-end">{{ $moneyPrefix }}{{ number_format((float) $row['ledger_liability'], 2) }}</td>
                            <td class="text-end">{{ $moneyPrefix }}{{ number_format((float) $row['difference'], 2) }}</td>
                            <td>{{ $row['last_movement_at'] ? \Illuminate\Support\Carbon::parse($row['last_movement_at'])->format('Y-m-d H:i') : '—' }}</td>
                            <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                            <td class="text-end">
                                <a href="{{ route($showRoute, $agency) }}" class="jp-btn jp-btn--sm jp-btn--outline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-secondary">No agencies found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
