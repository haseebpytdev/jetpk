@php
    $portalVariant = $portalVariant ?? 'agent';
    $jpThemeBase = rtrim(client_theme()->frontendThemeUrl(), '/');
    $jpPortalAssetVersion = 39;
    $jpFavicon = client_branding()->faviconUrl();
    $jpBrandName = client_branding()->companyName();
    $pageTitle = trim($__env->yieldContent('title'));
    $documentTitle = $pageTitle !== '' ? $pageTitle.' · '.$jpBrandName : $jpBrandName;
    $portalUser = auth()->user();
    $portalName = trim((string) ($portalUser?->name ?? ($portalVariant === 'agent' ? 'Agent' : 'Traveler')));
    $portalInitial = strtoupper(substr($portalName !== '' ? $portalName : 'U', 0, 1));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="day">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $documentTitle }}</title>
@if($jpFavicon)
<link rel="icon" href="{{ $jpFavicon }}" sizes="any">
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
<link rel="stylesheet" href="{{ $jpThemeBase }}/css/tokens.css?v={{ $jpPortalAssetVersion }}">
<link rel="stylesheet" href="{{ $jpThemeBase }}/css/theme.css?v={{ $jpPortalAssetVersion }}">
<link rel="stylesheet" href="{{ $jpThemeBase }}/css/forms.css?v={{ $jpPortalAssetVersion }}">
<link rel="stylesheet" href="{{ $jpThemeBase }}/css/portal.css?v={{ $jpPortalAssetVersion }}">
@php
    $jpBrandRuntimeCss = [];
    $jpBrandRuntimeCssDay = [];
    if (function_exists('uses_jetpk_company_branding') && uses_jetpk_company_branding()) {
        $blocks = jetpk_company_branding()->publicCssVariableBlocks();
        $jpBrandRuntimeCss = $blocks['night'];
        $jpBrandRuntimeCssDay = $blocks['day'];
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
<body class="jp-portal jp-portal--{{ $portalVariant }}">
<header class="jp-portal__top">
  <div class="jp-portal__top-inner">
    @include('themes.frontend.jetpakistan.components.portal.header-brand')
    <div class="jp-portal__top-actions">
      <a href="{{ client_route('home') }}" class="jp-portal-btn jp-portal-btn--ghost jp-portal-btn--sm">Public site</a>
      @if (Route::has('profile.edit'))
        <a href="{{ client_route('profile.edit') }}" class="jp-portal-btn jp-portal-btn--ghost jp-portal-btn--sm">Profile</a>
      @endif
    </div>
  </div>
</header>

<div class="jp-portal__shell">
  <aside class="jp-portal__aside" aria-label="Portal navigation">
    @include('themes.frontend.jetpakistan.components.portal.identity-card', [
        'name' => $portalName,
        'initial' => $portalInitial,
        'variant' => $portalVariant,
    ])
    @include('themes.frontend.jetpakistan.components.portal.nav-'.$portalVariant)
  </aside>

  <main class="jp-portal__main" id="jp-portal-main">
    @hasSection('content')
      @yield('content')
    @else
      @if (trim($__env->yieldContent('account_title')) !== '' || trim($__env->yieldContent('account_actions')) !== '')
        <header class="jp-portal-page-head">
          <div>
            @hasSection('account_pretitle')
              <p class="jp-portal__eyebrow">@yield('account_pretitle')</p>
            @endif
            <h1>@yield('account_title')</h1>
            @hasSection('account_subtitle')
              <p>@yield('account_subtitle')</p>
            @endif
          </div>
          @hasSection('account_actions')
            <div>@yield('account_actions')</div>
          @endif
        </header>
      @endif
      @yield('account_content')
    @endif
  </main>
</div>

<script src="{{ $jpThemeBase }}/js/theme.js?v={{ $jpPortalAssetVersion }}" defer></script>
@stack('scripts')
</body>
</html>
