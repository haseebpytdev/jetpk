@php
    $jpCheckoutAssetVersion = 36;
    $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
    $isPendingCard = ($meta['booking_method'] ?? '') === 'online_card'
        && ($meta['lifecycle_phase'] ?? '') === 'pending_online_payment';
    $progressStep = $isPendingCard && ! empty($abhiPayCheckout['show_pay_button'] ?? null) ? 3 : 4;
@endphp
@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', $isPendingCard ? 'Complete payment' : 'Booking confirmation')

@push('styles')
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/booking.css?v={{ $jpCheckoutAssetVersion }}">
@endpush

@section('content')
<section class="jp-page jp-page--checkout" aria-label="{{ $isPendingCard ? 'Card payment' : 'Booking confirmation' }}">
    <div class="wrap jp-page-wrap">
        @include('themes.frontend.jetpakistan.components.checkout.progress-bar', ['activeStep' => $progressStep])
        @include('themes.frontend.jetpakistan.frontend.booking.partials.confirmation-body')
    </div>
</section>
@endsection

@push('theme-scripts')
<script src="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/js/booking.js?v={{ $jpCheckoutAssetVersion }}" defer></script>
@endpush
