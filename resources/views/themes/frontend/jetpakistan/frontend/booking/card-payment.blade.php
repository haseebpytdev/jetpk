@php
    $jpCheckoutAssetVersion = 36;
@endphp
@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', 'Pay by card')

@push('styles')
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/booking.css?v={{ $jpCheckoutAssetVersion }}">
@endpush

@section('content')
<section class="jp-page jp-page--checkout" aria-label="Card payment">
    <div class="wrap jp-page-wrap">
        @include('themes.frontend.jetpakistan.components.checkout.progress-bar', ['activeStep' => 3])
        @include('themes.frontend.jetpakistan.frontend.booking.partials.confirmation-body', ['jetpkPaymentFocus' => true])
    </div>
</section>
@endsection
