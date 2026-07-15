@extends('themes.frontend.jetpakistan.layouts.auth')

@section('title', 'Sign up')

@php
    $countryCodes = ['+92' => 'Pakistan (+92)', '+971' => 'UAE (+971)', '+966' => 'Saudi Arabia (+966)', '+44' => 'UK (+44)', '+1' => 'US/Canada (+1)'];
    $selectedCountryCode = old('mobile_country_code', '+92');
@endphp

@push('auth_form')
    <header class="jp-auth-form-head">
        <h2 class="jp-auth-form-title">Create account</h2>
        <p class="jp-auth-form-lead">Book flights, track requests, and manage travel with JetPakistan.</p>
    </header>

    @if ($errors->has('social'))
        <x-jp.alert variant="danger">{{ $errors->first('social') }}</x-jp.alert>
    @endif

    <x-jp.google-sign-in verb="Sign up with Google" mode="register" />

    <form method="POST" action="{{ client_url('/register') }}" class="jp-form jp-auth-form">
        @csrf

        <div class="jp-form-grid jp-form-grid--2">
            <x-jp.form-group label="First name" for="first_name" :error="$errors->first('first_name')">
                <input id="first_name" class="jp-input @error('first_name') jp-input--invalid @enderror" type="text" name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name">
            </x-jp.form-group>
            <x-jp.form-group label="Last name" for="last_name" :error="$errors->first('last_name')">
                <input id="last_name" class="jp-input @error('last_name') jp-input--invalid @enderror" type="text" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name">
            </x-jp.form-group>
        </div>

        <x-jp.form-group label="Email" for="email" :error="$errors->first('email')">
            <input id="email" class="jp-input @error('email') jp-input--invalid @enderror" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
        </x-jp.form-group>

        <x-jp.form-group label="Mobile" for="mobile" :error="$errors->first('mobile') ?: $errors->first('mobile_country_code')">
            <div class="jp-phone-row">
                <select id="mobile_country_code" class="jp-select" name="mobile_country_code" aria-label="Country code">
                    @foreach ($countryCodes as $code => $label)
                        <option value="{{ $code }}" @selected((string) $selectedCountryCode === (string) $code)>{{ $code }}</option>
                    @endforeach
                </select>
                <input id="mobile" class="jp-input" type="tel" name="mobile" value="{{ old('mobile') }}" required autocomplete="tel-national" inputmode="numeric">
            </div>
        </x-jp.form-group>

        <div class="jp-form-grid jp-form-grid--2">
            <x-jp.form-group label="Password" for="password" :error="$errors->first('password')">
                <input id="password" class="jp-input @error('password') jp-input--invalid @enderror" type="password" name="password" required autocomplete="new-password" minlength="8">
            </x-jp.form-group>
            <x-jp.form-group label="Confirm password" for="password_confirmation" :error="$errors->first('password_confirmation')">
                <input id="password_confirmation" class="jp-input" type="password" name="password_confirmation" required autocomplete="new-password" minlength="8">
            </x-jp.form-group>
        </div>

        <x-jp.button type="submit" variant="primary" block>Create account</x-jp.button>
    </form>

    <nav class="jp-auth-links" aria-label="Account options">
        <a href="{{ client_route('login') }}">Already have an account? Log in</a>
        <a href="{{ client_route('agent.register') }}">Travel agent? Apply here</a>
    </nav>
@endpush
