@extends(client_layout('auth', 'frontend'))

@section('title', 'Forgot password')
@section('auth_card_class', 'auth-card--forgot-compact')

@push('auth_form')
    <div class="ota-forgot-compact">
        <header class="ota-forgot-header">
            <h2 class="ota-forgot-title">Reset your password</h2>
            <p class="ota-forgot-subtitle">Enter your email and we’ll send you a secure reset link.</p>
        </header>

        @if (session('status'))
            <div class="ota-alert ota-alert--success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="ota-forgot-form">
            @csrf
            <div class="ota-field ota-forgot-field">
                <label class="ota-label" for="email">Email</label>
                <input id="email" class="ota-input ota-forgot-input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                <div class="ota-error">@error('email'){{ $message }}@enderror</div>
            </div>

            <div class="ota-forgot-actions">
                <button class="ota-btn-primary ota-btn-primary--block ota-forgot-submit" type="submit">Send reset link</button>
            </div>
        </form>

        <nav class="ota-forgot-links" aria-label="Password reset options">
            <a href="{{ client_route('login') }}">Back to login</a>
        </nav>
    </div>
@endpush
