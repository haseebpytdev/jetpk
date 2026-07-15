@php
    use App\Support\Platform\PlatformModuleGate as ModuleGate;

    $navCustomerPortal = ModuleGate::visible('customer_portal');
    $navSavedTravelers = ModuleGate::visible('saved_travelers');
    $navSupport = ModuleGate::visible('support_system');
    $navBookingLookup = ModuleGate::visible('customer_booking_lookup');
@endphp
<ul class="navbar-nav pt-lg-3">
    @if ($navCustomerPortal)
        <li class="nav-item">
            <div class="ota-sidebar-section"><span>Customer</span></div>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}" href="{{ client_route('customer.dashboard') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-dashboard"></i></span>
                <span class="nav-link-title">Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('customer.bookings.*') ? 'active' : '' }}" href="{{ ui_preserve_route('customer.bookings.index') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-ticket"></i></span>
                <span class="nav-link-title">My bookings</span>
            </a>
        </li>
    @endif
    @if ($navSavedTravelers)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('customer.travelers.*') ? 'active' : '' }}" href="{{ ui_preserve_route('customer.travelers.index') }}" data-testid="customer-sidebar-travelers">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-users"></i></span>
                <span class="nav-link-title">Travelers</span>
            </a>
        </li>
    @endif
    @if ($navSupport || $navBookingLookup)
        <li class="nav-item">
            <div class="ota-sidebar-section"><span>Help</span></div>
        </li>
    @endif
    @if ($navSupport)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('customer.support.tickets.*') ? 'active' : '' }}" href="{{ ui_preserve_route('customer.support.tickets.index') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-messages"></i></span>
                <span class="nav-link-title">Support tickets</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('support') ? 'active' : '' }}" href="{{ client_route('support') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-lifebuoy"></i></span>
                <span class="nav-link-title">Support / Help</span>
            </a>
        </li>
    @endif
    @if ($navBookingLookup)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('booking.lookup') ? 'active' : '' }}" href="{{ client_route('booking.lookup') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-search"></i></span>
                <span class="nav-link-title">Lookup booking</span>
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
