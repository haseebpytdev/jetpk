@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Agency details')

@section('account_title', 'Agency details')
@section('account_subtitle', 'Manage your agency information used for bookings and account verification.')

@section('account_actions')
    @if ($canEditAgency ?? false)
        <a href="{{ route('agent.agency.edit') }}" class="ota-account-btn ota-account-btn--primary" data-testid="agent-agency-edit-link">Edit agency details</a>
    @endif
@endsection

@section('account_content')
    @php
        $d = $details ?? [];
        $ws = $walletSummary ?? [];
        $notSet = 'Not set';
    @endphp

    @if (session('status') === 'agency-updated')
        <div class="alert alert-success mb-4">Agency details saved.</div>
    @endif

    <div class="ota-account-card mb-4" data-testid="agent-agency-details">
        <div class="ota-account-card__head">
            <div>
                <h2 class="ota-account-card__title">Business profile</h2>
                <p class="ota-account-card__lead">
                    {{ \App\Support\Identity\IdentityDisplay::labelLegacyAgentProfileCode() }}
                    <span class="fw-semibold">{{ $d['agent_code'] ?? '—' }}</span>
                    on {{ $d['platform_agency_name'] ?? 'this platform' }}.
                </p>
            </div>
            @unless ($d['is_complete'] ?? true)
                <span class="ota-account-badge ota-account-badge--warning" data-testid="agent-agency-incomplete">Incomplete</span>
            @endunless
        </div>
        <div class="ota-account-card__body">
            <div class="ota-agent-agency-profile mb-4">
                <div class="ota-agent-agency-profile__logo" aria-hidden="true">
                    @if (! empty($d['logo_url']))
                        <img src="{{ $d['logo_url'] }}" alt="" width="72" height="72" loading="lazy" decoding="async">
                    @else
                        <span class="ota-agent-agency-profile__logo-placeholder"><i class="ti ti-building-store"></i></span>
                    @endif
                </div>
                <div>
                    <p class="ota-agent-agency-profile__name">{{ $d['agency_name'] ?? $notSet }}</p>
                    @unless ($d['is_complete'] ?? true)
                        <p class="ota-agent-agency-profile__hint">Complete your business profile so finance and operations can verify your agency.</p>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    <div class="ota-account-grid ota-account-grid--2 mb-4">
        <div class="ota-account-card mb-0" data-testid="agent-agency-business-info">
            <div class="ota-account-card__head">
                <h2 class="ota-account-card__title">Business information</h2>
            </div>
            <div class="ota-account-card__body">
                <dl class="ota-account-dl">
                    <div class="ota-account-dl__row">
                        <dt>Agency name</dt>
                        <dd>{{ $d['agency_name'] ?? $notSet }}</dd>
                    </div>
                    <div class="ota-account-dl__row">
                        <dt>License / registration</dt>
                        <dd>{{ $d['license_number'] ?? $notSet }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="ota-account-card mb-0" data-testid="agent-agency-contact-info">
            <div class="ota-account-card__head">
                <h2 class="ota-account-card__title">Contact information</h2>
            </div>
            <div class="ota-account-card__body">
                <dl class="ota-account-dl">
                    <div class="ota-account-dl__row">
                        <dt>Email</dt>
                        <dd>{{ $d['email'] ?? $notSet }}</dd>
                    </div>
                    <div class="ota-account-dl__row">
                        <dt>Phone</dt>
                        <dd>{{ $d['phone'] ?? $notSet }}</dd>
                    </div>
                    <div class="ota-account-dl__row">
                        <dt>City</dt>
                        <dd>{{ $d['city'] ?? $notSet }}</dd>
                    </div>
                    <div class="ota-account-dl__row">
                        <dt>Country</dt>
                        <dd>{{ $d['country'] ?? $notSet }}</dd>
                    </div>
                    <div class="ota-account-dl__row">
                        <dt>Address</dt>
                        <dd>{{ $d['address'] ?? $notSet }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    @unless ($d['is_complete'] ?? true)
        <div class="ota-account-note mb-4" data-testid="agent-agency-missing-fields">
            Missing: {{ implode(', ', $d['missing_fields'] ?? []) }}.
        </div>
    @endunless

    @if (($canViewWallet ?? true) && ! empty($ws))
        <div class="ota-account-card mb-4" data-testid="agent-agency-wallet-summary">
            <div class="ota-account-card__head">
                <div>
                    <h2 class="ota-account-card__title">Finance summary</h2>
                    <p class="ota-account-card__lead">Prepaid balance and credit limit for your agent account.</p>
                </div>
                @if (Route::has('agent.wallet.show') && (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::WalletView) ?? true))
                    <a href="{{ route('agent.wallet.show') }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">View wallet</a>
                @endif
            </div>
            <div class="ota-account-card__body">
                <dl class="ota-account-dl">
                    <div class="ota-account-dl__row">
                        <dt>Wallet balance</dt>
                        <dd>Rs {{ number_format((float) ($ws['balance'] ?? 0), 2) }}</dd>
                    </div>
                    <div class="ota-account-dl__row">
                        <dt>Available balance</dt>
                        <dd>Rs {{ number_format((float) ($ws['available_balance'] ?? 0), 2) }}</dd>
                    </div>
                    <div class="ota-account-dl__row">
                        <dt>Credit limit</dt>
                        <dd>
                            @if ($ws['credit_enabled'] ?? false)
                                Rs {{ number_format((float) $ws['credit_limit'], 2) }}
                            @else
                                Not enabled
                            @endif
                        </dd>
                    </div>
                    <div class="ota-account-dl__row">
                        <dt>Pending deposits</dt>
                        <dd>Rs {{ number_format((float) ($ws['pending_deposits'] ?? 0), 2) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    @endif

    @unless ($canEditAgency ?? false)
        <div class="ota-account-card mb-0">
            <div class="ota-account-card__body">
                <p class="mb-2 fw-semibold">Need to update your agency details?</p>
                <p class="mb-0 text-secondary">You do not have permission to edit agency details. Contact your agency admin or support.</p>
                @if (Route::has('agent.support.tickets.create') && (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::SupportManage) ?? false))
                    <a href="{{ route('agent.support.tickets.create') }}" class="ota-account-btn ota-account-btn--secondary mt-3">Contact support</a>
                @endif
            </div>
        </div>
    @endunless
@endsection
