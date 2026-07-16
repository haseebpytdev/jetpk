@extends(client_layout('agent-portal', 'agent'))

@section('title', $reportsTitle ?? 'Agency Reports')

@section('account_title', $reportsTitle ?? 'Agency Reports')
@section('account_subtitle', 'Financial summary and bookings for your agency only.')

@section('account_content')
    @php
        $f = $filters ?? [];
        $s = $summary ?? [];
        $moneyPrefix = 'Rs ';
    @endphp

    <form method="get" action="{{ route('agent.reports.index') }}" class="jp-panel jp-panel--filters" data-testid="agent-reports-filters">
        <div class="jp-field-grid jp-field-grid--filters">
            <div class="jp-field">
                <label class="jp-label" for="date_from">From date</label>
                <input type="date" name="date_from" id="date_from" value="{{ $f['date_from'] ?? '' }}" class="jp-input" aria-label="From date">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="date_to">To date</label>
                <input type="date" name="date_to" id="date_to" value="{{ $f['date_to'] ?? '' }}" class="jp-input" aria-label="To date">
            </div>
            <div class="jp-field">
                <label class="jp-label" for="status">Status</label>
                <select name="status" id="status" class="jp-input" aria-label="Status">
                    <option value="all">All statuses</option>
                    @foreach ($bookingStatusOptions ?? [] as $status)
                        <option value="{{ $status }}" @selected(($f['status'] ?? 'all') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="jp-field jp-field--actions">
                <button type="submit" class="jp-btn jp-btn--primary">Filter</button>
            </div>
        </div>
    </form>

    <div class="jp-kpi-grid jp-kpi-grid--3" data-testid="agent-reports-summary">
        <div class="jp-kpi">
            <p class="jp-kpi__label">Gross sales</p>
            <p class="jp-kpi__value jp-money">{{ $moneyPrefix }}{{ number_format((float) ($s['gross_sales'] ?? 0), 2) }}</p>
        </div>
        <div class="jp-kpi">
            <p class="jp-kpi__label">Net revenue</p>
            <p class="jp-kpi__value jp-money">{{ $moneyPrefix }}{{ number_format((float) ($s['net_revenue'] ?? 0), 2) }}</p>
        </div>
        <div class="jp-kpi">
            <p class="jp-kpi__label">Total bookings</p>
            <p class="jp-kpi__value">{{ number_format((int) ($s['total_bookings'] ?? 0)) }}</p>
        </div>
        <div class="jp-kpi">
            <p class="jp-kpi__label">Refunds paid</p>
            <p class="jp-kpi__value jp-money">{{ $moneyPrefix }}{{ number_format((float) ($s['refund_paid_amount'] ?? 0), 2) }}</p>
        </div>
        <div class="jp-kpi">
            <p class="jp-kpi__label">Outstanding balance</p>
            <p class="jp-kpi__value jp-money">{{ $moneyPrefix }}{{ number_format((float) ($s['outstanding_balance'] ?? 0), 2) }}</p>
        </div>
    </div>

    @if (empty($hasLiveData))
        @include('themes.frontend.jetpakistan.components.portal.empty-state', [
            'title' => 'No report data yet',
            'message' => 'Bookings in your agency will appear here once created.',
        ])
    @else
        <div class="jp-card">
            <div class="jp-panel__header">
                <h2 class="jp-panel__title">Top routes</h2>
            </div>
            <div class="jp-table-wrap">
                <table class="jp-table" data-testid="agent-reports-routes">
                    <thead>
                        <tr>
                            <th>Route</th>
                            <th class="jp-table__num">Bookings</th>
                            <th class="jp-table__num">Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topRoutes ?? [] as $row)
                            <tr>
                                <td>{{ $row['route'] ?? '—' }}</td>
                                <td class="jp-table__num">{{ (int) ($row['bookings'] ?? 0) }}</td>
                                <td class="jp-table__num jp-money">{{ $moneyPrefix }}{{ number_format((float) ($row['sales'] ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="jp-table__empty">No routes in range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
