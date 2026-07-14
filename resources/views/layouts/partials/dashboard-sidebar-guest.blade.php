@php
    use App\Support\Platform\PlatformModuleGate as ModuleGate;

    $navPublicSite = ModuleGate::visible('public_site');
    $navBookingLookup = ModuleGate::visible('customer_booking_lookup');
@endphp
<ul class="navbar-nav pt-lg-3">
    @if ($navPublicSite || $navBookingLookup)
        <li class="nav-item">
            <div class="ota-sidebar-section"><span>Booking</span></div>
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
    @if ($navBookingLookup)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('booking.lookup') ? 'active' : '' }}" href="{{ client_route('booking.lookup') }}">
                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-search"></i></span>
                <span class="nav-link-title">Lookup booking</span>
            </a>
        </li>
    @endif
    @stack('sidebar-nav')
</ul>
