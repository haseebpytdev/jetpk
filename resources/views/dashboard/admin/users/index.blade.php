@extends(client_layout('dashboard', 'admin'))

@section('title', 'Users & Access')

@push('styles')
<style>
    @media (max-width: 767.98px) {
        .admin-users-table .table thead th:nth-child(4),
        .admin-users-table .table thead th:nth-child(5),
        .admin-users-table .table thead th:nth-child(6),
        .admin-users-table .table tbody td:nth-child(4),
        .admin-users-table .table tbody td:nth-child(5),
        .admin-users-table .table tbody td:nth-child(6) {
            display: none;
        }
    }
</style>
@endpush

@section('page-header')
    <div class="jp-between ota-admin-page-header">
        <div class="col">
            <div class="page-pretitle">Network</div>
            <h1 class="jp-page-title">Users &amp; Access</h1>
            <div class="text-secondary mt-1">Manage user accounts, roles, and access across the platform.</div>
            <div class="text-secondary mt-2 small">
                <strong class="text-body">Platform Admin</strong> — full platform-wide access.
                <span class="mx-1 d-none d-md-inline">·</span>
                <span class="d-block d-md-inline mt-1 mt-md-0">
                    <strong class="text-body">Agency Owner</strong> and <strong class="text-body">Agency Staff</strong> — agency-scoped portal users linked to an agency company record.
                </span>
            </div>
        </div>
        <div class="col-auto ms-auto">
            <a href="{{ route('admin.users.create') }}" class="jp-btn jp-btn--primary">Create user</a>
        </div>
    </div>
@endsection

@section('content')
    @php
        use App\Support\Access\AccountTypeLabels;
        use App\Support\Identity\ActorIdentifier;
        $activeType = $filters['account_type'] ?? '';
        $typeTabs = [
            '' => 'All users',
            'platform_admin' => 'Platform admins',
            'staff' => 'Platform staff',
            'agent' => 'Agency owners',
            'agent_staff' => 'Agency staff',
            'customer' => 'Customers',
            'agency_admin' => 'Legacy users',
        ];
    @endphp

    <div class="row g-3 mb-3 ota-admin-kpi-card">
        <div class="col-6 col-md-2"><div class="card ota-kpi-card"><div class="jp-card__body"><small>Total</small><div class="h4 mb-0">{{ $kpis['total'] }}</div></div></div></div>
        <div class="col-6 col-md-2"><div class="card ota-kpi-card"><div class="jp-card__body"><small>Platform staff</small><div class="h4 mb-0">{{ $kpis['staff'] }}</div></div></div></div>
        <div class="col-6 col-md-2"><div class="card ota-kpi-card"><div class="jp-card__body"><small>Agency owners</small><div class="h4 mb-0">{{ $kpis['agency_owners'] }}</div></div></div></div>
        <div class="col-6 col-md-2"><div class="card ota-kpi-card"><div class="jp-card__body"><small>Agency staff</small><div class="h4 mb-0">{{ $kpis['agency_staff'] }}</div></div></div></div>
        <div class="col-6 col-md-2"><div class="card ota-kpi-card"><div class="jp-card__body"><small>Customers</small><div class="h4 mb-0">{{ $kpis['customers'] }}</div></div></div></div>
        <div class="col-6 col-md-2"><div class="card ota-kpi-card"><div class="jp-card__body"><small>Suspended / invited</small><div class="h4 mb-0">{{ $kpis['suspended_or_invited'] }}</div></div></div></div>
    </div>

    <div class="users-type-tabs ota-admin-queue-tabs">
        @foreach ($typeTabs as $typeKey => $typeLabel)
            @php
                $tabQuery = array_filter([
                    'account_type' => $typeKey !== '' ? $typeKey : null,
                    'search' => $filters['search'] ?? null,
                    'status' => $filters['status'] ?? null,
                    'agency_id' => $filters['agency_id'] ?? null,
                ], fn ($v) => $v !== null && $v !== '');
            @endphp
            <a href="{{ route('admin.users.index', $tabQuery) }}"
               class="users-type-tab ota-admin-queue-tab {{ $activeType === $typeKey ? 'is-active' : '' }}">
                {{ $typeLabel }}
            </a>
        @endforeach
    </div>

    <form class="card card-body mb-3 ota-admin-filter-bar" method="get" action="{{ route('admin.users.index') }}">
        @if ($activeType !== '')
            <input type="hidden" name="account_type" value="{{ $activeType }}">
        @endif
        <div class="jp-form-grid jp-form-grid--filter ota-r-form-grid">
            <div class="col-12 col-md-4">
                <label class="jp-label" for="users-search">Search</label>
                <input id="users-search" class="jp-control" name="search" placeholder="Search name, email, or username" value="{{ $filters['search'] ?? '' }}">
            </div>
            <div class="col-12 col-md-3">
                <label class="jp-label" for="users-status">Status</label>
                <select id="users-status" class="jp-control" name="status">
                    <option value="">All statuses</option>
                    @foreach(['active','invited','suspended','inactive'] as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            @if ($agencyOptions->isNotEmpty())
                <div class="col-12 col-md-3">
                    <label class="jp-label" for="users-agency">Agency</label>
                    <select id="users-agency" class="jp-control" name="agency_id">
                        <option value="">All agencies</option>
                        @foreach ($agencyOptions as $agencyOption)
                            <option value="{{ $agencyOption->id }}" @selected((string) ($filters['agency_id'] ?? '') === (string) $agencyOption->id)>{{ $agencyOption->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-2">
                <div class="ota-r-action-bar">
                    <button class="jp-btn jp-btn--primary flex-fill" type="submit">Apply</button>
                    <a href="{{ route('admin.users.index', $activeType !== '' ? ['account_type' => $activeType] : []) }}" class="jp-btn jp-btn--ghost flex-fill">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card admin-users-table ota-admin-table" data-testid="admin-users-access-index">
        <div class="card-header border-0 pb-0">
            <h3 class="jp-card__title mb-0">Accounts</h3>
            <div class="jp-card__subtitle text-secondary">Click a row to open account details and access controls.</div>
        </div>
        <div class="table-responsive ota-r-table-wrap">
            <table class="table card-jp-table table-hover ota-r-text-safe mb-0 ota-admin-table">
                <thead><tr><th>Name</th><th>{{ \App\Support\Identity\IdentityDisplay::labelUserActorId() }}</th><th>Email</th><th>{{ \App\Support\Identity\IdentityDisplay::labelAccessType() }}</th><th>Agency</th><th>Access mode</th><th>Status</th><th>Last login</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    @php
                        $agencyBadge = AccountTypeLabels::agencyBadge($user);
                    @endphp
                    <tr class="ota-admin-click-row"
                        data-href="{{ route('admin.users.show', $user) }}"
                        tabindex="0"
                        role="link"
                        aria-label="Open account for {{ $user->name }}">
                        <td>{{ $user->name }}</td>
                        <td class="small text-secondary">{{ ActorIdentifier::forUser($user) }}</td>
                        <td>
                            <div>{{ $user->email }}</div>
                            @if ($user->username)
                                <div class="text-secondary small" data-testid="user-index-username">{{ $user->username }}</div>
                            @endif
                        </td>
                        <td>{{ $accountTypeLabels[$user->account_type?->value ?? ''] ?? 'Unknown' }}</td>
                        <td>
                            @if ($agencyBadge !== '')
                                <span class="badge bg-azure-lt">{{ $agencyBadge }}</span>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td>{{ AccountTypeLabels::accessModeLabel($user) }}</td>
                        <td><x-dashboard.status-badge :status="$user->status?->value ?? 'unknown'" /></td>
                        <td class="text-nowrap">{{ $user->last_login_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            @if ($activeType === 'platform_admin')
                                <div class="text-secondary">
                                    <i class="ti ti-shield-off mb-2 d-block fs-2 opacity-75" aria-hidden="true"></i>
                                    <div class="ota-empty-state-title mb-1">No platform admins found</div>
                                    <div class="ota-empty-state-help mx-auto">Platform admins are super admins with cross-agency access.</div>
                                </div>
                            @else
                                <span class="text-secondary">No users found.</span>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $users->appends(request()->query())->links() }}</div>
@endsection
