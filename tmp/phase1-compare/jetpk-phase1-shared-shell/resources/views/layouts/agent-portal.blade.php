{{--
  layouts/agent-portal.blade.php — REFACTORED for Phase 1 (drop-in replacement).
  JETPK-DASHBOARD-UI-FOUNDATION · baseline 6fbfae4

  Behaviour preserved EXACTLY:
    - @extends('layouts.frontend'); same @push('styles') assets (+ foundation CSS);
    - same nav items and the SAME gating: Route::has() + PlatformModuleGate::visible()
      + agent permission (isAgentAdmin() for 'agent_admin_only', else hasAgentPermission());
    - same agency-name resolution, same wrapper chain, same data-testid
      ("agent-portal-subnav"); same page-facing section contract.
--}}
@extends('layouts.frontend')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.40.0/dist/tabler-icons.min.css"/>
    <link rel="stylesheet" href="{{ ui_asset('css/ota-portal-console.css') }}" />
    <link rel="stylesheet" href="{{ ui_asset('css/ota-dashboard-foundation.css') }}" />
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

        $agentNavSource = [
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

        $agentNavItems = [];
        foreach ($agentNavSource as $item) {
            if (! Route::has($item['route'])) {
                continue;
            }
            $moduleKey = $item['module'] ?? null;
            if ($moduleKey !== null && ! ModuleGate::visible($moduleKey)) {
                continue;
            }
            $perm = $item['permission'] ?? null;
            $allowed = $perm === null
                || ($perm === 'agent_admin_only'
                    ? $agentUser?->isAgentAdmin()
                    : $agentUser?->hasAgentPermission($perm));
            if (! $allowed) {
                continue;
            }
            $agentNavItems[] = [
                'href' => client_route($item['route']),
                'icon' => $item['icon'],
                'label' => $item['label'],
                'match' => $item['match'],
            ];
        }
    @endphp

    <x-dashboard.shell
        role="agent"
        wrap-class="ota-account-page ota-account-page-wrap ota-agent-page ota-agent-shell ota-agent-dashboard ota-portal-console ota-agent-portal"
        inner-class="ota-account-wrap ota-agent-page-inner"
        nav-aria-label="Agent dashboard navigation"
        eyebrow="Agent Portal"
        :identity-name="$agentAgencyName"
        :identity-initial="$agentInitial"
        :nav-items="$agentNavItems"
        nav-testid="agent-portal-subnav"
        mini-icon="ti-shield-check"
        :mini-title="$agentName !== '' ? $agentName : 'Agent'"
        mini-text="Bookings, wallet, and support tools for your agency."
    >
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
    </x-dashboard.shell>
@endsection

@push('scripts')
    <script src="{{ ui_asset('js/ota-dashboard-foundation.js') }}" defer></script>
@endpush
