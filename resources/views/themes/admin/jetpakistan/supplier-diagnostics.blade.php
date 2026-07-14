@extends(client_layout('dashboard', 'admin'))

@section('title', 'Supplier Diagnostics')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Supplier Diagnostics</h1>
            <p>Safe supplier error drilldown scoped to your agency.</p>
        </div>
        <a href="{{ client_route('admin.reports', ['tab' => 'suppliers']) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Back to supplier report</a>
    </div>
@endsection

@section('content')
@php $f = $filters ?? []; @endphp

<form method="GET" action="{{ client_route('admin.reports.supplier-diagnostics') }}" class="jp-filterbar" data-testid="ota-supplier-diagnostics-filters">
    <div class="jp-filterbar__field">
        <label class="jp-label">Provider</label>
        <select name="provider" class="jp-select">
            <option value="all" @selected(($f['provider'] ?? 'all') === 'all')>All providers</option>
            @foreach ($providerOptions as $provider)
                <option value="{{ $provider }}" @selected(($f['provider'] ?? '') === $provider)>{{ ucwords(str_replace('_', ' ', $provider)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label">Action</label>
        <select name="action" class="jp-select">
            <option value="" @selected(($f['action'] ?? '') === '')>All actions</option>
            @foreach ($actionOptions as $action)
                <option value="{{ $action }}" @selected(($f['action'] ?? '') === $action)>{{ ucwords(str_replace('_', ' ', $action)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label">Status</label>
        <select name="status" class="jp-select">
            @foreach ($statusOptions as $status)
                <option value="{{ $status }}" @selected(($f['status'] ?? 'errors') === $status)>{{ $status === 'errors' ? 'Errors only' : ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label">From</label>
        <input type="date" name="date_from" class="jp-input" value="{{ $f['date_from'] ?? '' }}">
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label">To</label>
        <input type="date" name="date_to" class="jp-input" value="{{ $f['date_to'] ?? '' }}">
    </div>
    <div class="jp-filterbar__actions">
        <button type="submit" class="jp-btn jp-btn--sm">Apply filters</button>
    </div>
</form>

<div class="jp-card" data-testid="ota-sabre-ndc-status-panel">
    <div class="jp-card__head"><h2 class="jp-card__title">Sabre NDC status (GDS separate)</h2></div>
    <div class="jp-kpis jp-kpis--compact">
        <div class="jp-kpi"><div class="jp-kpi__l">NDC enabled</div><div class="jp-kpi__v" style="font-size:1rem;">{{ config('suppliers.sabre.ndc.enabled') ? 'yes' : 'no' }}</div></div>
        <div class="jp-kpi"><div class="jp-kpi__l">NDC search</div><div class="jp-kpi__v" style="font-size:1rem;">{{ config('suppliers.sabre.ndc.search_enabled') ? 'yes' : 'no' }}</div></div>
        <div class="jp-kpi"><div class="jp-kpi__l">Order create</div><div class="jp-kpi__v" style="font-size:1rem;">{{ config('suppliers.sabre.ndc.order_create_enabled') ? 'yes' : 'no' }}</div></div>
        <div class="jp-kpi"><div class="jp-kpi__l">GDS ticketing</div><div class="jp-kpi__v" style="font-size:1rem;">{{ config('suppliers.sabre.ticketing_enabled') ? 'enabled' : 'disabled' }}</div></div>
    </div>
</div>

<div class="jp-dtable-wrap" data-testid="ota-supplier-diagnostics-page">
    <div style="padding: 12px 14px; border-bottom: 1px solid var(--line-soft); display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong>Latest diagnostics</strong>
            <p class="jp-cell-sub" style="margin: 4px 0 0;">Only safe fields are displayed.</p>
        </div>
        <span class="jp-badge-pill jp-badge-pill--blue">{{ number_format($diagnostics->count()) }} rows</span>
    </div>
    <table class="jp-dtable">
        <thead>
            <tr>
                <th>Time</th>
                <th>Provider</th>
                <th>Action</th>
                <th>Status</th>
                <th>Reason code</th>
                <th>Error code</th>
                <th>HTTP</th>
                <th>Endpoint</th>
                <th>Safe message</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($diagnostics as $row)
                <tr data-testid="ota-diagnostic-row">
                    <td class="jp-cell-sub">{{ $row['created_at'] }}</td>
                    <td><strong>{{ ucwords(str_replace('_', ' ', $row['provider'])) }}</strong></td>
                    <td>{{ display_unknown($row['action'] ?? null) }}</td>
                    <td><span class="jp-badge-pill {{ ($row['status'] ?? '') === 'success' ? 'jp-badge-pill--green' : 'jp-badge-pill--danger' }}">{{ display_unknown($row['status'] ?? null) }}</span></td>
                    <td>{{ display_unknown($row['reason_code'] ?? null) }}</td>
                    <td>{{ display_unknown($row['error_code'] ?? null) }}</td>
                    <td>{{ display_unknown(isset($row['http_status']) ? (string) $row['http_status'] : null) }}</td>
                    <td class="jp-cell-sub">{{ display_unknown($row['endpoint'] ?? null) }}</td>
                    <td>{{ display_unknown($row['safe_message'] ?? null) }}</td>
                </tr>
            @empty
                <tr><td colspan="9"><x-themes.admin.jetpakistan.components.empty-state title="No diagnostics" message="No supplier diagnostics match these filters." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
