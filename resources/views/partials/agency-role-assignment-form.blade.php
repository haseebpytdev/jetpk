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
    class="agency-role-assignment-form ota-agent-role-form"
    data-testid="{{ $formTestId }}"
>
    @csrf
    @method('PATCH')
    <select
        name="agency_role"
        class="form-select form-select-sm agency-role-assignment-form__select"
        required
        data-testid="{{ $selectTestId }}"
        data-agency-role-summaries='@json($summaries)'
        onchange="this.form.querySelector('[data-agency-role-suggested]')?.replaceChildren(document.createTextNode(this.dataset.agencyRoleSummaries ? (JSON.parse(this.dataset.agencyRoleSummaries)[this.value] ? 'Suggested permissions for this role: ' + JSON.parse(this.dataset.agencyRoleSummaries)[this.value] + ' (not applied automatically).' : '') : ''))"
    >
        @foreach ($roleOptions as $value => $label)
            <option value="{{ $value }}" @selected(old('agency_role', $currentRoleValue) === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">Save role</button>
    <details class="agency-role-assignment-form__hint ota-agent-role-form__hint">
        <summary>Suggested permissions</summary>
        <span class="text-secondary small" data-agency-role-suggested data-testid="agency-role-suggested-hint">
            @if ($initialSummary !== '')
                Suggested permissions for this role: {{ $initialSummary }} (not applied automatically).
            @endif
        </span>
    </details>
</form>
