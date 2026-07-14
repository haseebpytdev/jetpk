@extends('themes.frontend.jetpakistan.layouts.auth')

@section('title', 'Forgot password')

@push('auth_form')
    <header class="jp-auth-form-head">
        <h2 class="jp-auth-form-title">Reset your password</h2>
        <p class="jp-auth-form-lead">Enter your email and we will send a secure reset link.</p>
    </header>

    @if (session('status'))
        <x-jp.alert variant="success">{{ session('status') }}</x-jp.alert>
    @endif

    <form method="POST" action="{{ client_url('/forgot-password') }}" class="jp-form jp-auth-form">
        @csrf
        <x-jp.form-group label="Email" for="email" :error="$errors->first('email')">
            <input id="email" class="jp-input @error('email') jp-input--invalid @enderror" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </x-jp.form-group>
        <x-jp.button type="submit" variant="primary" block>Send reset link</x-jp.button>
    </form>

    <nav class="jp-auth-links">
        <a href="{{ client_route('login') }}">Back to login</a>
    </nav>
@endpush
