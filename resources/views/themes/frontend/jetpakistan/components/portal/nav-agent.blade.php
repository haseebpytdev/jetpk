@php
    use App\Support\Agents\AgentPermission;
    use App\Support\Platform\PlatformModuleGate as ModuleGate;
    use Illuminate\Support\Facades\Route;

    $agentNavItems = [
        ['route' => 'agent.dashboard', 'label' => 'Overview', 'icon' => 'plane', 'match' => 'agent.dashboard', 'permission' => null, 'module' => 'agent_portal'],
        ['route' => 'agent.bookings.index', 'label' => 'Bookings', 'icon' => 'calendar', 'match' => 'agent.bookings.*', 'permission' => AgentPermission::BookingsView, 'module' => 'agent_portal'],
        ['route' => 'agent.bookings.create', 'label' => 'New booking', 'icon' => 'search', 'match' => 'agent.bookings.create', 'permission' => AgentPermission::BookingsCreate, 'module' => 'agent_portal'],
        ['route' => 'agent.travelers.index', 'label' => 'Travelers', 'icon' => 'users', 'match' => 'agent.travelers.*', 'permission' => AgentPermission::TravelersManage, 'module' => 'saved_travelers'],
        ['route' => 'agent.wallet.show', 'label' => 'Wallet', 'icon' => 'shield', 'match' => 'agent.wallet.*', 'permission' => AgentPermission::WalletView, 'module' => 'agent_wallet'],
        ['route' => 'agent.support.tickets.index', 'label' => 'Support', 'icon' => 'chat', 'match' => 'agent.support.tickets.*', 'permission' => AgentPermission::SupportManage, 'module' => 'agent_support'],
        ['route' => 'agent.staff.index', 'label' => 'Agency staff', 'icon' => 'shield-check', 'match' => 'agent.staff.*', 'permission' => AgentPermission::StaffManage, 'module' => 'agent_staff'],
        ['route' => 'agent.agency.show', 'label' => 'Agency', 'icon' => 'map-pin', 'match' => 'agent.agency.*', 'permission' => AgentPermission::AgencyView, 'module' => 'agent_portal'],
    ];
@endphp
<nav class="jp-portal__nav" aria-label="Agent portal" data-testid="agent-portal-subnav">
  @foreach ($agentNavItems as $item)
    @if (! Route::has($item['route']))
      @continue
    @endif
    @php
        $moduleKey = $item['module'] ?? null;
        if ($moduleKey !== null && ! ModuleGate::visible($moduleKey)) {
            continue;
        }
        $perm = $item['permission'] ?? null;
        $allowed = $perm === null || auth()->user()?->hasAgentPermission($perm);
    @endphp
    @if (! $allowed)
      @continue
    @endif
    <a href="{{ client_route($item['route']) }}" @class(['is-active' => request()->routeIs($item['match'])])>
      <x-jp.icon :name="$item['icon']" />
      <span>{{ $item['label'] }}</span>
    </a>
  @endforeach
</nav>
