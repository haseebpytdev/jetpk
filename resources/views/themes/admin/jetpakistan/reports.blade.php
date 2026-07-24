@extends(client_layout('dashboard', 'admin'))

@section('title', $reportsTitle ?? 'Reports & Analytics')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>{{ $reportsTitle ?? 'Reports & Analytics' }}</h1>
            <p>Platform booking, payment, and commission metrics for the selected period.</p>
        </div>
        <a href="{{ client_route($reportsSupplierDiagnosticsRoute ?? 'admin.reports.supplier-diagnostics') }}" class="jp-btn jp-btn--sm jp-btn--outline">Supplier diagnostics</a>
    </div>
@endsection

@section('content')
@php
    if (! function_exists('ota_money')) {
        function ota_money($value): string
        {
            return 'Rs '.number_format((int) round((float) $value));
        }
    }

    $sum = $summary ?? [];
    $f = $filters ?? [];
    $financial = $financialKpis ?? [];
    $operational = $operationalKpis ?? [];
    $commissionTotals = $commissionTotals ?? [];
    $hasLiveData = (bool) ($hasLiveData ?? false);
    $statusOpts = $bookingStatusOptions ?? [];
    $reportsIndexRoute = $reportsIndexRoute ?? 'admin.reports';
    $reportsExportRoute = $reportsExportRoute ?? 'admin.reports.export';
@endphp

<div class="jp-alert jp-alert--info">
    Interactive report charts and tabbed drill-downs are deferred during the JetPakistan migration. KPIs and filters below use live report data from the platform backend.
</div>

@if (! $hasLiveData)
    <div class="jp-alert jp-alert--info">No live booking data yet for the selected filters.</div>
@endif

<form method="GET" action="{{ client_route($reportsIndexRoute) }}" class="jp-filterbar" style="flex-wrap: wrap; margin-bottom: 16px;">
    <input type="hidden" name="tab" value="{{ $activeTab ?? 'overview' }}">

    <div class="jp-filterbar__field" style="min-width: 100%; flex-basis: 100%;">
        <span class="jp-label">Quick range</span>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            @foreach ([['today','Today'],['7d','7 days'],['30d','30 days'],['this_month','This month']] as [$key, $label])
                <button type="submit" name="preset" value="{{ $key }}" class="jp-btn jp-btn--sm {{ ($f['preset'] ?? '') === $key ? '' : 'jp-btn--ghost' }}">{{ $label }}</button>
            @endforeach
            <button type="submit" name="preset" value="" class="jp-btn jp-btn--sm {{ ($f['preset'] ?? '') === '' ? '' : 'jp-btn--ghost' }}">Custom</button>
        </div>
    </div>

    <div class="jp-filterbar__field">
        <label class="jp-label" for="reports-date-from">From</label>
        <input type="date" id="reports-date-from" name="date_from" class="jp-input" value="{{ $f['date_from'] ?? '' }}">
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="reports-date-to">To</label>
        <input type="date" id="reports-date-to" name="date_to" class="jp-input" value="{{ $f['date_to'] ?? '' }}">
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="reports-channel">Channel</label>
        <select id="reports-channel" name="channel" class="jp-select">
            <option value="all" @selected(($f['channel'] ?? 'all') === 'all')>All</option>
            <option value="direct" @selected(($f['channel'] ?? '') === 'direct')>Direct</option>
            <option value="agent" @selected(($f['channel'] ?? '') === 'agent')>Agent</option>
        </select>
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="reports-supplier">Supplier</label>
        <select id="reports-supplier" name="supplier" class="jp-select">
            <option value="all" @selected(($f['supplier'] ?? 'all') === 'all')>All</option>
            <option value="duffel" @selected(($f['supplier'] ?? '') === 'duffel')>Duffel</option>
            <option value="sabre" @selected(($f['supplier'] ?? '') === 'sabre')>Sabre</option>
            <option value="pia_ndc" @selected(($f['supplier'] ?? '') === 'pia_ndc')>PIA NDC</option>
            <option value="airline_direct" @selected(($f['supplier'] ?? '') === 'airline_direct')>Airline direct</option>
            <option value="none" @selected(($f['supplier'] ?? '') === 'none')>No supplier</option>
        </select>
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="reports-status">Status</label>
        <select id="reports-status" name="status" class="jp-select">
            <option value="">Any</option>
            @foreach ($statusOpts as $opt)
                <option value="{{ $opt }}" @selected(($f['status'] ?? '') === $opt)>{{ ucwords(str_replace('_', ' ', $opt)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="reports-payment">Payment</label>
        <select id="reports-payment" name="payment_status" class="jp-select">
            <option value="">Any</option>
            @foreach (['unpaid', 'partial', 'paid', 'refunded'] as $ps)
                <option value="{{ $ps }}" @selected(($f['payment_status'] ?? '') === $ps)>{{ ucfirst($ps) }}</option>
            @endforeach
        </select>
    </div>
    <div class="jp-filterbar__actions">
        <button type="submit" class="jp-btn jp-btn--sm">Apply filters</button>
        <a href="{{ client_route($reportsExportRoute, array_merge(['type' => 'sales'], request()->query())) }}" class="jp-btn jp-btn--sm jp-btn--outline">Export sales CSV</a>
        <a href="{{ client_route($reportsExportRoute, array_merge(['type' => 'payments'], request()->query())) }}" class="jp-btn jp-btn--sm jp-btn--outline">Export payments CSV</a>
    </div>
</form>

<div class="jp-card">
    <div class="jp-card__head"><h2 class="jp-card__title">Financial performance</h2></div>
    <div class="jp-kpis jp-kpis--compact">
        <div class="jp-kpi"><div class="jp-kpi__v">{{ ota_money($financial['gross_sales'] ?? 0) }}</div><div class="jp-kpi__l">Gross booking value</div></div>
        <div class="jp-kpi"><div class="jp-kpi__v">{{ ota_money($financial['net_revenue'] ?? 0) }}</div><div class="jp-kpi__l">Net revenue</div></div>
        <div class="jp-kpi"><div class="jp-kpi__v">{{ ota_money($financial['markup_revenue'] ?? 0) }}</div><div class="jp-kpi__l">Markup revenue</div></div>
        <div class="jp-kpi"><div class="jp-kpi__v">{{ ota_money($financial['refund_paid'] ?? 0) }}</div><div class="jp-kpi__l">Refund paid</div></div>
        <div class="jp-kpi t-amber"><div class="jp-kpi__v">{{ ota_money($financial['outstanding_balance'] ?? 0) }}</div><div class="jp-kpi__l">Outstanding</div></div>
    </div>
</div>

<div class="jp-card">
    <div class="jp-card__head"><h2 class="jp-card__title">Operational workload</h2></div>
    <div class="jp-kpis jp-kpis--compact">
        <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($operational['total_bookings'] ?? 0)) }}</div><div class="jp-kpi__l">Total bookings</div></div>
        <div class="jp-kpi t-amber"><div class="jp-kpi__v">{{ number_format((int) ($operational['pending_bookings'] ?? 0)) }}</div><div class="jp-kpi__l">Pending</div></div>
        <div class="jp-kpi t-amber"><div class="jp-kpi__v">{{ number_format((int) ($operational['unpaid_partial_bookings'] ?? 0)) }}</div><div class="jp-kpi__l">Unpaid / partial</div></div>
        <div class="jp-kpi t-blue"><div class="jp-kpi__v">{{ number_format((int) ($operational['supplier_pnr_pending'] ?? 0)) }}</div><div class="jp-kpi__l">Supplier / PNR pending</div></div>
        <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($operational['ticketing_pending'] ?? 0)) }}</div><div class="jp-kpi__l">Ticketing pending</div></div>
        <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($operational['cancelled_bookings'] ?? 0)) }}</div><div class="jp-kpi__l">Cancelled</div></div>
    </div>
</div>

<div class="jp-card">
    <div class="jp-card__head"><h2 class="jp-card__title">Commission totals</h2></div>
    <div class="jp-kpis jp-kpis--4">
        <div class="jp-kpi"><div class="jp-kpi__v">{{ ota_money($commissionTotals['approved'] ?? 0) }}</div><div class="jp-kpi__l">Approved</div></div>
        <div class="jp-kpi t-blue"><div class="jp-kpi__v">{{ ota_money($commissionTotals['paid'] ?? 0) }}</div><div class="jp-kpi__l">Paid</div></div>
        @if (isset($sum['bookings_count']))
            <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) $sum['bookings_count']) }}</div><div class="jp-kpi__l">Bookings in range</div></div>
        @endif
        @if (isset($sum['total_sales']))
            <div class="jp-kpi"><div class="jp-kpi__v">{{ ota_money($sum['total_sales']) }}</div><div class="jp-kpi__l">Summary sales</div></div>
        @endif
    </div>
</div>
@endsection
