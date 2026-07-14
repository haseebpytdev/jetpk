@php
    use App\Support\Agents\AgentPermission;
    use App\Support\Platform\PlatformModuleGate as ModuleGate;
    use Illuminate\Support\Facades\Route;

    $user = auth()->user();

    $navItems = [
        ['route' => 'agent.dashboard', 'label' => 'Dashboard', 'match' => 'agent.dashboard', 'permission' => null, 'module' => 'agent_portal'],
        ['route' => 'agent.bookings.index', 'label' => 'Bookings', 'match' => 'agent.bookings.*', 'permission' => AgentPermission::BookingsView, 'module' => 'agent_portal'],
        ['route' => 'agent.wallet.show', 'label' => 'Wallet', 'match' => 'agent.wallet.*', 'permission' => AgentPermission::WalletView, 'module' => 'agent_wallet'],
        ['route' => 'agent.ledger.index', 'label' => 'My Ledger', 'match' => 'agent.ledger.*', 'permission' => AgentPermission::LedgerView, 'module' => 'agent_ledger'],
        ['route' => 'agent.accounting.ledger.index', 'label' => 'Accounting Ledger', 'match' => 'agent.accounting.ledger.*', 'permission' => AgentPermission::LedgerView, 'module' => 'agent_ledger'],
        ['route' => 'agent.reports.index', 'label' => 'Agency Reports', 'match' => 'agent.reports.*', 'permission' => AgentPermission::ReportsView, 'module' => 'agent_reports'],
        ['route' => 'agent.finance.statement.show', 'label' => 'Statement', 'match' => 'agent.finance.statement.*', 'permission' => 'agent_statement', 'module' => 'agent_reports'],
        ['route' => 'agent.deposits.index', 'label' => 'Deposits', 'match' => 'agent.deposits.*', 'permission' => AgentPermission::WalletView, 'module' => 'agent_deposits'],
        ['route' => 'agent.commissions.index', 'label' => 'Commissions', 'match' => 'agent.commissions.*', 'permission' => 'agent_admin_only', 'module' => 'agent_portal'],
        ['route' => 'agent.travelers.index', 'label' => 'Travelers', 'match' => 'agent.travelers.*', 'permission' => AgentPermission::TravelersManage, 'module' => 'saved_travelers'],
        ['route' => 'agent.support.tickets.index', 'label' => 'Support tickets', 'match' => 'agent.support.tickets.*', 'permission' => AgentPermission::SupportManage, 'module' => 'agent_support'],
        ['route' => 'agent.agency.show', 'label' => 'Agency details', 'match' => 'agent.agency.*', 'permission' => AgentPermission::AgencyView, 'module' => 'agent_portal'],
        ['route' => 'agent.staff.index', 'label' => 'Staff', 'match' => 'agent.staff.*', 'permission' => AgentPermission::StaffManage, 'module' => 'agent_staff'],
        ['route' => 'profile.edit', 'label' => 'Profile settings', 'match' => 'profile.*', 'permission' => null, 'module' => null],
    ];
@endphp
<div class="ota-account-subnav-wrap ota-agent-subnav-wrap">
    <nav class="ota-account-subnav ota-agent-nav" aria-label="Agent portal sections" data-testid="agent-portal-subnav">
        <div class="ota-account-subnav__track ota-agent-subnav__track">
            @foreach ($navItems as $item)
                @if (Route::has($item['route']))
                    @php
                        $moduleKey = $item['module'] ?? null;
                        if ($moduleKey !== null && ! ModuleGate::visible($moduleKey)) {
                            continue;
                        }

                        $perm = $item['permission'] ?? null;
                        $allowed = $perm === null
                            || ($perm === 'agent_admin_only' ? $user?->isAgentAdmin() : (
                                $perm === 'agent_statement'
                                    ? ($user?->hasAgentPermission(AgentPermission::ReportsView) || $user?->hasAgentPermission(AgentPermission::LedgerView))
                                    : $user?->hasAgentPermission($perm)
                            ));
                    @endphp
                    @if (! $allowed)
                        @continue
                    @endif
                    <a
                        href="{{ route($item['route']) }}"
                        class="ota-account-subnav__link {{ request()->routeIs($item['match']) ? 'is-active' : '' }}"
                    >{{ $item['label'] }}</a>
                @endif
            @endforeach
        </div>
    </nav>
</div>
