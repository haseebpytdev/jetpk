@php
    $jpThemeBase = '/themes/frontend/jetpakistan';
    $jpAssetVersion = 24;
    $jpErrorSlug = function_exists('ota_single_client_root_slug')
        ? (ota_single_client_root_slug() ?? (request_client_slug_for_errors() ?? 'jetpk'))
        : (request_client_slug_for_errors() ?? 'jetpk');
    $jpHomeUrl = '/';
    $jpLoginUrl = '/login';
    $jpSupportUrl = '/support';

    try {
        if (function_exists('client_route')) {
            $jpHomeUrl = client_route('home', [], $jpErrorSlug);
            $jpLoginUrl = client_route('login', [], $jpErrorSlug);
            $jpSupportUrl = Illuminate\Support\Facades\Route::has('support')
                ? client_route('support', [], $jpErrorSlug)
                : client_route('lookup-booking', [], $jpErrorSlug);
        }
    } catch (\Throwable $e) {
        // Error pages must render even when client context is partial.
    }

    $pageTitle = trim($__env->yieldContent('title'));
    $documentTitle = $pageTitle !== '' ? $pageTitle : (function_exists('client_branding') ? client_branding()->companyName() : 'JetPakistan');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="day">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $documentTitle }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ $jpThemeBase }}/css/tokens.css?v={{ $jpAssetVersion }}">
<link rel="stylesheet" href="{{ $jpThemeBase }}/css/theme.css?v={{ $jpAssetVersion }}">
<link rel="stylesheet" href="{{ $jpThemeBase }}/css/forms.css?v={{ $jpAssetVersion }}">
</head>
<body class="jp-body--error">
<header class="header header--error" id="header">
  <div class="wrap">
    <x-jp.brand-logo />
    <div class="header-right">
      <a href="{{ $jpLoginUrl }}" class="signin">Sign in</a>
    </div>
  </div>
</header>

<main class="jp-site-main jp-site-main--error" id="jp-main">
@yield('content')
</main>

<footer class="footer footer--error">
  <div class="wrap">
    <div class="foot-bot">
      <p>&copy; {{ date('Y') }} {{ client_branding()->companyName() }}. All rights reserved.</p>
      <a href="{{ $jpSupportUrl }}" class="footer--error__support">Contact support</a>
    </div>
  </div>
</footer>
</body>
</html>
