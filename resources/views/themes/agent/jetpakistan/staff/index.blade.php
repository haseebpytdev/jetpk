{{-- JP-PORTAL-3 TASK 7 · Agent staff management — index (JetPK theme)
     Resolved by client_view('staff.index', 'agent'); dashboard.agent.staff.index remains the
     fallback for standalone mode is off\.
     Route gate: agent.permission:StaffManage + platform.module:agent_staff.

     *** AGENT STAFF ADMINISTERING STAFF ***
     Legacy already permits any user holding StaffManage to reach this page — that includes an
     Agent Staff member who has been granted StaffManage. That is the EXISTING rule and is left
     exactly as-is. Per-row edit is additionally gated by AgentStaffPolicy::update(). Do not
     tighten or loosen either check here.

     PRESERVED EXACTLY:
       • controller vars: $staffMembers, $staffAgencyRoles, $staffAgencyRoleValues,
         $agencyRoleOptions, $permissionLabels
       • flash chain, verbatim: staff-created / staff-updated / staff-disabled /
         agency-role-updated — note the last one's copy explicitly states portal permissions were
         NOT changed. Role and permissions are separate systems; that wording must survive.
       • "Add staff user" gated by Route::has('agent.staff.create') && hasAgentPermission(StaffManage)
       • columns: Staff user, Agency role, Status, Actions
       • Agency role cell branches on app(AgentStaffPolicy::class)->update(auth()->user(), $member):
           - allowed  -> role assignment form (JetPK component; testids preserved:
                         agent-staff-role-form-{id}, agent-staff-role-select-{id})
           - denied   -> plain text $staffAgencyRoles[$member->id] ?? '—'
         The policy call is reproduced exactly, including app() resolution.
       • status badge: $member->status?->value === 'active' ? success : warning,
         label ucfirst($member->status?->value ?? 'unknown')
       • Edit link gated by hasAgentPermission(StaffManage)
       • empty state copy, verbatim
       • data-testids: agent-staff-create-link, agent-staff-index, agent-staff-row-{id}
     No permission value or module key is renamed. No internal role is exposed.
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Staff users')

@section('account_title', 'Staff users')
@section('account_subtitle', 'Manage portal users for your agency. Staff use the agent portal with permissions you assign.')

@section('account_actions')
    @if (Route::has('agent.staff.create') && (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::StaffManage) ?? false))
        <a href="{{ route('agent.staff.create') }}" class="jp-btn jp-btn--primary" data-testid="agent-staff-create-link">Add staff user</a>
    @endif
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Staff users'],
    ]" />

    @if (session('status') === 'staff-created')
        <x-jp.alert variant="success">Staff user created.</x-jp.alert>
    @elseif (session('status') === 'staff-updated')
        <x-jp.alert variant="success">Staff user updated.</x-jp.alert>
    @elseif (session('status') === 'staff-disabled')
        <x-jp.alert variant="success">Staff user disabled.</x-jp.alert>
    @elseif (session('status') === 'agency-role-updated')
        <x-jp.alert variant="success">Agency role updated. Portal permissions were not changed.</x-jp.alert>
    @endif

    <x-jp.card class="jp-portal__panel jp-portal__panel--flush" data-testid="agent-staff-index">
        @if ($staffMembers->isEmpty())
            <div class="jp-empty">
                <span class="jp-empty__icon" aria-hidden="true"><x-jp.icon name="user-shield" /></span>
                <p class="jp-empty__title">No staff users yet</p>
                <p class="jp-empty__help">Add a staff member to share portal access with your team.</p>
            </div>
        @else
            <div class="jp-table-wrap jp-table-wrap--desktop">
                <table class="jp-table jp-table--staff">
                    <thead>
                        <tr>
                            <th scope="col">Staff user</th>
                            <th scope="col">Agency role</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="jp-table__cell--end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staffMembers as $member)
                            <tr data-testid="agent-staff-row-{{ $member->id }}">
                                <td data-label="Staff user">
                                    <span class="jp-portal__person">
                                        <span class="jp-portal__person-name">{{ $member->name }}</span>
                                        <small class="jp-portal__person-meta">{{ $member->email }}</small>
                                    </span>
                                </td>
                                <td data-label="Agency role">
                                    @if (app(\App\Policies\AgentStaffPolicy::class)->update(auth()->user(), $member))
                                        @include('themes.frontend.jetpakistan.components.portal.agency-role-form', [
                                            'action' => route('agent.staff.agency-role.update', $member),
                                            'currentRoleValue' => $staffAgencyRoleValues[$member->id] ?? '',
                                            'roleOptions' => $agencyRoleOptions ?? [],
                                            'formTestId' => 'agent-staff-role-form-'.$member->id,
                                            'selectTestId' => 'agent-staff-role-select-'.$member->id,
                                        ])
                                    @else
                                        {{ $staffAgencyRoles[$member->id] ?? '—' }}
                                    @endif
                                </td>
                                <td data-label="Status">
                                    <span class="jp-badge {{ $member->status?->value === 'active' ? 'jp-badge--success' : 'jp-badge--warning' }}">
                                        {{ ucfirst($member->status?->value ?? 'unknown') }}
                                    </span>
                                </td>
                                <td data-label="Actions" class="jp-table__cell--end">
                                    @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::StaffManage))
                                        <a href="{{ route('agent.staff.edit', $member) }}" class="jp-btn jp-btn--ghost jp-btn--sm">Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="jp-portal__list jp-portal__list--mobile">
                @foreach ($staffMembers as $member)
                    <article class="jp-portal__list-card" data-testid="agent-staff-mobile-{{ $member->id }}">
                        <div class="jp-portal__list-card-head">
                            <div>
                                <h3 class="jp-portal__list-card-name">{{ $member->name }}</h3>
                                <p class="jp-portal__list-card-meta">{{ $member->email }}</p>
                            </div>
                            <span class="jp-badge {{ $member->status?->value === 'active' ? 'jp-badge--success' : 'jp-badge--warning' }}">
                                {{ ucfirst($member->status?->value ?? 'unknown') }}
                            </span>
                        </div>
                        <div class="jp-portal__list-card-meta">
                            @if (app(\App\Policies\AgentStaffPolicy::class)->update(auth()->user(), $member))
                                @include('themes.frontend.jetpakistan.components.portal.agency-role-form', [
                                    'action' => route('agent.staff.agency-role.update', $member),
                                    'currentRoleValue' => $staffAgencyRoleValues[$member->id] ?? '',
                                    'roleOptions' => $agencyRoleOptions ?? [],
                                    'formTestId' => 'agent-staff-role-form-mobile-'.$member->id,
                                    'selectTestId' => 'agent-staff-role-select-mobile-'.$member->id,
                                ])
                            @else
                                <span>Agency role: {{ $staffAgencyRoles[$member->id] ?? '—' }}</span>
                            @endif
                        </div>
                        @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::StaffManage))
                            <div class="jp-portal__list-card-actions">
                                <a href="{{ route('agent.staff.edit', $member) }}" class="jp-btn jp-btn--primary jp-btn--sm">Edit</a>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </x-jp.card>
@endsection
