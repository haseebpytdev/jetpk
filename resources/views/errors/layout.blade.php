@php
    use App\Support\Branding\BrandDisplayResolver;
    use App\Support\Branding\SafeBrandingResolver;
    use Illuminate\Support\Facades\Log;

    $brandName = BrandDisplayResolver::displayName();
    $tagline = 'Reliable travel support for agencies and customers.';
    $supportEmail = (string) (config('mail.from.address') ?: config('client.canonical_support_email', 'ota@jetpakistan.pk'));
    $supportPhone = '+92 300 0000000';
    $primary = '#0c4a6e';

    try {
        $payload = SafeBrandingResolver::resolveForPublic();
        $settings = $payload['settings'] ?? null;
        $fallback = $payload['fallback_brand'] ?? [];

        $brandName = BrandDisplayResolver::displayName($settings);
        $tagline = $settings?->tagline ?: ($fallback['tagline'] ?? $tagline);
        $supportEmail = $settings?->support_email ?: ($fallback['support_email'] ?? $supportEmail);
        $supportPhone = $settings?->support_phone ?: ($fallback['support_phone'] ?? $supportPhone);
        $primary = BrandDisplayResolver::themeColors($settings)['primary'];
    } catch (\Throwable $e) {
        Log::warning('Error layout fallback branding used.', [
            'error' => class_basename($e),
        ]);
    }
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brandName }} - @yield('title', 'Service Notice')</title>
    <style>
        :root { --brand: {{ $primary }}; }
        body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { width: 100%; max-width: 640px; background: #fff; border-radius: 14px; box-shadow: 0 10px 30px rgba(2, 6, 23, 0.08); border: 1px solid #e2e8f0; }
        .head { padding: 22px 24px; border-bottom: 1px solid #e2e8f0; }
        .brand { font-weight: 700; color: var(--brand); font-size: 1.05rem; }
        .tagline { margin-top: 4px; color: #64748b; font-size: .92rem; }
        .body { padding: 24px; }
        h1 { margin: 0 0 8px; font-size: 1.35rem; }
        p { margin: 0 0 18px; color: #334155; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
        .btn { text-decoration: none; border-radius: 8px; padding: 10px 14px; font-weight: 600; font-size: .92rem; }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-secondary { border: 1px solid #cbd5e1; color: #0f172a; background: #fff; }
        .support { margin-top: 18px; font-size: .88rem; color: #64748b; }
        .support a { color: #1d4ed8; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrap">
        <section class="card">
            <header class="head">
                <div class="brand">{{ $brandName }}</div>
                <div class="tagline">{{ $tagline }}</div>
            </header>
            <div class="body">
                <h1>@yield('heading')</h1>
                <p>@yield('message')</p>
                @stack('after-message')
                <div class="actions">
                    @hasSection('actions')
                        @yield('actions')
                    @else
                        <a href="{{ route('home') }}" class="btn btn-primary">Back to Home</a>
                        <a href="{{ route('support') }}" class="btn btn-secondary">Contact Support</a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn btn-secondary">Go to Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-secondary">Sign In</a>
                        @endauth
                    @endif
                </div>
                <div class="support">
                    Need help? Contact <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>@if ($supportPhone !== '') or {{ $supportPhone }}@endif.
                </div>
            </div>
        </section>
    </div>
</body>
</html>
