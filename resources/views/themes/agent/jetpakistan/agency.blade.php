{{-- JP-PORTAL-3 TASK 6 · Agent / Agent Staff agency — show (JetPK theme)
     Resolved by client_view('agency', 'agent'); dashboard.agent.agency remains the fallback for
     default/Parwaaz clients and is NOT modified.
     Route gate: agent.permission:AgencyView.

     PRESERVED EXACTLY:
       • controller vars: $details, $walletSummary, $canEditAgency (Gate::allows('updateAgency')),
         $canViewWallet
       • $d = $details ?? []; $ws = $walletSummary ?? []; $notSet = 'Not set'  (the '—' fallback is
         used ONLY for agent_code — every other field falls back to 'Not set'. Reproduced exactly.)
       • flash: session('status') === 'agency-updated' -> "Agency details saved."
       • IdentityDisplay::labelLegacyAgentProfileCode() + $d['agent_code'] ?? '—'
         + $d['platform_agency_name'] ?? 'this platform'
       • $d['is_complete'] ?? true  — note the TRUE default: absent data must NOT render as
         "Incomplete". @unless is used exactly as legacy does.
       • $d['missing_fields'] implode(', ') note, gated by the same @unless
       • logo: $d['logo_url'] with width/height 72, loading=lazy, decoding=async + placeholder
       • Business information: Agency name, License / registration
       • Contact information: Email, Phone, City, Country, Address
       • Finance summary gated by ($canViewWallet ?? true) && ! empty($ws) — note ?? TRUE
       • wallet figures 'Rs ' hardcoded, 2dp: Wallet balance, Available balance, Credit limit
         (credit_enabled branch -> 'Not enabled'), Pending deposits
       • "View wallet" gated by Route::has('agent.wallet.show') && (hasAgentPermission(WalletView) ?? true)
       • @unless($canEditAgency) no-permission panel + "Contact support" gated by
         Route::has('agent.support.tickets.create') && (hasAgentPermission(SupportManage) ?? false)
       • data-testids: agent-agency-edit-link, agent-agency-details, agent-agency-incomplete,
         agent-agency-business-info, agent-agency-contact-info, agent-agency-missing-fields,
         agent-agency-wallet-summary

     SECURITY: renders only the $details keys the controller exposes. No supplier credentials,
     API data, secrets or other clients' data are reachable from this payload.
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Agency details')

@section('account_title', 'Agency details')
@section('account_subtitle', 'Manage your agency information used for bookings and account verification.')

@section('account_actions')
    @if ($canEditAgency ?? false)
        <a href="{{ route('agent.agency.edit') }}" class="jp-btn jp-btn--primary" data-testid="agent-agency-edit-link">Edit agency details</a>
    @endif
@endsection

@section('account_content')
    @php
        $d = $details ?? [];
        $ws = $walletSummary ?? [];
        $notSet = 'Not set';
    @endphp

    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Agency details'],
    ]" />

    @if (session('status') === 'agency-updated')
        <x-jp.alert variant="success">Agency details saved.</x-jp.alert>
    @endif

    <x-jp.card class="jp-portal__panel" data-testid="agent-agency-details">
        <div class="jp-portal__panel-head">
            <div>
                <h2 class="jp-portal__panel-title">Business profile</h2>
                <p class="jp-portal__panel-lead">
                    {{ \App\Support\Identity\IdentityDisplay::labelLegacyAgentProfileCode() }}
                    <span class="jp-portal__cell-ref">{{ $d['agent_code'] ?? '—' }}</span>
                    on {{ $d['platform_agency_name'] ?? 'this platform' }}.
                </p>
            </div>
            @unless ($d['is_complete'] ?? true)
                <span class="jp-badge jp-badge--warning" data-testid="agent-agency-incomplete">Incomplete</span>
            @endunless
        </div>

        <div class="jp-portal__identity">
            <div class="jp-portal__identity-logo" aria-hidden="true">
                @if (! empty($d['logo_url']))
                    <img src="{{ $d['logo_url'] }}" alt="" width="72" height="72" loading="lazy" decoding="async">
                @else
                    <span class="jp-portal__identity-logo-placeholder"><x-jp.icon name="building-store" /></span>
                @endif
            </div>
            <div>
                <p class="jp-portal__identity-name">{{ $d['agency_name'] ?? $notSet }}</p>
                @unless ($d['is_complete'] ?? true)
                    <p class="jp-portal__identity-hint">Complete your business profile so finance and operations can verify your agency.</p>
                @endunless
            </div>
        </div>
    </x-jp.card>

    <div class="jp-portal__split">
        <x-jp.card class="jp-portal__panel" data-testid="agent-agency-business-info">
            <div class="jp-portal__panel-head">
                <h2 class="jp-portal__panel-title">Business information</h2>
            </div>
            <dl class="jp-portal__facts">
                <dt>Agency name</dt>
                <dd>{{ $d['agency_name'] ?? $notSet }}</dd>

                <dt>License / registration</dt>
                <dd>{{ $d['license_number'] ?? $notSet }}</dd>
            </dl>
        </x-jp.card>

        <x-jp.card class="jp-portal__panel" data-testid="agent-agency-contact-info">
            <div class="jp-portal__panel-head">
                <h2 class="jp-portal__panel-title">Contact information</h2>
            </div>
            <dl class="jp-portal__facts">
                <dt>Email</dt>
                <dd>{{ $d['email'] ?? $notSet }}</dd>

                <dt>Phone</dt>
                <dd>{{ $d['phone'] ?? $notSet }}</dd>

                <dt>City</dt>
                <dd>{{ $d['city'] ?? $notSet }}</dd>

                <dt>Country</dt>
                <dd>{{ $d['country'] ?? $notSet }}</dd>

                <dt>Address</dt>
                <dd>{{ $d['address'] ?? $notSet }}</dd>
            </dl>
        </x-jp.card>
    </div>

    @unless ($d['is_complete'] ?? true)
        <x-jp.alert variant="warning" data-testid="agent-agency-missing-fields">
            Missing: {{ implode(', ', $d['missing_fields'] ?? []) }}.
        </x-jp.alert>
    @endunless

    @if (($canViewWallet ?? true) && ! empty($ws))
        <x-jp.card class="jp-portal__panel" data-testid="agent-agency-wallet-summary">
            <div class="jp-portal__panel-head">
                <div>
                    <h2 class="jp-portal__panel-title">Finance summary</h2>
                    <p class="jp-portal__panel-lead">Prepaid balance and credit limit for your agent account.</p>
                </div>
                @if (Route::has('agent.wallet.show') && (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::WalletView) ?? true))
                    <a href="{{ route('agent.wallet.show') }}" class="jp-btn jp-btn--ghost jp-btn--sm">View wallet</a>
                @endif
            </div>
            <dl class="jp-portal__facts">
                <dt>Wallet balance</dt>
                <dd class="jp-money">Rs {{ number_format((float) ($ws['balance'] ?? 0), 2) }}</dd>

                <dt>Available balance</dt>
                <dd class="jp-money">Rs {{ number_format((float) ($ws['available_balance'] ?? 0), 2) }}</dd>

                <dt>Credit limit</dt>
                <dd class="jp-money">
                    @if ($ws['credit_enabled'] ?? false)
                        Rs {{ number_format((float) $ws['credit_limit'], 2) }}
                    @else
                        Not enabled
                    @endif
                </dd>

                <dt>Pending deposits</dt>
                <dd class="jp-money">Rs {{ number_format((float) ($ws['pending_deposits'] ?? 0), 2) }}</dd>
            </dl>
        </x-jp.card>
    @endif

    @unless ($canEditAgency ?? false)
        <x-jp.card class="jp-portal__panel">
            <p class="jp-portal__note"><strong>Need to update your agency details?</strong></p>
            <p class="jp-portal__note jp-portal__note--muted">You do not have permission to edit agency details. Contact your agency admin or support.</p>
            @if (Route::has('agent.support.tickets.create') && (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::SupportManage) ?? false))
                <a href="{{ route('agent.support.tickets.create') }}" class="jp-btn jp-btn--ghost">Contact support</a>
            @endif
        </x-jp.card>
    @endunless
@endsection
