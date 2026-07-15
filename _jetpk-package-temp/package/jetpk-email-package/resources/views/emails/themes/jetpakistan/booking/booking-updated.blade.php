{{-- Booking updated email. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand   = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $b       = (isset($booking) && is_array($booking)) ? $booking : [];
        $meta    = (isset($meta) && is_array($meta)) ? $meta : [];
        $change  = $meta['change_summary'] ?? null;
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'info',
        'title' => 'Your booking has been updated',
        'message' => !empty($change) ? $change : 'Your booking details have changed. The latest information is below.',
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
