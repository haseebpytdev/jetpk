@extends(client_layout('auth', 'frontend'))

@section('title', 'Verify email')

@push('auth_form')
    <div class="ota-auth-flow ota-auth-flow--verify">
        <header class="ota-auth-flow__header">
            <h2>Verify your email</h2>
            <p class="ota-auth-help">Please verify your email before accessing account features, booking documents, and customer support tools.</p>
            <p class="ota-auth-help">If the link expired or did not arrive, request a fresh verification email below.</p>
        </header>

        @if (session('status') === 'registration-complete')
            <div class="ota-auth-alert">Your account was created. Please check your email and verify your address to continue.</div>
        @endif

        @if (session('status') === 'verification-link-sent' || session('verification_notice'))
            <div class="ota-auth-alert">{{ session('verification_notice', 'A new verification link has been sent to your email address.') }}</div>
        @endif

        @if (session('status') && ! in_array(session('status'), ['registration-complete', 'verification-link-sent'], true))
            <div class="ota-auth-alert">{{ session('status') }}</div>
        @endif

        @if ($errors->has('email'))
            <div class="ota-auth-alert ota-auth-alert--danger">{{ $errors->first('email') }}</div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}" class="ota-auth-flow__form">
            @csrf
            <button class="ota-auth-btn" type="submit">Resend verification email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="ota-auth-flow__footer">
            @csrf
            <button class="ota-auth-link ota-auth-link-button" type="submit">Log out</button>
        </form>
    </div>
@endpush
