@extends('layouts.mobile-app')

@section('title', 'Create flight booking')

@section('mobile_app_title', 'Create booking')

@section('mobile_app_back')
    <a href="{{ route('agent.bookings.index') }}" class="ota-mobile-app__back-btn" aria-label="Back to bookings">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-bookings-create">
        @if (session('agent_booking_mode_notice'))
            <section class="ota-mobile-agent__card">
                <p class="ota-mobile-agent__note" data-testid="agent-booking-mode-notice">{{ session('agent_booking_mode_notice') }}</p>
            </section>
        @endif

        <section class="ota-mobile-agent__card">
            <h1 class="ota-mobile-agent__page-title">Create flight booking</h1>
            <p class="ota-mobile-agent__note">
                Search real-time fares using the main booking flow. This booking will be linked to
                <strong>{{ $agencyName ?? 'your agency' }}</strong>.
            </p>
        </section>

        <div class="ota-mobile-agent__actions">
            <a href="{{ route('home') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary ota-mobile-agent__btn--block" data-testid="agent-booking-search-flights">
                Search flights
            </a>
            <a href="{{ route('agent.bookings.index') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary ota-mobile-agent__btn--block">
                Back to bookings
            </a>
        </div>
    </div>
@endsection
