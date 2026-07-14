@extends('layouts.mobile-app')

@section('title', 'Lookup booking')

@section('content')
    <div class="ota-mobile-auth" data-testid="ota-mobile-booking-lookup">
        <header class="ota-mobile-auth__page-header">
            <h1 class="ota-mobile-auth__title">Lookup your booking</h1>
            <p class="ota-mobile-auth__subtitle">
                Enter your booking reference and the email address used when you booked.
            </p>
        </header>

        <div class="ota-mobile-auth__info-card">
            <h2 class="ota-mobile-auth__info-title">What you will need</h2>
            <ul class="ota-mobile-auth__list">
                <li><strong>Booking reference</strong> from your confirmation</li>
                <li><strong>Email address</strong> matching your booking</li>
            </ul>
        </div>

        <div class="ota-mobile-auth__card">
            <h2 class="ota-mobile-auth__card-title">Enter your details</h2>

            <form method="post" action="{{ route('lookup-booking.submit') }}" class="ota-mobile-auth__form">
                @csrf

                @if ($errors->has('lookup'))
                    @include('mobile.components.alert', ['type' => 'danger', 'message' => $errors->first('lookup')])
                @endif

                @include('mobile.components.form-field', [
                    'name' => 'booking_reference',
                    'label' => 'Booking reference',
                    'required' => true,
                    'autocomplete' => 'off',
                ])

                @include('mobile.components.form-field', [
                    'name' => 'email',
                    'label' => 'Email address',
                    'type' => 'email',
                    'autocomplete' => 'email',
                    'required' => true,
                ])

                <p class="ota-mobile-auth__note">For privacy, access links are only sent when your details match the booking.</p>

                <x-turnstile />
                <button class="ota-mobile-auth__btn ota-mobile-auth__btn--primary" type="submit">Lookup booking</button>
            </form>
        </div>

        <nav class="ota-mobile-auth__quick-actions" aria-label="Booking help">
            <a href="{{ route('support') }}" class="ota-mobile-auth__quick-link">Need help? Contact support</a>
            <a href="{{ route('home') }}" class="ota-mobile-auth__quick-link">Back to flight search</a>
        </nav>
    </div>
@endsection
