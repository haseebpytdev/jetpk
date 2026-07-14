@php
    $devCpCssVersion = 1;
    $devCpUserId = session('dev_cp_user_id');
    $devCpUser = $devCpUserId !== null
        ? \App\Models\DeveloperUser::query()->find($devCpUserId)
        : null;
    $jetpkDedicated = function_exists('ota_single_client_root_slug') && ota_single_client_root_slug() === 'jetpk';
    $deploymentLabel = $jetpkDedicated ? 'JetPakistan Deployment' : 'Deployment Owner Controls';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>@yield('title', 'Developer Control Panel') — Developer CP</title>
    <link rel="stylesheet" href="{{ asset('css/devcp.css') }}?v={{ $devCpCssVersion }}">
    @stack('styles')
</head>
<body class="ota-dev-cp-layout">
<div class="ota-dev-cp-shell" id="dev-cp-shell">
    <header class="ota-dev-cp-topbar">
        <div class="container-xl py-3">
            <div class="row g-3 align-items-center">
                <div class="col-lg-4">
                    <div class="ota-dev-cp-brand-title">Developer Control Panel</div>
                    <div class="ota-dev-cp-brand-sub">{{ $deploymentLabel }}</div>
                </div>
                <div class="col-lg-8">
                    <ul class="nav ota-dev-cp-nav flex-wrap gap-1">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.index') ? 'active' : '' }}"
                               href="{{ route('dev.cp.index') }}">Overview</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.users.*') ? 'active' : '' }}"
                               href="{{ route('dev.cp.users.index') }}">Platform Admins</a>
                        </li>
                        @unless ($jetpkDedicated)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.clients.*') ? 'active' : '' }}"
                               href="{{ route('dev.cp.clients.index') }}">Clients</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.companies.*') ? 'active' : '' }}"
                               href="{{ route('dev.cp.companies.index') }}">Companies</a>
                        </li>
                        @endunless
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.modules.*') ? 'active' : '' }}"
                               href="{{ route('dev.cp.modules.index') }}">Modules</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.security-events.*') ? 'active' : '' }}"
                               href="{{ route('dev.cp.security-events.index') }}">Security</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.health') ? 'active' : '' }}"
                               href="{{ route('dev.cp.health') }}">Health</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.sabre') ? 'active' : '' }}"
                               href="{{ route('dev.cp.sabre') }}">Sabre</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.group-ticketing') ? 'active' : '' }}"
                               href="{{ route('dev.cp.group-ticketing') }}">Group</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.dashboards') ? 'active' : '' }}"
                               href="{{ route('dev.cp.dashboards') }}">Dashboards</a>
                        </li>
                        @unless ($jetpkDedicated)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.ui-versions') ? 'active' : '' }}"
                               href="{{ route('dev.cp.ui-versions') }}">UI Versions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.ui-layers*') ? 'active' : '' }}"
                               href="{{ route('dev.cp.ui-layers') }}">UI Layers</a>
                        </li>
                        @endunless
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dev.cp.deployment') ? 'active' : '' }}"
                               href="{{ route('dev.cp.deployment') }}">Deploy</a>
                        </li>
                    </ul>
                </div>
                <div class="col-lg-4 d-flex flex-column align-items-lg-end gap-2">
                    @if ($devCpUser !== null)
                        <div class="ota-dev-cp-user">
                            <strong>{{ $devCpUser->name }}</strong><br>
                            <span>{{ $devCpUser->email }}</span>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('dev.cp.logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-light">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="ota-dev-cp-main">
        <div class="container-xl py-4">
            <div class="alert alert-warning mb-4" role="status">
                <strong>Developer-only area.</strong>
                These controls define deployment-level capabilities and are not client admin settings.
            </div>

            @hasSection('page-header')
                <div class="mb-4">
                    @yield('page-header')
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
@stack('scripts')
</body>
</html>
