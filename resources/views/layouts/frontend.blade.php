@php
    /** TourNest: legacy scripts only; public shell is overridden by ota-public.css */
    use App\Services\Agencies\FooterSettingsPresenter;
    use App\Services\Agencies\SlimTopbarPresenter;
    use App\Support\Branding\BrandDisplayResolver;
    use App\Support\Cms\FooterCmsLinks;
    use App\Support\Platform\PlatformModuleGate as ModuleGate;

    $navPublicSite = ModuleGate::visible('public_site');
    $navBookingLookup = ModuleGate::visible('customer_booking_lookup');
    $navSupport = ModuleGate::visible('support_system');
    $navUmrahGroups = ModuleGate::visible('public_umrah_groups');
    $navCustomerRegistration = ModuleGate::visible('customer_registration');
    $navAgentApplications = ModuleGate::visible('agent_applications');

    $tn = asset('vendor/tournest/assets');
    $brand = config('ota-brand', []);
    $client = config('ota-client', []);
    $dbSettings = $agencySettings ?? null;
    $brandName = $brandName ?? BrandDisplayResolver::displayName($dbSettings, auth()->user());
    $partnerAgencyName = (auth()->check() && auth()->user()->isAgentPortalUser())
        ? trim(auth()->user()->agentDisplayAgencyName())
        : '';
    $brandTagline = $dbSettings?->tagline ?: ($client['agency_tagline'] ?? '');
    $publicAgencyContact = $publicAgencyContact ?? \App\Support\Branding\PublicAgencyContactResolver::resolve($dbSettings);
    $brandCssVariables = $brandCssVariables ?? BrandDisplayResolver::cssVariables($dbSettings);
    $logoPath = $dbSettings?->logo_path;
    $headerLogoUrl = null;
    $hasHeaderLogo = is_string($logoPath) && $logoPath !== '';
    $faviconUrl = null;
    $slimTopbar = app(SlimTopbarPresenter::class)->presentForPublic($dbSettings, $client, $brand);
    $footerPresentation = app(FooterCmsLinks::class)->mergeIntoFooterPresentation(
        app(FooterSettingsPresenter::class)->presentForPublic($dbSettings, $client, $brand)
    );
    $previewBranding = \App\Support\Client\ClientPreviewLayoutBranding::apply(
        $brandName,
        $brandTagline,
        $brandCssVariables,
        $headerLogoUrl,
        $hasHeaderLogo,
        $faviconUrl,
        $footerPresentation,
        $slimTopbar,
    );
    extract($previewBranding, EXTR_OVERWRITE);
    $clientThemeMeta = $previewBranding['clientThemeMeta'] ?? [];
    $subContact = rawurlencode($brandName.' support inquiry');
    $pageTitleSection = trim($__env->yieldContent('title'));
    $documentTitle = $pageTitleSection !== ''
        ? BrandDisplayResolver::pageTitle($pageTitleSection, $brandName)
        : $brandName;
@endphp
<!doctype html>
<html @class(['no-js', 'ota-html', 'ui-version-v1' => ($currentUiVersion ?? 'v1') === 'v1', 'ui-version-v2' => ($currentUiVersion ?? 'v1') === 'v2']) lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <meta name="description" content="@yield('meta-description', $brandTagline !== '' ? $brandTagline : ($brand['tagline'] ?? 'Book flights with '.$brandName.'.'))">
    <title>{{ $documentTitle }}</title>
    @stack('head-meta')

    <link rel="shortcut icon" type="image/icon" href="{{ $faviconUrl ?? ($dbSettings?->favicon_path ? asset('storage/'.$dbSettings->favicon_path) : $tn.'/logo/favicon.png') }}"/>
    @if(is_client_preview() && ($clientThemeMeta['frontend_theme'] ?? '') !== '')
        <meta name="ota-client-slug" content="{{ current_client_slug() }}">
        <meta name="ota-client-frontend-theme" content="{{ $clientThemeMeta['frontend_theme'] }}">
        <meta name="ota-client-asset-profile" content="{{ $clientThemeMeta['asset_profile'] ?? '' }}">
    @endif

    <link rel="stylesheet" href="{{ $tn }}/css/font-awesome.min.css" />
    <link rel="stylesheet" href="{{ $tn }}/css/bootstrap.min.css" />
    <link rel="stylesheet" href="{{ ui_asset('css/ota-design-system.css') }}" />
    <link rel="stylesheet" href="{{ ui_asset('css/ota-public.css') }}" />

    <style>
        :root {
            @foreach ($brandCssVariables as $cssVar => $cssValue)
            {{ $cssVar }}: {{ $cssValue }};
            @endforeach
        }
        /* Sprint 6C-P1 — lookup: avoid 100vw fixed submits before ota-public.css paints */
        @media (max-width: 640px) {
            body.ota-public .ota-lookup-page .ota-form-card form > .ota-btn-primary {
                left: max(var(--ota-gutter-x, 1rem), 12px);
                right: max(var(--ota-gutter-x, 1rem), 12px);
                width: auto;
                max-width: none;
                transform: none;
            }
        }
    </style>
    @stack('styles')
    @include('layouts.partials.ui-layer-styles', ['contexts' => $uiLayerContexts ?? ui_layer_contexts()])
</head>

<body @class([
    'ota-public',
    request()->routeIs('home') ? 'ota-page-home' : 'ota-page-inner',
    'ui-v1' => ($currentUiVersion ?? 'v1') === 'v1',
    'ui-v2' => ($currentUiVersion ?? 'v1') === 'v2',
    'ui-preview-namespace' => $isUiPreviewNamespace ?? false,
])>
    <div class="ota-site-header public-header">
        @if ($slimTopbar['is_enabled'] ?? true)
            @php
                $topbarStyle = '';
                foreach ($slimTopbar['css_variables'] ?? [] as $var => $value) {
                    $topbarStyle .= $var.': '.$value.'; ';
                }
            @endphp
            <div class="ota-slim-topbar"@if($topbarStyle !== '') style="{{ trim($topbarStyle) }}"@endif>
                <div class="ota-slim-topbar-inner">
                    @foreach ($slimTopbar['items'] ?? [] as $topbarItem)
                        @php
                            $topbarUrl = $topbarItem['url'] ?? null;
                            $topbarLabel = $topbarItem['label'] ?? '';
                            $topbarIcon = $topbarItem['icon'] ?? 'fa-circle';
                        @endphp
                        @if ($topbarUrl)
                            <a href="{{ e($topbarUrl) }}" class="ota-slim-topbar-item"@if(str_starts_with($topbarUrl, 'http')) target="_blank" rel="noopener noreferrer"@endif>
                                <i class="fa {{ $topbarIcon }}" aria-hidden="true"></i>
                                <span>{{ $topbarLabel }}</span>
                            </a>
                        @else
                            <span class="ota-slim-topbar-item">
                                <i class="fa {{ $topbarIcon }}" aria-hidden="true"></i>
                                <span>{{ $topbarLabel }}</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <header class="ota-main-nav">
            <div class="ota-nav-inner">
                <a href="{{ client_route('home') }}"
                   @class(['ota-brand', 'ota-brand-with-mark', 'ota-brand--has-logo' => $hasHeaderLogo])
                   title="{{ $hasHeaderLogo ? $brandName : ($brandTagline !== '' ? $brandTagline : $brandName) }}"
                   @if($hasHeaderLogo) aria-label="{{ e($brandName) }}"@endif>
                    @if($hasHeaderLogo)
                        <span class="ota-brand-mark ota-brand-mark--logo">
                            <img src="{{ $headerLogoUrl ?? asset('storage/'.$logoPath) }}" alt="{{ e($brandName) }}" class="ota-brand-logo-img">
                        </span>
                    @else
                        <span class="ota-brand-mark" aria-hidden="true">
                            <i class="fa fa-plane"></i>
                        </span>
                        <span class="ota-brand-text" data-testid="header-brand-name">{{ $brandName }}@if($brandTagline !== '' && $partnerAgencyName === '')<small>{{ $brandTagline }}</small>@endif</span>
                    @endif
                </a>
                <input type="checkbox" id="ota-nav-open" class="ota-nav-toggle" autocomplete="off" tabindex="-1">
                <label for="ota-nav-open" class="ota-burger" data-mobile-nav-toggle aria-controls="ota-mobile-nav" aria-expanded="false" aria-label="Open menu"><i class="fa fa-bars"></i></label>
                <label for="ota-nav-open" class="ota-nav-sidebar-backdrop" data-mobile-nav-backdrop aria-hidden="true"></label>
                <nav id="ota-mobile-nav" class="ota-nav-links public-nav" data-public-nav aria-label="Primary">
                    <span class="ota-visually-hidden">Agent Registration</span>
                    <span class="ota-visually-hidden">Signup</span>
                    <div class="ota-nav-links-desktop" data-testid="public-nav-desktop">
                        @if ($navPublicSite)
                            <a href="{{ client_route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}">Home</a>
                        @endif
                        @if ($navBookingLookup)
                            <a href="{{ client_route('booking.lookup') }}" class="{{ request()->routeIs('booking.lookup') ? 'is-active' : '' }}">Booking</a>
                        @endif
                        @if ($navSupport)
                            <a href="{{ client_route('support') }}" class="{{ request()->routeIs('support') ? 'is-active' : '' }}">Support</a>
                        @endif
                        @if ($navPublicSite)
                            <a href="{{ client_route('about') }}" class="{{ request()->routeIs('about') ? 'is-active' : '' }}">About us</a>
                        @endif
                    </div>
                    <div class="ota-nav-actions">
                        @auth
                            <x-account-dropdown variant="desktop" />
                        @else
                            <a href="{{ client_route('login') }}" class="ota-nav-btn ota-nav-btn-secondary">Login</a>
                            @if ($navCustomerRegistration || $navAgentApplications)
                                <div class="public-signup-menu" data-testid="public-nav-signup-menu" aria-label="Sign up options">
                                    @if ($navCustomerRegistration)
                                        <a href="{{ client_route('register') }}" class="public-signup-button ota-nav-btn ota-nav-btn-primary">
                                            Sign Up
                                            @if ($navAgentApplications)
                                                <span class="public-signup-caret" aria-hidden="true"><i class="fa fa-caret-down"></i></span>
                                            @endif
                                        </a>
                                    @elseif ($navAgentApplications)
                                        <a href="{{ client_route('agent.register') }}" class="ota-nav-btn ota-nav-btn-primary" data-testid="public-nav-agent-registration">Agent Registration</a>
                                    @endif
                                    @if ($navCustomerRegistration && $navAgentApplications)
                                        <div class="public-signup-dropdown" role="menu">
                                            <a href="{{ client_route('register') }}" role="menuitem">Sign Up</a>
                                            <a href="{{ client_route('agent.register') }}" role="menuitem" data-testid="public-nav-agent-registration">Agent Registration</a>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            <span class="ota-visually-hidden">Customer Login</span>
                            <span class="ota-visually-hidden">Agent Login</span>
                            <span class="ota-visually-hidden">Operator Login</span>
                        @endauth
                    </div>
                    <div class="ota-nav-mobile-groups" aria-label="Mobile menu sections" data-testid="public-nav-mobile">
                        @if ($navPublicSite)
                            <a href="{{ client_route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}">Home</a>
                        @endif
                        @if ($navBookingLookup)
                            <a href="{{ client_route('booking.lookup') }}" class="{{ request()->routeIs('booking.lookup') ? 'is-active' : '' }}">Booking</a>
                        @endif
                        @if ($navSupport)
                            <a href="{{ client_route('support') }}" class="{{ request()->routeIs('support') ? 'is-active' : '' }}">Support</a>
                        @endif
                        @if ($navPublicSite)
                            <a href="{{ client_route('about') }}" class="{{ request()->routeIs('about') ? 'is-active' : '' }}">About us</a>
                        @endif
                        @auth
                            <x-account-dropdown variant="mobile" />
                        @else
                            <a href="{{ client_route('login') }}" class="ota-nav-mobile-action ota-nav-mobile-action--secondary">Login</a>
                            @if ($navCustomerRegistration)
                                <a href="{{ client_route('register') }}" class="ota-nav-mobile-action ota-nav-mobile-action--primary">Sign Up</a>
                            @endif
                            @if ($navAgentApplications)
                                <a href="{{ client_route('agent.register') }}" class="ota-nav-mobile-action ota-nav-mobile-action--secondary" data-testid="public-nav-mobile-agent-registration">Agent Registration</a>
                            @endif
                        @endauth
                    </div>
                </nav>
            </div>
        </header>
    </div>

    <main class="ota-site-main" id="ota-main">
        @if (session('offer_warning'))
            <div class="container" style="padding-top:16px;">
                <div class="alert alert-warning" role="alert" aria-live="polite">
                    {{ session('offer_warning') }}
                </div>
            </div>
        @endif
        @yield('content')
    </main>

    @include('frontend.partials.ota-footer', [
        'footerPresentation' => $footerPresentation,
        'partnerAgencyName' => $partnerAgencyName,
    ])
    @include('layouts.partials.desktop-mobile-link')
    <script src="{{ $tn }}/js/jquery.js"></script>
    <script src="{{ $tn }}/js/bootstrap.min.js"></script>
    <script>
        (function () {
            var root = document.documentElement;
            var header = document.querySelector('.ota-site-header');
            if (!root || !header) return;

            function syncHeaderOffset() {
                var height = Math.max(0, Math.round(header.getBoundingClientRect().height));
                if (height > 0) {
                    root.style.setProperty('--ota-fixed-header-height', height + 'px');
                }
            }

            syncHeaderOffset();
            window.addEventListener('resize', syncHeaderOffset, { passive: true });
            var toggle = document.getElementById('ota-nav-open');
            var burger = document.querySelector('[data-mobile-nav-toggle]');
            var mobileNav = document.getElementById('ota-mobile-nav');
            var mobileNavMq = window.matchMedia('(max-width: 991px)');
            function syncMobileNavAria() {
                if (!toggle || !burger) return;
                if (!mobileNavMq.matches) {
                    burger.removeAttribute('aria-expanded');
                    burger.setAttribute('aria-label', 'Open menu');
                    if (mobileNav) mobileNav.removeAttribute('aria-hidden');
                    return;
                }
                var open = toggle.checked;
                burger.setAttribute('aria-expanded', open ? 'true' : 'false');
                burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
                if (mobileNav) mobileNav.setAttribute('aria-hidden', open ? 'false' : 'true');
            }
            if (toggle) {
                toggle.addEventListener('change', function () {
                    document.body.classList.toggle('ota-mobile-nav-open', toggle.checked);
                    syncMobileNavAria();
                    window.setTimeout(syncHeaderOffset, 40);
                });
                mobileNavMq.addEventListener('change', function () {
                    if (!mobileNavMq.matches && toggle.checked) {
                        toggle.checked = false;
                        document.body.classList.remove('ota-mobile-nav-open');
                    }
                    syncMobileNavAria();
                });
                syncMobileNavAria();
                document.addEventListener('click', function (event) {
                    if (!toggle.checked) return;
                    var inner = document.querySelector('.ota-nav-inner');
                    if (!inner || inner.contains(event.target)) return;
                    toggle.checked = false;
                    document.body.classList.remove('ota-mobile-nav-open');
                    syncMobileNavAria();
                });
            }

            document.querySelectorAll('[data-account-menu]').forEach(function (menu) {
                var trigger = menu.querySelector('[data-account-trigger]');
                var dropdown = menu.querySelector('[data-account-dropdown]');
                if (!trigger || !dropdown) return;

                function setOpen(open) {
                    trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                    dropdown.hidden = !open;
                }

                trigger.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var open = trigger.getAttribute('aria-expanded') === 'true';
                    document.querySelectorAll('[data-account-menu]').forEach(function (other) {
                        if (other === menu) return;
                        var otherTrigger = other.querySelector('[data-account-trigger]');
                        var otherDropdown = other.querySelector('[data-account-dropdown]');
                        if (!otherTrigger || !otherDropdown) return;
                        otherTrigger.setAttribute('aria-expanded', 'false');
                        otherDropdown.hidden = true;
                    });
                    setOpen(!open);
                });

                document.addEventListener('click', function (event) {
                    if (!menu.contains(event.target)) {
                        setOpen(false);
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        setOpen(false);
                    }
                });
            });
        })();
    </script>
    @stack('scripts')
    @include('layouts.partials.ui-layer-scripts', ['contexts' => $uiLayerContexts ?? ui_layer_contexts()])
    <script>
        (function () {
            var selector = 'input[type="date"], input.ota-hero-search-control--date, input.ota-field--date';

            function getDateInput(target) {
                if (!target || !target.closest) {
                    return null;
                }

                var input = target.closest(selector);

                if (!input || input.disabled || input.readOnly || input.type !== 'date') {
                    return null;
                }

                if (input.hasAttribute('data-return-range-native')
                    && input.closest('.ota-hero-search-dates--round')) {
                    return null;
                }

                return input;
            }

            function openDatePicker(input) {
                if (!input) {
                    return;
                }

                input.focus();

                if (typeof input.showPicker === 'function') {
                    try {
                        input.showPicker();
                    } catch (error) {
                        // Some browsers only allow showPicker during a direct user action.
                    }
                }
            }

            document.addEventListener('pointerdown', function (event) {
                var input = getDateInput(event.target);

                if (input) {
                    openDatePicker(input);
                }
            }, true);

            document.addEventListener('click', function (event) {
                var input = getDateInput(event.target);

                if (input) {
                    openDatePicker(input);
                }
            }, true);
        })();
    </script>
    <script>
        (function () {
            try {
                var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                if (!tz) {
                    return;
                }
                var prefix = 'visitor_timezone=';
                var parts = document.cookie ? document.cookie.split(';') : [];
                for (var i = 0; i < parts.length; i++) {
                    var part = parts[i].trim();
                    if (part.indexOf(prefix) === 0 && decodeURIComponent(part.substring(prefix.length)) === tz) {
                        return;
                    }
                }
                document.cookie = prefix + encodeURIComponent(tz) + ';path=/;max-age=31536000;SameSite=Lax';
            } catch (error) {
                // Ignore unavailable browser timezone APIs.
            }
        })();
    </script>
    @stack('modals')
</body>
</html>
