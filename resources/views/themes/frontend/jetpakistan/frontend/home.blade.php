{{-- JETPK-HOME-SEARCH-UX-POLISH-1: JetPakistan homepage with polished search UX --}}
@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', client_branding()->companyName().' — Book flights across Pakistan')
@section('jp_body_class', 'jp-home')

@push('styles')
@php $jpSearchAssetVersion = 34; @endphp
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/jp-search.css?v={{ $jpSearchAssetVersion }}">
@endpush

@section('content')
  @include('themes.frontend.jetpakistan.sections.hero')
  @include('themes.frontend.jetpakistan.sections.feature-board')
  @include('themes.frontend.jetpakistan.sections.trust')
  @include('themes.frontend.jetpakistan.sections.groups')
  @include('themes.frontend.jetpakistan.sections.fares')
  @include('themes.frontend.jetpakistan.sections.routes')
  @include('themes.frontend.jetpakistan.sections.destinations')
  @include('themes.frontend.jetpakistan.sections.why-book')
  @include('themes.frontend.jetpakistan.sections.support-cta')
@endsection

@push('theme-scripts')
@php $jpSearchAssetVersion = 34; @endphp
@php $jpThemeBase = rtrim(client_theme()->frontendThemeUrl(), '/'); @endphp
<script src="{{ $jpThemeBase }}/js/reveal.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/effects.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/jp-dates.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/forms.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/airport-autocomplete.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/passengers.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/search.js?v={{ $jpSearchAssetVersion }}" defer></script>
@endpush
