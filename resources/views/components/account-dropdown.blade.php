@props([
    'variant' => 'desktop',
])

@php
    use App\Support\Auth\LoginDestination;
    use App\Support\Agents\AgentPermission;
    use App\Support\Platform\PlatformModuleGate as ModuleGate;
    use Illuminate\Support\Facades\Route;

    $user = auth()->user();
    $displayName = $user?->isAgentPortalUser()
        ? $user->agentActorName()
        : ($user?->name ?: ($user?->email ?: 'Account'));
    $email = $user?->email ?? '';
    $initials = $user?->displayInitials() ?? 'AC';
    $avatarUrl = $user?->avatarUrl();
    $menuId = $variant === 'mobile' ? 'ota-account-menu-mobile' : 'ota-account-menu';
    $triggerId = $variant === 'mobile' ? 'ota-account-trigger-mobile' : 'ota-account-trigger';

    $roleLabel = match (true) {
        $user?->isCustomer() => 'Customer',
        $user?->isAgentStaff() => 'Agent staff',
        $user?->isAgent() => 'Agent',
        $user?->isStaff() => 'Staff',
        $user?->isPlatformAdmin() => 'Admin',
        $user?->isAgencyAdmin() => 'Legacy',
        default => 'Account',
    };

    $links = [];

    $pushRoute = function (string $routeName, string $label, string|array|null $activePattern = null, array $params = []) use (&$links): void {
        if (! Route::has($routeName)) {
            return;
        }

        $pattern = $activePattern ?? $routeName;
        $links[] = [
            'href' => client_route($routeName, $params),
            'label' => $label,
            'active' => request()->routeIs($pattern),
        ];
    };

    $pushUrl = function (string $href, string $label, string|array $activePattern) use (&$links): void {
        $links[] = [
            'href' => $href,
            'label' => $label,
            'active' => request()->routeIs($activePattern),
        ];
    };

    if ($user?->isCustomer() && ModuleGate::visible('customer_portal')) {
        if (Route::has('customer.dashboard')) {
            $pushRoute('customer.dashboard', 'Dashboard', 'customer.dashboard');
        }
        $pushRoute('customer.bookings.index', 'Bookings', 'customer.bookings.*');
    } elseif ($user?->isAgentPortalUser() && ModuleGate::visible('agent_portal')) {
        $pushUrl(LoginDestination::path($user), 'Dashboard', 'agent.dashboard');

        if ($user->isAgentAdmin()
            || $user->hasAgentPermission(AgentPermission::BookingsView)
            || $user->hasAgentPermission(AgentPermission::BookingsCreate)) {
            $pushRoute('agent.bookings.index', 'Bookings', 'agent.bookings.*');
        }

        if ($user->isAgentAdmin() || $user->hasAgentPermission(AgentPermission::AgencyView)) {
            $pushRoute('agent.agency.show', 'Agency Settings', 'agent.agency.*');
        }
    } elseif ($user?->isStaff() && ModuleGate::visible('staff_portal')) {
        $pushUrl(LoginDestination::path($user), 'Dashboard', 'staff.dashboard');
        $pushRoute('staff.bookings.index', 'Bookings', 'staff.bookings.*');
    } elseif ($user?->isPlatformAdmin() && ModuleGate::visible('admin_portal')) {
        $pushUrl(LoginDestination::path($user), 'Dashboard', 'admin.dashboard');
        $pushRoute('admin.bookings', 'Bookings', 'admin.bookings*');
    } elseif ($user?->isAgencyAdmin()) {
        $pushUrl(LoginDestination::path($user), 'Legacy account notice', 'account.legacy');
    } else {
        $pushUrl(LoginDestination::path($user), 'Dashboard', 'dashboard');
    }

    $pushRoute('profile.edit', 'Profile Settings', 'profile.*');

    $balanceSummary = $user?->agentDropdownBalanceSummary();
@endphp

<div
    class="ota-account-menu ota-account-menu--{{ $variant }}"
    data-account-menu
    data-testid="account-dropdown-{{ $variant }}"
>
    <button
        type="button"
        class="ota-account-trigger"
        id="{{ $triggerId }}"
        data-account-trigger
        aria-haspopup="true"
        aria-expanded="false"
        aria-controls="{{ $menuId }}"
    >
        <span class="ota-account-avatar" aria-hidden="true">
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="" width="26" height="26" loading="lazy" decoding="async">
            @else
                {{ $initials }}
            @endif
        </span>
        <span class="ota-account-label">{{ $displayName }}</span>
        <span class="ota-account-chevron" aria-hidden="true">▾</span>
    </button>

    <div
        class="ota-account-dropdown ota-r-dropdown-panel"
        id="{{ $menuId }}"
        role="menu"
        aria-labelledby="{{ $triggerId }}"
        data-account-dropdown
        hidden
    >
        <div class="ota-account-dropdown-header">
            <div class="ota-account-dropdown-header-top">
                <p class="ota-account-dropdown-name">{{ $displayName }}</p>
                <span class="ota-role-badge">{{ $roleLabel }}</span>
            </div>
            @if ($email !== '')
                <p class="ota-account-dropdown-email">{{ $email }}</p>
            @endif
        </div>

        @if ($balanceSummary)
            @if ($balanceSummary['href'])
                <a
                    href="{{ $balanceSummary['href'] }}"
                    role="menuitem"
                    class="ota-account-balance-box"
                    data-testid="account-dropdown-balance"
                >
                    <span class="ota-account-balance-box__label">{{ $balanceSummary['label'] }}</span>
                    <span class="ota-account-balance-box__amount">{{ $balanceSummary['amount'] }}</span>
                </a>
            @else
                <div class="ota-account-balance-box ota-account-balance-box--static" data-testid="account-dropdown-balance">
                    <span class="ota-account-balance-box__label">{{ $balanceSummary['label'] }}</span>
                    <span class="ota-account-balance-box__amount">{{ $balanceSummary['amount'] }}</span>
                </div>
            @endif
        @endif

        @foreach ($links as $link)
            <a
                href="{{ $link['href'] }}"
                role="menuitem"
                class="ota-account-dropdown-link {{ ! empty($link['active']) ? 'is-active' : '' }}"
                data-testid="account-dropdown-link-{{ \Illuminate\Support\Str::slug($link['label']) }}"
            >{{ $link['label'] }}</a>
        @endforeach

        <div class="ota-account-dropdown-divider" role="separator"></div>

        <form method="POST" action="{{ route('logout') }}" class="ota-account-logout-form" role="none">
            @csrf
            <button type="submit" role="menuitem" class="ota-account-dropdown-logout ota-account-logout" data-testid="account-dropdown-link-logout">Logout</button>
        </form>
    </div>
</div>
