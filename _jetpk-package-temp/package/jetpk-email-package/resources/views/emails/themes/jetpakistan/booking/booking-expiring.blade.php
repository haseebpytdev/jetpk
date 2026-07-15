{{-- Booking expiring reminder email. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand    = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $b        = (isset($booking) && is_array($booking)) ? $booking : [];
        $deadline = $b['payment_deadline'] ?? (($meta['deadline'] ?? null));
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'warning',
        'title' => 'Your booking is about to expire',
        'message' => !empty($deadline)
            ? 'Please complete payment before '.$deadline.' to keep your seats. After this time the booking may be released.'
            : 'Please complete payment soon to keep your seats. After the deadline the booking may be released.',
    ])

    @include('emails.themes.jetpakistan.partials.booking-summary', ['booking' => $b, 'emailBrand' => $brand])

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
