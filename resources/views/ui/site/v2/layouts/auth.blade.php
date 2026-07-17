@php
    $brand = config('ota-brand', []);
    $client = config('ota-client', []);
    $safeBranding = \App\Support\Branding\SafeBrandingResolver::resolveForPublic(app(\App\Services\Agencies\AgencyBrandingService::class));
    $settings = $safeBranding['settings'] ?? null;
    $supportEmail = $settings?->support_email ?: ($client['support_email'] ?? ($brand['support_email'] ?? config('client.canonical_support_email', 'ota@jetpakistan.pk')));
    $authCardClass = trim($__env->yieldContent('auth_card_class'));
    $isLoginPage = str_contains($authCardClass, 'login-premium');
    $authLogoPath = $settings?->logo_path;
    $hasAuthLogo = is_string($authLogoPath) && $authLogoPath !== '';
@endphp

@extends('ui.site.v2.layouts.frontend')

@section('content')
    <div class="ota-auth-page ota-auth ota-auth-access ota-v2-auth-page{{ $isLoginPage ? ' ota-auth-page--login' : '' }}" data-testid="v2-auth-page">
        <main class="ota-auth-shell auth-shell auth-shell--premium ota-v2-auth-shell">
            <section class="ota-auth-card auth-card ota-v2-card {{ $authCardClass }}" data-auth-premium-layout data-testid="v2-auth-card">
                <div class="ota-auth-brand" aria-label="{{ $brandName }} account access">
                    <a href="{{ client_route('home') }}" class="ota-auth-brand-link">
                        <span class="ota-auth-brand-mark" aria-hidden="true">
                            @if ($hasAuthLogo)
                                <img src="{{ asset('storage/'.$authLogoPath) }}" alt="">
                            @else
                                <i class="fa fa-plane"></i>
                            @endif
                        </span>
                        <span class="ota-auth-brand-text">
                            <strong>{{ $brandName }}</strong>
                            <small>Secure account access</small>
                        </span>
                    </a>
                </div>
                @stack('auth_form')
                @unless ($isLoginPage || str_contains($authCardClass, 'register-compact'))
                    <p class="ota-auth-support">
                        Need Help? Contact <a class="ota-auth-link" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                    </p>
                @endunless
            </section>
        </main>
    </div>
@endsection
