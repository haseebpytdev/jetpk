@extends('layouts.frontend')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.40.0/dist/tabler-icons.min.css"/>
    <link rel="stylesheet" href="{{ ui_asset('css/ota-portal-console.css') }}" />
@endpush

@section('content')
    @php
        use App\Support\Agents\AgentPermission;
        use App\Support\Platform\PlatformModuleGate as ModuleGate;
        use Illuminate\Support\Facades\Route;

        $agentUser = auth()->user();
        $agentName = trim((string) ($agentUser?->name ?? 'Agent'));
        $agentInitial = strtoupper(substr($agentName !== '' ? $agentName : 'A', 0, 1));
        $agentProfile = $agentUser?->agent();
        $agentMeta = is_array($agentProfile?->meta) ? $agentProfile->meta : [];
        $agentAgencyCandidates = [
            $agentMeta['agency_name'] ?? null,
            $agentMeta['company_name'] ?? null,
            $agentProfile?->agency?->agencySetting?->display_name,
            $agentUser?->currentAgency?->name,
            $agentProfile?->agency?->name,
        ];
        $agentAgencyName = 'Agency Portal';
        foreach ($agentAgencyCandidates as $agentAgencyCandidate) {
            $agentAgencyCandidate = trim((string) $agentAgencyCandidate);
            if ($agentAgencyCandidate !== '') {
                $agentAgencyName = $agentAgencyCandidate;
                break;
            }
        }
        $agentNavItems = [
            ['route' => 'agent.dashboard', 'label' => 'Overview', 'icon' => 'ti-layout-dashboard', 'match' => 'agent.dashboard', 'permission' => null, 'module' => 'agent_portal'],
            ['route' => 'agent.bookings.index', 'label' => 'Flight Bookings', 'icon' => 'ti-plane-departure', 'match' => 'agent.bookings.*', 'permission' => AgentPermission::BookingsView, 'module' => 'agent_portal'],
            ['route' => 'agent.travelers.index', 'label' => 'Travelers / Customers', 'icon' => 'ti-users', 'match' => 'agent.travelers.*', 'permission' => AgentPermission::TravelersManage, 'module' => 'saved_travelers'],
            ['route' => 'agent.commissions.index', 'label' => 'Commissions', 'icon' => 'ti-percentage', 'match' => 'agent.commissions.*', 'permission' => 'agent_admin_only', 'module' => 'agent_portal'],
            ['route' => 'agent.wallet.show', 'label' => 'Wallet', 'icon' => 'ti-wallet', 'match' => 'agent.wallet.*', 'permission' => AgentPermission::WalletView, 'module' => 'agent_wallet'],
            ['route' => 'agent.support.tickets.index', 'label' => 'Support Tickets', 'icon' => 'ti-headset', 'match' => 'agent.support.tickets.*', 'permission' => AgentPermission::SupportManage, 'module' => 'agent_support'],
            ['route' => 'agent.agency.show', 'label' => 'Agency Settings', 'icon' => 'ti-building-store', 'match' => 'agent.agency.*', 'permission' => AgentPermission::AgencyView, 'module' => 'agent_portal'],
            ['route' => 'agent.staff.index', 'label' => 'Agency Staff', 'icon' => 'ti-user-shield', 'match' => 'agent.staff.*', 'permission' => AgentPermission::StaffManage, 'module' => 'agent_staff'],
            ['route' => 'profile.edit', 'label' => 'Profile Settings', 'icon' => 'ti-user-cog', 'match' => 'profile.*', 'permission' => null, 'module' => null],
        ];
    @endphp

    <div class="ota-page-wrap ota-account-page ota-account-page-wrap ota-agent-page ota-agent-shell ota-dashboard-shell ota-agent-dashboard ota-portal-console ota-agent-portal">
        <div class="container ota-account-page-inner ota-account-wrap ota-agent-page-inner">
            <div class="ota-dashboard-shell__grid">
                <aside class="ota-dashboard-sidebar" aria-label="Agent dashboard navigation">
                    <div class="ota-dashboard-sidebar__identity">
                        <span class="ota-dashboard-sidebar__avatar">{{ $agentInitial }}</span>
                        <span>
                            <span class="ota-dashboard-sidebar__eyebrow">Agent Portal</span>
                            <strong>{{ $agentAgencyName }}</strong>
                        </span>
                    </div>

                    <nav class="ota-dashboard-sidebar__nav" aria-label="Agent portal sections" data-testid="agent-portal-subnav">
                        @foreach ($agentNavItems as $item)
                            @if (! Route::has($item['route']))
                                @continue
                            @endif
                            @php
                                $moduleKey = $item['module'] ?? null;
                                if ($moduleKey !== null && ! ModuleGate::visible($moduleKey)) {
                                    continue;
                                }

                                $perm = $item['permission'] ?? null;
                                $allowed = $perm === null
                                    || ($perm === 'agent_admin_only'
                                        ? $agentUser?->isAgentAdmin()
                                        : $agentUser?->hasAgentPermission($perm));
                            @endphp
                            @if (! $allowed)
                                @continue
                            @endif
                            <a
                                href="{{ client_route($item['route']) }}"
                                class="ota-dashboard-sidebar__link {{ request()->routeIs($item['match']) ? 'is-active' : '' }}"
                            >
                                <i class="ti {{ $item['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </nav>

                    <div class="ota-dashboard-sidebar__mini">
                        <div class="ota-dashboard-sidebar__mini-icon"><i class="ti ti-shield-check" aria-hidden="true"></i></div>
                        <div>
                            <strong>{{ $agentName !== '' ? $agentName : 'Agent' }}</strong>
                            <span>Bookings, wallet, and support tools for your agency.</span>
                        </div>
                    </div>
                </aside>

                <main class="ota-dashboard-main">
                    @if (trim($__env->yieldContent('account_title')) !== '' || trim($__env->yieldContent('account_actions')) !== '')
                        <header class="ota-account-header">
                            <div class="ota-account-header-main">
                                @hasSection('account_pretitle')
                                    <p class="ota-account-pretitle">@yield('account_pretitle')</p>
                                @endif
                                <h1 class="ota-account-title">@yield('account_title')</h1>
                                @hasSection('account_subtitle')
                                    <p class="ota-account-subtitle">@yield('account_subtitle')</p>
                                @endif
                            </div>
                            @hasSection('account_actions')
                                <div class="ota-account-header-actions">@yield('account_actions')</div>
                            @endif
                        </header>
                    @endif

                    @yield('account_content')
                </main>
            </div>
        </div>
    </div>
@endsection
