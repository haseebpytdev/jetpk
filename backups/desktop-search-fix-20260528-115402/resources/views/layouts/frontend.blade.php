@php
    /** TourNest: legacy scripts only; public shell is overridden by ota-public.css */
    use App\Services\Agencies\FooterSettingsPresenter;
    use App\Support\Branding\SafeBrandingResolver;

    $tn = asset('vendor/tournest/assets');
    $brand = config('ota-brand', []);
    $client = config('ota-client', []);
    $dbBranding = $publicBranding ?? SafeBrandingResolver::resolveForPublic();
    $dbSettings = $agencySettings ?? ($dbBranding['settings'] ?? null);
    $brandName = $dbSettings?->display_name ?: ($client['agency_name'] ?? ($brand['product_name'] ?? 'Asif Travels'));
    $partnerAgencyName = null;
    if (auth()->check() && auth()->user()->isAgentPortalUser()) {
        $partnerAgencyName = auth()->user()->agentDisplayAgencyName();
        if ($partnerAgencyName !== '') {
            $brandName = $partnerAgencyName;
        }
    }
    $brandTagline = $dbSettings?->tagline ?: ($client['agency_tagline'] ?? '');
    $supportEmail = $dbSettings?->support_email ?: ($client['support_email'] ?? ($brand['support_email'] ?? ''));
    $supportPhone = $dbSettings?->support_phone ?: ($client['support_phone'] ?? ($brand['support_phone'] ?? ''));
    $supportWhatsapp = $dbSettings?->support_whatsapp ?: ($client['support_whatsapp'] ?? ($brand['support_whatsapp'] ?? ''));
    $clientPrimary = $dbSettings?->primary_color ?: ($client['primary_color'] ?? '#0c4a6e');
    $logoPath = $dbSettings?->logo_path;
    $footerPresentation = app(FooterSettingsPresenter::class)->presentForPublic($dbSettings, $client, $brand);
    $subContact = rawurlencode('Asif Travels support inquiry');
@endphp
<!doctype html>
<html class="no-js ota-html" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <meta name="description" content="{{ $brandTagline !== '' ? $brandTagline : ($brand['tagline'] ?? 'Book flights with Asif Travels.') }}">
    <title>@yield('title', ($brandName ?: config('app.name')))</title>

    <link rel="shortcut icon" type="image/icon" href="{{ $dbSettings?->favicon_path ? asset('storage/'.$dbSettings->favicon_path) : $tn.'/logo/favicon.png' }}"/>

    <link rel="stylesheet" href="{{ $tn }}/css/font-awesome.min.css" />
    <link rel="stylesheet" href="{{ $tn }}/css/bootstrap.min.css" />
    <link rel="stylesheet" href="{{ asset('css/ota-design-system.css') }}?v=5" />
    <link rel="stylesheet" href="{{ asset('css/ota-public.css') }}?v=123" />

    <style>
        :root {
            --client-primary: {{ $clientPrimary }};
        }
    </style>
    @stack('styles')
</head>

<body class="ota-public {{ request()->routeIs('home') ? 'ota-page-home' : 'ota-page-inner' }}">
    <div class="ota-site-header public-header">
        <div class="ota-slim-topbar">
            <div class="ota-slim-topbar-inner">
                <span><i class="fa fa-headphones"></i> 24/7 Support</span>
                <span><i class="fa fa-lock"></i> Secure booking</span>
                <span><i class="fa fa-whatsapp"></i> Fast response</span>
                <span><i class="fa fa-suitcase"></i> Flexible travel options</span>
            </div>
        </div>

        <header class="ota-main-nav">
            <div class="ota-nav-inner">
                <a href="{{ route('home') }}" class="ota-brand ota-brand-with-mark" title="{{ $brandTagline }}">
                    <span class="ota-brand-mark" aria-hidden="true">
                        @if($logoPath)
                            <img src="{{ asset('storage/'.$logoPath) }}" alt="{{ e($brandName) }}" style="width:28px;height:28px;object-fit:contain;border-radius:6px;">
                        @else
                            <i class="fa fa-plane"></i>
                        @endif
                    </span>
                    <span class="ota-brand-text" data-testid="header-brand-name">{{ $brandName }}@if($brandTagline !== '' && $partnerAgencyName === null)<small>{{ $brandTagline }}</small>@endif</span>
                </a>
                <input type="checkbox" id="ota-nav-open" class="ota-nav-toggle" autocomplete="off" tabindex="-1">
                <label for="ota-nav-open" class="ota-burger" data-mobile-nav-toggle aria-controls="ota-mobile-nav" aria-expanded="false" aria-label="Open menu"><i class="fa fa-bars"></i></label>
                <label for="ota-nav-open" class="ota-nav-sidebar-backdrop" data-mobile-nav-backdrop aria-hidden="true"></label>
                <nav id="ota-mobile-nav" class="ota-nav-links public-nav" data-public-nav aria-label="Primary">
                    <span class="ota-visually-hidden">Agent Registration</span>
                    <span class="ota-visually-hidden">Signup</span>
                    <div class="ota-nav-links-desktop" data-testid="public-nav-desktop">
                        <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}">Home</a>
                        <a href="{{ route('booking.lookup') }}" class="{{ request()->routeIs('booking.lookup') ? 'is-active' : '' }}">Booking</a>
                        <a href="{{ route('support') }}" class="{{ request()->routeIs('support') ? 'is-active' : '' }}">Support</a>
                        <a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? 'is-active' : '' }}">About us</a>
                    </div>
                    <div class="ota-nav-actions">
                        @auth
                            <x-account-dropdown variant="desktop" />
                        @else
                            <a href="{{ route('login') }}" class="ota-nav-btn ota-nav-btn-secondary">Login</a>
                            <div class="public-signup-menu" data-testid="public-nav-signup-menu" aria-label="Sign up options">
                                <a href="{{ route('register') }}" class="public-signup-button ota-nav-btn ota-nav-btn-primary">
                                    Sign Up
                                    <span class="public-signup-caret" aria-hidden="true"><i class="fa fa-caret-down"></i></span>
                                </a>
                                <div class="public-signup-dropdown" role="menu">
                                    <a href="{{ route('register') }}" role="menuitem">Sign Up</a>
                                    <a href="{{ route('agent.register') }}" role="menuitem" data-testid="public-nav-agent-registration">Agent Registration</a>
                                </div>
                            </div>
                            <span class="ota-visually-hidden">Customer Login</span>
                            <span class="ota-visually-hidden">Agent Login</span>
                            <span class="ota-visually-hidden">Operator Login</span>
                        @endauth
                    </div>
                    <div class="ota-nav-mobile-groups" aria-label="Mobile menu sections" data-testid="public-nav-mobile">
                        <div class="ota-mobile-menu">
                            <div class="ota-mobile-menu__top">
                                <div class="ota-mobile-menu__brand">
                                    <strong>{{ $brandName }}</strong>
                                    <small>Your travel partner</small>
                                </div>
                                <label for="ota-nav-open" class="ota-mobile-menu__close" aria-label="Close menu">
                                    <i class="fa fa-times" aria-hidden="true"></i>
                                </label>
                            </div>
                            <div class="ota-mobile-menu__section">
                                <p class="ota-mobile-menu__section-title">Main</p>
                                <div class="ota-mobile-menu__links">
                                    <a href="{{ route('home') }}" class="ota-mobile-menu__link {{ request()->routeIs('home') ? 'is-active' : '' }}">Home</a>
                                    <a href="{{ route('booking.lookup') }}" class="ota-mobile-menu__link {{ request()->routeIs('booking.lookup') ? 'is-active' : '' }}">Booking</a>
                                    <a href="{{ route('support') }}" class="ota-mobile-menu__link {{ request()->routeIs('support') ? 'is-active' : '' }}">Support</a>
                                    <a href="{{ route('about') }}" class="ota-mobile-menu__link {{ request()->routeIs('about') ? 'is-active' : '' }}">About us</a>
                                </div>
                            </div>
                            @auth
                                <div class="ota-mobile-menu__section">
                                    <p class="ota-mobile-menu__section-title">Account</p>
                                    <x-account-dropdown variant="mobile" />
                                </div>
                            @else
                                <div class="ota-mobile-menu__section">
                                    <p class="ota-mobile-menu__section-title">Account</p>
                                    <div class="ota-mobile-menu__actions">
                                        <a href="{{ route('login') }}" class="ota-nav-mobile-action ota-nav-mobile-action--secondary ota-mobile-menu__button">Login</a>
                                        <a href="{{ route('register') }}" class="ota-nav-mobile-action ota-nav-mobile-action--primary ota-mobile-menu__button">Sign Up</a>
                                        <a href="{{ route('agent.register') }}" class="ota-nav-mobile-action ota-nav-mobile-action--secondary ota-mobile-menu__button" data-testid="public-nav-mobile-agent-registration">Agent Registration</a>
                                    </div>
                                </div>
                            @endauth
                        </div>
                    </div>
                </nav>
            </div>
        </header>
    </div>

    <main class="ota-site-main" id="ota-main">
        @yield('content')
    </main>

    @include('frontend.partials.ota-footer', [
        'footerPresentation' => $footerPresentation,
        'partnerAgencyName' => $partnerAgencyName,
    ])
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
</body>
</html>
