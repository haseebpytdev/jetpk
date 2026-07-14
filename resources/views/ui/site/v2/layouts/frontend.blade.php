@php
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
    $pageTitleSection = trim($__env->yieldContent('title'));
    $documentTitle = $pageTitleSection !== ''
        ? BrandDisplayResolver::pageTitle($pageTitleSection, $brandName)
        : $brandName;
@endphp
<!doctype html>
<html @class(['no-js', 'ota-html', 'ui-version-v2']) lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
    </style>
    @stack('styles')
</head>
<body @class([
    'ota-public',
    'ui-v2',
    'ota-v2-shell',
    'ui-preview-namespace' => $isUiPreviewNamespace ?? false,
    request()->routeIs('home') ? 'ota-page-home ota-v2-home-page' : 'ota-page-inner',
    request()->routeIs('flights.results*', 'flights.return-options*') ? 'ota-v2-results-page' : null,
    request()->routeIs('flights.details*') ? 'ota-v2-flight-details-page' : null,
    request()->routeIs('booking.*', 'checkout.*') ? 'ota-v2-checkout-page' : null,
    request()->routeIs('group-ticketing.*') ? 'ota-v2-group-page' : null,
    request()->routeIs('booking.lookup*') ? 'ota-v2-lookup-page' : null,
    request()->routeIs('support*') ? 'ota-v2-support-page' : null,
    request()->routeIs('login', 'register', 'password.*') ? 'ota-v2-auth-route' : null,
])>
    @include('ui.site.v2.partials.public-header', [
        'brandName' => $brandName,
        'brandTagline' => $brandTagline,
        'hasHeaderLogo' => $hasHeaderLogo,
        'logoPath' => $logoPath,
        'headerLogoUrl' => $headerLogoUrl ?? null,
        'partnerAgencyName' => $partnerAgencyName,
        'slimTopbar' => $slimTopbar,
        'navPublicSite' => $navPublicSite,
        'navBookingLookup' => $navBookingLookup,
        'navSupport' => $navSupport,
        'navCustomerRegistration' => $navCustomerRegistration,
        'navAgentApplications' => $navAgentApplications,
    ])

    <main class="ota-site-main ota-v2-site-main" id="ota-main" @if(request()->routeIs('home')) data-testid="v2-main-content" @endif>
        @if (session('offer_warning'))
            <div class="ota-v2-page-wrap">
                <div class="ota-v2-alert ota-v2-alert--warning" role="alert" aria-live="polite">
                    {{ session('offer_warning') }}
                </div>
            </div>
        @endif
        @yield('content')
    </main>

    @include('ui.site.v2.partials.public-footer', [
        'footerPresentation' => $footerPresentation,
        'partnerAgencyName' => $partnerAgencyName,
        'brandName' => $brandName,
    ])

    @include('layouts.partials.desktop-mobile-link')

    <script src="{{ $tn }}/js/jquery.js"></script>
    <script src="{{ $tn }}/js/bootstrap.min.js"></script>
    <script>
        (function () {
            var root = document.documentElement;
            var header = document.querySelector('.ota-v2-public-header');
            if (!root || !header) return;

            function syncHeaderOffset() {
                var height = Math.max(0, Math.round(header.getBoundingClientRect().height));
                if (height > 0) {
                    root.style.setProperty('--ota-fixed-header-height', height + 'px');
                    root.style.setProperty('--ota-v2-header-height', height + 'px');
                }
            }

            syncHeaderOffset();
            window.addEventListener('resize', syncHeaderOffset, { passive: true });

            var toggle = document.getElementById('ota-v2-nav-open');
            var burger = document.querySelector('[data-v2-mobile-nav-toggle]');
            var mobileNav = document.getElementById('ota-v2-mobile-nav');
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
                    document.body.classList.toggle('ota-v2-mobile-nav-open', toggle.checked);
                    syncMobileNavAria();
                    window.setTimeout(syncHeaderOffset, 40);
                });
                mobileNavMq.addEventListener('change', function () {
                    if (!mobileNavMq.matches && toggle.checked) {
                        toggle.checked = false;
                        document.body.classList.remove('ota-v2-mobile-nav-open');
                    }
                    syncMobileNavAria();
                });
                syncMobileNavAria();
                document.addEventListener('click', function (event) {
                    if (!toggle.checked) return;
                    var inner = document.querySelector('.ota-v2-public-header__inner');
                    if (!inner || inner.contains(event.target)) return;
                    toggle.checked = false;
                    document.body.classList.remove('ota-v2-mobile-nav-open');
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
    @stack('scripts')
    @stack('modals')
</body>
</html>
