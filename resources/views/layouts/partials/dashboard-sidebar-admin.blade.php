@php
    use App\Support\Platform\PlatformModuleGate as ModuleGate;

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

    $showOverviewGroup = $navAdminPortal || $navPublicSite;
    $showBookingsOpsGroup = $navAdminPortal || $navSupport || $navFinanceReports || $navGroupTicketing;
    $showBookingQueuesSubmenu = $navAdminPortal;
    $showNetworkGroup = $navAdminPortal || $navAgentApplications || $navAgentDeposits || $navFinanceReports;
    $showAgenciesSubmenu = $navAdminPortal || $navAgentApplications || $navAgentDeposits || $navFinanceReports;
    $showCustomersGroup = $navCustomerPortal || $navSupport;
    $showFinanceGroup = $navFinanceReports || $navMarkupSettings;
    $showSuppliersGroup = $navApiSettings;
    $showWebsiteGroup = $navBrandingSettings || $navNotifications || $navAdminPortal || $navGroupTicketing;

    $overviewActive = request()->routeIs('admin.dashboard', 'home');
    $bookingsOpsActive = request()->routeIs('admin.bookings', 'admin.support.tickets.*', 'admin.reports*', 'admin.group-bookings.*', 'admin.group-ticketing.*');
    $bookingsActive = request()->routeIs('admin.bookings');
    $bookingsQueuesActive = $bookingsActive && request()->query('queue', 'all') !== 'all';
    $customerListActive = request()->routeIs('admin.customers.*');
    $customersActive = $customerListActive || request()->routeIs('admin.support.tickets.*');
    $usersAccessActive = request()->routeIs('admin.users.*');
    $networkActive = request()->routeIs('admin.agencies.*', 'admin.agents', 'admin.agent-applications.*', 'admin.agent-deposits*', 'admin.commissions*', 'admin.staff')
        || $usersAccessActive;
    $agenciesActive = request()->routeIs('admin.agencies.*', 'admin.agent-applications.*', 'admin.agent-deposits*', 'admin.commissions*');
    $financeActive = request()->routeIs('admin.commissions*', 'admin.markups*', 'admin.ledger*', 'admin.accounting.*', 'admin.finance.*');
    $suppliersActive = request()->routeIs('admin.api-settings*');
    $websiteActive = request()->routeIs(
        'admin.settings.branding.*',
        'admin.settings.homepage.*',
        'admin.settings.media.*',
        'admin.settings.communications.*',
        'admin.cms-pages.*',
        'admin.group-ticketing.*'
    );
    $groupTicketingActive = request()->routeIs('admin.group-ticketing.*');
    $brandingActive = request()->routeIs('admin.settings.branding.*', 'admin.settings.homepage.*');
    $commsActive = request()->routeIs('admin.settings.communications.*');
    $systemActive = request()->routeIs(
        'admin.settings.index',
        'admin.promo-codes.*',
        'admin.roles-permissions',
        'admin.system-health',
        'admin.go-live-checklist',
        'admin.deployment-checklist'
    );
    $accountActive = request()->routeIs('profile.*');
@endphp
<ul class="navbar-nav pt-lg-2 ota-sidebar-compact">
    @if ($showOverviewGroup)
    {{-- Overview --}}
    <li class="nav-item">
        <a class="nav-link ota-sidebar-group {{ $overviewActive ? 'ota-sidebar-parent-active' : '' }}"
           href="#sidebar-group-overview"
           data-bs-toggle="collapse"
           role="button"
           aria-expanded="{{ $overviewActive ? 'true' : 'false' }}"
           aria-controls="sidebar-group-overview">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-layout-dashboard"></i></span>
            <span class="nav-link-title">Overview</span>
            <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
        </a>
        <div class="collapse {{ $overviewActive ? 'show' : '' }}" id="sidebar-group-overview">
            <ul class="ota-submenu">
                @if ($navAdminPortal)
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ client_route('admin.dashboard') }}">Dashboard</a>
                </li>
                @endif
                @if ($navPublicSite)
                <li>
                    <a class="nav-link ota-sidebar-utility {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ client_route('home') }}">Public OTA home</a>
                </li>
                @endif
            </ul>
        </div>
    </li>
    @endif

    @if ($showBookingsOpsGroup)
    {{-- Bookings & Operations --}}
    <li class="nav-item">
        <a class="nav-link ota-sidebar-group {{ $bookingsOpsActive ? 'ota-sidebar-parent-active' : '' }}"
           href="#sidebar-group-bookings-ops"
           data-bs-toggle="collapse"
           role="button"
           aria-expanded="{{ $bookingsOpsActive ? 'true' : 'false' }}"
           aria-controls="sidebar-group-bookings-ops">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-ticket"></i></span>
            <span class="nav-link-title">Bookings &amp; Operations</span>
            <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
        </a>
        <div class="collapse {{ $bookingsOpsActive ? 'show' : '' }}" id="sidebar-group-bookings-ops">
            <ul class="ota-submenu">
                @if ($navAdminPortal)
                <li>
                    <a class="nav-link {{ $bookingsActive && request()->query('queue', 'all') === 'all' ? 'active' : '' }}" href="{{ ui_preserve_route('admin.bookings', ['queue' => 'all']) }}">All bookings</a>
                </li>
                @endif
                @if ($navGroupTicketing)
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.group-bookings.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.group-bookings.index') }}">Group bookings</a>
                </li>
                @endif
                @if ($showBookingQueuesSubmenu)
                <li class="ota-submenu-nested">
                    <a class="nav-link ota-submenu-toggle {{ $bookingsQueuesActive ? 'ota-sidebar-parent-active' : '' }}"
                       href="#sidebar-bookings-queues"
                       data-bs-toggle="collapse"
                       role="button"
                       aria-expanded="{{ $bookingsQueuesActive ? 'true' : 'false' }}"
                       aria-controls="sidebar-bookings-queues">
                        <span class="nav-link-title">Booking queues</span>
                        <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
                    </a>
                    <div class="collapse {{ $bookingsQueuesActive ? 'show' : '' }}" id="sidebar-bookings-queues">
                        <ul class="ota-submenu ota-submenu-sub">
                            <li>
                                <a class="nav-link {{ $bookingsActive && request()->query('queue') === 'needs_action' ? 'active' : '' }}" href="{{ ui_preserve_route('admin.bookings', ['queue' => 'needs_action']) }}">Needs action</a>
                            </li>
                            <li>
                                <a class="nav-link {{ $bookingsActive && request()->query('queue') === 'payment_review' ? 'active' : '' }}" href="{{ ui_preserve_route('admin.bookings', ['queue' => 'payment_review']) }}">Payment review</a>
                            </li>
                            <li>
                                <a class="nav-link {{ $bookingsActive && request()->query('queue') === 'supplier_pnr' ? 'active' : '' }}" href="{{ ui_preserve_route('admin.bookings', ['queue' => 'supplier_pnr']) }}">Supplier / PNR</a>
                            </li>
                            @if ($navTicketing)
                            <li>
                                <a class="nav-link {{ $bookingsActive && request()->query('queue') === 'ticketing' ? 'active' : '' }}" href="{{ ui_preserve_route('admin.bookings', ['queue' => 'ticketing']) }}">Ticketing</a>
                            </li>
                            @endif
                            <li>
                                <a class="nav-link {{ $bookingsActive && request()->query('queue') === 'cancellations' ? 'active' : '' }}" href="{{ ui_preserve_route('admin.bookings', ['queue' => 'cancellations']) }}">Cancellations</a>
                            </li>
                            <li>
                                <a class="nav-link {{ $bookingsActive && request()->query('queue') === 'refunds' ? 'active' : '' }}" href="{{ ui_preserve_route('admin.bookings', ['queue' => 'refunds']) }}">Refunds</a>
                            </li>
                            <li>
                                <a class="nav-link {{ $bookingsActive && request()->query('queue') === 'invoices' ? 'active' : '' }}" href="{{ ui_preserve_route('admin.bookings', ['queue' => 'invoices']) }}">Invoices</a>
                            </li>
                            <li>
                                <a class="nav-link {{ $bookingsActive && request()->query('queue') === 'documents' ? 'active' : '' }}" href="{{ ui_preserve_route('admin.bookings', ['queue' => 'documents']) }}">Documents</a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endif
                @if ($navSupport)
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.support.tickets.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.support.tickets.index') }}">Support tickets</a>
                </li>
                @endif
                @if ($navFinanceReports)
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.reports*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.reports') }}">Platform Reports</a>
                </li>
                @endif
            </ul>
        </div>
    </li>
    @endif

    @if ($showNetworkGroup)
    {{-- Network --}}
    <li class="nav-item">
        <a class="nav-link ota-sidebar-group {{ $networkActive ? 'ota-sidebar-parent-active' : '' }}"
           href="#sidebar-group-network"
           data-bs-toggle="collapse"
           role="button"
           aria-expanded="{{ $networkActive ? 'true' : 'false' }}"
           aria-controls="sidebar-group-network">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-users"></i></span>
            <span class="nav-link-title">Network</span>
            <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
        </a>
        <div class="collapse {{ $networkActive ? 'show' : '' }}" id="sidebar-group-network">
            <ul class="ota-submenu">
                @if ($showAgenciesSubmenu)
                <li class="ota-submenu-nested">
                    <a class="nav-link ota-submenu-toggle {{ $agenciesActive ? 'ota-sidebar-parent-active' : '' }}"
                       href="#sidebar-network-agencies"
                       data-bs-toggle="collapse"
                       role="button"
                       aria-expanded="{{ $agenciesActive ? 'true' : 'false' }}"
                       aria-controls="sidebar-network-agencies">
                        <span class="nav-link-title">Agency Management</span>
                        <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
                    </a>
                    <div class="collapse {{ $agenciesActive ? 'show' : '' }}" id="sidebar-network-agencies">
                        <ul class="ota-submenu ota-submenu-sub">
                            @if ($navAdminPortal)
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.agencies.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.agencies.index') }}">Agencies</a>
                            </li>
                            @endif
                            @if ($navAgentApplications)
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.agent-applications.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.agent-applications.index') }}">Agency applications</a>
                            </li>
                            @endif
                            @if ($navAgentDeposits)
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.agent-deposits*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.agent-deposits.index') }}">Agency deposits</a>
                            </li>
                            @endif
                            @if ($navFinanceReports)
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.commissions*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.commissions.index') }}">Agency commissions</a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endif
                @if ($navAdminPortal)
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.staff') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.staff') }}">Staff</a>
                </li>
                <li>
                    <a class="nav-link {{ $usersAccessActive ? 'active' : '' }}" href="{{ ui_preserve_route('admin.users.index') }}">Users &amp; Access</a>
                </li>
                @endif
            </ul>
        </div>
    </li>
    @endif

    @if ($showCustomersGroup)
    {{-- Customers --}}
    <li class="nav-item">
        <a class="nav-link ota-sidebar-group {{ $customersActive ? 'ota-sidebar-parent-active' : '' }}"
           href="#sidebar-group-customers"
           data-bs-toggle="collapse"
           role="button"
           aria-expanded="{{ $customersActive ? 'true' : 'false' }}"
           aria-controls="sidebar-group-customers">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-user-heart"></i></span>
            <span class="nav-link-title">Customers</span>
            <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
        </a>
        <div class="collapse {{ $customersActive ? 'show' : '' }}" id="sidebar-group-customers">
            <ul class="ota-submenu">
                @if ($navCustomerPortal)
                <li>
                    <a class="nav-link {{ $customerListActive ? 'active' : '' }}" href="{{ ui_preserve_route('admin.customers.index') }}">Customer list</a>
                </li>
                @endif
                @if ($navSupport)
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.support.tickets.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.support.tickets.index') }}">Support tickets</a>
                </li>
                @endif
            </ul>
        </div>
    </li>
    @endif

    @if ($showFinanceGroup)
    {{-- Finance --}}
    <li class="nav-item">
        <a class="nav-link ota-sidebar-group {{ $financeActive ? 'ota-sidebar-parent-active' : '' }}"
           href="#sidebar-group-finance"
           data-bs-toggle="collapse"
           role="button"
           aria-expanded="{{ $financeActive ? 'true' : 'false' }}"
           aria-controls="sidebar-group-finance">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-currency-dollar"></i></span>
            <span class="nav-link-title">Finance</span>
            <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
        </a>
        <div class="collapse {{ $financeActive ? 'show' : '' }}" id="sidebar-group-finance">
            <ul class="ota-submenu">
                @if ($navFinanceReports)
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.finance.dashboard') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.finance.dashboard') }}" data-testid="admin-nav-finance-dashboard">Finance Dashboard</a>
                </li>
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.finance.wallet-audit*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.finance.wallet-audit.index') }}" data-testid="admin-nav-wallet-audit">Wallet Audit</a>
                </li>
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.ledger*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.ledger.index') }}" data-testid="admin-nav-master-ledger">Master Ledger</a>
                </li>
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.accounting.ledger*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.accounting.ledger.index') }}" data-testid="admin-nav-accounting-ledger">Accounting Ledger</a>
                </li>
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.accounting.reconciliation*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.accounting.reconciliation.index') }}" data-testid="admin-nav-reconciliation">Reconciliation</a>
                </li>
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.finance.statements*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.finance.statements.index') }}" data-testid="admin-nav-agent-statements">Agent Statements</a>
                </li>
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.finance.adjustments*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.finance.adjustments.index') }}" data-testid="admin-nav-manual-adjustments">Manual Adjustments</a>
                </li>
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.commissions*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.commissions.index') }}">Commissions</a>
                </li>
                @endif
                @if ($navMarkupSettings)
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.markups*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.markups') }}" data-testid="admin-nav-markups">Markups</a>
                </li>
                @endif
            </ul>
        </div>
    </li>
    @endif

    @if ($showSuppliersGroup)
    {{-- Suppliers --}}
    <li class="nav-item">
        <a class="nav-link ota-sidebar-group {{ $suppliersActive ? 'ota-sidebar-parent-active' : '' }}"
           href="#sidebar-group-suppliers"
           data-bs-toggle="collapse"
           role="button"
           aria-expanded="{{ $suppliersActive ? 'true' : 'false' }}"
           aria-controls="sidebar-group-suppliers">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-api"></i></span>
            <span class="nav-link-title">Suppliers</span>
            <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
        </a>
        <div class="collapse {{ $suppliersActive ? 'show' : '' }}" id="sidebar-group-suppliers">
            <ul class="ota-submenu">
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.api-settings*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.api-settings') }}" data-testid="admin-nav-api-settings">API Settings</a>
                </li>
            </ul>
        </div>
    </li>
    @endif

    @if ($showWebsiteGroup)
    {{-- Website & Comms --}}
    <li class="nav-item">
        <a class="nav-link ota-sidebar-group {{ $websiteActive ? 'ota-sidebar-parent-active' : '' }}"
           href="#sidebar-group-website"
           data-bs-toggle="collapse"
           role="button"
           aria-expanded="{{ $websiteActive ? 'true' : 'false' }}"
           aria-controls="sidebar-group-website">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-world-www"></i></span>
            <span class="nav-link-title">Website &amp; Comms</span>
            <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
        </a>
        <div class="collapse {{ $websiteActive ? 'show' : '' }}" id="sidebar-group-website">
            <ul class="ota-submenu">
                @if ($navBrandingSettings)
                <li class="ota-submenu-nested">
                    <a class="nav-link ota-submenu-toggle {{ $brandingActive ? 'ota-sidebar-parent-active' : '' }}"
                       href="#sidebar-branding-submenu"
                       data-bs-toggle="collapse"
                       role="button"
                       aria-expanded="{{ $brandingActive ? 'true' : 'false' }}"
                       aria-controls="sidebar-branding-submenu">
                        <span class="nav-link-title">Branding</span>
                        <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
                    </a>
                    <div class="collapse {{ $brandingActive ? 'show' : '' }}" id="sidebar-branding-submenu">
                        <ul class="ota-submenu ota-submenu-sub">
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.settings.branding.edit') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.settings.branding.edit') }}">Company profile</a>
                            </li>
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.settings.homepage.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.settings.homepage.edit') }}">Homepage</a>
                            </li>
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.settings.branding.footer.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.settings.branding.footer.edit') }}">Footer</a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endif
                @if ($navAdminPortal)
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.cms-pages.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.cms-pages.index') }}">Website Pages</a>
                </li>
                @endif
                @if ($navBrandingSettings)
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.settings.media.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.settings.media.index') }}">Media Library</a>
                </li>
                @endif
                @if ($navNotifications)
                <li class="ota-submenu-nested">
                    <a class="nav-link ota-submenu-toggle {{ $commsActive ? 'ota-sidebar-parent-active' : '' }}"
                       href="#sidebar-comms-submenu"
                       data-bs-toggle="collapse"
                       role="button"
                       aria-expanded="{{ $commsActive ? 'true' : 'false' }}"
                       aria-controls="sidebar-comms-submenu">
                        <span class="nav-link-title">Communications</span>
                        <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
                    </a>
                    <div class="collapse {{ $commsActive ? 'show' : '' }}" id="sidebar-comms-submenu">
                        <ul class="ota-submenu ota-submenu-sub">
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.settings.communications.index') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.settings.communications.index') }}">Messages</a>
                            </li>
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.settings.communications.templates.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.settings.communications.templates.index') }}">Email Templates</a>
                            </li>
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.settings.communications.notification-events.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.settings.communications.notification-events.index') }}">Notification routing</a>
                            </li>
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.settings.communications.delivery-log.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.settings.communications.delivery-log.index') }}">Delivery log</a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endif
                @if ($navGroupTicketing)
                <li class="ota-submenu-nested">
                    <a class="nav-link ota-submenu-toggle {{ $groupTicketingActive ? 'ota-sidebar-parent-active' : '' }}"
                       href="#sidebar-group-ticketing-submenu"
                       data-bs-toggle="collapse"
                       role="button"
                       aria-expanded="{{ $groupTicketingActive ? 'true' : 'false' }}"
                       aria-controls="sidebar-group-ticketing-submenu">
                        <span class="nav-link-title">Group Ticketing</span>
                        <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
                    </a>
                    <div class="collapse {{ $groupTicketingActive ? 'show' : '' }}" id="sidebar-group-ticketing-submenu">
                        <ul class="ota-submenu ota-submenu-sub">
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.group-ticketing.index') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.group-ticketing.index') }}">Overview</a>
                            </li>
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.group-ticketing.tiles.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.group-ticketing.tiles.index') }}">Homepage Tiles</a>
                            </li>
                            <li>
                                <a class="nav-link {{ request()->routeIs('admin.group-ticketing.inventory.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.group-ticketing.inventory.index') }}">Inventory</a>
                            </li>
                        </ul>
                    </div>
                </li>
                @endif
            </ul>
        </div>
    </li>
    @endif

    @if ($navAdminPortal)
    {{-- System --}}
    <li class="nav-item">
        <a class="nav-link ota-sidebar-group {{ $systemActive ? 'ota-sidebar-parent-active' : '' }}"
           href="#sidebar-group-system"
           data-bs-toggle="collapse"
           role="button"
           aria-expanded="{{ $systemActive ? 'true' : 'false' }}"
           aria-controls="sidebar-group-system">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-settings"></i></span>
            <span class="nav-link-title">System</span>
            <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
        </a>
        <div class="collapse {{ $systemActive ? 'show' : '' }}" id="sidebar-group-system">
            <ul class="ota-submenu">
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.settings.index') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.settings.index') }}">Settings hub</a>
                </li>
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.promo-codes.*') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.promo-codes.index') }}">Promo codes</a>
                </li>
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.roles-permissions') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.roles-permissions') }}">Roles &amp; Permissions</a>
                </li>
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.system-health') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.system-health') }}">System health</a>
                </li>
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.go-live-checklist') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.go-live-checklist') }}">Go-live checklist</a>
                </li>
                <li>
                    <a class="nav-link {{ request()->routeIs('admin.deployment-checklist') ? 'active' : '' }}" href="{{ ui_preserve_route('admin.deployment-checklist') }}">Deployment checklist</a>
                </li>
            </ul>
        </div>
    </li>
    @endif

    {{-- Account --}}
    <li class="nav-item">
        <a class="nav-link ota-sidebar-group {{ $accountActive ? 'ota-sidebar-parent-active' : '' }}"
           href="#sidebar-group-account"
           data-bs-toggle="collapse"
           role="button"
           aria-expanded="{{ $accountActive ? 'true' : 'false' }}"
           aria-controls="sidebar-group-account">
            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-user-circle"></i></span>
            <span class="nav-link-title">Account</span>
            <span class="ota-nav-caret"><i class="ti ti-chevron-down"></i></span>
        </a>
        <div class="collapse {{ $accountActive ? 'show' : '' }}" id="sidebar-group-account">
            <ul class="ota-submenu">
                <li>
                    <a class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ ui_preserve_route('profile.edit') }}">My profile</a>
                </li>
            </ul>
        </div>
    </li>

    @stack('sidebar-nav')
</ul>
