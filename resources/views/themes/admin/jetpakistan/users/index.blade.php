@extends(client_layout('dashboard', 'admin'))

@section('title', 'Users & Access')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Users &amp; Access</h1>
            <p>Manage user accounts, roles, and access across the platform.</p>
        </div>
        <a href="{{ client_route('admin.users.create') }}" class="jp-btn jp-btn--sm">Create user</a>
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

<div class="jp-kpis jp-kpis--compact">
    <div class="jp-kpi"><div class="jp-kpi__v">{{ $kpis['total'] }}</div><div class="jp-kpi__l">Total</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">{{ $kpis['staff'] }}</div><div class="jp-kpi__l">Platform staff</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">{{ $kpis['agency_owners'] }}</div><div class="jp-kpi__l">Agency owners</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">{{ $kpis['agency_staff'] }}</div><div class="jp-kpi__l">Agency staff</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">{{ $kpis['customers'] }}</div><div class="jp-kpi__l">Customers</div></div>
    <div class="jp-kpi t-amber"><div class="jp-kpi__v">{{ $kpis['suspended_or_invited'] }}</div><div class="jp-kpi__l">Suspended / invited</div></div>
</div>

<div class="jp-queue-tabs">
    @foreach ($typeTabs as $typeKey => $typeLabel)
        @php
            $tabQuery = array_filter([
                'account_type' => $typeKey !== '' ? $typeKey : null,
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null,
                'agency_id' => $filters['agency_id'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
        @endphp
        <a href="{{ client_route('admin.users.index', $tabQuery) }}" class="jp-queue-tab {{ $activeType === $typeKey ? 'is-active' : '' }}">{{ $typeLabel }}</a>
    @endforeach
</div>

<form class="jp-filterbar" method="get" action="{{ client_route('admin.users.index') }}">
    @if ($activeType !== '')
        <input type="hidden" name="account_type" value="{{ $activeType }}">
    @endif
    <div class="jp-filterbar__field">
        <label class="jp-label" for="users-search">Search</label>
        <input id="users-search" class="jp-input" name="search" placeholder="Search name, email, or username" value="{{ $filters['search'] ?? '' }}">
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="users-status">Status</label>
        <select id="users-status" class="jp-select" name="status">
            <option value="">All statuses</option>
            @foreach(['active','invited','suspended','inactive'] as $s)
                <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    @if ($agencyOptions->isNotEmpty())
        <div class="jp-filterbar__field">
            <label class="jp-label" for="users-agency">Agency</label>
            <select id="users-agency" class="jp-select" name="agency_id">
                <option value="">All agencies</option>
                @foreach ($agencyOptions as $agencyOption)
                    <option value="{{ $agencyOption->id }}" @selected((string) ($filters['agency_id'] ?? '') === (string) $agencyOption->id)>{{ $agencyOption->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="jp-filterbar__actions">
        <button class="jp-btn jp-btn--sm" type="submit">Apply</button>
        <a href="{{ client_route('admin.users.index', $activeType !== '' ? ['account_type' => $activeType] : []) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Reset</a>
    </div>
</form>

<div class="jp-dtable-wrap" data-testid="admin-users-access-index">
    <table class="jp-dtable">
        <thead>
            <tr>
                <th>Name</th>
                <th>{{ \App\Support\Identity\IdentityDisplay::labelUserActorId() }}</th>
                <th>Email</th>
                <th>{{ \App\Support\Identity\IdentityDisplay::labelAccessType() }}</th>
                <th>Agency</th>
                <th>Access mode</th>
                <th>Status</th>
                <th>Last login</th>
            </tr>
        </thead>
        <tbody>
        @forelse($users as $user)
            @php $agencyBadge = AccountTypeLabels::agencyBadge($user); @endphp
            <tr style="cursor: pointer;" onclick="window.location='{{ client_route('admin.users.show', $user) }}'">
                <td data-label="Name">{{ $user->name }}</td>
                <td data-label="Actor ID" class="jp-cell-sub">{{ ActorIdentifier::forUser($user) }}</td>
                <td data-label="Email">
                    {{ $user->email }}
                    @if ($user->username)<div class="jp-cell-sub">{{ $user->username }}</div>@endif
                </td>
                <td data-label="Access type">{{ $accountTypeLabels[$user->account_type?->value ?? ''] ?? 'Unknown' }}</td>
                <td data-label="Agency">
                    @if ($agencyBadge !== '')
                        <span class="jp-badge-pill jp-badge-pill--blue">{{ $agencyBadge }}</span>
                    @else
                        —
                    @endif
                </td>
                <td data-label="Access mode">{{ AccountTypeLabels::accessModeLabel($user) }}</td>
                <td data-label="Status"><x-themes.admin.jetpakistan.components.status-badge :label="$user->status?->value ?? 'unknown'" /></td>
                <td data-label="Last login">{{ $user->last_login_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
            </tr>
        @empty
            <tr><td colspan="8"><x-themes.admin.jetpakistan.components.empty-state title="No users found" /></td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="jp-pagination">{{ $users->appends(request()->query())->links() }}</div>
</div>
@endsection
