@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Staff users')

@section('account_title', 'Staff users')
@section('account_subtitle', 'Manage portal users for your agency. Staff use the agent portal with permissions you assign.')

@section('account_actions')
    @if (Route::has('agent.staff.create') && (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::StaffManage) ?? false))
        <a href="{{ route('agent.staff.create') }}" class="ota-account-btn ota-account-btn--primary" data-testid="agent-staff-create-link">Add staff user</a>
    @endif
@endsection

@section('account_content')
    @if (session('status') === 'staff-created')
        <div class="alert alert-success">Staff user created.</div>
    @elseif (session('status') === 'staff-updated')
        <div class="alert alert-success">Staff user updated.</div>
    @elseif (session('status') === 'staff-disabled')
        <div class="alert alert-success">Staff user disabled.</div>
    @elseif (session('status') === 'agency-role-updated')
        <div class="alert alert-success">Agency role updated. Portal permissions were not changed.</div>
    @endif

    <div class="ota-account-card" data-testid="agent-staff-index">
        <div class="ota-account-card__body p-0">
            @if ($staffMembers->isEmpty())
                <div class="ota-account-empty ota-account-empty--compact">
                    <div class="ota-account-empty-icon" aria-hidden="true"><i class="ti ti-user-shield"></i></div>
                    <p class="ota-account-empty-title">No staff users yet</p>
                    <p class="ota-account-empty-help">Add a staff member to share portal access with your team.</p>
                </div>
            @else
                <div class="ota-account-table-wrap">
                    <table class="ota-account-table ota-agent-staff-table mb-0">
                        <thead>
                            <tr>
                                <th>Staff user</th>
                                <th>Agency role</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($staffMembers as $member)
                                <tr class="ota-agent-staff-row" data-testid="agent-staff-row-{{ $member->id }}">
                                    <td class="ota-agent-staff-role-cell">
                                        <div class="ota-agent-staff-person">
                                            <span>{{ $member->name }}</span>
                                            <small>{{ $member->email }}</small>
                                        </div>
                                    </td>
                                    <td class="ota-agent-staff-status-cell">
                                        @if (app(\App\Policies\AgentStaffPolicy::class)->update(auth()->user(), $member))
                                            @include('partials.agency-role-assignment-form', [
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
                                    <td>
                                        <span class="ota-account-badge ota-agent-status-badge {{ $member->status?->value === 'active' ? 'ota-account-badge--success' : 'ota-account-badge--warning' }}">
                                            {{ ucfirst($member->status?->value ?? 'unknown') }}
                                        </span>
                                    </td>
                                    <td class="text-end ota-agent-staff-action-cell">
                                        @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::StaffManage))
                                            <a href="{{ route('agent.staff.edit', $member) }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">Edit</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
