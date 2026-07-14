@php
    use App\Support\Platform\PlatformModuleGate as ModuleGate;

    $dashArea = $dashArea ?? 'admin';
    $dashHomeUrl = $dashHomeUrl ?? client_route('admin.dashboard');
    $dashProductName = $dashProductName ?? client_branding()->companyName();

    $navAdminPortal = ModuleGate::visible('admin_portal');
    $navPublicSite = ModuleGate::visible('public_site');
    $navSupport = ModuleGate::visible('support_system');
    $navFinanceReports = ModuleGate::visible('finance_reports');
    $navTicketing = ModuleGate::visible('ticketing');
    $navAgentApplications = ModuleGate::visible('agent_applications');
    $navAgentDeposits = ModuleGate::visible('agent_deposits');
    $navCustomerPortal = ModuleGate::visible('customer_portal');
    $navApiSettings = ModuleGate::visible('api_settings');
    $navBrandingSettings = ModuleGate::visible('branding_settings');
    $navNotifications = ModuleGate::visible('notifications');
    $navMarkupSettings = ModuleGate::visible('markup_settings');
    $navGroupTicketing = ModuleGate::visible('public_umrah_groups');
    $isOpsArea = in_array($dashArea, ['admin', 'staff'], true);
@endphp
<aside class="jp-side2" id="jp-dash-sidebar" aria-label="Dashboard navigation">
    <div class="jp-side2__top">
        @include('themes.admin.jetpakistan.partials.sidebar-brand', [
            'dashHomeUrl' => $dashHomeUrl,
            'dashProductName' => $dashProductName,
        ])
    </div>
    <div class="jp-side2__scroll">
        <div class="jp-side2__group">
            <div class="jp-side2__label">Overview</div>
            @if ($dashArea === 'admin' && $navAdminPortal)
                <a href="{{ client_route('admin.dashboard') }}" class="jp-navlink @if(request()->routeIs('admin.dashboard')) is-active @endif">
                    <span class="jp-navlink__ic" aria-hidden="true">▣</span>
                    <span class="jp-navlink__txt">Dashboard</span>
                </a>
            @endif
            @if ($dashArea === 'staff')
                <a href="{{ client_route('staff.dashboard') }}" class="jp-navlink @if(request()->routeIs('staff.dashboard')) is-active @endif">
                    <span class="jp-navlink__ic" aria-hidden="true">▣</span>
                    <span class="jp-navlink__txt">Staff home</span>
                </a>
            @endif
            @if ($dashArea === 'agent')
                <a href="{{ client_route('agent.dashboard') }}" class="jp-navlink @if(request()->routeIs('agent.dashboard')) is-active @endif">
                    <span class="jp-navlink__ic" aria-hidden="true">▣</span>
                    <span class="jp-navlink__txt">Agent home</span>
                </a>
            @endif
            @if ($dashArea === 'customer')
                <a href="{{ client_route('customer.dashboard') }}" class="jp-navlink @if(request()->routeIs('customer.dashboard', 'customer.index')) is-active @endif">
                    <span class="jp-navlink__ic" aria-hidden="true">▣</span>
                    <span class="jp-navlink__txt">My trips</span>
                </a>
            @endif
        </div>

        @if ($isOpsArea && ($navAdminPortal || $navSupport || $navFinanceReports || $navGroupTicketing))
            <div class="jp-side2__group">
                <div class="jp-side2__label">Operations</div>
                @if ($navAdminPortal)
                    <a href="{{ client_route($dashArea === 'staff' ? 'staff.bookings.index' : 'admin.bookings') }}" class="jp-navlink @if(request()->routeIs('admin.bookings', 'staff.bookings.*')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">◎</span>
                        <span class="jp-navlink__txt">Bookings</span>
                    </a>
                @endif
                @if ($navPublicSite && $dashArea === 'admin')
                    <a href="{{ client_route('flights.search') }}" class="jp-navlink @if(request()->routeIs('flights.*')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">✈</span>
                        <span class="jp-navlink__txt">Flight search</span>
                    </a>
                @endif
                @if ($navCustomerPortal && $dashArea === 'admin')
                    <a href="{{ client_route('admin.customers.index') }}" class="jp-navlink @if(request()->routeIs('admin.customers.*')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">◇</span>
                        <span class="jp-navlink__txt">Customers</span>
                    </a>
                @endif
                @if ($navApiSettings && $dashArea === 'admin')
                    <a href="{{ client_route('admin.api-settings') }}" class="jp-navlink @if(request()->routeIs('admin.api-settings*')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">⬡</span>
                        <span class="jp-navlink__txt">Suppliers</span>
                    </a>
                @endif
                @if ($navGroupTicketing && $dashArea === 'admin')
                    <a href="{{ client_route('admin.group-ticketing.index') }}" class="jp-navlink @if(request()->routeIs('admin.group-ticketing.*', 'admin.group-bookings.*')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">◈</span>
                        <span class="jp-navlink__txt">Group ticketing</span>
                    </a>
                @endif
            </div>
        @endif

        @if ($dashArea === 'admin' && ($navFinanceReports || $navMarkupSettings))
            <div class="jp-side2__group">
                <div class="jp-side2__label">Finance</div>
                @if ($navFinanceReports)
                    <a href="{{ client_route('admin.reports') }}" class="jp-navlink @if(request()->routeIs('admin.reports*', 'staff.reports*')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">▤</span>
                        <span class="jp-navlink__txt">Reports</span>
                    </a>
                    <a href="{{ client_route('admin.ledger.index') }}" class="jp-navlink @if(request()->routeIs('admin.ledger*', 'admin.accounting.*', 'admin.finance.*')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">▥</span>
                        <span class="jp-navlink__txt">Accounting</span>
                    </a>
                @endif
                @if ($navMarkupSettings)
                    <a href="{{ client_route('admin.markups') }}" class="jp-navlink @if(request()->routeIs('admin.markups*', 'admin.commissions*')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">▦</span>
                        <span class="jp-navlink__txt">Markups</span>
                    </a>
                @endif
            </div>
        @endif

        @if ($navSupport && ($dashArea === 'admin' || $dashArea === 'staff'))
            <div class="jp-side2__group">
                <div class="jp-side2__label">Service</div>
                <a href="{{ client_route($dashArea === 'staff' ? 'staff.support.tickets.index' : 'admin.support.tickets.index') }}" class="jp-navlink @if(request()->routeIs('admin.support.tickets.*', 'staff.support.tickets.*')) is-active @endif">
                    <span class="jp-navlink__ic" aria-hidden="true">☎</span>
                    <span class="jp-navlink__txt">Support tickets</span>
                </a>
            </div>
        @endif

        @if ($dashArea === 'admin' && ($navAdminPortal || $navAgentApplications || $navBrandingSettings || $navNotifications))
            <div class="jp-side2__group">
                <div class="jp-side2__label">Administration</div>
                <a href="{{ client_route('admin.users.index') }}" class="jp-navlink @if(request()->routeIs('admin.users.*')) is-active @endif">
                    <span class="jp-navlink__ic" aria-hidden="true">◉</span>
                    <span class="jp-navlink__txt">Users</span>
                </a>
                @if ($navAgentApplications || $navAgentDeposits)
                    <a href="{{ client_route('admin.agents') }}" class="jp-navlink @if(request()->routeIs('admin.agents*', 'admin.agencies.*', 'admin.agent-applications.*')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">◐</span>
                        <span class="jp-navlink__txt">Agents</span>
                    </a>
                @endif
                @if (current_client_slug())
                    <a href="{{ client_route('admin.page-settings.index') }}" class="jp-navlink @if(request()->routeIs('admin.page-settings.*', 'client.parity.admin.page-settings.*')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">▤</span>
                        <span class="jp-navlink__txt">Page settings</span>
                    </a>
                @endif
                <a href="{{ client_route('admin.settings.index') }}" class="jp-navlink @if(request()->routeIs('admin.settings.index', 'admin.settings.branding.*', 'admin.promo-codes.*', 'admin.roles-permissions')) is-active @endif">
                    <span class="jp-navlink__ic" aria-hidden="true">⚙</span>
                    <span class="jp-navlink__txt">Settings</span>
                </a>
                @if ($navNotifications || $navBrandingSettings)
                    <a href="{{ client_route('admin.settings.communications.index') }}" class="jp-navlink @if(request()->routeIs('admin.settings.communications.*')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">✉</span>
                        <span class="jp-navlink__txt">Communications</span>
                    </a>
                @endif
                @if ($navAdminPortal)
                    <a href="{{ client_route('admin.system-health') }}" class="jp-navlink @if(request()->routeIs('admin.system-health', 'admin.deployment-checklist', 'admin.go-live-checklist')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">⬢</span>
                        <span class="jp-navlink__txt">Diagnostics</span>
                    </a>
                @endif
            </div>
        @endif

        @if ($dashArea === 'agent')
            <div class="jp-side2__group">
                <div class="jp-side2__label">Sales</div>
                <a href="{{ client_route('agent.bookings.index') }}" class="jp-navlink @if(request()->routeIs('agent.bookings.*')) is-active @endif">
                    <span class="jp-navlink__ic" aria-hidden="true">◎</span>
                    <span class="jp-navlink__txt">Bookings</span>
                </a>
                <a href="{{ client_route('agent.wallet.show') }}" class="jp-navlink @if(request()->routeIs('agent.wallet.*')) is-active @endif">
                    <span class="jp-navlink__ic" aria-hidden="true">▥</span>
                    <span class="jp-navlink__txt">Wallet</span>
                </a>
            </div>
        @endif

        @if ($dashArea === 'customer')
            <div class="jp-side2__group">
                <div class="jp-side2__label">Travel</div>
                <a href="{{ client_route('customer.bookings.index') }}" class="jp-navlink @if(request()->routeIs('customer.bookings.*')) is-active @endif">
                    <span class="jp-navlink__ic" aria-hidden="true">◎</span>
                    <span class="jp-navlink__txt">My bookings</span>
                </a>
                @if ($navSupport)
                    <a href="{{ client_route('customer.support.tickets.index') }}" class="jp-navlink @if(request()->routeIs('customer.support.tickets.*')) is-active @endif">
                        <span class="jp-navlink__ic" aria-hidden="true">☎</span>
                        <span class="jp-navlink__txt">Support</span>
                    </a>
                @endif
            </div>
        @endif
    </div>
    <div class="jp-side2__foot">
        @if ($navPublicSite)
            <a href="{{ client_route('home') }}" class="jp-navlink">
                <span class="jp-navlink__ic" aria-hidden="true">↗</span>
                <span class="jp-navlink__txt">View site</span>
            </a>
        @endif
    </div>
</aside>
