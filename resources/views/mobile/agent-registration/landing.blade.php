@extends('layouts.mobile-app')

@section('title', 'Agent onboarding')

@section('content')
    <div class="ota-mobile-auth" data-testid="ota-mobile-agent-registration-landing">
        <div class="ota-mobile-auth__card">
            <header class="ota-mobile-auth__header">
                <p class="ota-mobile-public__kicker">Partner with us</p>
                <h1 class="ota-mobile-auth__title">Agent onboarding</h1>
                <p class="ota-mobile-auth__subtitle">Submit your agency details. Our team will review your application and provide access after approval.</p>
            </header>

            <section class="ota-mobile-public__card">
                <h2 class="ota-mobile-public__card-title">How it works</h2>
                <ul class="ota-mobile-public__list">
                    <li>Submit your agency application.</li>
                    <li>Our team reviews your business profile.</li>
                    <li>Access is provided after approval.</li>
                </ul>
            </section>

            <a href="{{ route('agent.register.form') }}" class="ota-mobile-auth__btn ota-mobile-auth__btn--primary">Start agency application</a>

            <nav class="ota-mobile-auth__links" aria-label="Agent onboarding options">
                <a href="{{ route('login') }}">Log in</a>
                <a href="{{ route('home') }}">Back to home</a>
            </nav>
        </div>
    </div>
@endsection
