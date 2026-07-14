@php
    use App\Support\Platform\PlatformModuleGate as ModuleGate;

    $agent = auth()->user()?->agent();
    $commissionBalance = 0.0;
    $walletSidebar = null;
    if ($agent !== null) {
        $commissionBalance = (float) app(\App\Services\Agents\AgentCommissionService::class)->calculateBalance($agent);
        $walletSidebar = app(\App\Services\Agents\AgentWalletService::class)->summary($agent);
    }

    $navAgentPortal = ModuleGate::visible('agent_portal');
    $navAgentWallet = ModuleGate::visible('agent_wallet');
    $navAgentDeposits = ModuleGate::visible('agent_deposits');
    $navSavedTravelers = ModuleGate::visible('saved_travelers');
    $navAgentSupport = ModuleGate::visible('agent_support');
    $navSupport = ModuleGate::visible('support_system');
    $showAgentHelpSection = $navAgentSupport || $navSupport;
    $showWalletFinanceCard = $navAgentWallet && $walletSidebar !== null;
@endphp

<ul class="navbar-nav pt-lg-3">
    @if ($navAgentPortal)
        <li class="nav-item">
            <div class="ota-sidebar-section"><span>Agent</span></div>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('agent.dashboard') ? 'active' : '' }}" href="{{ client_route('agent.dashboard') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-dashboard"></i></span>
                <span class="nav-link-title">Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('agent.bookings.create') ? 'active' : '' }}" href="{{ ui_preserve_route('agent.bookings.create') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-plus"></i></span>
                <span class="nav-link-title">Create booking</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('agent.bookings.index') || request()->routeIs('agent.bookings.show') ? 'active' : '' }}" href="{{ ui_preserve_route('agent.bookings.index') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-ticket"></i></span>
                <span class="nav-link-title">My bookings</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('agent.commissions.*') ? 'active' : '' }}" href="{{ ui_preserve_route('agent.commissions.index') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-receipt"></i></span>
                <span class="nav-link-title">Commissions</span>
            </a>
        </li>
    @endif
    @if ($navAgentWallet)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('agent.wallet.*') || request()->routeIs('agent.deposits.*') ? 'active' : '' }}" href="{{ ui_preserve_route('agent.wallet.show') }}" data-testid="agent-sidebar-wallet">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-wallet"></i></span>
                <span class="nav-link-title">Wallet / Deposits</span>
            </a>
        </li>
    @elseif ($navAgentDeposits)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('agent.deposits.*') ? 'active' : '' }}" href="{{ ui_preserve_route('agent.deposits.index') }}" data-testid="agent-sidebar-deposits">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-cash"></i></span>
                <span class="nav-link-title">Deposits</span>
            </a>
        </li>
    @endif
    @if ($navSavedTravelers)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('agent.travelers.*') ? 'active' : '' }}" href="{{ ui_preserve_route('agent.travelers.index') }}" data-testid="agent-sidebar-travelers">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-users"></i></span>
                <span class="nav-link-title">Travelers</span>
            </a>
        </li>
    @endif
    @if ($showAgentHelpSection)
        <li class="nav-item">
            <div class="ota-sidebar-section"><span>Help</span></div>
        </li>
    @endif
    @if ($navAgentSupport)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('agent.support.tickets.*') ? 'active' : '' }}" href="{{ ui_preserve_route('agent.support.tickets.index') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-messages"></i></span>
                <span class="nav-link-title">Support tickets</span>
            </a>
        </li>
    @endif
    @if ($navSupport)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('support') ? 'active' : '' }}" href="{{ client_route('support') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-lifebuoy"></i></span>
                <span class="nav-link-title">Support / Help</span>
            </a>
        </li>
    @endif
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ ui_preserve_route('profile.edit') }}">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-user"></i></span>
            <span class="nav-link-title">Profile</span>
        </a>
    </li>
    @if ($showWalletFinanceCard)
        <li class="nav-item mt-2 px-2" data-testid="agent-sidebar-finance">
            <div class="card card-sm border-0 bg-light">
                <div class="card-body py-2 px-3 small">
                    <div class="text-secondary">Wallet balance</div>
                    <div class="fw-semibold" data-testid="agent-sidebar-wallet-balance">Rs {{ number_format((float) ($walletSidebar['balance'] ?? 0), 2) }}</div>
                    <div class="text-secondary mt-1">Pending deposits: Rs {{ number_format((float) ($walletSidebar['pending_deposits'] ?? 0), 2) }}</div>
                    <div class="text-secondary mt-1">
                        @if ($walletSidebar['credit_enabled'] ?? false)
                            Credit limit: Rs {{ number_format((float) $walletSidebar['credit_limit'], 2) }}
                        @else
                            Credit limit not enabled
                        @endif
                    </div>
                    <a href="{{ ui_preserve_route('agent.wallet.show') }}" class="d-block mt-2 pt-2 border-top small fw-semibold">Deposits / wallet</a>
                    <div class="text-secondary">Commission: Rs {{ number_format($commissionBalance, 2) }}</div>
                </div>
            </div>
        </li>
    @endif
    @stack('sidebar-nav')
</ul>
