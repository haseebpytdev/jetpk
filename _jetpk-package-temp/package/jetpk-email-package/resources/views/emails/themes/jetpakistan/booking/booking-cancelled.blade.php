{{-- Booking cancelled email. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand   = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $b       = (isset($booking) && is_array($booking)) ? $booking : [];
        $meta    = (isset($meta) && is_array($meta)) ? $meta : [];
        $refund  = $meta['refund_info'] ?? null;
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'warning',
        'title' => 'Your booking has been cancelled',
        'message' => 'This booking is now cancelled. Details are below for your records.',
    ])

    @include('emails.themes.jetpakistan.partials.booking-summary', ['booking' => $b, 'emailBrand' => $brand])

    @if(!empty($itinerary))
        @include('emails.themes.jetpakistan.partials.flight-itinerary', ['itinerary' => $itinerary, 'emailBrand' => $brand])
    @endif

    @if(!empty($refund))
        @include('emails.themes.jetpakistan.partials.alert-box', ['type' => 'info', 'title' => 'Refund', 'message' => $refund])
    @endif

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
