{{-- Booking confirmed email. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $b     = (isset($booking) && is_array($booking)) ? $booking : [];
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'success',
        'title' => 'Your booking is confirmed',
        'message' => 'Everything is set. Your itinerary and passenger details are below.',
    ])

    @include('emails.themes.jetpakistan.partials.booking-summary', ['booking' => $b, 'emailBrand' => $brand])

    @if(!empty($itinerary))
        @include('emails.themes.jetpakistan.partials.flight-itinerary', ['itinerary' => $itinerary, 'emailBrand' => $brand])
    @endif

    @if(!empty($passengers))
        @include('emails.themes.jetpakistan.partials.passenger-summary', ['passengers' => $passengers, 'emailBrand' => $brand])
    @endif

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
