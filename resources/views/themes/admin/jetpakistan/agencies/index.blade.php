@extends(client_layout('dashboard', 'admin'))

@section('title', 'Agencies')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Agencies</h1>
            <p>Agency and partner company records across the platform.</p>
        </div>
        <a href="{{ client_route('admin.agent-applications.index') }}" class="jp-btn jp-btn--sm jp-btn--outline">Agency applications</a>
    </div>
@endsection

@section('content')
<div class="jp-kpis jp-kpis--4">
    <div class="jp-kpi"><div class="jp-kpi__v">{{ $kpis['total'] }}</div><div class="jp-kpi__l">Total agencies</div></div>
    <div class="jp-kpi t-blue"><div class="jp-kpi__v">{{ $kpis['active'] }}</div><div class="jp-kpi__l">Active</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">{{ $kpis['inactive'] }}</div><div class="jp-kpi__l">Inactive</div></div>
</div>

<form class="jp-filterbar" method="get" action="{{ client_route('admin.agencies.index') }}">
    <div class="jp-filterbar__field">
        <label class="jp-label" for="agencies-search">Search</label>
        <input id="agencies-search" class="jp-input" name="search" placeholder="Agency name, slug, owner name or email" value="{{ $filters['search'] ?? '' }}">
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="agencies-status">Status</label>
        <select id="agencies-status" class="jp-select" name="status">
            <option value="">All statuses</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
        </select>
    </div>
    <div class="jp-filterbar__actions">
        <button class="jp-btn jp-btn--sm" type="submit">Apply filters</button>
        <a href="{{ client_route('admin.agencies.index') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Reset</a>
    </div>
</form>

<div class="jp-dtable-wrap" data-testid="admin-agencies-index">
    <table class="jp-dtable">
        <thead>
            <tr>
                <th>Agency name</th>
                <th>Owner / admin</th>
                <th class="num">Staff</th>
                <th class="num">Bookings</th>
                <th>Wallet</th>
                <th>Deposits</th>
                <th>Status</th>
                <th>Created</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($agencyRows as $row)
            <tr>
                <td data-label="Agency">
                    <strong>{{ $row['name'] }}</strong>
                    @if (! empty($row['agency_code']))
                        <div class="jp-cell-sub">{{ \App\Support\Identity\IdentityDisplay::labelAgencyCode() }}: {{ $row['agency_code'] }}</div>
                    @endif
                </td>
                <td data-label="Owner">
                    {{ $row['owner_name'] }}
                    @if ($row['owner_email'] !== '—')<div class="jp-cell-sub">{{ $row['owner_email'] }}</div>@endif
                </td>
                <td data-label="Staff" class="num">{{ number_format($row['staff_count']) }}</td>
                <td data-label="Bookings" class="num">{{ number_format($row['bookings_count']) }}</td>
                <td data-label="Wallet">{{ $row['wallet_label'] }}</td>
                <td data-label="Deposits">{{ $row['deposit_status'] }}</td>
                <td data-label="Status"><x-themes.admin.jetpakistan.components.status-badge :label="$row['status']" /></td>
                <td data-label="Created">{{ $row['created_at'] }}</td>
                <td data-label="Actions">
                    <a href="{{ client_route('admin.agencies.show', $row['id']) }}" class="jp-btn jp-btn--sm jp-btn--ghost">View</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="9"><x-themes.admin.jetpakistan.components.empty-state title="No agencies found" /></td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="jp-pagination">{{ $agencies->appends(request()->query())->links() }}</div>
</div>
@endsection
