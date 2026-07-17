{{-- JP-PORTAL-3 TASK 7 · Agent staff management — create (JetPK theme)
     Resolved by client_view('staff.create', 'agent'); dashboard.agent.staff.create remains the
     fallback for standalone mode is off\.
     Route gate: agent.permission:StaffManage + platform.module:agent_staff.

     PRESERVED EXACTLY:
       • controller vars: $permissionLabels, $defaultPermissions
       • $defaultPermissions is passed through as 'selectedPermissions' — the exact legacy wiring,
         so a new staff user starts with the same default checkboxes as before
       • form: method="post" action=route('agent.staff.store'), @csrf, NO @method spoof,
         NO enctype (there is no file input on this form)
       • permissions fieldset IS shown here (component default $showPermissionFieldset = true)
       • submit label "Create staff user"
       • data-testids: agent-staff-create-form-card, agent-staff-create-form
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Add staff user')

@section('account_title', 'Add staff user')
@section('account_subtitle', 'Create a staff login for your agency. They cannot access platform admin or create agents.')

@section('account_actions')
    <a href="{{ route('agent.staff.index') }}" class="jp-btn jp-btn--ghost">Back to staff</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Staff users', 'href' => route('agent.staff.index')],
        ['label' => 'Add staff user'],
    ]" />

    <x-jp.card class="jp-portal__panel" data-testid="agent-staff-create-form-card">
        <form method="post" action="{{ route('agent.staff.store') }}" data-testid="agent-staff-create-form" class="jp-form">
            @csrf
            @include('themes.frontend.jetpakistan.components.portal.staff-form', [
                'permissionLabels' => $permissionLabels,
                'selectedPermissions' => $defaultPermissions ?? [],
            ])
            <div class="jp-form__actions">
                <button type="submit" class="jp-btn jp-btn--primary">Create staff user</button>
            </div>
        </form>
    </x-jp.card>
@endsection
