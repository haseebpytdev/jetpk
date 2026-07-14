@php
    $selected = collect(old('permissions', $selectedPermissions ?? []))->all();
    $permissionsAction = $permissionsUpdateRoute ?? '';
    $templateAction = $agentPermissionsApplyTemplateRoute ?? null;
    $canApplyTemplate = ($canApplyAgentStaffRoleTemplate ?? false) && $templateAction !== null;
    $templateSummary = $agentStaffRoleTemplateSummary ?? '';
    $matrixTestId = $matrixTestId ?? 'agent-staff-permission-matrix';
@endphp

@include('partials.agent-staff-access-clarification', [
    'showAgentStaffOwnerLabelWarning' => $showAgentStaffOwnerLabelWarning ?? false,
    'showOwnerAccountTypeNote' => false,
    'showApplyTemplateHint' => true,
])

@if (! empty($agencyRoleLabel))
    <div class="ota-agent-staff-role-info" data-testid="agent-staff-current-agency-role">
        <span>Current agency role</span>
        <strong>{{ $agencyRoleLabel }}</strong>
        @if ($canApplyTemplate && $templateSummary !== '' && ! ($showAgentStaffOwnerLabelWarning ?? false))
            <small>Template grants: {{ $templateSummary }}</small>
        @endif
    </div>
@endif

<form
    method="post"
    action="{{ $permissionsAction }}"
    class="ota-agent-permission-form mb-3"
    data-testid="{{ $matrixTestId }}-form"
>
    @csrf
    @method('PATCH')
    <div class="ota-access-matrix-body">
        @foreach ($groupedAgentPermissions as $module => $permissions)
            <div class="ota-access-module">
                <div class="ota-access-module__title">{{ $module }}</div>
                @foreach ($permissions as $key => $label)
                    @php $enabled = in_array($key, $selected, true); @endphp
                    <label class="ota-access-toggle-row ota-access-permission-tile">
                        <span class="ota-access-toggle-row__label">
                            <strong>{{ $label }}</strong>
                            <small>{{ str_replace('_', ' ', $key) }}</small>
                        </span>
                        <span class="ota-access-toggle-row__control">
                            <span class="ota-access-state {{ $enabled ? 'ota-access-state--allowed' : 'ota-access-state--blocked' }}">{{ $enabled ? 'Allowed' : 'Blocked' }}</span>
                            <span class="ota-agent-permission-switch">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                    name="permissions[]"
                                    value="{{ $key }}"
                                    @checked($enabled)
                                    aria-label="{{ $label }}"
                                >
                                <span aria-hidden="true"></span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="ota-agent-permission-actions">
        <button type="submit" class="ota-account-btn ota-account-btn--primary ota-account-btn--sm" data-testid="{{ $matrixTestId }}-save">Save Permissions</button>
    </div>
</form>

@if ($canApplyTemplate)
    <form
        method="post"
        action="{{ $templateAction }}"
        class="ota-agent-template-form"
        data-testid="{{ $matrixTestId }}-apply-template-form"
        onsubmit="return confirm('Apply the permission template for the current agency role? This replaces the saved permission matrix.');"
    >
        @csrf
        <input type="hidden" name="confirm_template_apply" value="1">
        <p class="text-secondary small mb-0">Apply Template copies suggested permissions for the selected role. This does not change the Agency Role.</p>
        <button type="submit" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm" data-testid="{{ $matrixTestId }}-apply-template">Apply Template</button>
    </form>
@endif
