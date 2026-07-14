@extends('layouts.mobile-app')

@section('title', 'Forgot password')

@section('content')
    <div class="ota-mobile-auth" data-testid="ota-mobile-forgot-password">
        <div class="ota-mobile-auth__card">
            <header class="ota-mobile-auth__header">
                <h1 class="ota-mobile-auth__title">Reset your password</h1>
                <p class="ota-mobile-auth__subtitle">Enter your email and we will send you a secure reset link.</p>
            </header>

            @if (session('status'))
                @include('mobile.components.alert', ['type' => 'success', 'message' => session('status')])
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="ota-mobile-auth__form">
                @csrf

                @include('mobile.components.form-field', [
                    'name' => 'email',
                    'label' => 'Email',
                    'type' => 'email',
                    'required' => true,
                    'autocomplete' => 'username',
                ])

                <button class="ota-mobile-auth__btn ota-mobile-auth__btn--primary" type="submit">Send reset link</button>
            </form>

            <nav class="ota-mobile-auth__links" aria-label="Password reset options">
                <a href="{{ route('login') }}">Back to login</a>
            </nav>
        </div>
    </div>
@endsection
