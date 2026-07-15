{{-- Manual payment received / under review email. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'success',
        'title' => 'We\'ve received your payment details',
        'message' => 'Thanks! Your payment is now under review. We\'ll confirm your booking once it\'s verified.',
    ])

    @include('emails.themes.jetpakistan.partials.booking-summary', ['booking' => $booking ?? null, 'emailBrand' => $brand])

    @if(!empty($payment))
        @include('emails.themes.jetpakistan.partials.payment-summary', ['payment' => $payment, 'emailBrand' => $brand])
    @endif

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
