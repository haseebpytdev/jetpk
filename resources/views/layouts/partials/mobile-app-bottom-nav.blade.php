@php
    use App\Support\Platform\PlatformModuleGate as ModuleGate;

    $isCustomerUser = auth()->check() && auth()->user()->isCustomer();
    $isCustomerPortal = request()->routeIs('customer.*')
        || (request()->routeIs('profile.edit') && $isCustomerUser);
    $isAgentPortal = request()->routeIs('agent.*');

    $navPublicSite = ModuleGate::visible('public_site');
    $navPublicFlightSearch = ModuleGate::visible('public_flight_search');
    $navBookingLookup = ModuleGate::visible('customer_booking_lookup');
    $navSupport = ModuleGate::visible('support_system');
    $navCustomerPortal = ModuleGate::visible('customer_portal');
    $navAgentPortal = ModuleGate::visible('agent_portal');
    $navAgentWallet = ModuleGate::visible('agent_wallet');
    $navAgentDeposits = ModuleGate::visible('agent_deposits');
    $navAgentLedger = ModuleGate::visible('agent_ledger');
    $navAgentReports = ModuleGate::visible('agent_reports');
    $navAgentSupport = ModuleGate::visible('agent_support');
@endphp
@if ($isCustomerPortal && auth()->check())
    <nav class="ota-mobile-app__bottom-nav" aria-label="Customer app navigation" data-testid="ota-mobile-app-bottom-nav">
        @if ($navPublicFlightSearch)
        <a
            href="{{ ui_preserve_route('flights.search') }}"
            class="ota-mobile-app__bottom-nav-item {{ request()->routeIs('flights.search', 'home', 'flights.results', 'flights.results.offer', 'flights.details') ? 'is-active' : '' }}"
            data-testid="mobile-nav-customer-search"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">Search</span>
        </a>
        @endif
        @if ($navCustomerPortal)
        <a
            href="{{ ui_preserve_route('customer.bookings.index') }}"
            class="ota-mobile-app__bottom-nav-item {{ request()->routeIs('customer.bookings.*') ? 'is-active' : '' }}"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M22 10.99V18c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2h16c1.1 0 2 .9 2 2v4.99l-2-.01V6H4v12h16v-5.01l2 .01zM14 8l-6 4 6 4V8z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">Bookings</span>
        </a>
        @endif
        @if ($navSupport)
        <a
            href="{{ ui_preserve_route('customer.support.tickets.index') }}"
            class="ota-mobile-app__bottom-nav-item {{ request()->routeIs('customer.support.*') ? 'is-active' : '' }}"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2a7 7 0 00-7 7c0 2.38 1.19 4.47 3 5.74V17a2 2 0 002 2h4a2 2 0 002-2v-2.26c1.81-1.27 3-3.36 3-5.74a7 7 0 00-7-7zm-1 18h2v1a1 1 0 01-2 0v-1z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">Support</span>
        </a>
        @endif
        <a
            href="{{ ui_preserve_route('profile.edit') }}"
            class="ota-mobile-app__bottom-nav-item {{ request()->routeIs('profile.edit', 'customer.dashboard') ? 'is-active' : '' }}"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">Account</span>
        </a>
    </nav>
@elseif ($isAgentPortal && auth()->check())
    @php
        $user = auth()->user();
        $canViewBookings = ($user?->hasAgentPermission(\App\Support\Agents\AgentPermission::BookingsView) ?? false) && $navAgentPortal;
        $canViewWallet = ($user?->hasAgentPermission(\App\Support\Agents\AgentPermission::WalletView) ?? false) && $navAgentWallet;
        $canViewDeposits = ($user?->hasAgentPermission(\App\Support\Agents\AgentPermission::WalletView) ?? false) && $navAgentDeposits;
        $canViewLedger = ($user?->hasAgentPermission(\App\Support\Agents\AgentPermission::LedgerView) ?? false) && $navAgentLedger;
        $canViewReports = ($user?->hasAgentPermission(\App\Support\Agents\AgentPermission::ReportsView) ?? false) && $navAgentReports;
        $canViewCommissions = ($user?->isAgentAdmin() ?? false) && $navAgentPortal;
        $canManageSupport = ($user?->hasAgentPermission(\App\Support\Agents\AgentPermission::SupportManage) ?? false) && ($navAgentSupport || $navSupport);
        $canViewAgency = ($user?->hasAgentPermission(\App\Support\Agents\AgentPermission::AgencyView) ?? false) && $navAgentPortal;

        $bookingsUrl = $canViewBookings ? ui_preserve_route('agent.bookings.index') : ui_preserve_route('agent.dashboard');
        $financeUrl = $canViewWallet
            ? ui_preserve_route('agent.wallet.show')
            : ($canViewDeposits
                ? ui_preserve_route('agent.deposits.index')
                : ($canViewLedger ? ui_preserve_route('agent.ledger.index') : ui_preserve_route('agent.dashboard')));
        $financeActive = request()->routeIs(
            'agent.wallet.*',
            'agent.ledger.*',
            'agent.deposits.*',
            'agent.accounting.ledger.*',
            'agent.reports.*',
            'agent.commissions.*',
            'agent.finance.statement.*',
        );
        $financeLabel = $canViewWallet ? 'Wallet' : ($canViewDeposits ? 'Deposits' : ($canViewLedger ? 'Ledger' : 'Wallet'));
        $showFinanceTab = $canViewWallet || $canViewDeposits || $canViewLedger || $canViewCommissions || $canViewReports;
        if (! $canViewWallet && ! $canViewDeposits && ! $canViewLedger && $canViewCommissions) {
            $financeUrl = ui_preserve_route('agent.commissions.index');
            $financeLabel = 'Finance';
        } elseif (! $canViewWallet && ! $canViewDeposits && ! $canViewLedger && $canViewReports) {
            $financeUrl = ui_preserve_route('agent.reports.index');
            $financeLabel = 'Finance';
        }
        $supportUrl = $canManageSupport ? ui_preserve_route('agent.support.tickets.index') : ui_preserve_route('agent.dashboard');
        $accountUrl = $canViewAgency ? ui_preserve_route('agent.agency.show') : ui_preserve_route('agent.dashboard');
        $accountActive = request()->routeIs('agent.agency.*', 'agent.staff.*');
    @endphp
    <nav class="ota-mobile-app__bottom-nav" aria-label="Agent app navigation" data-testid="ota-mobile-app-bottom-nav">
        @if ($navAgentPortal)
        <a
            href="{{ ui_preserve_route('agent.dashboard') }}"
            class="ota-mobile-app__bottom-nav-item {{ request()->routeIs('agent.dashboard') ? 'is-active' : '' }}"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3l9-8z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">Dashboard</span>
        </a>
        @endif
        @if ($canViewBookings)
        <a
            href="{{ $bookingsUrl }}"
            class="ota-mobile-app__bottom-nav-item {{ request()->routeIs('agent.bookings.*') ? 'is-active' : '' }}"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M22 10.99V18c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2h16c1.1 0 2 .9 2 2v4.99l-2-.01V6H4v12h16v-5.01l2 .01zM14 8l-6 4 6 4V8z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">Bookings</span>
        </a>
        @endif
        @if ($showFinanceTab)
        <a
            href="{{ $financeUrl }}"
            class="ota-mobile-app__bottom-nav-item {{ $financeActive ? 'is-active' : '' }}"
            data-testid="mobile-nav-agent-wallet"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1 1.05.82 1.87 2.65 1.87 1.96 0 2.4-.98 2.4-1.59 0-.83-.44-1.61-2.67-2.14-2.48-.6-4.18-1.62-4.18-3.67 0-1.72 1.39-2.84 3.11-3.21V4h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.87-2.22-1.87-1.5 0-2.4.68-2.4 1.64 0 .84.65 1.39 2.67 1.91s4.18 1.39 4.18 3.91c-.01 1.83-1.38 2.83-3.12 3.16z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">{{ $financeLabel }}</span>
        </a>
        @endif
        @if ($canManageSupport)
        <a
            href="{{ $supportUrl }}"
            class="ota-mobile-app__bottom-nav-item {{ request()->routeIs('agent.support.*') ? 'is-active' : '' }}"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2a7 7 0 00-7 7c0 2.38 1.19 4.47 3 5.74V17a2 2 0 002 2h4a2 2 0 002-2v-2.26c1.81-1.27 3-3.36 3-5.74a7 7 0 00-7-7zm-1 18h2v1a1 1 0 01-2 0v-1z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">Support</span>
        </a>
        @endif
        @if ($navAgentPortal)
        <a
            href="{{ $accountUrl }}"
            class="ota-mobile-app__bottom-nav-item {{ $accountActive ? 'is-active' : '' }}"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">Agency</span>
        </a>
        @endif
    </nav>
@else
    @php
        $accountUrl = auth()->check()
            ? ui_preserve_route('dashboard')
            : client_route('login');
        $accountLabel = auth()->check() ? 'Account' : 'Login';
    @endphp
    <nav class="ota-mobile-app__bottom-nav" aria-label="App navigation" data-testid="ota-mobile-app-bottom-nav">
        @if ($navPublicSite)
        <a
            href="{{ ui_preserve_route('home') }}"
            class="ota-mobile-app__bottom-nav-item {{ request()->routeIs('home', 'flights.search', 'flights.results', 'flights.results.offer', 'flights.details') ? 'is-active' : '' }}"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3l9-8z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">Home</span>
        </a>
        @endif
        @if ($navBookingLookup)
        <a
            href="{{ ui_preserve_route('booking.lookup') }}"
            class="ota-mobile-app__bottom-nav-item {{ request()->routeIs('booking.lookup', 'guest.bookings.show') ? 'is-active' : '' }}"
            data-testid="mobile-nav-booking-lookup"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M7 3h10a2 2 0 012 2v14l-5-3-5 3V5a2 2 0 012-2z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">Booking</span>
        </a>
        @endif
        @if ($navSupport)
        <a
            href="{{ ui_preserve_route('support') }}"
            class="ota-mobile-app__bottom-nav-item {{ request()->routeIs('support') ? 'is-active' : '' }}"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2a7 7 0 00-7 7c0 2.38 1.19 4.47 3 5.74V17a2 2 0 002 2h4a2 2 0 002-2v-2.26c1.81-1.27 3-3.36 3-5.74a7 7 0 00-7-7zm-1 18h2v1a1 1 0 01-2 0v-1z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">Support</span>
        </a>
        @endif
        <a
            href="{{ $accountUrl }}"
            class="ota-mobile-app__bottom-nav-item {{ request()->routeIs('login', 'register', 'password.request', 'password.reset', 'dashboard', 'profile.edit') ? 'is-active' : '' }}"
        >
            <span class="ota-mobile-app__bottom-nav-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z"/></svg>
            </span>
            <span class="ota-mobile-app__bottom-nav-label">{{ $accountLabel }}</span>
        </a>
    </nav>
@endif
