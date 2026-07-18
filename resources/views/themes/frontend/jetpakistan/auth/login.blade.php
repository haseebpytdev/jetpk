@extends('themes.frontend.jetpakistan.layouts.auth')

@section('title', $seo['title'] ?? 'Log in')

@php
    $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
@endphp

@push('auth_form')
    <header class="jp-auth-form-head">
        <h2 class="jp-auth-form-title">{{ $hero['title'] ?? 'Log in' }}</h2>
        @if (($hero['subtitle'] ?? '') !== '')
            <p class="jp-auth-form-lead">{{ $hero['subtitle'] }}</p>
        @endif
    </header>

    @if (\App\Support\Auth\CheckoutReturnIntent::hasGroupBookingIntent(request()))
        <x-jp.alert variant="warning">Please log in or create an account to book this group ticket.</x-jp.alert>
    @endif

    @if (session('status'))
        <x-jp.alert variant="success">{{ session('status') }}</x-jp.alert>
    @endif
    @if ($errors->has('social'))
        <x-jp.alert variant="danger">{{ $errors->first('social') }}</x-jp.alert>
    @endif

    <x-jp.google-sign-in verb="Continue with Google" mode="login" />

    <div
        class="jp-alert jp-alert--danger"
        data-jp-login-alert
        role="alert"
        aria-live="polite"
        tabindex="-1"
        hidden
    ></div>

    <form
        method="POST"
        action="{{ client_url('/login') }}"
        class="jp-form jp-auth-form"
        data-jp-login-form
        aria-busy="false"
    >
        @csrf
        @if (current_client_slug())
            <input type="hidden" name="client_slug" value="{{ current_client_slug() }}">
        @endif

        <x-jp.form-group label="Email or username" for="login" data-jp-field-group="login">
            <input
                id="login"
                class="jp-input @if($errors->has('login') || $errors->has('email')) jp-input--invalid @endif"
                type="text"
                name="login"
                value="{{ old('login', old('email')) }}"
                required
                autofocus
                autocomplete="username"
                @if($errors->has('login') || $errors->has('email')) aria-invalid="true" @endif
            >
            <p class="jp-field-error" data-jp-field-error @if(! $errors->has('login') && ! $errors->has('email')) hidden @endif>{{ $errors->first('login') ?: $errors->first('email') }}</p>
        </x-jp.form-group>

        <x-jp.form-group label="Password" for="password" data-jp-field-group="password">
            <input
                id="password"
                class="jp-input @if($errors->has('password')) jp-input--invalid @endif"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                @if($errors->has('password')) aria-invalid="true" @endif
            >
            <p class="jp-field-error" data-jp-field-error @if(! $errors->has('password')) hidden @endif>{{ $errors->first('password') }}</p>
        </x-jp.form-group>

        <div class="jp-form-row">
            <label class="jp-checkbox">
                <input type="checkbox" name="remember" @checked(old('remember'))>
                <span>Remember me</span>
            </label>
            <a href="{{ route('password.request') }}" class="jp-link">Forgot password?</a>
        </div>

        <x-jp.button type="submit" variant="primary" block>Log in</x-jp.button>
    </form>
@endpush
