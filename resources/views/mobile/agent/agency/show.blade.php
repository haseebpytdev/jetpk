@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Agency details')

@section('mobile_app_title', 'Agency')

@section('mobile_app_top_actions')
    @if ($canEditAgency ?? false)
        <a href="{{ route('agent.agency.edit') }}" class="ota-mobile-app__top-action" data-testid="ota-mobile-agent-agency-edit-link">Edit</a>
    @endif
@endsection

@section('content')
    @php
        $d = $details ?? [];
        $ws = $walletSummary ?? [];
        $notSet = 'Not set';
    @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-agency-show">
        @if (session('status') === 'agency-updated')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Agency details saved.'])
        @endif

        <section class="ota-mobile-agent__card ota-mobile-agent__agency-hero">
            <div class="ota-mobile-agent__agency-logo">
                @if (! empty($d['logo_url']))
                    <img src="{{ $d['logo_url'] }}" alt="" width="72" height="72" loading="lazy" decoding="async">
                @else
                    <span class="ota-mobile-agent__agency-logo-placeholder" aria-hidden="true">🏢</span>
                @endif
            </div>
            <h1 class="ota-mobile-agent__page-title">{{ $d['agency_name'] ?? $notSet }}</h1>
            <p class="ota-mobile-agent__muted">
                Agent code <strong>{{ $d['agent_code'] ?? '—' }}</strong>
                · {{ $d['platform_agency_name'] ?? 'this platform' }}
            </p>
            @unless ($d['is_complete'] ?? true)
                <span class="ota-mobile-agent__pill ota-mobile-agent__pill--pending">Incomplete profile</span>
            @endunless
        </section>

        <section class="ota-mobile-agent__card" data-testid="agent-agency-business-info">
            <h2 class="ota-mobile-agent__card-title">Business information</h2>
            <dl class="ota-mobile-agent__meta">
                <div><dt>Agency name</dt><dd>{{ $d['agency_name'] ?? $notSet }}</dd></div>
                <div><dt>License / registration</dt><dd>{{ $d['license_number'] ?? $notSet }}</dd></div>
            </dl>
        </section>

        <section class="ota-mobile-agent__card" data-testid="agent-agency-contact-info">
            <h2 class="ota-mobile-agent__card-title">Contact information</h2>
            <dl class="ota-mobile-agent__meta">
                <div><dt>Email</dt><dd class="ota-mobile-agent__text-safe">{{ $d['email'] ?? $notSet }}</dd></div>
                <div><dt>Phone</dt><dd>{{ $d['phone'] ?? $notSet }}</dd></div>
                <div><dt>City</dt><dd>{{ $d['city'] ?? $notSet }}</dd></div>
                <div><dt>Country</dt><dd>{{ $d['country'] ?? $notSet }}</dd></div>
                <div><dt>Address</dt><dd>{{ $d['address'] ?? $notSet }}</dd></div>
            </dl>
        </section>

        @unless ($d['is_complete'] ?? true)
            <section class="ota-mobile-agent__card" data-testid="agent-agency-missing-fields">
                @include('mobile.components.alert', ['type' => 'info', 'message' => 'Missing: '.implode(', ', $d['missing_fields'] ?? []).'.'])
            </section>
        @endunless

        @if (($canViewWallet ?? false) && ! empty($ws))
            <section class="ota-mobile-agent__card" data-testid="agent-agency-wallet-summary">
                <div class="ota-mobile-agent__card-head">
                    <h2 class="ota-mobile-agent__card-title">Finance summary</h2>
                    @if (Route::has('agent.wallet.show'))
                        <a href="{{ route('agent.wallet.show') }}" class="ota-mobile-agent__link">View wallet</a>
                    @endif
                </div>
                <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                    <div><dt>Wallet balance</dt><dd class="ota-mobile-agent__amount">Rs {{ number_format((float) ($ws['balance'] ?? 0), 2) }}</dd></div>
                    <div><dt>Available balance</dt><dd class="ota-mobile-agent__amount">Rs {{ number_format((float) ($ws['available_balance'] ?? 0), 2) }}</dd></div>
                    <div>
                        <dt>Credit limit</dt>
                        <dd class="ota-mobile-agent__amount">
                            @if ($ws['credit_enabled'] ?? false)
                                Rs {{ number_format((float) $ws['credit_limit'], 2) }}
                            @else
                                Not enabled
                            @endif
                        </dd>
                    </div>
                    <div><dt>Pending deposits</dt><dd class="ota-mobile-agent__amount">Rs {{ number_format((float) ($ws['pending_deposits'] ?? 0), 2) }}</dd></div>
                </dl>
            </section>
        @endif

        @unless ($canEditAgency ?? false)
            <section class="ota-mobile-agent__card">
                <p class="ota-mobile-agent__note">You do not have permission to edit agency details. Contact your agency admin or support.</p>
                @if (Route::has('agent.support.tickets.create') && (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::SupportManage) ?? false))
                    <a href="{{ route('agent.support.tickets.create') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary">Contact support</a>
                @endif
            </section>
        @endunless
    </div>
@endsection
