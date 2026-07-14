@extends(client_layout('dashboard', 'admin'))

@section('title', 'Agencies')

@section('page-header')
    <x-dashboard.section-header title="Agencies" subtitle="Agency and partner company records across the platform.">
        <x-slot:actions>
            <a href="{{ route('admin.agent-applications.index') }}" class="jp-btn jp-btn--outline btn-sm">Agency applications</a>
        </x-slot:actions>
    </x-dashboard.section-header>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4"><div class="jp-card"><div class="jp-card__body"><small>Total agencies</small><div class="h4 mb-0">{{ $kpis['total'] }}</div></div></div></div>
        <div class="col-6 col-md-4"><div class="jp-card"><div class="jp-card__body"><small>Active</small><div class="h4 mb-0">{{ $kpis['active'] }}</div></div></div></div>
        <div class="col-6 col-md-4"><div class="jp-card"><div class="jp-card__body"><small>Inactive</small><div class="h4 mb-0">{{ $kpis['inactive'] }}</div></div></div></div>
    </div>

    <form class="card card-body mb-3" method="get" action="{{ route('admin.agencies.index') }}">
        <div class="jp-form-grid jp-form-grid--filter ota-r-form-grid">
            <div class="col-12 col-md-5">
                <label class="jp-label" for="agencies-search">Search</label>
                <input id="agencies-search" class="jp-control" name="search" placeholder="Agency name, slug, owner name or email" value="{{ $filters['search'] ?? '' }}">
            </div>
            <div class="col-12 col-md-3">
                <label class="jp-label" for="agencies-status">Status</label>
                <select id="agencies-status" class="jp-control" name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <div class="ota-r-action-bar">
                    <button class="jp-btn jp-btn--primary flex-fill" type="submit">Apply filters</button>
                    <a href="{{ route('admin.agencies.index') }}" class="jp-btn jp-btn--ghost flex-fill">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card admin-agencies-table" data-testid="admin-agencies-index">
        <div class="card-header border-0 pb-0">
            <h3 class="jp-card__title mb-0">Agency list</h3>
            <div class="jp-card__subtitle text-secondary">Company entities — open an agency to view owner, staff, wallet, and bookings.</div>
        </div>
        <div class="table-responsive ota-r-table-wrap">
            <table class="table card-jp-table table-hover ota-r-text-safe mb-0">
                <thead>
                    <tr>
                        <th>Agency name</th>
                        <th>Owner / admin</th>
                        <th>Staff</th>
                        <th>Bookings</th>
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
                        <td class="fw-semibold">
                            <div>{{ $row['name'] }}</div>
                            @if (! empty($row['agency_code']))
                                <small class="text-secondary">{{ \App\Support\Identity\IdentityDisplay::labelAgencyCode() }}: {{ $row['agency_code'] }}</small>
                            @endif
                        </td>
                        <td>
                            <div>{{ $row['owner_name'] }}</div>
                            @if ($row['owner_email'] !== '—')
                                <small class="text-secondary">{{ $row['owner_email'] }}</small>
                            @endif
                        </td>
                        <td>{{ number_format($row['staff_count']) }}</td>
                        <td>{{ number_format($row['bookings_count']) }}</td>
                        <td class="text-nowrap">{{ $row['wallet_label'] }}</td>
                        <td>{{ $row['deposit_status'] }}</td>
                        <td><x-dashboard.status-badge :status="$row['status']" /></td>
                        <td class="text-nowrap">{{ $row['created_at'] }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.agencies.show', $row['id']) }}" class="jp-btn jp-btn--sm jp-btn--outline">View agency</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-secondary">No agencies found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $agencies->appends(request()->query())->links() }}</div>
@endsection
