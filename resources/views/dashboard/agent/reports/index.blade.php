@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Agency Reports')

@section('account_title', 'Agency Reports')
@section('account_subtitle', 'Financial summary and bookings for your agency only.')

@section('account_content')
    @php
        $f = $filters ?? [];
        $s = $summary ?? [];
        $moneyPrefix = 'Rs ';
    @endphp

    <form method="get" action="{{ route('agent.reports.index') }}" class="ota-account-toolbar mb-3" data-testid="agent-reports-filters">
        <input type="date" name="date_from" value="{{ $f['date_from'] ?? '' }}" class="form-control form-control-sm" aria-label="From date">
        <input type="date" name="date_to" value="{{ $f['date_to'] ?? '' }}" class="form-control form-control-sm" aria-label="To date">
        <select name="status" class="form-select form-select-sm" aria-label="Status">
            <option value="all">All statuses</option>
            @foreach ($bookingStatusOptions ?? [] as $status)
                <option value="{{ $status }}" @selected(($f['status'] ?? 'all') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button type="submit" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">Filter</button>
    </form>

    <div class="row g-3 mb-4" data-testid="agent-reports-summary">
        <div class="col-6 col-md-4">
            <div class="ota-account-card h-100">
                <div class="ota-account-card__body">
                    <div class="text-secondary small">Gross sales</div>
                    <div class="h3 mb-0">{{ $moneyPrefix }}{{ number_format((float) ($s['gross_sales'] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="ota-account-card h-100">
                <div class="ota-account-card__body">
                    <div class="text-secondary small">Net revenue</div>
                    <div class="h3 mb-0">{{ $moneyPrefix }}{{ number_format((float) ($s['net_revenue'] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="ota-account-card h-100">
                <div class="ota-account-card__body">
                    <div class="text-secondary small">Total bookings</div>
                    <div class="h3 mb-0">{{ number_format((int) ($s['total_bookings'] ?? 0)) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="ota-account-card h-100">
                <div class="ota-account-card__body">
                    <div class="text-secondary small">Refunds paid</div>
                    <div class="h3 mb-0">{{ $moneyPrefix }}{{ number_format((float) ($s['refund_paid_amount'] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="ota-account-card h-100">
                <div class="ota-account-card__body">
                    <div class="text-secondary small">Outstanding balance</div>
                    <div class="h3 mb-0">{{ $moneyPrefix }}{{ number_format((float) ($s['outstanding_balance'] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if (empty($hasLiveData))
        <div class="ota-account-empty" data-testid="agent-reports-empty">
            <p class="ota-account-empty-title">No report data yet</p>
            <p class="ota-account-empty-help">Bookings in your agency will appear here once created.</p>
        </div>
    @else
        <div class="ota-account-card">
            <div class="ota-account-card__header">
                <h3 class="ota-account-card__title">Top routes</h3>
            </div>
            <div class="ota-account-card__body ota-account-card__body--flush">
                <div class="ota-account-table-wrap">
                    <table class="ota-account-table mb-0" data-testid="agent-reports-routes">
                        <thead>
                            <tr>
                                <th>Route</th>
                                <th class="text-end">Bookings</th>
                                <th class="text-end">Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topRoutes ?? [] as $row)
                                <tr>
                                    <td>{{ $row['route'] ?? '—' }}</td>
                                    <td class="text-end">{{ (int) ($row['bookings'] ?? 0) }}</td>
                                    <td class="text-end">{{ $moneyPrefix }}{{ number_format((float) ($row['sales'] ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-secondary text-center py-3">No routes in range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
