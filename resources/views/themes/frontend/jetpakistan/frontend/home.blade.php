{{-- JETPK-HOME-SEARCH-UX-POLISH-1: JetPakistan homepage with polished search UX --}}
@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', client_branding()->companyName().' — Book flights across Pakistan')
@section('jp_body_class', 'jp-home')

@push('head-meta')
@php
    $jpSeoResolver = app(\App\Services\Client\ClientPageContentResolver::class);
    $jpIsPreview = $jpSeoResolver->isDraftPreview(\App\Support\Client\ClientPageKeys::HOME);
    $jpBrandName = client_branding()->companyName();
    $jpMetaDescription = trim((string) config('ota-brand.tagline', 'Book domestic and international flights with trusted travel support.'));
    $jpCanonical = url('/');
    $jpOgImage = client_branding()->logoUrl() ?: asset('themes/frontend/jetpakistan/images/og-default.png');
@endphp
<meta name="description" content="{{ $jpMetaDescription }}">
@if (! $jpIsPreview)
<link rel="canonical" href="{{ $jpCanonical }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $jpBrandName }} — Book flights across Pakistan">
<meta property="og:description" content="{{ $jpMetaDescription }}">
<meta property="og:url" content="{{ $jpCanonical }}">
<meta property="og:image" content="{{ $jpOgImage }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $jpBrandName }} — Book flights across Pakistan">
<meta name="twitter:description" content="{{ $jpMetaDescription }}">
@else
<meta name="robots" content="noindex, nofollow">
@endif
@endpush

@push('styles')
@php $jpSearchAssetVersion = 37; @endphp
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/jp-search.css?v={{ $jpSearchAssetVersion }}">
@endpush

@section('content')
  @include('themes.frontend.jetpakistan.sections.hero')
  @foreach (($homepageOrderedSections ?? []) as $jpSection)
    <!-- jp-section-start:{{ $jpSection['key'] }}:order-{{ $jpSection['order'] }} -->
    @include('themes.frontend.jetpakistan.sections.'.$jpSection['view'])
  @endforeach
@endsection

@push('theme-scripts')
@php $jpSearchAssetVersion = 37; @endphp
@php $jpThemeBase = rtrim(client_theme()->frontendThemeUrl(), '/'); @endphp
<script src="{{ $jpThemeBase }}/js/reveal.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/effects.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/jp-dates.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/forms.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/airport-autocomplete.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/passengers.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/search.js?v={{ $jpSearchAssetVersion }}" defer></script>
@endpush
