{{-- JETPK-HOME-SEARCH-UX-POLISH-1: JetPakistan homepage with polished search UX --}}
@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', $seo['title'] ?? (client_branding()->companyName().' — Book flights across Pakistan'))
@section('jp_body_class', 'jp-home')

@push('styles')
@php $jpSearchAssetVersion = 37; @endphp
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/jp-search.css?v={{ $jpSearchAssetVersion }}">
@endpush

@php
    use App\Support\Client\JetpkHomepageSectionData;

    $jpHomeSizing = app(JetpkHomepageSectionData::class);
    $jpHeroLayoutCss = $jpHomeSizing->heroLayoutCssVariables();
@endphp
@push('head')
<style>
.jp-home {
@foreach ($jpHeroLayoutCss as $cssVar => $cssValue)
  {{ $cssVar }}: {{ $cssValue }};
@endforeach
}
</style>
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
