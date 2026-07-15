@extends('themes.frontend.jetpakistan.layouts.auth')

@push('theme-scripts')
@php $jpAuthAssetVersion = 21; @endphp
<script src="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/js/forms.js?v={{ $jpAuthAssetVersion }}" defer></script>
@endpush

@section('title', 'Verify login')

@push('auth_form')
    <header class="jp-auth-form-head">
        <h2 class="jp-auth-form-title">Verify your sign-in</h2>
        <p class="jp-auth-form-lead">
            Enter the 6-digit code we sent
            @if (! empty($maskedEmail))
                to <strong>{{ $maskedEmail }}</strong>.
            @else
                to your email.
            @endif
        </p>
    </header>

    @if (session('status'))
        <x-jp.alert variant="success">{{ session('status') }}</x-jp.alert>
    @endif

    <form method="POST" action="{{ client_url('/login/otp') }}" class="jp-form jp-auth-form">
        @csrf
        @if (current_client_slug())
            <input type="hidden" name="client_slug" value="{{ current_client_slug() }}">
        @endif

        <x-jp.form-group label="Verification code" for="otp" :error="$errors->first('otp')">
            <input
                id="otp"
                class="jp-input jp-otp-input @error('otp') jp-input--invalid @enderror"
                type="text"
                name="otp"
                value="{{ old('otp') }}"
                inputmode="numeric"
                pattern="\d{6}"
                maxlength="6"
                autocomplete="one-time-code"
                required
                autofocus
            >
        </x-jp.form-group>

        <x-jp.button type="submit" variant="primary" block>Verify and continue</x-jp.button>
    </form>

    <form
        method="POST"
        action="{{ client_url('/login/otp/resend') }}"
        class="jp-auth-otp-resend"
        data-resend-seconds="{{ max(0, (int) ($resendAvailableIn ?? 0)) }}"
    >
        @csrf
        @if (current_client_slug())
            <input type="hidden" name="client_slug" value="{{ current_client_slug() }}">
        @endif
        <x-jp.button type="submit" variant="secondary" block data-jp-otp-resend-btn :disabled="($resendAvailableIn ?? 0) > 0">
            @if(($resendAvailableIn ?? 0) > 0)
                Resend code in {{ $resendAvailableIn }}s
            @else
                Resend code
            @endif
        </x-jp.button>
    </form>

    <nav class="jp-auth-links" aria-label="Account options">
        <a href="{{ client_route('login') }}">Back to sign in</a>
        <a href="{{ client_route('support') }}">Need help?</a>
    </nav>
@endpush
