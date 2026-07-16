{{-- JP-PORTAL-3 TASK 7 · JetPK portal Agent Staff permission matrix
     Replaces partials.agent-staff-permission-matrix-controls on JetPK-resolved AGENT pages only.
     The legacy partial REMAINS on disk untouched and still serves dashboard/admin/users/show and
     themes/admin/jetpakistan/users/show.

     *** PERMISSION SEMANTICS UNCHANGED — READ BEFORE EDITING ***
     The brief asks for a CLEARER permission UI that is SEMANTICALLY IDENTICAL. Accordingly:
       • every permission KEY is emitted verbatim as permissions[] values — none renamed
       • every MODULE key from $groupedAgentPermissions is used as-is — none renamed
       • $selected = collect(old('permissions', $selectedPermissions ?? []))->all()  — identical
       • an unchecked box submits nothing, exactly as legacy: the controller's replace-semantics
         are unchanged
       • Allowed/Blocked is a LABEL for the checkbox state, not a second source of truth
     Clarity here comes from layout and typography only — never from changing what is submitted.

     Preserved verbatim:
       • the access-clarification block is included with the SAME three arguments
         (showAgentStaffOwnerLabelWarning, showOwnerAccountTypeNote=false, showApplyTemplateHint=true).
         It resolves to the JetPK portal staff-access-clarification component: the legacy partial
         carries ota-agent-* classes and a Bootstrap alert, so it could not be reused here.
       • current-agency-role block gated by ! empty($agencyRoleLabel), with the template-grants
         line gated by $canApplyTemplate && $templateSummary !== '' && ! $showAgentStaffOwnerLabelWarning
       • $canApplyTemplate = ($canApplyAgentStaffRoleTemplate ?? false) && $templateAction !== null
         (Owner role cannot apply a template — controller sets this; not re-derived here)
       • permissions form: method="post" + @method('PATCH') + @csrf -> $permissionsUpdateRoute
       • apply-template form: method="post" + @csrf (NO method spoof) -> $agentPermissionsApplyTemplateRoute,
         hidden confirm_template_apply=1, and the exact confirm() copy
       • role="switch" + aria-label on every toggle
       • data-testids: {matrix}-form, {matrix}-save, {matrix}-apply-template-form,
         {matrix}-apply-template, agent-staff-current-agency-role
--}}
@php
    $selected = collect(old('permissions', $selectedPermissions ?? []))->all();
    $permissionsAction = $permissionsUpdateRoute ?? '';
    $templateAction = $agentPermissionsApplyTemplateRoute ?? null;
    $canApplyTemplate = ($canApplyAgentStaffRoleTemplate ?? false) && $templateAction !== null;
    $templateSummary = $agentStaffRoleTemplateSummary ?? '';
    $matrixTestId = $matrixTestId ?? 'agent-staff-permission-matrix';
@endphp

@include('themes.frontend.jetpakistan.components.portal.staff-access-clarification', [
    'showAgentStaffOwnerLabelWarning' => $showAgentStaffOwnerLabelWarning ?? false,
    'showOwnerAccountTypeNote' => false,
    'showApplyTemplateHint' => true,
])

@if (! empty($agencyRoleLabel))
    <div class="jp-portal__role-info" data-testid="agent-staff-current-agency-role">
        <span class="jp-portal__role-info-label">Current agency role</span>
        <strong class="jp-portal__role-info-value">{{ $agencyRoleLabel }}</strong>
        @if ($canApplyTemplate && $templateSummary !== '' && ! ($showAgentStaffOwnerLabelWarning ?? false))
            <small class="jp-field__help">Template grants: {{ $templateSummary }}</small>
        @endif
    </div>
@endif

<form
    method="post"
    action="{{ $permissionsAction }}"
    class="jp-permission-form"
    data-testid="{{ $matrixTestId }}-form"
>
    @csrf
    @method('PATCH')

    <div class="jp-permission-matrix">
        @foreach ($groupedAgentPermissions as $module => $permissions)
            <section class="jp-permission-module">
                <h3 class="jp-permission-module__title">{{ $module }}</h3>
                @foreach ($permissions as $key => $label)
                    @php $enabled = in_array($key, $selected, true); @endphp
                    <label class="jp-permission-row">
                        <span class="jp-permission-row__label">
                            <strong>{{ $label }}</strong>
                            <small class="jp-permission-row__key">{{ str_replace('_', ' ', $key) }}</small>
                        </span>
                        <span class="jp-permission-row__control">
                            <span class="jp-permission-state {{ $enabled ? 'jp-permission-state--allowed' : 'jp-permission-state--blocked' }}">{{ $enabled ? 'Allowed' : 'Blocked' }}</span>
                            <span class="jp-switch">
                                <input
                                    class="jp-switch__input"
                                    type="checkbox"
                                    role="switch"
                                    name="permissions[]"
                                    value="{{ $key }}"
                                    @checked($enabled)
                                    aria-label="{{ $label }}"
                                >
                                <span class="jp-switch__track" aria-hidden="true"></span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </section>
        @endforeach
    </div>

    <div class="jp-form__actions">
        <button type="submit" class="jp-btn jp-btn--primary jp-btn--sm" data-testid="{{ $matrixTestId }}-save">Save Permissions</button>
    </div>
</form>

@if ($canApplyTemplate)
    <form
        method="post"
        action="{{ $templateAction }}"
        class="jp-template-form"
        data-testid="{{ $matrixTestId }}-apply-template-form"
        onsubmit="return confirm('Apply the permission template for the current agency role? This replaces the saved permission matrix.');"
    >
        @csrf
        <input type="hidden" name="confirm_template_apply" value="1">
        <p class="jp-field__help">Apply Template copies suggested permissions for the selected role. This does not change the Agency Role.</p>
        <button type="submit" class="jp-btn jp-btn--ghost jp-btn--sm" data-testid="{{ $matrixTestId }}-apply-template">Apply Template</button>
    </form>
@endif
