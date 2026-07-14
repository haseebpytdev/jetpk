@php
    use App\Support\Access\AccountTypeLabels;
    use App\Support\Identity\ActorIdentifier;
    $u = $userModel;
    $typeValue = $u->account_type?->value ?? 'unknown';
    $typeLabel = AccountTypeLabels::label($u->account_type);
    $statusValue = $u->status?->value ?? 'unknown';
    $typeBadge = match ($typeValue) {
        'platform_admin' => 'bg-purple',
        'agency_admin' => 'bg-primary',
        'staff' => 'bg-azure',
        'agent' => 'bg-indigo',
        'agent_staff' => 'bg-teal',
        'customer' => 'bg-secondary',
        default => 'bg-secondary',
    };
    $statusBadge = match ($statusValue) {
        'active' => 'bg-success',
        'invited' => 'bg-warning',
        'suspended' => 'bg-danger',
        default => 'bg-secondary',
    };
    $initials = collect(preg_split('/\s+/', trim($u->name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
        ->join('');
    if ($initials === '') {
        $initials = strtoupper(substr($u->email, 0, 1));
    }
@endphp
@extends(client_layout('dashboard', 'admin'))

@section('title', 'User access')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.users.index') }}" class="text-secondary">Users &amp; Access</a></div>
            <h1 class="jp-page-title">{{ $u->name }}</h1>
        </div>
    </div>
@endsection

@section('content')
    <div class="ota-access-shell">
        @if (session('status') === 'agent-permissions-updated')
            <div class="jp-alert jp-alert--success">Agent portal permissions saved. Agency role was not changed.</div>
        @elseif (session('status') === 'agent-permissions-template-applied')
            <div class="jp-alert jp-alert--success">Role permission template applied. Agency role was not changed.</div>
        @elseif (session('status'))
            <div class="jp-alert jp-alert--success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())<div class="jp-alert jp-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <div class="card ota-access-header-card mb-3">
            <div class="jp-card__body">
                <div class="ota-access-header-grid">
                    <div class="ota-access-avatar" aria-hidden="true">{{ $initials }}</div>
                    <div class="ota-access-header-main min-w-0">
                        <div class="ota-access-meta mb-2">
                            <span class="badge {{ $typeBadge }}">{{ $typeLabel }}</span>
                            <span class="badge {{ $statusBadge }}">{{ $statusValue }}</span>
                        </div>
                        <h2 class="h3 mb-1">{{ $u->name }}</h2>
                        <p class="text-secondary mb-1 ota-r-text-safe"><strong>{{ \App\Support\Identity\IdentityDisplay::labelUserActorId() }}:</strong> {{ ActorIdentifier::forUser($u) }}</p>
                        <p class="text-secondary mb-1 ota-r-text-safe">{{ $u->email }}</p>
                        @if ($u->username)
                            <p class="text-secondary mb-2 ota-r-text-safe"><strong>Username:</strong> {{ $u->username }}</p>
                        @endif
                        <div class="ota-access-header-meta">
                            <span><strong>Agency:</strong> {{ $agencyScope }}</span>
                            <span><strong>Last login:</strong> {{ $u->last_login_at?->format('M j, Y g:i A') ?? 'Never' }}</span>
                            <span><strong>Email verified:</strong> {{ $u->email_verified_at ? $u->email_verified_at->format('M j, Y') : 'Not verified' }}</span>
                            <span><strong>Social linked:</strong> {{ count($linkedProviders) ? implode(', ', $linkedProviders) : 'None' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ota-access-action-bar ota-access-action-bar--primary mb-2">
            <a class="jp-btn jp-btn--primary btn-sm" href="{{ route('admin.users.edit', $u) }}"><i class="ti ti-shield-lock me-1"></i>Edit access</a>
            <div class="ota-access-action-item">
                <form method="post" action="{{ route('admin.users.send-invite', $u) }}">@csrf<button class="jp-btn jp-btn--outline btn-sm" type="submit">Send account invitation</button></form>
                <span class="ota-access-action-help">Sends an onboarding and account setup email.</span>
            </div>
            <div class="ota-access-action-item">
                <form method="post" action="{{ route('admin.users.reset-password-link', $u) }}">@csrf<button class="jp-btn jp-btn--outline btn-sm" type="submit">Send password reset</button></form>
                <span class="ota-access-action-help">Sends a password reset email to this user.</span>
            </div>
            @if ($isCustomerAccount)
                <a class="jp-btn jp-btn--ghost btn-sm" href="{{ route('admin.customers.show', $u) }}"><i class="ti ti-user me-1"></i>Open customer profile</a>
            @endif
        </div>

        <div class="ota-access-action-bar ota-access-action-bar--danger mb-3">
            @if ($statusValue !== 'suspended')
                <form method="post" action="{{ route('admin.users.suspend', $u) }}">@csrf @method('PATCH')<button class="jp-btn jp-btn--danger btn-sm" type="submit"><i class="ti ti-ban me-1"></i>Suspend account</button></form>
                <span class="ota-access-action-help text-danger">Revokes portal access until reactivated.</span>
            @else
                <form method="post" action="{{ route('admin.users.activate', $u) }}">@csrf @method('PATCH')<button class="btn btn-outline-success btn-sm" type="submit"><i class="ti ti-check me-1"></i>Activate account</button></form>
                <span class="ota-access-action-help">Restores portal access for this user.</span>
            @endif
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card h-100 ota-access-panel">
                    <div class="jp-card__head"><h3 class="jp-card__title mb-0">Access summary</h3></div>
                    <div class="jp-card__body">
                        <div class="ota-access-summary-grid ota-access-summary-grid--compact">
                            <div class="ota-access-summary-item">
                                <div class="ota-access-summary-label">Email</div>
                                <div class="ota-access-summary-value">{{ $u->email }}</div>
                            </div>
                            <div class="ota-access-summary-item">
                                <div class="ota-access-summary-label">Username</div>
                                <div class="ota-access-summary-value" data-testid="user-access-username">{{ display_unknown($u->username) }}</div>
                            </div>
                            <div class="ota-access-summary-item">
                                <div class="ota-access-summary-label">Account type</div>
                                <div class="ota-access-summary-value">{{ $typeLabel }}</div>
                            </div>
                            @if ($agencyRoleLabel)
                                <div class="ota-access-summary-item">
                                    <div class="ota-access-summary-label">Agency role</div>
                                    <div class="ota-access-summary-value" data-testid="user-access-agency-role">
                                        {{ $agencyRoleLabel }}
                                        @if (! ($agencyRoleIsStored ?? false))
                                            <span class="text-secondary small">(inferred)</span>
                                        @endif
                                    </div>
                                </div>
                                @if ($agencyRoleAssignmentAgencyId && ($agencyRoleOptions ?? []) !== [])
                                    <div class="ota-access-summary-item w-100">
                                        <div class="ota-access-summary-label">Update agency role</div>
                                        <div class="ota-access-summary-value">
                                            @include('partials.agency-role-assignment-form', [
                                                'action' => route('admin.agencies.users.agency-role.update', [
                                                    'agency' => $agencyRoleAssignmentAgencyId,
                                                    'user' => $u,
                                                ]),
                                                'currentRoleValue' => $agencyRoleCurrentValue ?? '',
                                                'roleOptions' => $agencyRoleOptions,
                                                'formTestId' => 'admin-user-agency-role-form',
                                                'selectTestId' => 'admin-user-agency-role-select',
                                            ])
                                        </div>
                                    </div>
                                @endif
                            @endif
                            <div class="ota-access-summary-item">
                                <div class="ota-access-summary-label">Role preset</div>
                                <div class="ota-access-summary-value">{{ $rolePreset }}</div>
                            </div>
                            <div class="ota-access-summary-item">
                                <div class="ota-access-summary-label">Agency scope</div>
                                <div class="ota-access-summary-value">{{ $agencyScope }}</div>
                            </div>
                            <div class="ota-access-summary-item">
                                <div class="ota-access-summary-label">Department</div>
                                <div class="ota-access-summary-value">{{ display_unknown($u->staffProfile?->department) }}</div>
                            </div>
                            <div class="ota-access-summary-item">
                                <div class="ota-access-summary-label">Role title</div>
                                <div class="ota-access-summary-value">{{ display_unknown($u->staffProfile?->job_title) }}</div>
                            </div>
                            @if ($typeValue === 'staff')
                                <div class="ota-access-summary-item">
                                    <div class="ota-access-summary-label">Staff access mode</div>
                                    <div class="ota-access-summary-value">
                                        @if ($usesLegacyStaffPermissions ?? false)
                                            <span class="badge bg-warning-lt" data-testid="staff-access-mode-legacy">Default staff access active</span>
                                            @if (! empty($staffAccessModeHelp))
                                                <div class="text-secondary small mt-1">{{ $staffAccessModeHelp }}</div>
                                            @endif
                                        @else
                                            <span class="badge bg-azure-lt" data-testid="staff-access-mode-permissions">Permission-based</span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            @if ($permissionGroupLabel)
                                <div class="ota-access-summary-item">
                                    <div class="ota-access-summary-label">Permission group</div>
                                    <div class="ota-access-summary-value">
                                        {{ $permissionGroupLabel }}
                                        <span class="badge bg-secondary-lt ms-1">Label only</span>
                                    </div>
                                </div>
                            @endif
                            @if ($typeValue === 'agent')
                                <div class="ota-access-summary-item">
                                    <div class="ota-access-summary-label">{{ \App\Support\Identity\IdentityDisplay::labelLegacyAgentProfileCode() }}</div>
                                    <div class="ota-access-summary-value">{{ display_unknown($u->agentProfile?->code) }}</div>
                                </div>
                            @endif
                            @if ($typeValue === 'agent_staff')
                                <div class="ota-access-summary-item">
                                    <div class="ota-access-summary-label">Owning agency owner</div>
                                    <div class="ota-access-summary-value">{{ display_unknown($ownerAgent?->user?->name) }} ({{ display_unknown($ownerAgent?->code, 'N/A') }})</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                @if ($isAgentStaffPermissions ?? false)
                    @include('partials.recent-agent-permission-audit', [
                        'showRecentPermissionAuditPanel' => $showRecentPermissionAuditPanel ?? false,
                        'recentPermissionAuditLogs' => $recentPermissionAuditLogs ?? [],
                        'agencyActivityUrl' => $agencyActivityUrl ?? null,
                    ])
                @endif

                @if ($isCustomerAccount)
                    <div class="card h-100 ota-access-panel ota-access-customer-note">
                        <div class="jp-card__head"><h3 class="jp-card__title mb-0">Portal access</h3></div>
                        <div class="jp-card__body">
                            <div class="ota-access-customer-banner mb-3">
                                <i class="ti ti-user-circle me-1"></i>
                                <strong>Customer portal only.</strong>
                                This account has no staff, agent, or admin permissions. Manage bookings and profile details in the Customers module.
                            </div>
                            <p class="text-secondary mb-3">Promote the account type on the access editor to assign operator or admin access.</p>
                            <a class="jp-btn jp-btn--outline btn-sm" href="{{ route('admin.customers.show', $u) }}"><i class="ti ti-external-link me-1"></i>Open customer profile</a>
                        </div>
                    </div>
                @else
                    <div class="card h-100 ota-access-panel" id="agent-staff-permissions">
                        <div class="jp-card__head">
                            <h3 class="jp-card__title mb-0">
                                @if ($isAgentStaffPermissions)
                                    Agent portal permissions
                                @elseif ($isStaffPermissions ?? false)
                                    Staff portal permissions
                                @else
                                    Effective access summary
                                @endif
                            </h3>
                        </div>
                        <div class="card-body ota-access-matrix-body">
                            <div class="ota-access-readonly-banner mb-3">
                                @if ($isAgentStaffPermissions && ($isEditableAgentStaffPermissions ?? false))
                                    <p class="text-secondary small mb-0">Use the permission matrix below to control portal access.</p>
                                @elseif ($isAgentStaffPermissions)
                                    @include('partials.agent-staff-access-clarification', [
                                        'showAgentStaffOwnerLabelWarning' => $showAgentStaffOwnerLabelWarning ?? false,
                                        'showOwnerAccountTypeNote' => false,
                                        'showApplyTemplateHint' => true,
                                    ])
                                    <p class="text-secondary small mb-0 mt-2">Granted and blocked permissions below reflect saved agent staff settings. Edit on the access editor page.</p>
                                @elseif ($isStaffPermissions ?? false)
                                    @if ($usesLegacyStaffPermissions ?? false)
                                        <span class="badge bg-warning-lt me-1">Legacy mode</span>
                                        This staff user has legacy full staff access until permissions are saved from the access editor.
                                    @else
                                        <span class="badge bg-azure-lt me-1">Permission-based</span>
                                        Granted and blocked permissions below reflect saved staff settings. Edit on the access editor page.
                                    @endif
                                @else
                                    Read-only summary based on account type and enforced middleware/policies. Individual toggles are not editable.
                                    @if ($showOwnerAccountTypeNote ?? false)
                                        <p class="text-secondary small mb-0 mt-2">Owner access is based on account type, not staff role label.</p>
                                    @endif
                                @endif
                            </div>
                            @if ($permissionScopeNote)
                                <p class="ota-access-matrix-note mb-3">{{ $permissionScopeNote }}</p>
                            @endif

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
                            @elseif ($isAgentStaffPermissions)
                                @foreach ($groupedAgentPermissions as $module => $permissions)
                                    <div class="ota-access-module">
                                        <div class="ota-access-module__title">{{ $module }}</div>
                                        @foreach ($permissions as $key => $label)
                                            @php $enabled = in_array($key, $selectedAgentPermissions, true); @endphp
                                            <div class="ota-access-toggle-row">
                                                <div class="ota-access-toggle-row__label">{{ $label }}</div>
                                                <div class="ota-access-toggle-row__control">
                                                    <span class="ota-access-state {{ $enabled ? 'ota-access-state--allowed' : 'ota-access-state--blocked' }}">{{ $enabled ? 'Allowed' : 'Blocked' }}</span>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" role="switch" disabled @checked($enabled) aria-label="{{ $label }}">
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            @elseif ($isStaffPermissions ?? false)
                                @if ($usesLegacyStaffPermissions ?? false)
                                    <p class="text-secondary mb-0">All staff portal capabilities remain available until permissions are configured and saved on the access editor.</p>
                                @else
                                    @foreach ($groupedStaffPermissions as $module => $permissions)
                                        <div class="ota-access-module">
                                            <div class="ota-access-module__title">{{ $module }}</div>
                                            @foreach ($permissions as $key => $label)
                                                @php $enabled = in_array($key, $selectedStaffPermissions, true); @endphp
                                                <div class="ota-access-toggle-row">
                                                    <div class="ota-access-toggle-row__label">{{ $label }}</div>
                                                    <div class="ota-access-toggle-row__control">
                                                        <span class="ota-access-state {{ $enabled ? 'ota-access-state--allowed' : 'ota-access-state--blocked' }}">{{ $enabled ? 'Allowed' : 'Blocked' }}</span>
                                                        <div class="form-check form-switch mb-0">
                                                            <input class="form-check-input" type="checkbox" role="switch" disabled @checked($enabled) aria-label="{{ $label }}">
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                @endif
                            @else
                                @foreach ($groupedEffectiveAccess as $module => $rows)
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
                                                        <input class="form-check-input" type="checkbox" role="switch" disabled @checked($row['enabled']) aria-label="{{ $row['area'] }}">
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-12">
                <div class="card ota-access-panel">
                    <div class="jp-card__head"><h3 class="jp-card__title mb-0">Security</h3></div>
                    <div class="jp-card__body">
                        <div class="ota-access-summary-grid ota-access-security-grid">
                            <div class="ota-access-summary-item">
                                <div class="ota-access-summary-label">Email verified</div>
                                <div class="ota-access-summary-value">{{ $u->email_verified_at?->format('M j, Y g:i A') ?? 'Not verified' }}</div>
                            </div>
                            <div class="ota-access-summary-item">
                                <div class="ota-access-summary-label">Last login</div>
                                <div class="ota-access-summary-value">{{ $u->last_login_at?->format('M j, Y g:i A') ?? 'Never' }}</div>
                            </div>
                            <div class="ota-access-summary-item">
                                <div class="ota-access-summary-label">Linked providers</div>
                                <div class="ota-access-summary-value">{{ count($linkedProviders) ? implode(', ', $linkedProviders) : 'None' }}</div>
                            </div>
                            <div class="ota-access-summary-item">
                                <div class="ota-access-summary-label">Account status</div>
                                <div class="ota-access-summary-value"><span class="badge {{ $statusBadge }}">{{ $statusValue }}</span></div>
                            </div>
                            @if ($u->invited_at)
                                <div class="ota-access-summary-item">
                                    <div class="ota-access-summary-label">Last invited</div>
                                    <div class="ota-access-summary-value">{{ $u->invited_at->format('M j, Y g:i A') }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
