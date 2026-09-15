@php
    $jpThemeBase = rtrim(client_theme()->frontendThemeUrl(), '/');
    $jpAssetVersion = 58; // JETPK-SEARCH-UI-VERTICAL-COMPACTNESS-HEIGHT-ONLY-SCALING-FIX
    $jpBrandName = client_branding()->companyName();
    $jpFavicon = client_branding()->faviconUrl();
    $jpLogo = client_branding()->logoUrl();
    $jpSeo = is_array($seo ?? null) ? $seo : [];
    $pageTitle = trim($__env->yieldContent('title'));
    $seoTitle = trim((string) ($jpSeo['title'] ?? ''));
    $documentTitle = $pageTitle !== '' ? $pageTitle : ($seoTitle !== '' ? $seoTitle : $jpBrandName);
    $jpBodyClass = trim($__env->yieldContent('jp_body_class'));

    $jpMetaDescription = trim((string) ($jpSeo['description'] ?? ''));
    $jpRobots = trim((string) ($jpSeo['robots'] ?? 'index,follow'));
    $jpCanonical = trim((string) ($jpSeo['canonical'] ?? ''));
    if ($jpCanonical === '') {
        $jpCanonical = url()->current();
    } elseif (str_starts_with($jpCanonical, '/')) {
        $jpCanonical = url($jpCanonical);
    }

    $jpSeoPageKey = $pageKey ?? (request()->routeIs('home') ? \App\Support\Client\ClientPageKeys::HOME : null);
    $jpIsDraftPreview = is_string($jpSeoPageKey)
        ? app(\App\Services\Client\ClientPageContentResolver::class)->isDraftPreview($jpSeoPageKey)
        : false;
    if ($jpIsDraftPreview) {
        $jpRobots = 'noindex,nofollow';
    }

    $jpOgTitle = trim((string) ($jpSeo['og_title'] ?? $documentTitle));
    $jpOgDescription = trim((string) ($jpSeo['og_description'] ?? $jpMetaDescription));
    $jpOgImage = trim((string) ($jpSeo['og_image'] ?? ''));
    if ($jpOgImage === '' && $jpLogo) {
        $jpOgImage = $jpLogo;
    }

    $jpSchemaGraph = [];
    if ($jpSeo !== [] && ! $jpIsDraftPreview) {
        $jpOrganization = [
            '@type' => 'Organization',
            '@id' => rtrim($jpCanonical, '/').'#organization',
            'name' => $jpBrandName,
            'url' => request()->routeIs('home') ? $jpCanonical : url('/'),
        ];
        if ($jpLogo) {
            $jpOrganization['logo'] = $jpLogo;
        }

        if (request()->routeIs('home')) {
            $jpSchemaGraph[] = $jpOrganization;
            $jpSchemaGraph[] = [
                '@type' => 'WebSite',
                '@id' => rtrim($jpCanonical, '/').'#website',
                'url' => $jpCanonical,
                'name' => $jpBrandName,
                'description' => $jpMetaDescription,
                'publisher' => ['@id' => rtrim($jpCanonical, '/').'#organization'],
            ];
        } else {
            $jpSchemaGraph[] = [
                '@type' => 'WebPage',
                '@id' => $jpCanonical.'#webpage',
                'url' => $jpCanonical,
                'name' => $documentTitle,
                'description' => $jpMetaDescription,
            ];
        }
    }
    $jpGoogleSiteVerification = trim((string) config('services.google.site_verification', ''));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="day">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $documentTitle }}</title>
@if ($jpSeo !== [])
  @if ($jpMetaDescription !== '')
<meta name="description" content="{{ $jpMetaDescription }}">
  @endif
<meta name="robots" content="{{ $jpRobots }}">
  @if (! $jpIsDraftPreview)
<link rel="canonical" href="{{ $jpCanonical }}">
<meta property="og:type" content="{{ request()->routeIs('home') ? 'website' : 'article' }}">
<meta property="og:site_name" content="{{ $jpBrandName }}">
<meta property="og:title" content="{{ $jpOgTitle }}">
    @if ($jpOgDescription !== '')
<meta property="og:description" content="{{ $jpOgDescription }}">
    @endif
<meta property="og:url" content="{{ $jpCanonical }}">
    @if ($jpOgImage !== '')
<meta property="og:image" content="{{ $jpOgImage }}">
    @endif
<meta name="twitter:card" content="{{ $jpOgImage !== '' ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $jpOgTitle }}">
    @if ($jpOgDescription !== '')
<meta name="twitter:description" content="{{ $jpOgDescription }}">
    @endif
    @if ($jpOgImage !== '')
<meta name="twitter:image" content="{{ $jpOgImage }}">
    @endif
  @endif
@endif
@if ($jpGoogleSiteVerification !== '')
<meta name="google-site-verification" content="{{ $jpGoogleSiteVerification }}">
@endif
@if ($jpSchemaGraph !== [])
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $jpSchemaGraph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
@stack('head-meta')
@stack('head')
@if($jpFavicon)
<link rel="icon" href="{{ $jpFavicon }}" sizes="any">
<link rel="shortcut icon" href="{{ $jpFavicon }}" type="image/x-icon">
@endif

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<script>
(function () {
  var t = 'day';
  try { var s = localStorage.getItem('jp-theme'); if (s === 'day' || s === 'night') { t = s; } } catch (e) {}
  document.documentElement.setAttribute('data-theme', t);
})();
</script>

<link rel="stylesheet" href="{{ $jpThemeBase }}/css/tokens.css?v={{ $jpAssetVersion }}">
<link rel="stylesheet" href="{{ $jpThemeBase }}/css/theme.css?v={{ $jpAssetVersion }}">
<link rel="stylesheet" href="{{ $jpThemeBase }}/css/forms.css?v={{ $jpAssetVersion }}">
@if (! request()->routeIs('home'))
<link rel="stylesheet" href="{{ $jpThemeBase }}/css/booking.css?v={{ $jpAssetVersion }}">
@endif
@if (! empty($jpApprovedPaletteCss ?? []))
<style>
:root {
@foreach ($jpApprovedPaletteCss as $cssVar => $cssValue)
  {{ $cssVar }}: {{ $cssValue }};
@endforeach
}
</style>
@endif
@php
    $jpBrandRuntimeCss = [];
    $jpBrandRuntimeCssDay = [];
    if (uses_jetpk_company_branding()) {
        $blocks = jetpk_company_branding()->publicCssVariableBlocks();
        $jpBrandRuntimeCss = $blocks['night'];
        $jpBrandRuntimeCssDay = $blocks['day'];
    } elseif (function_exists('client_branding')) {
        $jpBrandRuntimeCss = [
            '--jp-header-logo-height' => client_branding()->headerLogoHeight().'px',
            '--jp-logo-mark' => client_branding()->headerLogoHeight().'px',
        ];
        $jpBrandRuntimeCssDay = $jpBrandRuntimeCss;
    }
@endphp
@if ($jpBrandRuntimeCss !== [])
<style>
:root {
@foreach ($jpBrandRuntimeCss as $cssVar => $cssValue)
  {{ $cssVar }}: {{ $cssValue }};
@endforeach
}
@if ($jpBrandRuntimeCssDay !== [] && $jpBrandRuntimeCssDay !== $jpBrandRuntimeCss)
html[data-theme="day"] {
@foreach ($jpBrandRuntimeCssDay as $cssVar => $cssValue)
  {{ $cssVar }}: {{ $cssValue }};
@endforeach
}
@endif
</style>
@endif
@stack('styles')
</head>
<body @class([$jpBodyClass])>
<div class="jp-loader done" id="jpLoader" aria-hidden="true" data-jp-loader="ssr">
  <div class="jp-loader-inner">
    <div class="loader-orbit">
      <span class="orbit-ring"></span>
      <span class="orbit-ring r2"></span>
      <span class="loader-mark"><svg viewBox="0 0 24 24"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 3.8c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z" stroke="none" fill="currentColor"/></svg></span>
      <span class="orbit-plane"><svg viewBox="0 0 24 24"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 3.8c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z" stroke="none" fill="currentColor"/></svg></span>
    </div>
    <div class="loader-word">Jet<b>Pakistan</b></div>
    <div class="loader-cap">Preparing your journey</div>
  </div>
</div>

@include('themes.frontend.jetpakistan.partials.header')
@include('themes.frontend.jetpakistan.partials.drawer')

<main class="jp-site-main" id="jp-main">
@yield('content')
</main>

@include('themes.frontend.jetpakistan.partials.footer')

@stack('modals')

<script src="{{ $jpThemeBase }}/js/theme.js?v={{ $jpAssetVersion }}" defer></script>
<script>document.documentElement.classList.add('js');</script>
@stack('theme-scripts')
@stack('scripts')
</body>
</html>
