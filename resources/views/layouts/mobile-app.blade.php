@php
    use App\Support\Branding\BrandDisplayResolver;

    $brand = config('ota-brand', []);
    $client = config('ota-client', []);
    $dbSettings = $agencySettings ?? null;
    $brandName = $brandName ?? BrandDisplayResolver::displayName($dbSettings, auth()->user());
    $brandTagline = $dbSettings?->tagline ?: ($client['agency_tagline'] ?? '');
    $brandCssVariables = $brandCssVariables ?? BrandDisplayResolver::cssVariables($dbSettings);
    $logoPath = $dbSettings?->logo_path;
    $tn = asset('vendor/tournest/assets');
    $pageTitleSection = trim($__env->yieldContent('title'));
    $documentTitle = $pageTitleSection !== ''
        ? BrandDisplayResolver::pageTitle($pageTitleSection, $brandName)
        : $brandName;
@endphp
<!doctype html>
<html @class(['ota-mobile-app-html', 'ui-version-v1' => ($currentUiVersion ?? 'v1') === 'v1', 'ui-version-v2' => ($currentUiVersion ?? 'v1') === 'v2']) lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $brandTagline !== '' ? $brandTagline : ($brand['tagline'] ?? 'Book flights.') }}">
    <title>{{ $documentTitle }}</title>

    <link rel="shortcut icon" type="image/icon" href="{{ $dbSettings?->favicon_path ? asset('storage/'.$dbSettings->favicon_path) : $tn.'/logo/favicon.png' }}"/>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ ui_asset('css/ota-design-system.css') }}" />
    <link rel="stylesheet" href="{{ ui_asset('css/ota-mobile-app.css') }}" />

    <style>
        :root {
            @foreach ($brandCssVariables as $cssVar => $cssValue)
            {{ $cssVar }}: {{ $cssValue }};
            @endforeach
            --ota-mobile-primary: var(--brand-primary);
        }
    </style>
    @stack('styles')
    @include('layouts.partials.ui-layer-styles', ['contexts' => $uiLayerContexts ?? ui_layer_contexts()])
</head>
<body @class([
    'ota-mobile-app',
    'ui-v1' => ($currentUiVersion ?? 'v1') === 'v1',
    'ui-v2' => ($currentUiVersion ?? 'v1') === 'v2',
    'ui-preview-namespace' => $isUiPreviewNamespace ?? false,
]) data-testid="ota-mobile-app-shell">
    <div class="ota-mobile-app__frame">
        @include('layouts.partials.mobile-app-top-bar')

        <main class="ota-mobile-app__main" id="ota-mobile-app-main">
            @if (session('offer_warning'))
                <div class="ota-mobile-results__freshness ota-mobile-results__freshness--checkout" role="alert">
                    <p class="ota-mobile-results__freshness-text">{{ session('offer_warning') }}</p>
                </div>
            @endif
            @yield('content')
        </main>

        @include('layouts.partials.mobile-app-bottom-nav')

        @include('layouts.partials.mobile-app-desktop-link')
    </div>

    <script src="{{ ui_asset('js/ota-mobile-app.js') }}" defer></script>
    @include('layouts.partials.ui-layer-scripts', ['contexts' => $uiLayerContexts ?? ui_layer_contexts()])
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
    @stack('scripts')
    @include('layouts.partials.mobile-viewport-reconcile')
</body>
</html>
