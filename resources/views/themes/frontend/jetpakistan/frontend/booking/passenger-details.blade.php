@php
    $jpCheckoutAssetVersion = 36;
@endphp
@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', 'Passenger details')

@push('styles')
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/booking.css?v={{ $jpCheckoutAssetVersion }}">
@endpush

@section('content')
<section class="jp-page jp-page--checkout" aria-label="Passenger details">
    <div class="wrap jp-page-wrap">
        @include('themes.frontend.jetpakistan.components.checkout.progress-bar', ['activeStep' => 2])
        @include('themes.frontend.jetpakistan.frontend.booking.partials.passenger-details-body')
    </div>
</section>
@endsection
