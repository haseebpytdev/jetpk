@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Application submitted')

@section('content')
    <div class="ota-mobile-auth" data-testid="ota-mobile-agent-registration-submitted">
        <div class="ota-mobile-auth__card">
            <header class="ota-mobile-auth__header">
                <p class="ota-mobile-public__kicker">Thank you</p>
                <h1 class="ota-mobile-auth__title">Application submitted</h1>
                <p class="ota-mobile-auth__subtitle">Our team will review your agency application.</p>
            </header>

            @if (session('status'))
                @include('mobile.components.alert', ['type' => 'info', 'message' => session('status')])
            @endif

            <section class="ota-mobile-public__card" aria-label="What happens next">
                <h2 class="ota-mobile-public__card-title">What happens next</h2>
                <ul class="ota-mobile-public__list">
                    <li>Our team will review your agency application.</li>
                    <li>Access is provided after approval.</li>
                    <li>We may contact you for verification.</li>
                </ul>
            </section>

            <div class="ota-mobile-auth__alert ota-mobile-auth__alert--info">
                You will receive login access only after approval.
            </div>

            <a href="{{ route('home') }}" class="ota-mobile-auth__btn ota-mobile-auth__btn--primary">Back to home</a>

            <nav class="ota-mobile-auth__quick-actions" aria-label="Application submitted options">
                <a href="{{ route('support') }}" class="ota-mobile-auth__quick-link">Contact support</a>
                <a href="{{ route('login') }}" class="ota-mobile-auth__quick-link">Log in if you already have an account</a>
            </nav>
        </div>
    </div>
@endsection
