{{-- JP-PORTAL-3 TASK 7 · JetPK portal agency-role assignment form
     Replaces partials.agency-role-assignment-form on JetPK-resolved AGENT pages only.
     The legacy partial REMAINS on disk untouched and still serves
     dashboard/admin/agencies/show, dashboard/admin/users/show and
     themes/admin/jetpakistan/users/show. Admin is out of scope for this phase.

     *** PERMISSION SEMANTICS UNCHANGED ***
     Agency ROLE and portal PERMISSIONS are deliberately separate systems. Selecting a role here
     does NOT apply permissions — the suggested-permissions text is advisory only and says so.
     No role value, option key or permission key is renamed.

     Preserved verbatim from partials.agency-role-assignment-form:
       • $roleOptions default AgencyRole::options(); $formTestId / $selectTestId defaults
       • $summaries built from AgencyRole::cases() filtered by array_key_exists in $roleOptions,
         via AgencyRolePermissionMatrix::suggestedPermissionSummary($roleCase)
       • $initialSummary = $summaries[$currentRoleValue] ?? ''
       • form: method="post" + @method('PATCH') + @csrf, action = $action
       • field name: agency_role, required, old('agency_role', $currentRoleValue)
       • the onchange summary-swap behaviour and data-agency-role-summaries JSON payload
       • the exact advisory copy, including "(not applied automatically)."
       • data-testids: $formTestId, $selectTestId, agency-role-suggested-hint
--}}
@php
    use App\Enums\AgencyRole;
    use App\Support\Agencies\AgencyRolePermissionMatrix;

    $formAction = $action ?? '';
    $currentRoleValue = $currentRoleValue ?? '';
    $roleOptions = $roleOptions ?? AgencyRole::options();
    $formTestId = $formTestId ?? 'agency-role-assignment-form';
    $selectTestId = $selectTestId ?? 'agency-role-select';
    $summaries = [];
    foreach (AgencyRole::cases() as $roleCase) {
        if (! array_key_exists($roleCase->value, $roleOptions)) {
            continue;
        }
        $summaries[$roleCase->value] = AgencyRolePermissionMatrix::suggestedPermissionSummary($roleCase);
    }
    $initialSummary = $summaries[$currentRoleValue] ?? '';
@endphp
<form
    method="post"
    action="{{ $formAction }}"
    class="jp-role-form"
    data-testid="{{ $formTestId }}"
>
    @csrf
    @method('PATCH')
    <select
        name="agency_role"
        class="jp-select jp-select--sm jp-role-form__select"
        required
        data-testid="{{ $selectTestId }}"
        data-agency-role-summaries='@json($summaries)'
        onchange="this.form.querySelector('[data-agency-role-suggested]')?.replaceChildren(document.createTextNode(this.dataset.agencyRoleSummaries ? (JSON.parse(this.dataset.agencyRoleSummaries)[this.value] ? 'Suggested permissions for this role: ' + JSON.parse(this.dataset.agencyRoleSummaries)[this.value] + ' (not applied automatically).' : '') : ''))"
    >
        @foreach ($roleOptions as $value => $label)
            <option value="{{ $value }}" @selected(old('agency_role', $currentRoleValue) === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit" class="jp-btn jp-btn--ghost jp-btn--sm">Save role</button>
    <details class="jp-role-form__hint">
        <summary>Suggested permissions</summary>
        <span class="jp-field__help" data-agency-role-suggested data-testid="agency-role-suggested-hint">
            @if ($initialSummary !== '')
                Suggested permissions for this role: {{ $initialSummary }} (not applied automatically).
            @endif
        </span>
    </details>
</form>
