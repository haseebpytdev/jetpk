@extends(client_layout('dashboard', 'admin'))

@section('title', 'Agents')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Agents</h1>
            <p>Partner agencies, commissions, and onboarding pipeline.</p>
        </div>
        <a href="{{ client_route('admin.agents.export', request()->query()) }}" class="jp-btn jp-btn--sm jp-btn--outline" data-testid="ota-agents-export-csv">Export CSV</a>
    </div>
@endsection

@section('content')
@php
    $k = $kpis ?? [];
    $agentRows = collect($agents ?? [])->take(25);
@endphp

@include('themes.admin.jetpakistan.partials.flash')

<div class="jp-alert jp-alert--info">
    The full interactive agents console (filters, preview panel, and live search) is being migrated to the JetPakistan dashboard shell. KPIs and the agent list below reflect current server data.
</div>

<div class="jp-kpis jp-kpis--6">
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($k['total'] ?? 0)) }}</div><div class="jp-kpi__l">Total agents</div></div>
    <div class="jp-kpi t-blue"><div class="jp-kpi__v">{{ number_format((int) ($k['active'] ?? 0)) }}</div><div class="jp-kpi__l">Active</div></div>
    <div class="jp-kpi t-amber"><div class="jp-kpi__v">{{ number_format((int) ($k['pending_applications'] ?? 0)) }}</div><div class="jp-kpi__l">Pending applications</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">Rs {{ number_format((int) round((float) ($k['monthly_sales'] ?? 0))) }}</div><div class="jp-kpi__l">Monthly sales</div></div>
    <div class="jp-kpi t-amber"><div class="jp-kpi__v">Rs {{ number_format((int) round((float) ($k['commission_pending_total'] ?? 0))) }}</div><div class="jp-kpi__l">Commission pending</div></div>
    <div class="jp-kpi t-danger"><div class="jp-kpi__v">Rs {{ number_format((int) round((float) ($k['outstanding'] ?? 0))) }}</div><div class="jp-kpi__l">Outstanding balance</div></div>
</div>

<div class="jp-kpis jp-kpis--compact" style="margin-top: -8px;">
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($k['inactive'] ?? 0)) }}</div><div class="jp-kpi__l">Inactive</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($k['with_balance'] ?? 0)) }}</div><div class="jp-kpi__l">With balance</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($k['recent_onboards'] ?? 0)) }}</div><div class="jp-kpi__l">Recent onboardings</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($k['approved_this_month'] ?? 0)) }}</div><div class="jp-kpi__l">Approved this month</div></div>
</div>

<div class="jp-dtable-wrap">
    <table class="jp-dtable">
        <thead>
            <tr>
                <th>Agent</th>
                <th>Contact</th>
                <th>Status</th>
                <th class="num">Commission</th>
                <th class="num">Bookings</th>
                <th class="num">Monthly sales</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($agentRows as $row)
                @php
                    $userId = $row['user_id'] ?? null;
                    $openUrl = $userId
                        ? client_route('admin.users.show', ['user' => $userId])
                        : client_route('admin.commissions.show', ['agent' => $row['id']]);
                    $st = $row['status'] ?? 'inactive';
                @endphp
                <tr>
                    <td data-label="Agent">
                        <span class="jp-cell-id">{{ $row['agent_code'] }}</span>
                        <span class="jp-cell-sub">{{ $row['agency_name'] }}</span>
                    </td>
                    <td data-label="Contact">
                        {{ $row['contact_person'] }}
                        <span class="jp-cell-sub">{{ $row['email'] }}</span>
                    </td>
                    <td data-label="Status">
                        <span class="jp-badge-pill {{ $st === 'active' ? 'jp-badge-pill--green' : '' }}">{{ ucfirst($st) }}</span>
                    </td>
                    <td data-label="Commission" class="num">{{ number_format((float) ($row['commission_percent'] ?? 0), 1) }}%</td>
                    <td data-label="Bookings" class="num">{{ number_format((int) ($row['bookings_count'] ?? 0)) }}</td>
                    <td data-label="Monthly sales" class="num">Rs {{ number_format((int) round((float) ($row['monthly_sales'] ?? 0))) }}</td>
                    <td data-label="Actions">
                        <a href="{{ $openUrl }}" class="jp-btn jp-btn--sm jp-btn--ghost">Open</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <x-themes.admin.jetpakistan.components.empty-state title="No agents yet" message="Agents appear after application approval or manual creation." />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if (($agentsTotalCount ?? count($agents ?? [])) > $agentRows->count())
    <p class="jp-cell-sub" style="margin-top: 12px;">Showing first {{ $agentRows->count() }} of {{ number_format((int) ($agentsTotalCount ?? count($agents ?? []))) }} agents. Full filtering arrives with the migrated console.</p>
@endif
@endsection
