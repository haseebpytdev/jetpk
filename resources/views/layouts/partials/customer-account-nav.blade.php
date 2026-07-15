@php
    use App\Support\Platform\PlatformModuleGate as ModuleGate;

    $navItems = [
        ['route' => 'customer.dashboard', 'label' => 'Dashboard', 'match' => 'customer.dashboard', 'module' => 'customer_portal'],
        ['route' => 'customer.bookings.index', 'label' => 'My bookings', 'match' => 'customer.bookings.*', 'module' => 'customer_portal'],
        ['route' => 'customer.travelers.index', 'label' => 'Travelers', 'match' => 'customer.travelers.*', 'module' => 'saved_travelers'],
        ['route' => 'customer.support.tickets.index', 'label' => 'Support tickets', 'match' => 'customer.support.tickets.*', 'module' => 'support_system'],
        ['route' => 'profile.edit', 'label' => 'Profile', 'match' => 'profile.*', 'module' => null],
        ['route' => 'booking.lookup', 'label' => 'Lookup booking', 'match' => 'booking.lookup', 'module' => 'customer_booking_lookup'],
    ];
@endphp
<nav class="ota-account-subnav" aria-label="Account sections" data-testid="customer-account-subnav">
    @foreach ($navItems as $item)
        @php
            $moduleKey = $item['module'] ?? null;
            if ($moduleKey !== null && ! ModuleGate::visible($moduleKey)) {
                continue;
            }
        @endphp
        <a
            href="{{ route($item['route']) }}"
            class="ota-account-subnav__link {{ request()->routeIs($item['match']) ? 'is-active' : '' }}"
        >{{ $item['label'] }}</a>
    @endforeach
</nav>
