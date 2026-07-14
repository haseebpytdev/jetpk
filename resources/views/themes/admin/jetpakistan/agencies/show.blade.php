@extends(client_layout('dashboard', 'admin'))

@section('title', 'Agency: '.$overview['name'])

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-cell-sub"><a href="{{ client_route('admin.agencies.index') }}">Agencies</a></p>
            <h1>{{ $overview['name'] }}</h1>
            <p>Agency company profile and operational summary.</p>
        </div>
        <a href="{{ client_route('admin.agencies.index') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Back to agencies</a>
    </div>
@endsection

@section('content')
@php
    use App\Support\Access\AccountTypeLabels;
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

<div class="jp-queue-tabs" data-testid="admin-agency-tabs">
    @foreach ($tabs as $tabKey => $tabLabel)
        <a class="jp-queue-tab {{ $activeTab === $tabKey ? 'is-active' : '' }}"
           href="{{ client_route('admin.agencies.show', ['agency' => $agency, 'tab' => $tabKey]) }}">{{ $tabLabel }}</a>
    @endforeach
</div>

@if ($activeTab === 'overview')
    <div class="jp-card" data-testid="admin-agency-tab-overview">
        @foreach ([
            'Agency name' => $overview['name'],
            'Slug' => $overview['slug'],
            'Owner / admin' => $overview['owner_name'],
            'Agency staff' => number_format($overview['staff_count']),
            'Bookings' => number_format($overview['bookings_count']),
            'Wallet available' => $overview['wallet_label'],
            'Deposits' => $overview['deposit_status'],
            'Status' => $overview['status'],
            'Created' => $overview['created_at'],
            IdentityDisplay::labelAgencyCode() => IdentityDisplay::agencyCodeDisplay($agency) ?? $agencyPrefix,
        ] as $label => $value)
            <div style="display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px dashed var(--line-soft); font-size: 0.875rem;">
                <span class="jp-cell-sub">{{ $label }}</span>
                <strong>@if ($label === 'Status')<x-themes.admin.jetpakistan.components.status-badge :label="$value" />@else{{ $value }}@endif</strong>
            </div>
        @endforeach
        @if ($primaryAgent)
            <div style="display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px dashed var(--line-soft); font-size: 0.875rem;">
                <span class="jp-cell-sub">{{ IdentityDisplay::labelLegacyAgentProfileCode() }}</span>
                <strong>{{ IdentityDisplay::legacyAgentProfileCode($primaryAgent) }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; font-size: 0.875rem;">
                <span class="jp-cell-sub">Commission rate</span>
                <strong>{{ number_format((float) $primaryAgent->commission_percent, 2) }}%</strong>
            </div>
        @endif
        <form method="post" action="{{ client_route('admin.agencies.prefix.update', $agency) }}" class="jp-filterbar" style="margin-top: 16px; background: transparent; border: 0; padding: 0;">
            @csrf
            @method('PATCH')
            <div class="jp-filterbar__field">
                <label class="jp-label" for="agency-code-prefix">{{ IdentityDisplay::labelAgencyPrefix() }}</label>
                <input id="agency-code-prefix" class="jp-input" name="code_prefix" maxlength="4" pattern="[A-Z0-9]{2,4}" value="{{ old('code_prefix', $storedAgencyPrefix ?? $suggestedAgencyPrefix) }}" required>
                <p class="jp-cell-sub">Suggested: {{ $suggestedAgencyPrefix }}</p>
            </div>
            <div class="jp-filterbar__actions">
                <button type="submit" class="jp-btn jp-btn--sm">Save prefix</button>
            </div>
        </form>
    </div>
@elseif ($activeTab === 'owner')
    <div class="jp-card" data-testid="admin-agency-tab-owner">
        @if ($ownerUser)
            <p><strong>Name:</strong> {{ $ownerUser->name }}</p>
            <p><strong>Email:</strong> {{ $ownerUser->email }}</p>
            <p><strong>Status:</strong> <x-themes.admin.jetpakistan.components.status-badge :label="$ownerUser->status?->value ?? 'unknown'" /></p>
            <a href="{{ client_route('admin.users.show', $ownerUser) }}" class="jp-btn jp-btn--sm jp-btn--outline">Open in Users &amp; Access</a>
        @else
            <x-themes.admin.jetpakistan.components.empty-state title="No owner linked" message="No agency owner user is linked to this agency yet." />
        @endif
    </div>
@elseif ($activeTab === 'staff')
    <div class="jp-dtable-wrap" data-testid="admin-agency-tab-staff">
        <table class="jp-dtable">
            <thead><tr><th>Name</th><th>Email</th><th>Access mode</th><th>Status</th><th>Last login</th><th></th></tr></thead>
            <tbody>
            @forelse($staffMembers as $member)
                <tr>
                    <td>{{ $member->name }}</td>
                    <td>{{ $member->email }}</td>
                    <td>{{ AccountTypeLabels::accessModeLabel($member) }}</td>
                    <td><x-themes.admin.jetpakistan.components.status-badge :label="$member->status?->value ?? 'unknown'" /></td>
                    <td>{{ $member->last_login_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                    <td><a href="{{ client_route('admin.users.show', $member) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Access</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-themes.admin.jetpakistan.components.empty-state title="No staff" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@elseif ($activeTab === 'markups')
    <div class="jp-dtable-wrap" data-testid="admin-agency-tab-markups">
        <table class="jp-dtable">
            <thead><tr><th>Rule</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($markupRuleRows as $rule)
                <tr>
                    <td>{{ $rule['name'] }}</td>
                    <td>{{ $rule['type'] }}</td>
                    <td><x-themes.admin.jetpakistan.components.status-badge :label="$rule['status']" /></td>
                </tr>
            @empty
                <tr><td colspan="3"><x-themes.admin.jetpakistan.components.empty-state title="No markup rules" /></td></tr>
            @endforelse
            </tbody>
        </table>
        @if (Route::has('admin.markups'))
            <div style="padding: 12px;"><a href="{{ client_route('admin.markups') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Open markups admin</a></div>
        @endif
    </div>
@elseif ($activeTab === 'wallet')
    <div class="jp-card" data-testid="admin-agency-tab-wallet">
        <div class="jp-kpis jp-kpis--compact">
            <div class="jp-kpi"><div class="jp-kpi__l">Balance</div><div class="jp-kpi__v" style="font-size:1rem;">{{ $walletSummary['currency'] }} {{ number_format((float) $walletSummary['balance'], 2) }}</div></div>
            <div class="jp-kpi"><div class="jp-kpi__l">Available</div><div class="jp-kpi__v" style="font-size:1rem;">{{ $walletSummary['currency'] }} {{ number_format((float) $walletSummary['available_balance'], 2) }}</div></div>
            <div class="jp-kpi"><div class="jp-kpi__l">Pending deposits</div><div class="jp-kpi__v" style="font-size:1rem;">{{ $walletSummary['currency'] }} {{ number_format((float) $walletSummary['pending_deposits'], 2) }}</div></div>
        </div>
    </div>
@elseif ($activeTab === 'deposits')
    <div class="jp-dtable-wrap" data-testid="admin-agency-tab-deposits">
        <table class="jp-dtable">
            <thead><tr><th>ID</th><th class="num">Amount</th><th>Status</th><th>Submitted</th></tr></thead>
            <tbody>
            @forelse($depositRequests as $deposit)
                <tr>
                    <td><a href="{{ client_route('admin.agent-deposits.show', $deposit['id']) }}">#{{ $deposit['id'] }}</a></td>
                    <td class="num">{{ number_format((float) $deposit['amount'], 2) }}</td>
                    <td>{{ $deposit['status'] }}</td>
                    <td>{{ $deposit['created_at'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><x-themes.admin.jetpakistan.components.empty-state title="No deposits" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@elseif ($activeTab === 'bookings')
    <div class="jp-dtable-wrap" data-testid="admin-agency-tab-bookings">
        <table class="jp-dtable">
            <thead><tr><th>Reference</th><th>Route</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
            @forelse($recentBookings as $booking)
                <tr>
                    <td><a href="{{ client_route('admin.bookings.show', $booking['id']) }}">{{ $booking['booking_reference'] }}</a></td>
                    <td>{{ $booking['route'] }}</td>
                    <td>{{ $booking['status'] }}</td>
                    <td>{{ $booking['created_at'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><x-themes.admin.jetpakistan.components.empty-state title="No bookings" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@elseif ($activeTab === 'travelers')
    <div class="jp-dtable-wrap" data-testid="admin-agency-tab-travelers">
        <table class="jp-dtable">
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
                <tr><td colspan="4"><x-themes.admin.jetpakistan.components.empty-state title="No travelers" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@elseif ($activeTab === 'support')
    <div class="jp-dtable-wrap" data-testid="admin-agency-tab-support">
        <table class="jp-dtable">
            <thead><tr><th>Subject</th><th>Status</th><th>Priority</th><th>Created</th></tr></thead>
            <tbody>
            @forelse($supportTickets as $ticket)
                <tr>
                    <td><a href="{{ client_route('admin.support.tickets.show', $ticket['id']) }}">{{ $ticket['subject'] }}</a></td>
                    <td>{{ $ticket['status'] }}</td>
                    <td>{{ $ticket['priority'] }}</td>
                    <td>{{ $ticket['created_at'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><x-themes.admin.jetpakistan.components.empty-state title="No tickets" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@elseif ($activeTab === 'activity')
    <div class="jp-dtable-wrap" data-testid="admin-agency-tab-activity">
        <table class="jp-dtable">
            <thead><tr><th>Action</th><th>{{ IdentityDisplay::labelPerformedBy() }}</th><th>When</th></tr></thead>
            <tbody>
            @forelse($auditLogs as $log)
                <tr>
                    <td>
                        {{ $log['action'] }}
                        @if (! empty($log['details']))
                            <div class="jp-cell-sub">{{ \Illuminate\Support\Str::limit(json_encode($log['details'], JSON_UNESCAPED_UNICODE), 240) }}</div>
                        @endif
                    </td>
                    <td>{{ $log['actor_label'] ?? 'System' }} <span class="jp-cell-sub">{{ $log['actor_code'] }}</span></td>
                    <td>{{ $log['created_at'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3"><x-themes.admin.jetpakistan.components.empty-state title="No activity" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endif
@endsection
