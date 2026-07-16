@extends(client_layout('auth', 'frontend'))

@section('title', 'Application submitted')
@section('auth_card_class', 'auth-card--register-compact')

@push('auth_form')
    <div class="ota-register-compact ota-agent-register ota-agent-register-submitted">
        <header class="ota-register-header">
            <h2 class="ota-register-title">Application submitted</h2>
            <p class="ota-register-subtitle">Our team will review your details and contact you after verification.</p>
        </header>

        @if (session('status'))
            <div class="ota-alert ota-alert--warning">{{ session('status') }}</div>
        @endif

        <div class="ota-alert ota-alert--info">
            You will receive login access only after approval.
        </div>

        <p class="ota-agent-register-note">Need help with your application? Contact ota@jetpakistan.pk.</p>

        <nav class="ota-register-links" aria-label="Agent application options">
            <a href="{{ client_route('agent.register') }}">Agent info</a>
            <a href="{{ client_route('login') }}">Log in</a>
            <a href="{{ client_route('home') }}">Back to home</a>
        </nav>
    </div>
@endpush
