{{-- JP-PORTAL-3 TASK 7 · Agent staff management — edit (JetPK theme)
     Resolved by client_view('staff.edit', 'agent'); dashboard.agent.staff.edit remains the
     fallback for standalone mode is off\.
     Route gate: agent.permission:StaffManage + platform.module:agent_staff.

     *** THREE INDEPENDENT FORMS — DO NOT MERGE ***
     Legacy posts profile, permissions and the role template to THREE different routes. Merging
     them would change what each endpoint receives. Reproduced as three separate forms:
       1. profile     -> agent.staff.update            (PATCH)  — $showPermissionFieldset = false
       2. permissions -> agent.staff.permissions.update (PATCH) — via the matrix component
       3. template    -> agent.staff.permissions.apply-template (POST) — via the matrix component
     Note form 1 passes showPermissionFieldset=false precisely BECAUSE the matrix owns permissions
     on this page. Turning it on would submit permissions[] to the profile route too.

     PRESERVED EXACTLY:
       • controller vars: $staff, $permissionLabels, $selectedPermissions,
         $groupedAgentPermissions, $permissionsUpdateRoute, $agentPermissionsApplyTemplateRoute,
         $canApplyAgentStaffRoleTemplate, $agentStaffRoleTemplateSummary, $agencyRoleLabel,
         $showAgentStaffOwnerLabelWarning
       • account_subtitle is a SECTION BODY (dynamic): "Update access and permissions for {name}."
       • flash: staff-permissions-updated / staff-permissions-template-applied — both explicitly
         state the agency role was NOT changed. That separation must survive.
       • disable form gated by auth()->user()?->isAgentAdmin() — AGENT ADMIN ONLY, not StaffManage.
         method="post" + @method('DELETE') -> agent.staff.destroy, with the exact confirm() copy.
       • data-testids: agent-staff-edit-form-card, agent-staff-edit-form,
         agent-staff-permissions-section, agent-staff-disable-form,
         and matrixTestId 'agent-staff-edit-permissions'
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Edit staff user')

@section('account_title', 'Edit staff user')

@section('account_subtitle')
    Update access and permissions for {{ $staff->name }}.
@endsection

@section('account_actions')
    <a href="{{ route('agent.staff.index') }}" class="jp-btn jp-btn--ghost">Back to staff</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Staff users', 'href' => route('agent.staff.index')],
        ['label' => $staff->name],
    ]" />

    @if (session('status') === 'staff-permissions-updated')
        <x-jp.alert variant="success">Permissions saved. Agency role was not changed.</x-jp.alert>
    @elseif (session('status') === 'staff-permissions-template-applied')
        <x-jp.alert variant="success">Role permission template applied. Agency role was not changed.</x-jp.alert>
    @endif

    {{-- FORM 1 — profile only. Permission fieldset deliberately suppressed. --}}
    <x-jp.card class="jp-portal__panel" data-testid="agent-staff-edit-form-card">
        <form method="post" action="{{ route('agent.staff.update', $staff) }}" data-testid="agent-staff-edit-form" class="jp-form">
            @csrf
            @method('PATCH')
            @include('themes.frontend.jetpakistan.components.portal.staff-form', [
                'staff' => $staff,
                'permissionLabels' => $permissionLabels,
                'selectedPermissions' => $selectedPermissions ?? [],
                'showPermissionFieldset' => false,
            ])
            <div class="jp-form__actions">
                <button type="submit" class="jp-btn jp-btn--primary">Save profile</button>
            </div>
        </form>
    </x-jp.card>

    {{-- FORMS 2 & 3 — permissions + role template, posted to their own routes. --}}
    <x-jp.card class="jp-portal__panel" data-testid="agent-staff-permissions-section">
        <div class="jp-portal__panel-head">
            <div>
                <h2 class="jp-portal__panel-title">Permission matrix</h2>
                <p class="jp-portal__panel-lead">Use toggles for portal access. Agency roles stay separate from permissions.</p>
            </div>
        </div>

        @include('themes.frontend.jetpakistan.components.portal.staff-permission-matrix', [
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
    </x-jp.card>

    @if (auth()->user()?->isAgentAdmin())
        <x-jp.card class="jp-portal__panel">
            <form method="post" action="{{ route('agent.staff.destroy', $staff) }}" data-testid="agent-staff-disable-form" onsubmit="return confirm('Disable this staff user? They will no longer be able to sign in.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="jp-btn jp-btn--danger">Disable staff user</button>
            </form>
        </x-jp.card>
    @endif
@endsection
