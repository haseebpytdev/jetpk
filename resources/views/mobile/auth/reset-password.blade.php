@extends('layouts.mobile-app')

@section('title', 'Set new password')

@section('content')
    <div class="ota-mobile-auth" data-testid="ota-mobile-reset-password">
        <div class="ota-mobile-auth__card">
            <header class="ota-mobile-auth__header">
                <h1 class="ota-mobile-auth__title">Set a new password</h1>
                <p class="ota-mobile-auth__subtitle">Create a new password to continue to your account.</p>
            </header>

            <form method="POST" action="{{ route('password.store') }}" class="ota-mobile-auth__form">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                @include('mobile.components.form-field', [
                    'name' => 'email',
                    'label' => 'Email',
                    'type' => 'email',
                    'value' => old('email', $request->email),
                    'required' => true,
                    'autocomplete' => 'username',
                ])

                @include('mobile.components.form-field', [
                    'name' => 'password',
                    'label' => 'Password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                ])

                @include('mobile.components.form-field', [
                    'name' => 'password_confirmation',
                    'label' => 'Confirm password',
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                ])

                <button class="ota-mobile-auth__btn ota-mobile-auth__btn--primary" type="submit">Reset password</button>
            </form>

            <nav class="ota-mobile-auth__links" aria-label="Account options">
                <a href="{{ route('login') }}">Back to login</a>
            </nav>
        </div>
    </div>
@endsection
