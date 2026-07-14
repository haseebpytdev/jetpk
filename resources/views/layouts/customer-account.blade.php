@extends('layouts.frontend')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.40.0/dist/tabler-icons.min.css"/>
    <link rel="stylesheet" href="{{ ui_asset('css/ota-portal-console.css') }}" />
@endpush

@section('content')
    @php
        use App\Support\Platform\PlatformModuleGate as ModuleGate;

        $accountUser = auth()->user();
        $accountName = trim((string) ($accountUser?->name ?? 'Traveler'));
        $accountInitial = strtoupper(substr($accountName !== '' ? $accountName : 'T', 0, 1));
        $customerNavItems = [
            ['route' => 'customer.dashboard', 'label' => 'Overview', 'icon' => 'ti-layout-dashboard', 'match' => 'customer.dashboard', 'module' => 'customer_portal'],
            ['route' => 'customer.bookings.index', 'label' => 'Bookings', 'icon' => 'ti-calendar-event', 'match' => 'customer.bookings.*', 'module' => 'customer_portal'],
            ['route' => 'customer.travelers.index', 'label' => 'Travelers', 'icon' => 'ti-users', 'match' => 'customer.travelers.*', 'module' => 'saved_travelers'],
            ['route' => 'customer.bookings.index', 'label' => 'Payments', 'icon' => 'ti-credit-card', 'match' => 'customer.bookings.*', 'module' => 'customer_portal', 'params' => ['filter' => 'pending_payment']],
            ['route' => 'customer.support.tickets.index', 'label' => 'Support Tickets', 'icon' => 'ti-headset', 'match' => 'customer.support.tickets.*', 'module' => 'support_system'],
            ['route' => 'profile.edit', 'label' => 'Profile Settings', 'icon' => 'ti-user-cog', 'match' => 'profile.*', 'module' => null],
        ];
    @endphp

    <div class="ota-page-wrap ota-account-page ota-account-page-wrap ota-dashboard-shell ota-customer-dashboard ota-portal-console ota-customer-portal">
        <div class="container ota-account-page-inner ota-account-wrap">
            <div class="ota-dashboard-shell__grid">
                <aside class="ota-dashboard-sidebar" aria-label="Customer dashboard navigation">
                    <div class="ota-dashboard-sidebar__identity">
                        <span class="ota-dashboard-sidebar__avatar">{{ $accountInitial }}</span>
                        <span>
                            <span class="ota-dashboard-sidebar__eyebrow">Customer Portal</span>
                            <strong>{{ $accountName !== '' ? $accountName : 'Traveler' }}</strong>
                        </span>
                    </div>

                    <nav class="ota-dashboard-sidebar__nav" aria-label="Account sections" data-testid="customer-account-subnav">
                        @foreach ($customerNavItems as $item)
                            @php
                                $moduleKey = $item['module'] ?? null;
                                if ($moduleKey !== null && ! ModuleGate::visible($moduleKey)) {
                                    continue;
                                }
                            @endphp
                            <a
                                href="{{ client_route($item['route'], $item['params'] ?? []) }}"
                                class="ota-dashboard-sidebar__link {{ request()->routeIs($item['match']) ? 'is-active' : '' }}"
                            >
                                <i class="ti {{ $item['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </nav>

                    <div class="ota-dashboard-sidebar__mini">
                        <div class="ota-dashboard-sidebar__mini-icon"><i class="ti ti-stars" aria-hidden="true"></i></div>
                        <div>
                            <strong>Travel account</strong>
                            <span>Bookings, travelers, and support in one place.</span>
                        </div>
                    </div>
                </aside>

                <main class="ota-dashboard-main">
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
                </main>
            </div>
        </div>
    </div>
@endsection
