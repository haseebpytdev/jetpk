@php
    $u = $userModel;
    $initialAccountType = old('account_type', $u->account_type?->value ?? 'staff');
    $selectedAgentPermissions = collect(old('permissions', $u->meta['agent_permissions'] ?? []))->all();
    $selectedStaffPermissions = collect(old('staff_permissions', $u->meta['staff_permissions'] ?? []))->all();
    $usesLegacyStaffPermissions = ($isEdit ?? false) && $u->exists
        ? $u->usesLegacyStaffPermissions()
        : false;
    $isCustomerAccount = $initialAccountType === 'customer';
    $accountTypeHints = [
        'platform_admin' => 'Platform Admin — full platform-wide access across all agencies.',
        'agency_admin' => 'Legacy account type — disabled. Users are routed to the legacy notice page and cannot access admin or platform controls.',
        'staff' => 'Platform Staff — internal operator with granular staff portal permissions.',
        'agent' => 'Agency Owner — full access to the agency booking portal.',
        'agent_staff' => 'Agency Staff — granular agent portal permissions (editable below).',
        'customer' => 'Customer portal user — booking and account self-service only.',
    ];
    $accountTypeOptionLabels = [
        'platform_admin' => 'Platform Admin',
        'agency_admin' => 'Legacy Agency Admin',
        'staff' => 'Platform Staff',
        'agent' => 'Agency Owner',
        'agent_staff' => 'Agency Staff',
        'customer' => 'Customer',
    ];
@endphp

<div id="admin-user-form" class="ota-access-form {{ $isCustomerAccount ? 'ota-access-form--customer-only' : '' }}">
    <div id="ota-access-form-row" class="row g-3">
        <div class="ota-access-form-identity-col {{ $isCustomerAccount ? 'col-12' : 'col-lg-5' }}">
            <div class="card ota-access-panel mb-3 mb-lg-0">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Identity</h3></div>
                <div class="jp-card__body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="jp-label">Name</label>
                            <input class="jp-control" name="name" value="{{ old('name', $u->name) }}" required>
                        </div>
                        <div class="col-12">
                            <label class="jp-label">Email</label>
                            <input class="jp-control" name="email" type="email" value="{{ old('email', $u->email) }}" required>
                        </div>
                        <div class="col-12">
                            <label class="jp-label">Phone</label>
                            <input class="jp-control" name="phone" value="{{ old('phone', $u->meta['phone'] ?? '') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card ota-access-panel ota-access-control-card mb-3 mb-lg-0 mt-lg-3">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Access classification</h3></div>
                <div class="jp-card__body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="jp-label" for="account_type">Account type</label>
                            <select class="jp-control ota-access-control-select" name="account_type" id="account_type" required>
                                @foreach($accountTypeOptions as $opt)
                                    <option value="{{ $opt }}" @selected($initialAccountType === $opt)>{{ $accountTypeOptionLabels[$opt] ?? str_replace('_', ' ', $opt) }}</option>
                                @endforeach
                            </select>
                            <div class="form-hint" id="account_type_hint">{{ $accountTypeHints[$initialAccountType] ?? 'Determines portal access and role preset.' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="jp-label" for="status">Account status</label>
                            <select class="jp-control ota-access-control-select" name="status" id="status" required>
                                @foreach(['active','invited','suspended','inactive'] as $st)
                                    <option value="{{ $st }}" @selected(old('status', $u->status?->value ?? 'active') === $st)>{{ ucfirst($st) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="jp-label">Agency scope</label>
                            <input class="jp-control" value="{{ $u->currentAgency?->name ?? 'Assigned on save' }}" disabled readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card ota-access-panel mb-3 mb-lg-0 mt-lg-3">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Role details</h3></div>
                <div class="jp-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="jp-label">Department</label>
                            <input class="jp-control" name="department" value="{{ old('department', $u->staffProfile?->department) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="jp-label">Role title</label>
                            <input class="jp-control" name="role_title" value="{{ old('role_title', $u->staffProfile?->job_title) }}">
                        </div>
                        <div class="col-12">
                            <label class="jp-label">Permission group <span class="text-secondary fw-normal">(label only)</span></label>
                            <input class="jp-control" name="permission_group" value="{{ old('permission_group', $u->meta['permission_group'] ?? '') }}" placeholder="Optional display label">
                        </div>
                        <div class="col-md-6" data-agent-only @if($initialAccountType !== 'agent') hidden @endif>
                            <label class="jp-label">Agency name</label>
                            <input class="jp-control" name="agency_name" value="{{ old('agency_name', $u->agentProfile?->meta['agency_name'] ?? '') }}">
                        </div>
                        <div class="col-md-6" data-agent-only @if($initialAccountType !== 'agent') hidden @endif>
                            <label class="jp-label">City</label>
                            <input class="jp-control" name="city" value="{{ old('city', $u->agentProfile?->meta['city'] ?? '') }}">
                        </div>
                        <div class="col-md-6" data-agent-only @if($initialAccountType !== 'agent') hidden @endif>
                            <label class="jp-label">Commission %</label>
                            <input class="jp-control" name="commission_percent" type="number" step="0.01" value="{{ old('commission_percent', $u->agentProfile?->commission_percent) }}">
                        </div>
                        <div class="col-md-6" data-agent-only @if($initialAccountType !== 'agent') hidden @endif>
                            <label class="jp-label">Agent code</label>
                            <input class="jp-control" name="agent_code" value="{{ old('agent_code', $u->agentProfile?->code) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div id="customer-access-note" class="card ota-access-panel ota-access-customer-note mt-lg-3" @if($initialAccountType !== 'customer') hidden @endif>
                <div class="card-header border-0 pb-0">
                    <h3 class="jp-card__title mb-0">Portal access</h3>
                </div>
                <div class="card-body pt-2">
                    <div class="ota-access-customer-banner">
                        <i class="ti ti-info-circle me-1" aria-hidden="true"></i>
                        Customer portal access only. Promote account type to assign staff/admin access.
                    </div>
                </div>
            </div>
        </div>

        <div class="ota-access-form-matrix-col col-lg-7" @if($isCustomerAccount) hidden @endif>
            <div class="card ota-access-panel h-100" id="user-permission-card" @if($initialAccountType === 'customer') hidden @endif>
                <div class="jp-card__head">
                    <h3 class="jp-card__title mb-0">Permission matrix</h3>
                </div>
                <div class="card-body ota-access-matrix-body">
                    <div id="role-preset-note" class="mb-3 ota-access-matrix-note fw-semibold">{{ $rolePresets[$initialAccountType] ?? '' }}</div>

                    <div data-permission-panel="agent_staff" @if($initialAccountType !== 'agent_staff') hidden @endif>
                        <div class="ota-access-editable-banner mb-3">
                            Editable agent portal permissions. Only these toggles are saved to the user record.
                        </div>

                        <div class="mb-3">
                            <label class="jp-label" for="owner_agent_id">Owning agency owner <span class="text-danger">*</span></label>
                            <select class="jp-control ota-access-control-select" name="owner_agent_id" id="owner_agent_id">
                                <option value="">Select agency owner…</option>
                                @foreach ($agencyAgents as $agentOption)
                                    <option
                                        value="{{ $agentOption->id }}"
                                        @selected((string) old('owner_agent_id', $u->meta['owner_agent_id'] ?? '') === (string) $agentOption->id)
                                    >
                                        {{ $agentOption->user?->name ?? 'Agency Owner' }} ({{ $agentOption->code }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-hint">Required for agency staff. The staff member inherits this agency owner's agency scope.</div>
                            @error('owner_agent_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        @foreach ($groupedAgentPermissions as $module => $permissions)
                            <div class="ota-access-module">
                                <div class="ota-access-module__title">{{ $module }}</div>
                                @foreach ($permissions as $key => $label)
                                    @php $enabled = in_array($key, $selectedAgentPermissions, true); @endphp
                                    <div class="ota-access-toggle-row">
                                        <div class="ota-access-toggle-row__label">{{ $label }}</div>
                                        <div class="ota-access-toggle-row__control">
                                            <span class="ota-access-state {{ $enabled ? 'ota-access-state--allowed' : 'ota-access-state--blocked' }}" data-toggle-state="{{ $key }}">{{ $enabled ? 'Allowed' : 'Blocked' }}</span>
                                            <div class="form-check form-switch mb-0">
                                                <input
                                                    class="form-check-input ota-agent-perm-toggle"
                                                    type="checkbox"
                                                    role="switch"
                                                    name="permissions[]"
                                                    value="{{ $key }}"
                                                    data-perm-key="{{ $key }}"
                                                    @checked($enabled)
                                                    aria-label="{{ $label }}"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div data-permission-panel="staff" @if($initialAccountType !== 'staff') hidden @endif>
                        <input type="hidden" name="staff_permissions_configured" value="1">
                        <div class="ota-access-editable-banner mb-3">
                            Editable staff portal permissions. Saving writes <code>users.meta.staff_permissions</code> and enables permission-based access.
                        </div>
                        @php
                            $showStaffLegacyWarning = ($isEdit && ($usesLegacyStaffPermissions ?? false))
                                || (! $isEdit && $initialAccountType === 'staff');
                        @endphp
                        @if ($showStaffLegacyWarning)
                            <div class="jp-alert jp-alert--warn py-2 mb-3" data-testid="staff-legacy-access-warning">
                                This staff user is currently using legacy full staff access. Saving permissions will enable permission-based access.
                            </div>
                        @endif

                        <div class="mb-3">
                            <div class="jp-label mb-2">Permission presets</div>
                            <div class="d-flex flex-wrap gap-2" role="group" aria-label="Staff permission presets">
                                @foreach ($staffPresetLabels ?? [] as $presetKey => $presetLabel)
                                    <button
                                        type="button"
                                        class="jp-btn jp-btn--outline btn-sm"
                                        data-staff-preset="{{ $presetKey }}"
                                        data-testid="staff-preset-{{ $presetKey }}"
                                    >{{ $presetLabel }}</button>
                                @endforeach
                            </div>
                            <div class="form-hint mt-2">Presets check the toggles below. You can adjust individual permissions before saving.</div>
                        </div>

                        @foreach ($groupedStaffPermissions as $module => $permissions)
                            <div class="ota-access-module">
                                <div class="ota-access-module__title">{{ $module }}</div>
                                @foreach ($permissions as $key => $label)
                                    @php $enabled = in_array($key, $selectedStaffPermissions, true); @endphp
                                    <div class="ota-access-toggle-row">
                                        <div class="ota-access-toggle-row__label">{{ $label }}</div>
                                        <div class="ota-access-toggle-row__control">
                                            <span class="ota-access-state {{ $enabled ? 'ota-access-state--allowed' : 'ota-access-state--blocked' }}" data-staff-toggle-state="{{ $key }}">{{ $enabled ? 'Allowed' : 'Blocked' }}</span>
                                            <div class="form-check form-switch mb-0">
                                                <input
                                                    class="form-check-input ota-staff-perm-toggle"
                                                    type="checkbox"
                                                    role="switch"
                                                    name="staff_permissions[]"
                                                    value="{{ $key }}"
                                                    data-staff-perm-key="{{ $key }}"
                                                    @checked($enabled)
                                                    aria-label="{{ $label }}"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    @foreach ($groupedEffectiveAccessByType as $type => $modules)
                        <div data-permission-panel="{{ $type }}" @if($initialAccountType !== $type) hidden @endif>
                            <div class="ota-access-readonly-banner mb-3">
                                Effective access summary — read-only. Role permissions are enforced by account type and policies, not by these toggles.
                            </div>
                            @if (! empty($permissionScopeNotes[$type] ?? null))
                                <p class="ota-access-matrix-note mb-3">{{ $permissionScopeNotes[$type] }}</p>
                            @endif
                            @foreach ($modules as $module => $rows)
                                <div class="ota-access-module">
                                    <div class="ota-access-module__title">{{ $module }}</div>
                                    @foreach ($rows as $row)
                                        @php
                                            $stateClass = $row['limited']
                                                ? 'ota-access-state--limited'
                                                : ($row['enabled'] ? 'ota-access-state--allowed' : 'ota-access-state--blocked');
                                            $stateLabel = $row['limited'] ? 'Limited' : ($row['enabled'] ? 'Allowed' : 'Blocked');
                                        @endphp
                                        <div class="ota-access-toggle-row {{ $row['limited'] ? 'ota-access-toggle-row--limited' : '' }} {{ ! $row['enabled'] ? 'ota-access-toggle-row--readonly-off' : '' }}">
                                            <div class="ota-access-toggle-row__label">{{ $row['area'] }}</div>
                                            <div class="ota-access-toggle-row__control">
                                                <span class="ota-access-state {{ $stateClass }}">{{ $stateLabel }}</span>
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" role="switch" disabled @checked($row['enabled']) tabindex="-1" aria-label="{{ $row['area'] }}">
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if(! $isEdit)
        <div class="card ota-access-panel mt-3">
            <div class="jp-card__body">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" name="send_invite" value="1" @checked(old('send_invite'))>
                    <span class="form-check-label">Send account invitation after creating user</span>
                </label>
                <div class="form-hint">Sends an onboarding and account setup email.</div>
            </div>
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const accountTypeSelect = document.getElementById('account_type');
                const permissionCard = document.getElementById('user-permission-card');
                const customerNote = document.getElementById('customer-access-note');
                const presetNote = document.getElementById('role-preset-note');
                const accountTypeHint = document.getElementById('account_type_hint');
                const accessForm = document.getElementById('admin-user-form');
                const identityCol = document.querySelector('.ota-access-form-identity-col');
                const matrixCol = document.querySelector('.ota-access-form-matrix-col');
                const rolePresets = @json($rolePresets);
                const accountTypeHints = @json($accountTypeHints);
                const staffPresetMap = @json($staffPresetPermissions ?? []);

                if (! accountTypeSelect) {
                    return;
                }

                function syncPermissionMatrix() {
                    const type = accountTypeSelect.value;
                    const isCustomer = type === 'customer';

                    if (accessForm) {
                        accessForm.classList.toggle('ota-access-form--customer-only', isCustomer);
                    }

                    if (identityCol) {
                        identityCol.classList.toggle('col-12', isCustomer);
                        identityCol.classList.toggle('col-lg-5', ! isCustomer);
                    }

                    if (matrixCol) {
                        matrixCol.hidden = isCustomer;
                    }

                    if (permissionCard) {
                        permissionCard.hidden = isCustomer;
                    }

                    if (customerNote) {
                        customerNote.hidden = ! isCustomer;
                    }

                    if (accountTypeHint && accountTypeHints[type]) {
                        accountTypeHint.textContent = accountTypeHints[type];
                    }

                    document.querySelectorAll('[data-permission-panel]').forEach(function (panel) {
                        panel.hidden = panel.dataset.permissionPanel !== type;
                    });

                    document.querySelectorAll('[data-agent-only]').forEach(function (el) {
                        el.hidden = type !== 'agent';
                    });

                    const ownerSelect = document.getElementById('owner_agent_id');
                    if (ownerSelect) {
                        ownerSelect.required = type === 'agent_staff';
                    }

                    document.querySelectorAll('.ota-agent-perm-toggle').forEach(function (input) {
                        input.disabled = type !== 'agent_staff';
                        if (type !== 'agent_staff') {
                            input.removeAttribute('name');
                        } else {
                            input.setAttribute('name', 'permissions[]');
                        }
                    });

                    document.querySelectorAll('.ota-staff-perm-toggle').forEach(function (input) {
                        input.disabled = type !== 'staff';
                        if (type !== 'staff') {
                            input.removeAttribute('name');
                        } else {
                            input.setAttribute('name', 'staff_permissions[]');
                        }
                    });

                    if (presetNote && rolePresets[type]) {
                        presetNote.textContent = rolePresets[type];
                    }
                }

                document.querySelectorAll('.ota-agent-perm-toggle').forEach(function (input) {
                    input.addEventListener('change', function () {
                        const state = input.closest('.ota-access-toggle-row')?.querySelector('[data-toggle-state]');
                        if (state) {
                            state.textContent = input.checked ? 'Allowed' : 'Blocked';
                            state.classList.toggle('ota-access-state--allowed', input.checked);
                            state.classList.toggle('ota-access-state--blocked', ! input.checked);
                        }
                    });
                });

                function updateStaffToggleState(input) {
                    const state = input.closest('.ota-access-toggle-row')?.querySelector('[data-staff-toggle-state]');
                    if (state) {
                        state.textContent = input.checked ? 'Allowed' : 'Blocked';
                        state.classList.toggle('ota-access-state--allowed', input.checked);
                        state.classList.toggle('ota-access-state--blocked', ! input.checked);
                    }
                }

                function applyStaffPreset(presetKey) {
                    const keys = staffPresetMap[presetKey] || [];
                    document.querySelectorAll('.ota-staff-perm-toggle').forEach(function (input) {
                        input.checked = keys.includes(input.value);
                        updateStaffToggleState(input);
                    });
                }

                document.querySelectorAll('[data-staff-preset]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        applyStaffPreset(button.dataset.staffPreset || '');
                    });
                });

                document.querySelectorAll('.ota-staff-perm-toggle').forEach(function (input) {
                    input.addEventListener('change', function () {
                        updateStaffToggleState(input);
                    });
                });

                accountTypeSelect.addEventListener('change', syncPermissionMatrix);
                syncPermissionMatrix();
            });
        </script>
    @endpush
@endonce
