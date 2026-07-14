@php
    use App\Support\Branding\BrandDisplayResolver;

    $dbSettings = $agencySettings ?? ($publicBranding['settings'] ?? null);
    $dashProductName = BrandDisplayResolver::displayName($dbSettings, auth()->user());
    $brandCssVariables = $brandCssVariables ?? BrandDisplayResolver::cssVariables($dbSettings);
    $dashFaviconUrl = null;
    $jpDashAssetVersion = 20;

    if (uses_jetpk_company_branding()) {
        $dashProductName = jetpk_company_branding()->companyName();
        $dashFaviconUrl = jetpk_company_branding()->faviconUrl();
    } elseif (is_client_preview()) {
        $previewDashBranding = \App\Support\Client\ClientPreviewLayoutBranding::apply(
            $dashProductName,
            '',
            $brandCssVariables,
            null,
            false,
            null,
            [],
            [],
        );
        $dashProductName = $previewDashBranding['brandName'];
        $brandCssVariables = $previewDashBranding['brandCssVariables'];
        $dashFaviconUrl = $previewDashBranding['faviconUrl'] ?? null;
    }

    $relativePath = client_relative_path();
    $urlArea = match (true) {
        str_starts_with($relativePath, 'admin') => 'admin',
        str_starts_with($relativePath, 'staff') => 'staff',
        str_starts_with($relativePath, 'agent') => 'agent',
        str_starts_with($relativePath, 'customer') => 'customer',
        request()->is('admin*') => 'admin',
        request()->is('staff*') => 'staff',
        request()->is('agent*') => 'agent',
        request()->is('customer*') => 'customer',
        default => null,
    };

    if ($urlArea !== null) {
        $dashArea = $urlArea;
    } elseif (auth()->check()) {
        $u = auth()->user();
        $dashArea = match (true) {
            $u->isPlatformAdmin() => 'admin',
            $u->isStaff() => 'staff',
            $u->isAgent() => 'agent',
            $u->isCustomer() => 'customer',
            default => 'customer',
        };
    } else {
        $dashArea = 'guest';
    }

    $dashHomeUrl = match ($dashArea) {
        'staff' => client_route('staff.dashboard'),
        'agent' => client_route('agent.dashboard'),
        'customer' => client_route('customer.dashboard'),
        default => client_route('admin.dashboard'),
    };

    $dashSubtitle = match ($dashArea) {
        'staff' => 'Staff',
        'agent' => 'Agent',
        'customer' => 'Customer',
        default => 'Operations',
    };

    $jpAdminThemeBase = rtrim(client_theme()->adminThemeUrl(), '/');
    $pageTitleSection = trim($__env->yieldContent('title'));
    $documentTitle = $pageTitleSection !== ''
        ? BrandDisplayResolver::pageTitle($pageTitleSection, $dashProductName)
        : $dashProductName;
    $userInitials = '';
    if (auth()->check()) {
        $parts = preg_split('/\s+/', trim((string) auth()->user()->name)) ?: [];
        $userInitials = strtoupper(substr($parts[0] ?? 'U', 0, 1).substr($parts[1] ?? '', 0, 1));
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="day">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $documentTitle }}</title>
    @if($dashFaviconUrl)
        <link rel="icon" href="{{ $dashFaviconUrl }}" sizes="any">
    @endif
    <script>
    (function () {
      var t = 'day';
      try { var s = localStorage.getItem('jp-theme'); if (s === 'day' || s === 'night') t = s; } catch (e) {}
      document.documentElement.setAttribute('data-theme', t);
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/tokens.css?v={{ $jpDashAssetVersion }}">
    <link rel="stylesheet" href="{{ $jpAdminThemeBase }}/css/dashboard.css?v={{ $jpDashAssetVersion }}">
    @php
        $paletteCss = app(\App\Services\Branding\ClientThemePaletteService::class)->cssVariablesForProfile();
        if ($paletteCss !== []) {
            foreach ($paletteCss as $cssVar => $cssValue) {
                $brandCssVariables[$cssVar] = $cssValue;
            }
        }
        $brandCssVariablesDay = $brandCssVariables;
        if (uses_jetpk_company_branding()) {
            $blocks = jetpk_company_branding()->publicCssVariableBlocks();
            foreach ($blocks['night'] as $cssVar => $cssValue) {
                $brandCssVariables[$cssVar] = $cssValue;
            }
            $brandCssVariablesDay = array_merge($brandCssVariables, $blocks['day']);
        }
    @endphp
    <style>
        :root {
            @foreach ($brandCssVariables as $cssVar => $cssValue)
            {{ $cssVar }}: {{ $cssValue }};
            @endforeach
        }
        @if ($brandCssVariablesDay !== $brandCssVariables)
        html[data-theme="day"] {
            @foreach ($brandCssVariablesDay as $cssVar => $cssValue)
            {{ $cssVar }}: {{ $cssValue }};
            @endforeach
        }
        @endif
    </style>
    @stack('styles')
</head>
<body class="jp-dash-body" data-jp-dash-area="{{ $dashArea }}">
<div class="jp-dash">
    @include('themes.admin.jetpakistan.partials.sidebar', [
        'dashArea' => $dashArea,
        'dashHomeUrl' => $dashHomeUrl,
        'dashProductName' => $dashProductName,
    ])

    <div class="jp-dash__main">
        @include('themes.admin.jetpakistan.partials.topbar', [
            'dashSubtitle' => $dashSubtitle,
            'userInitials' => $userInitials,
        ])

        <div class="jp-dash__body @if($jpLegacyAdminShell ?? false) jp-dash__body--legacy @endif">
            @includeWhen($jpLegacyAdminShell ?? false, 'themes.admin.jetpakistan.partials.legacy-module-notice')

            @hasSection('page-header')
                <div class="jp-pagehead">
                    @yield('page-header')
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</div>

<script src="{{ $jpAdminThemeBase }}/js/dashboard.js?v={{ $jpDashAssetVersion }}" defer></script>
@stack('scripts')
</body>
</html>
