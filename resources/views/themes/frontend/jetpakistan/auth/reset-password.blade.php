@extends('themes.frontend.jetpakistan.layouts.auth')

@section('title', 'Set new password')

@push('auth_form')
    <header class="jp-auth-form-head">
        <h2 class="jp-auth-form-title">Set a new password</h2>
        <p class="jp-auth-form-lead">Choose a secure password for your JetPakistan account.</p>
    </header>

    <form method="POST" action="{{ client_url('/reset-password') }}" class="jp-form jp-auth-form">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-jp.form-group label="Email" for="email" :error="$errors->first('email')">
            <input id="email" class="jp-input @error('email') jp-input--invalid @enderror" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
        </x-jp.form-group>

        <x-jp.form-group label="Password" for="password" :error="$errors->first('password')">
            <input id="password" class="jp-input @error('password') jp-input--invalid @enderror" type="password" name="password" required autocomplete="new-password">
        </x-jp.form-group>

        <x-jp.form-group label="Confirm password" for="password_confirmation" :error="$errors->first('password_confirmation')">
            <input id="password_confirmation" class="jp-input" type="password" name="password_confirmation" required autocomplete="new-password">
        </x-jp.form-group>

        <x-jp.button type="submit" variant="primary" block>Reset password</x-jp.button>
    </form>

    <nav class="jp-auth-links">
        <a href="{{ client_route('login') }}">Back to login</a>
    </nav>
@endpush
