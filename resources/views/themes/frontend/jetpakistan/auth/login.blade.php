@extends('themes.frontend.jetpakistan.layouts.auth')

@section('title', 'Log in')

@push('auth_form')
    <header class="jp-auth-form-head">
        <h2 class="jp-auth-form-title">Log in</h2>
        <p class="jp-auth-form-lead">Sign in to manage bookings, e-tickets, and travel updates.</p>
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

    <form method="POST" action="{{ client_url('/login') }}" class="jp-form jp-auth-form">
        @csrf
        @if (current_client_slug())
            <input type="hidden" name="client_slug" value="{{ current_client_slug() }}">
        @endif

        <x-jp.form-group label="Email or username" for="login" :error="$errors->first('login') ?: $errors->first('email')">
            <input id="login" class="jp-input @if($errors->has('login') || $errors->has('email')) jp-input--invalid @endif" type="text" name="login" value="{{ old('login', old('email')) }}" required autofocus autocomplete="username">
        </x-jp.form-group>

        <x-jp.form-group label="Password" for="password" :error="$errors->first('password')">
            <input id="password" class="jp-input @if($errors->has('password')) jp-input--invalid @endif" type="password" name="password" required autocomplete="current-password">
        </x-jp.form-group>

        <div class="jp-auth-row">
            <label class="jp-auth-remember" for="remember">
                <input id="remember" type="checkbox" name="remember">
                <span>Remember me</span>
            </label>
            @if (Route::has('password.request'))
                <a class="jp-auth-link" href="{{ client_route('password.request') }}">Forgot password?</a>
            @endif
        </div>

        <x-jp.button type="submit" variant="primary" block>Log in</x-jp.button>
    </form>

    <nav class="jp-auth-links" aria-label="Account options">
        <a href="{{ client_route('register') }}">Create account</a>
        <a href="{{ client_route('agent.register') }}">Register as travel agent</a>
        <a href="{{ client_route('home') }}">Back to home</a>
    </nav>
@endpush
