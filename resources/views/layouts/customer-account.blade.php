{{--
  layouts/customer-account.blade.php — REFACTORED for Phase 1 (drop-in replacement).
  JETPK-DASHBOARD-UI-FOUNDATION · baseline 6fbfae4

  Behaviour preserved EXACTLY:
    - @extends('layouts.frontend'); same @push('styles') assets (+ foundation CSS);
    - same nav items, same PlatformModuleGate gating, same client_route() URLs;
    - same wrapper class chain, same data-testid ("customer-account-subnav");
    - same page-facing section contract: @section('account_content'),
      @yield('account_title' | 'account_pretitle' | 'account_subtitle' | 'account_actions').

  What changed: the shell grid + sidebar markup is now produced by <x-dashboard.shell>
  (single canonical shell) instead of being duplicated inline. No page needs to change.
--}}
@extends('layouts.frontend')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.40.0/dist/tabler-icons.min.css"/>
    <link rel="stylesheet" href="{{ ui_asset('css/ota-portal-console.css') }}" />
    <link rel="stylesheet" href="{{ ui_asset('css/ota-dashboard-foundation.css') }}" />
@endpush

@section('content')
    @php
        use App\Support\Platform\PlatformModuleGate as ModuleGate;

        $accountUser = auth()->user();
        $accountName = trim((string) ($accountUser?->name ?? 'Traveler'));
        $accountInitial = strtoupper(substr($accountName !== '' ? $accountName : 'T', 0, 1));

        $customerNavSource = [
            ['route' => 'customer.dashboard', 'label' => 'Overview', 'icon' => 'ti-layout-dashboard', 'match' => 'customer.dashboard', 'module' => 'customer_portal'],
            ['route' => 'customer.bookings.index', 'label' => 'Bookings', 'icon' => 'ti-calendar-event', 'match' => 'customer.bookings.*', 'module' => 'customer_portal'],
            ['route' => 'customer.travelers.index', 'label' => 'Travelers', 'icon' => 'ti-users', 'match' => 'customer.travelers.*', 'module' => 'saved_travelers'],
            ['route' => 'customer.bookings.index', 'label' => 'Payments', 'icon' => 'ti-credit-card', 'match' => 'customer.bookings.*', 'module' => 'customer_portal', 'params' => ['filter' => 'pending_payment']],
            ['route' => 'customer.support.tickets.index', 'label' => 'Support Tickets', 'icon' => 'ti-headset', 'match' => 'customer.support.tickets.*', 'module' => 'support_system'],
            ['route' => 'profile.edit', 'label' => 'Profile Settings', 'icon' => 'ti-user-cog', 'match' => 'profile.*', 'module' => null],
        ];

        // Gate + resolve to the shell's expected item shape (preserves original visibility rules).
        $customerNavItems = [];
        foreach ($customerNavSource as $item) {
            $moduleKey = $item['module'] ?? null;
            if ($moduleKey !== null && ! ModuleGate::visible($moduleKey)) {
                continue;
            }
            $customerNavItems[] = [
                'href' => client_route($item['route'], $item['params'] ?? []),
                'icon' => $item['icon'],
                'label' => $item['label'],
                'match' => $item['match'],
            ];
        }
    @endphp

    <x-dashboard.shell
        role="customer"
        wrap-class="ota-account-page ota-account-page-wrap ota-customer-dashboard ota-portal-console ota-customer-portal"
        inner-class="ota-account-wrap"
        nav-aria-label="Customer dashboard navigation"
        eyebrow="Customer Portal"
        :identity-name="$accountName !== '' ? $accountName : 'Traveler'"
        :identity-initial="$accountInitial"
        :nav-items="$customerNavItems"
        nav-testid="customer-account-subnav"
        mini-icon="ti-stars"
        mini-title="Travel account"
        mini-text="Bookings, travelers, and support in one place."
    >
        @if (trim($__env->yieldContent('account_title')) !== '' || trim($__env->yieldContent('account_actions')) !== '')
            <header class="ota-account-header">
                <div class="ota-account-header-main">
                    @hasSection('account_pretitle')
                        <p class="ota-account-pretitle">@yield('account_pretitle')</p>
                    @endif
                    <h1 class="ota-account-title">@yield('account_title')</h1>
                    @hasSection('account_subtitle')
                        <p class="ota-account-subtitle">@yield('account_subtitle')</p>
                    @endif
                </div>
                @hasSection('account_actions')
                    <div class="ota-account-header-actions">@yield('account_actions')</div>
                @endif
            </header>
        @endif

        @yield('account_content')
    </x-dashboard.shell>
@endsection

@push('scripts')
    <script src="{{ ui_asset('js/ota-dashboard-foundation.js') }}" defer></script>
@endpush
