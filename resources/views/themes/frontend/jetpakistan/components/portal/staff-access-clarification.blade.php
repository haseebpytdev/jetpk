{{-- JP-PORTAL-3 TASK 7 · JetPK portal Agent Staff access clarification
     Replaces partials.agent-staff-access-clarification on JetPK-resolved AGENT pages only.
     The legacy partial REMAINS on disk untouched and still serves dashboard/admin/users/show and
     themes/admin/jetpakistan/users/show.

     WHY FORKED: the legacy partial is built from `ota-agent-staff-access-clarification` classes
     plus a Bootstrap `alert alert-warning`. Reusing it would leave a legacy body fragment on the
     JetPK Agent staff-edit page and fail Task 13. Content is reproduced exactly; only the class
     vocabulary changes.

     Preserved verbatim:
       • $showOwnerLabelWarning  = $showAgentStaffOwnerLabelWarning ?? false
       • $showOwnerAccountNote   = $showOwnerAccountTypeNote ?? false
       • $showApplyTemplateHint  = $showApplyTemplateHint ?? true   (note: default TRUE)
       • all four bullets and their exact wording, each gated as in legacy
       • the owner-label warning copy, verbatim
       • data-testids: agent-staff-access-clarification, agent-staff-owner-label-warning

     This block is the user-facing statement that Agency Role and Permission Matrix are SEPARATE
     systems. Its wording is load-bearing for Task 11 — do not paraphrase it.
--}}
@php
    $showOwnerLabelWarning = $showAgentStaffOwnerLabelWarning ?? false;
    $showOwnerAccountNote = $showOwnerAccountTypeNote ?? false;
    $showApplyTemplateHint = $showApplyTemplateHint ?? true;
@endphp

<div class="jp-portal__clarification" data-testid="agent-staff-access-clarification">
    <span class="jp-portal__clarification-icon" aria-hidden="true"><x-jp.icon name="info-circle" /></span>
    <ul class="jp-portal__clarification-list">
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
    <x-jp.alert variant="warning" data-testid="agent-staff-owner-label-warning">
        This staff member is labelled Owner, but they are still an Agency Staff account. Owner-level access requires an Agency Owner account type.
    </x-jp.alert>
@endif
