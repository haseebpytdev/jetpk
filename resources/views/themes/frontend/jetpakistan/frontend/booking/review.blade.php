@php
    $jpCheckoutAssetVersion = 36;
@endphp
@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', 'Review & payment')

@push('styles')
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/booking.css?v={{ $jpCheckoutAssetVersion }}">
@endpush

@section('content')
<section class="jp-page jp-page--checkout" aria-label="Review and payment">
    <div class="wrap jp-page-wrap">
        @include('themes.frontend.jetpakistan.components.checkout.progress-bar', ['activeStep' => 3])
        @include('themes.frontend.jetpakistan.frontend.booking.partials.review-body')
    </div>
</section>
@endsection

@push('theme-scripts')
<script src="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/js/booking.js?v={{ $jpCheckoutAssetVersion }}" defer></script>
@endpush
