@php
    use App\Support\Platform\PlatformModuleGate as ModuleGate;
    use Illuminate\Support\Facades\Route;

    $customerNavItems = [
        ['route' => 'customer.dashboard', 'label' => 'Overview', 'icon' => 'plane', 'match' => 'customer.dashboard', 'module' => 'customer_portal'],
        ['route' => 'customer.bookings.index', 'label' => 'My trips', 'icon' => 'calendar', 'match' => 'customer.bookings.*', 'module' => 'customer_portal'],
        ['route' => 'customer.travelers.index', 'label' => 'Travelers', 'icon' => 'users', 'match' => 'customer.travelers.*', 'module' => 'saved_travelers'],
        ['route' => 'customer.support.tickets.index', 'label' => 'Support', 'icon' => 'chat', 'match' => 'customer.support.*', 'module' => 'support_system'],
    ];
@endphp
<nav class="jp-portal__nav" aria-label="Customer account" data-testid="customer-account-subnav">
  @foreach ($customerNavItems as $item)
    @if (! Route::has($item['route']))
      @continue
    @endif
    @php
        $moduleKey = $item['module'] ?? null;
        if ($moduleKey !== null && ! ModuleGate::visible($moduleKey)) {
            continue;
        }
    @endphp
    <a href="{{ client_route($item['route']) }}" @class(['is-active' => request()->routeIs($item['match'])])>
      <x-jp.icon :name="$item['icon']" />
      <span>{{ $item['label'] }}</span>
    </a>
  @endforeach
  <a href="{{ client_route('flights.search') }}">
    <x-jp.icon name="search" />
    <span>Search flights</span>
  </a>
</nav>
