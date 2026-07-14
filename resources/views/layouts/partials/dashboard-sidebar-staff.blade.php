@php
    use App\Support\Platform\PlatformModuleGate as ModuleGate;
    use App\Support\Staff\StaffPermission;

    $staffUser = auth()->user();
    $canLedger = ($staffUser?->hasStaffPermission(StaffPermission::LedgerView) ?? false) && ModuleGate::visible('finance_reports');
    $canReports = ($staffUser?->hasStaffPermission(StaffPermission::ReportsView) ?? false) && ModuleGate::visible('finance_reports');

    $navStaffPortal = ModuleGate::visible('staff_portal');
    $navPublicSite = ModuleGate::visible('public_site');
    $navSupport = ModuleGate::visible('support_system');
@endphp
<ul class="navbar-nav pt-lg-3">
    @if ($navStaffPortal)
        <li class="nav-item">
            <div class="ota-sidebar-section"><span>Staff</span></div>
        </li>
    @endif
    @if ($navPublicSite)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ client_route('home') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-world"></i></span>
                <span class="nav-link-title">Public OTA home</span>
            </a>
        </li>
    @endif
    @if ($navStaffPortal)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('staff.dashboard') ? 'active' : '' }}" href="{{ client_route('staff.dashboard') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-dashboard"></i></span>
                <span class="nav-link-title">Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('staff.bookings.index') && request()->boolean('assigned_to_me') ? 'active' : '' }}" href="{{ ui_preserve_route('staff.bookings.index', ['assigned_to_me' => 1]) }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-user-check"></i></span>
                <span class="nav-link-title">Assigned bookings</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('staff.bookings.index') && ! request()->boolean('assigned_to_me') && request()->query('queue', 'all') === 'all' ? 'active' : '' }}" href="{{ ui_preserve_route('staff.bookings.index') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-ticket"></i></span>
                <span class="nav-link-title">All bookings</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('staff.bookings.index') && request()->query('queue') === 'payment_review' ? 'active' : '' }}" href="{{ ui_preserve_route('staff.bookings.index', ['queue' => 'payment_review']) }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-cash"></i></span>
                <span class="nav-link-title">Payment review</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('staff.bookings.index') && request()->query('queue') === 'needs_action' ? 'active' : '' }}" href="{{ ui_preserve_route('staff.bookings.index', ['queue' => 'needs_action']) }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-alert-triangle"></i></span>
                <span class="nav-link-title">Manual review</span>
            </a>
        </li>
    @endif
    @if ($canLedger)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('staff.ledger*') ? 'active' : '' }}" href="{{ ui_preserve_route('staff.ledger.index') }}" data-testid="staff-nav-ledger">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-list-details"></i></span>
                <span class="nav-link-title">Master Ledger</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('staff.accounting.ledger*') ? 'active' : '' }}" href="{{ ui_preserve_route('staff.accounting.ledger.index') }}" data-testid="staff-nav-accounting-ledger">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-book-2"></i></span>
                <span class="nav-link-title">Accounting Ledger</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('staff.accounting.reconciliation*') ? 'active' : '' }}" href="{{ ui_preserve_route('staff.accounting.reconciliation.index') }}" data-testid="staff-nav-reconciliation">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-scale"></i></span>
                <span class="nav-link-title">Reconciliation</span>
            </a>
        </li>
    @endif
    @if ($canReports)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('staff.reports*') ? 'active' : '' }}" href="{{ ui_preserve_route('staff.reports.index') }}" data-testid="staff-nav-reports">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-chart-bar"></i></span>
                <span class="nav-link-title">Platform Reports</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('staff.finance.statements*') ? 'active' : '' }}" href="{{ ui_preserve_route('staff.finance.statements.index') }}" data-testid="staff-nav-agent-statements">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-file-invoice"></i></span>
                <span class="nav-link-title">Agent Statements</span>
            </a>
        </li>
    @endif
    @if ($navSupport)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('staff.support.tickets.*') ? 'active' : '' }}" href="{{ ui_preserve_route('staff.support.tickets.index') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-messages"></i></span>
                <span class="nav-link-title">Support tickets</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('support') ? 'active' : '' }}" href="{{ ui_preserve_route('support') }}">
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
    @stack('sidebar-nav')
</ul>
