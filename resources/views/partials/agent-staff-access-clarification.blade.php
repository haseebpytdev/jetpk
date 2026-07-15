@php
    $showOwnerLabelWarning = $showAgentStaffOwnerLabelWarning ?? false;
    $showOwnerAccountNote = $showOwnerAccountTypeNote ?? false;
    $showApplyTemplateHint = $showApplyTemplateHint ?? true;
@endphp

<div class="ota-agent-staff-access-clarification mb-3" data-testid="agent-staff-access-clarification">
    <div class="ota-agent-staff-access-clarification__icon" aria-hidden="true"><i class="ti ti-info-circle"></i></div>
    <ul class="small text-secondary mb-0 ps-0">
        <li><strong>Agency Role</strong> is a business label, not automatic access.</li>
        <li><strong>Permission Matrix</strong> controls actual portal access.</li>
        @if ($showApplyTemplateHint)
            <li><strong>Apply Template</strong> copies suggested permissions.</li>
        @endif
        @if ($showOwnerAccountNote)
            <li><strong>Owner access</strong> is based on account type, not staff role label.</li>
        @endif
    </ul>
</div>

@if ($showOwnerLabelWarning)
    <div class="alert alert-warning mb-3" data-testid="agent-staff-owner-label-warning">
        This staff member is labelled Owner, but they are still an Agency Staff account. Owner-level access requires an Agency Owner account type.
    </div>
@endif
