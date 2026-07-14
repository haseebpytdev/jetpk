@php
    $jpThemeBase = rtrim(client_theme()->frontendThemeUrl(), '/');
    $jpAssetVersion = 49;
    $jpBrandName = client_branding()->companyName();
    $jpFavicon = client_branding()->faviconUrl();
    $pageTitle = trim($__env->yieldContent('title'));
    $documentTitle = $pageTitle !== '' ? $pageTitle : $jpBrandName;
    $jpBodyClass = trim($__env->yieldContent('jp_body_class'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="day">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $documentTitle }}</title>
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
