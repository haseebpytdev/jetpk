@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Edit staff user')

@section('account_title', 'Edit staff user')
@section('account_subtitle')
    Update access and permissions for {{ $staff->name }}.
@endsection

@section('account_actions')
    <a href="{{ route('agent.staff.index') }}" class="ota-account-btn ota-account-btn--secondary">Back to staff</a>
@endsection

@section('account_content')
    @if (session('status') === 'staff-permissions-updated')
        <div class="alert alert-success">Permissions saved. Agency role was not changed.</div>
    @elseif (session('status') === 'staff-permissions-template-applied')
        <div class="alert alert-success">Role permission template applied. Agency role was not changed.</div>
    @endif

    <div class="ota-account-card ota-account-form-card" data-testid="agent-staff-edit-form-card">
        <div class="ota-account-card__body">
            <form method="post" action="{{ route('agent.staff.update', $staff) }}" data-testid="agent-staff-edit-form">
                @csrf
                @method('PATCH')
                @include('dashboard.agent.staff._form', [
                    'staff' => $staff,
                    'selectedPermissions' => $selectedPermissions ?? [],
                    'showPermissionFieldset' => false,
                ])
                <div class="ota-agent-form-actions mt-4">
                    <button type="submit" class="ota-account-btn ota-account-btn--primary">Save profile</button>
                </div>
            </form>

            <div class="ota-agent-staff-permission-panel mt-4 pt-4 border-top" data-testid="agent-staff-permissions-section">
                <div class="ota-agent-staff-permission-panel__head">
                    <h2 class="h4 mb-1">Permission matrix</h2>
                    <p class="text-secondary small mb-0">Use toggles for portal access. Agency roles stay separate from permissions.</p>
                </div>
                @include('partials.agent-staff-permission-matrix-controls', [
                    'permissionsUpdateRoute' => $permissionsUpdateRoute,
                    'agentPermissionsApplyTemplateRoute' => $agentPermissionsApplyTemplateRoute,
                    'canApplyAgentStaffRoleTemplate' => $canApplyAgentStaffRoleTemplate ?? false,
                    'agentStaffRoleTemplateSummary' => $agentStaffRoleTemplateSummary ?? '',
                    'agencyRoleLabel' => $agencyRoleLabel ?? null,
                    'showAgentStaffOwnerLabelWarning' => $showAgentStaffOwnerLabelWarning ?? false,
                    'groupedAgentPermissions' => $groupedAgentPermissions ?? [],
                    'selectedPermissions' => $selectedPermissions ?? [],
                    'matrixTestId' => 'agent-staff-edit-permissions',
                ])
            </div>

            @if (auth()->user()?->isAgentAdmin())
                <form method="post" action="{{ route('agent.staff.destroy', $staff) }}" class="ota-agent-staff-disable-form mt-3" data-testid="agent-staff-disable-form" onsubmit="return confirm('Disable this staff user? They will no longer be able to sign in.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ota-account-btn ota-account-btn--secondary">Disable staff user</button>
                </form>
            @endif
        </div>
    </div>
@endsection
