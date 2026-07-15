{{-- JETPK-PUBLIC-SEARCH-RUNTIME-3: JetPakistan flight results — JetPK assets only --}}
@extends('themes.frontend.jetpakistan.layouts.frontend')

@php
    $jpBrandName = client_branding()->companyName();
    $jpAssetVersion = 43;
    $jpSearchAssetVersion = 34;
@endphp

@section('title', 'Flight results — '.$jpBrandName)
@section('jp_body_class', 'jp-flights-results')

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/results-base.css?v={{ $jpAssetVersion }}">
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/results.css?v={{ $jpAssetVersion }}">
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/jp-search.css?v={{ $jpSearchAssetVersion }}">
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/flight-cards.css?v=9">
@endpush

@push('theme-scripts')
@php $jpThemeBase = rtrim(client_theme()->frontendThemeUrl(), '/'); @endphp
<script src="{{ $jpThemeBase }}/js/jp-dates.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/forms.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/airport-autocomplete.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/passengers.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/search.js?v={{ $jpSearchAssetVersion }}" defer></script>
@endpush

@push('scripts')
<script src="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/js/results.js?v=34"></script>
<script src="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/js/flight-cards.js?v=7"></script>
@endpush

@include('frontend.flights.partials.results-page')
