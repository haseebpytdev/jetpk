{{-- Booking created email. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'info',
        'title' => 'Your booking request has been received',
        'message' => 'We are processing your request. You\'ll get another email once it is confirmed.',
    ])

    @include('emails.themes.jetpakistan.partials.booking-summary', ['booking' => $booking ?? null, 'emailBrand' => $brand])

    @if(!empty($itinerary))
        @include('emails.themes.jetpakistan.partials.flight-itinerary', ['itinerary' => $itinerary, 'emailBrand' => $brand])
    @endif

    @if(!empty($passengers))
        @include('emails.themes.jetpakistan.partials.passenger-summary', ['passengers' => $passengers, 'emailBrand' => $brand])
    @endif

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
