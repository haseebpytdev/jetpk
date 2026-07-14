@php
    use App\Support\Access\AccountTypeLabels;
    use App\Support\Identity\ActorIdentifier;
    $u = $userModel;
    $typeValue = $u->account_type?->value ?? 'unknown';
    $typeLabel = AccountTypeLabels::label($u->account_type);
    $statusValue = $u->status?->value ?? 'unknown';
@endphp
@extends(client_layout('dashboard', 'admin'))

@section('title', 'User access')

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-cell-sub"><a href="{{ client_route('admin.users.index') }}">Users &amp; Access</a></p>
            <h1>{{ $u->name }}</h1>
            <p>{{ $u->email }}</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <span class="jp-badge-pill jp-badge-pill--blue">{{ $typeLabel }}</span>
            <x-themes.admin.jetpakistan.components.status-badge :label="$statusValue" />
        </div>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

<div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px;">
    <a class="jp-btn jp-btn--sm" href="{{ client_route('admin.users.edit', $u) }}">Edit access</a>
    <form method="post" action="{{ client_route('admin.users.send-invite', $u) }}">@csrf<button class="jp-btn jp-btn--sm jp-btn--outline" type="submit">Send invitation</button></form>
    <form method="post" action="{{ client_route('admin.users.reset-password-link', $u) }}">@csrf<button class="jp-btn jp-btn--sm jp-btn--outline" type="submit">Send password reset</button></form>
    @if ($isCustomerAccount)
        <a class="jp-btn jp-btn--sm jp-btn--ghost" href="{{ client_route('admin.customers.show', $u) }}">Open customer profile</a>
    @endif
    @if ($statusValue !== 'suspended')
        <form method="post" action="{{ client_route('admin.users.suspend', $u) }}">@csrf @method('PATCH')<button class="jp-btn jp-btn--sm jp-btn--ghost" type="submit">Suspend account</button></form>
    @else
        <form method="post" action="{{ client_route('admin.users.activate', $u) }}">@csrf @method('PATCH')<button class="jp-btn jp-btn--sm" type="submit">Activate account</button></form>
    @endif
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
    <div class="jp-card">
        <div class="jp-card__head"><h2 class="jp-card__title">Access summary</h2></div>
        <dl style="margin: 0; font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed var(--line-soft);"><dt class="jp-cell-sub">Email</dt><dd>{{ $u->email }}</dd></div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed var(--line-soft);"><dt class="jp-cell-sub">Username</dt><dd>{{ display_unknown($u->username) }}</dd></div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed var(--line-soft);"><dt class="jp-cell-sub">Account type</dt><dd>{{ $typeLabel }}</dd></div>
            @if ($agencyRoleLabel)
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed var(--line-soft);"><dt class="jp-cell-sub">Agency role</dt><dd>{{ $agencyRoleLabel }}</dd></div>
            @endif
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed var(--line-soft);"><dt class="jp-cell-sub">Agency scope</dt><dd>{{ $agencyScope }}</dd></div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0;"><dt class="jp-cell-sub">Last login</dt><dd>{{ $u->last_login_at?->format('M j, Y g:i A') ?? 'Never' }}</dd></div>
        </dl>
        @if ($agencyRoleLabel && $agencyRoleAssignmentAgencyId && ($agencyRoleOptions ?? []) !== [])
            <div class="jp-module-compat" style="margin-top: 12px;">
                @include('partials.agency-role-assignment-form', [
                    'action' => client_route('admin.agencies.users.agency-role.update', ['agency' => $agencyRoleAssignmentAgencyId, 'user' => $u]),
                    'currentRoleValue' => $agencyRoleCurrentValue ?? '',
                    'roleOptions' => $agencyRoleOptions,
                    'formTestId' => 'admin-user-agency-role-form',
                    'selectTestId' => 'admin-user-agency-role-select',
                ])
            </div>
        @endif
    </div>

    <div class="jp-card jp-module-compat" id="agent-staff-permissions">
        @if ($isCustomerAccount)
            <div class="jp-card__head"><h2 class="jp-card__title">Portal access</h2></div>
            <p class="jp-cell-sub">Customer portal only — manage bookings in the Customers module.</p>
        @else
            <div class="jp-card__head"><h2 class="jp-card__title">
                @if ($isAgentStaffPermissions) Agent portal permissions
                @elseif ($isStaffPermissions ?? false) Staff portal permissions
                @else Effective access summary
                @endif
            </h2></div>
            @if ($isAgentStaffPermissions && ($isEditableAgentStaffPermissions ?? false))
                @include('partials.agent-staff-permission-matrix-controls', [
                    'permissionsUpdateRoute' => $agentPermissionsUpdateRoute,
                    'agentPermissionsApplyTemplateRoute' => $agentPermissionsApplyTemplateRoute,
                    'canApplyAgentStaffRoleTemplate' => $canApplyAgentStaffRoleTemplate ?? false,
                    'agentStaffRoleTemplateSummary' => $agentStaffRoleTemplateSummary ?? '',
                    'agencyRoleLabel' => $agencyRoleLabel ?? null,
                    'showAgentStaffOwnerLabelWarning' => $showAgentStaffOwnerLabelWarning ?? false,
                    'groupedAgentPermissions' => $groupedAgentPermissions,
                    'selectedPermissions' => $selectedAgentPermissions,
                    'matrixTestId' => 'admin-user-agent-permissions',
                ])
            @else
                <p class="jp-cell-sub">Permission details — edit on the access editor page.</p>
            @endif
        @endif
    </div>
</div>

<div class="jp-card" style="margin-top: 16px;">
    <div class="jp-card__head"><h2 class="jp-card__title">Security</h2></div>
    <div class="jp-kpis jp-kpis--compact">
        <div class="jp-kpi"><div class="jp-kpi__l">Email verified</div><div class="jp-kpi__v" style="font-size: 1rem;">{{ $u->email_verified_at?->format('M j, Y') ?? 'Not verified' }}</div></div>
        <div class="jp-kpi"><div class="jp-kpi__l">Social linked</div><div class="jp-kpi__v" style="font-size: 1rem;">{{ count($linkedProviders) ? implode(', ', $linkedProviders) : 'None' }}</div></div>
    </div>
</div>
@endsection
