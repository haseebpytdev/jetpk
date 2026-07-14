@extends(client_layout('dashboard', 'admin'))

@section('title', 'Agency: '.$overview['name'])

@push('styles')
<style>
    .agency-profile-tabs { margin-bottom: 1rem; }
    .agency-profile-tabs .nav-link {
        font-size: .82rem;
        font-weight: 600;
        border-radius: 999px;
        padding: .38rem .72rem;
        color: #475569;
    }
    .agency-profile-tabs .nav-link.active {
        background: #e0edff;
        color: #1d4ed8;
        border-color: #93c5fd;
    }
    .agency-kv {
        display: flex;
        justify-content: space-between;
        gap: .75rem;
        font-size: .88rem;
        border-bottom: 1px dashed rgba(148, 163, 184, .3);
        padding-bottom: .35rem;
        margin-bottom: .35rem;
    }
    .agency-kv:last-child { border-bottom: 0; margin-bottom: 0; padding-bottom: 0; }
    .agency-kv .label { color: #64748b; font-weight: 600; }
    .agency-kv .value { color: #0f172a; font-weight: 600; text-align: right; word-break: break-word; }
    .agency-tab-hidden { display: none !important; }
</style>
@endpush

@section('page-header')
    <x-dashboard.section-header :title="$overview['name']" subtitle="Agency company profile and operational summary.">
        <x-slot:actions>
            <a href="{{ route('admin.agencies.index') }}" class="jp-btn jp-btn--ghost btn-sm">Back to agencies</a>
        </x-slot:actions>
    </x-dashboard.section-header>
@endsection

@section('content')
    @php
        use App\Support\Access\AccountTypeLabels;
        use App\Support\Identity\ActorIdentifier;
        use App\Support\Identity\IdentityDisplay;
        $tabs = [
            'overview' => 'Overview',
            'owner' => 'Agency owner',
            'staff' => 'Agency staff',
            'markups' => 'Markups',
            'wallet' => 'Wallet',
            'deposits' => 'Deposits',
            'bookings' => 'Bookings',
            'travelers' => 'Travelers',
            'support' => 'Support tickets',
            'activity' => 'Activity',
        ];
    @endphp

    <ul class="nav nav-pills agency-profile-tabs flex-wrap gap-1" role="tablist" data-testid="admin-agency-tabs">
        @foreach ($tabs as $tabKey => $tabLabel)
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === $tabKey ? 'active' : '' }}"
                   href="{{ route('admin.agencies.show', ['agency' => $agency, 'tab' => $tabKey]) }}">
                    {{ $tabLabel }}
                </a>
            </li>
        @endforeach
    </ul>

    <div class="{{ $activeTab !== 'overview' ? 'agency-tab-hidden' : '' }}" data-testid="admin-agency-tab-overview">
        <div class="card border-0 shadow-sm">
            <div class="jp-card__body">
                <div class="agency-kv"><span class="label">Agency name</span><span class="value">{{ $overview['name'] }}</span></div>
                <div class="agency-kv"><span class="label">Slug</span><span class="value">{{ $overview['slug'] }}</span></div>
                <div class="agency-kv"><span class="label">Owner / admin</span><span class="value">{{ $overview['owner_name'] }}</span></div>
                <div class="agency-kv"><span class="label">Agency staff</span><span class="value">{{ number_format($overview['staff_count']) }}</span></div>
                <div class="agency-kv"><span class="label">Bookings</span><span class="value">{{ number_format($overview['bookings_count']) }}</span></div>
                <div class="agency-kv"><span class="label">Wallet available</span><span class="value">{{ $overview['wallet_label'] }}</span></div>
                <div class="agency-kv"><span class="label">Deposits</span><span class="value">{{ $overview['deposit_status'] }}</span></div>
                <div class="agency-kv"><span class="label">Status</span><span class="value"><x-dashboard.status-badge :status="$overview['status']" /></span></div>
                <div class="agency-kv"><span class="label">Created</span><span class="value">{{ $overview['created_at'] }}</span></div>
                <div class="agency-kv"><span class="label">{{ IdentityDisplay::labelAgencyCode() }}</span><span class="value">{{ IdentityDisplay::agencyCodeDisplay($agency) ?? $agencyPrefix }}</span></div>
                @if ($primaryAgent)
                    <div class="agency-kv"><span class="label">{{ IdentityDisplay::labelLegacyAgentProfileCode() }}</span><span class="value">{{ IdentityDisplay::legacyAgentProfileCode($primaryAgent) }}</span></div>
                    <div class="agency-kv"><span class="label">Commission rate</span><span class="value">{{ number_format((float) $primaryAgent->commission_percent, 2) }}%</span></div>
                @endif
                <form method="post" action="{{ route('admin.agencies.prefix.update', $agency) }}" class="mt-3 pt-3 border-top">
                    @csrf
                    @method('PATCH')
                    <div class="jp-form-grid jp-form-grid--filter">
                        <div class="col-md-4">
                            <label class="jp-label small mb-0" for="agency-code-prefix">{{ IdentityDisplay::labelAgencyPrefix() }}</label>
                            <input id="agency-code-prefix" class="jp-control jp-control-sm" name="code_prefix" maxlength="4" pattern="[A-Z0-9]{2,4}" value="{{ old('code_prefix', $storedAgencyPrefix ?? $suggestedAgencyPrefix) }}" required>
                            <div class="form-text">2–4 uppercase letters/numbers. Suggested: {{ $suggestedAgencyPrefix }}</div>
                        </div>
                        <div class="col-md-auto">
                            <button type="submit" class="jp-btn jp-btn--sm jp-btn--outline">Save prefix</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="{{ $activeTab !== 'owner' ? 'agency-tab-hidden' : '' }}" data-testid="admin-agency-tab-owner">
        <div class="card border-0 shadow-sm">
            <div class="jp-card__body">
                @if ($ownerUser)
                    <div class="agency-kv"><span class="label">Name</span><span class="value">{{ $ownerUser->name }}</span></div>
                    <div class="agency-kv"><span class="label">Email</span><span class="value">{{ $ownerUser->email }}</span></div>
                    <div class="agency-kv"><span class="label">{{ IdentityDisplay::labelUserActorId() }}</span><span class="value font-monospace small">{{ IdentityDisplay::userActorId($ownerUser) }}</span></div>
                    <div class="agency-kv"><span class="label">{{ IdentityDisplay::labelAccessType() }}</span><span class="value">{{ IdentityDisplay::accessTypeLabel($ownerUser) }}</span></div>
                    <div class="agency-kv"><span class="label">Phone</span><span class="value">{{ $ownerUser->meta['phone'] ?? '—' }}</span></div>
                    <div class="agency-kv"><span class="label">Status</span><span class="value"><x-dashboard.status-badge :status="$ownerUser->status?->value ?? 'unknown'" /></span></div>
                    <div class="agency-kv"><span class="label">Last login</span><span class="value">{{ $ownerUser->last_login_at?->format('Y-m-d H:i') ?? 'Never' }}</span></div>
                    <div class="mt-3">
                        <a href="{{ route('admin.users.show', $ownerUser) }}" class="jp-btn jp-btn--sm jp-btn--outline">Open in Users &amp; Access</a>
                    </div>
                @else
                    <p class="text-secondary mb-0">No agency owner user is linked to this agency yet.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="{{ $activeTab !== 'staff' ? 'agency-tab-hidden' : '' }}" data-testid="admin-agency-tab-staff">
        <p class="text-secondary small mb-3" data-testid="admin-agency-staff-access-hint">
            Agency Role is a business label. It does not automatically change access.
            Permission Matrix controls actual portal access — use
            <strong>Permissions</strong> on each staff member to edit capabilities.
        </p>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive ota-r-table-wrap">
                <table class="table card-jp-table mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Access mode</th>
                            <th>Agency role</th>
                            <th>Status</th>
                            <th>Last login</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($staffMembers as $member)
                        @php
                            $permKeys = is_array($member->meta['agent_permissions'] ?? null) ? $member->meta['agent_permissions'] : [];
                            $permSummary = $permKeys === []
                                ? 'No permissions assigned'
                                : collect($permKeys)->map(fn ($k) => $agentPermissionLabels[$k] ?? $k)->take(3)->implode(', ').(count($permKeys) > 3 ? '…' : '');
                        @endphp
                        <tr>
                            <td>
                                <div>{{ $member->name }}</div>
                                <div class="text-secondary small">{{ IdentityDisplay::labelUserActorId() }}: <span class="font-monospace">{{ IdentityDisplay::userActorId($member) }}</span></div>
                            </td>
                            <td>{{ $member->email }}</td>
                            <td>{{ AccountTypeLabels::accessModeLabel($member) }} — {{ $permSummary }}</td>
                            <td>
                                @php
                                    $roleMeta = $staffAgencyRoles[$member->id] ?? ['label' => '—', 'value' => '', 'stored' => false];
                                @endphp
                                @include('partials.agency-role-assignment-form', [
                                    'action' => route('admin.agencies.users.agency-role.update', ['agency' => $agency, 'user' => $member]),
                                    'currentRoleValue' => $roleMeta['value'] ?? '',
                                    'roleOptions' => $agencyRoleOptions ?? [],
                                    'formTestId' => 'admin-agency-staff-role-form-'.$member->id,
                                    'selectTestId' => 'admin-agency-staff-role-select-'.$member->id,
                                ])
                                @if (! ($roleMeta['stored'] ?? false))
                                    <span class="text-secondary small">(was inferred)</span>
                                @endif
                            </td>
                            <td><x-dashboard.status-badge :status="$member->status?->value ?? 'unknown'" /></td>
                            <td class="text-nowrap">{{ $member->last_login_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.users.show', $member) }}#agent-staff-permissions" class="jp-btn jp-btn--sm jp-btn--outline">Permissions</a>
                                <a href="{{ route('admin.users.show', $member) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Access</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-secondary">No agency staff members linked to this agency.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="{{ $activeTab !== 'markups' ? 'agency-tab-hidden' : '' }}" data-testid="admin-agency-tab-markups">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive ota-r-table-wrap">
                <table class="table card-jp-table mb-0">
                    <thead><tr><th>Rule</th><th>Type</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($markupRuleRows as $rule)
                        <tr>
                            <td>{{ $rule['name'] }}</td>
                            <td>{{ $rule['type'] }}</td>
                            <td><x-dashboard.status-badge :status="$rule['status']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center py-4 text-secondary">No markup rules for this agency.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if (Route::has('admin.markups'))
                <div class="card-footer bg-transparent"><a href="{{ route('admin.markups') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Open markups admin</a></div>
            @endif
        </div>
    </div>

    <div class="{{ $activeTab !== 'wallet' ? 'agency-tab-hidden' : '' }}" data-testid="admin-agency-tab-wallet">
        <div class="card border-0 shadow-sm">
            <div class="jp-card__body">
                <div class="agency-kv" data-testid="admin-agency-wallet-balance"><span class="label">Balance</span><span class="value">{{ $walletSummary['currency'] }} {{ number_format((float) $walletSummary['balance'], 2) }}</span></div>
                <div class="agency-kv" data-testid="admin-agency-wallet-available"><span class="label">Available</span><span class="value">{{ $walletSummary['currency'] }} {{ number_format((float) $walletSummary['available_balance'], 2) }}</span></div>
                <div class="agency-kv"><span class="label">Pending deposits</span><span class="value">{{ $walletSummary['currency'] }} {{ number_format((float) $walletSummary['pending_deposits'], 2) }}</span></div>
                <div class="agency-kv"><span class="label">Credit limit</span><span class="value">{{ $walletSummary['credit_enabled'] ? ($walletSummary['currency'].' '.number_format((float) $walletSummary['credit_limit'], 2)) : 'Not enabled' }}</span></div>
                @if (count($walletSummary['wallets'] ?? []) > 1)
                    <div class="mt-4">
                        <h3 class="h6 mb-2">Individual wallets</h3>
                        <div class="table-responsive ota-r-table-wrap">
                            <table class="table table-sm mb-0" data-testid="admin-agency-wallet-rows">
                                <thead><tr><th>Wallet</th><th>Role</th><th>Agent</th><th>Status</th><th>Last movement</th><th class="text-end">Balance</th><th class="text-end">Credit limit</th></tr></thead>
                                <tbody>
                                @foreach ($walletSummary['wallets'] as $walletRow)
                                    <tr @if (($walletSummary['canonical_wallet_id'] ?? null) === $walletRow['id']) data-testid="admin-agency-canonical-wallet-row" @endif>
                                        <td>#{{ $walletRow['id'] }}</td>
                                        <td>{{ $walletRow['role_label'] ?? '—' }}</td>
                                        <td>{{ $walletRow['agent_id'] ? '#'.$walletRow['agent_id'] : '—' }}</td>
                                        <td>{{ ucfirst((string) ($walletRow['status'] ?? '—')) }}</td>
                                        <td>{{ ! empty($walletRow['last_movement_at']) ? \Illuminate\Support\Carbon::parse($walletRow['last_movement_at'])->format('Y-m-d H:i') : '—' }}</td>
                                        <td class="text-end">{{ $walletSummary['currency'] }} {{ number_format((float) $walletRow['balance'], 2) }}</td>
                                        <td class="text-end">{{ $walletRow['credit_limit'] !== null ? ($walletSummary['currency'].' '.number_format((float) $walletRow['credit_limit'], 2)) : '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="{{ $activeTab !== 'deposits' ? 'agency-tab-hidden' : '' }}" data-testid="admin-agency-tab-deposits">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive ota-r-table-wrap">
                <table class="table card-jp-table mb-0">
                    <thead><tr><th>ID</th><th>Amount</th><th>Status</th><th>Submitted</th></tr></thead>
                    <tbody>
                    @forelse($depositRequests as $deposit)
                        <tr>
                            <td><a href="{{ route('admin.agent-deposits.show', $deposit['id']) }}">#{{ $deposit['id'] }}</a></td>
                            <td>{{ number_format((float) $deposit['amount'], 2) }}</td>
                            <td>{{ $deposit['status'] }}</td>
                            <td>{{ $deposit['created_at'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4 text-secondary">No deposit requests for this agency.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="{{ $activeTab !== 'bookings' ? 'agency-tab-hidden' : '' }}" data-testid="admin-agency-tab-bookings">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive ota-r-table-wrap">
                <table class="table card-jp-table mb-0">
                    <thead><tr><th>Reference</th><th>Route</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                    @forelse($recentBookings as $booking)
                        <tr>
                            <td><a href="{{ route('admin.bookings.show', $booking['id']) }}">{{ $booking['booking_reference'] }}</a></td>
                            <td>{{ $booking['route'] }}</td>
                            <td>{{ $booking['status'] }}</td>
                            <td>{{ $booking['created_at'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4 text-secondary">No bookings for this agency.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="{{ $activeTab !== 'travelers' ? 'agency-tab-hidden' : '' }}" data-testid="admin-agency-tab-travelers">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive ota-r-table-wrap">
                <table class="table card-jp-table mb-0">
                    <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Last login</th></tr></thead>
                    <tbody>
                    @forelse($travelerUsers as $traveler)
                        <tr>
                            <td>{{ $traveler->name }}</td>
                            <td>{{ $traveler->email }}</td>
                            <td>{{ $traveler->status?->value ?? '—' }}</td>
                            <td>{{ $traveler->last_login_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4 text-secondary">No customer accounts scoped to this agency.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="{{ $activeTab !== 'support' ? 'agency-tab-hidden' : '' }}" data-testid="admin-agency-tab-support">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive ota-r-table-wrap">
                <table class="table card-jp-table mb-0">
                    <thead><tr><th>Subject</th><th>Status</th><th>Priority</th><th>Created</th></tr></thead>
                    <tbody>
                    @forelse($supportTickets as $ticket)
                        <tr>
                            <td><a href="{{ route('admin.support.tickets.show', $ticket['id']) }}">{{ $ticket['subject'] }}</a></td>
                            <td>{{ $ticket['status'] }}</td>
                            <td>{{ $ticket['priority'] }}</td>
                            <td>{{ $ticket['created_at'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4 text-secondary">No support tickets for this agency.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="{{ $activeTab !== 'activity' ? 'agency-tab-hidden' : '' }}" data-testid="admin-agency-tab-activity">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive ota-r-table-wrap">
                <table class="table card-jp-table mb-0">
                    <thead><tr><th>Action</th><th>{{ IdentityDisplay::labelPerformedBy() }}</th><th>When</th></tr></thead>
                    <tbody>
                    @forelse($auditLogs as $log)
                        <tr>
                            <td>
                                <div>{{ $log['action'] }}</div>
                                @if (! empty($log['details']))
                                    <div class="small text-secondary">{{ \Illuminate\Support\Str::limit(json_encode($log['details'], JSON_UNESCAPED_UNICODE), 240) }}</div>
                                @endif
                            </td>
                            <td>
                                @if (($log['actor_label'] ?? 'System') !== 'System')
                                    <div>{{ $log['actor_label'] }}</div>
                                @endif
                                <div class="small {{ ($log['actor_label'] ?? 'System') !== 'System' ? 'text-secondary font-monospace' : '' }}">{{ $log['actor_code'] }}</div>
                            </td>
                            <td>{{ $log['created_at'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center py-4 text-secondary">No audit activity recorded for this agency.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
