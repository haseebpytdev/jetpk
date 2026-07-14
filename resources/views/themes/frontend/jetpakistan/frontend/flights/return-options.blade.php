{{-- JetPK return flight selection — JetPK layout + result cards --}}
@extends('themes.frontend.jetpakistan.layouts.frontend')

@php
    $jpBrandName = client_branding()->companyName();
    $jpAssetVersion = 31;
@endphp

@section('title', 'Select return flight — '.$jpBrandName)
@section('jp_body_class', 'jp-flights-results')

@push('styles')
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/results-base.css?v={{ $jpAssetVersion }}">
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/results.css?v={{ $jpAssetVersion }}">
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/flight-cards.css?v=9">
@endpush

@section('content')
    <div class="ota-results-pro jp-return-options">
        <header class="ota-results-pro-head" aria-labelledby="ota-return-options-heading">
            <div class="wrap">
                <div class="ota-results-pro-head-grid">
                    <div class="ota-results-pro-head-main">
                        <p class="ota-results-pro-kicker">{{ __('Return flight') }}</p>
                        <h1 id="ota-return-options-heading" class="ota-results-pro-title">{{ __('Select return flight') }}</h1>
                        <p class="ota-results-pro-sub">
                            <span>{{ $searchSummary ?? '' }}</span>
                            <span class="ota-results-pro-pill">{{ __('Fares in PKR') }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </header>
        <div class="wrap ota-results-pro-body ota-results-pro-body--wide">
            <div class="ota-return-split-steps jp-return-split-steps" role="status">
                <strong>{{ __('Round-trip selection') }}</strong>
                <span class="ota-return-split-steps__track">
                    <span class="ota-return-split-steps__step is-done">{{ __('1. Outbound selected') }}</span>
                    <span class="ota-return-split-steps__sep" aria-hidden="true">·</span>
                    <span class="ota-return-split-steps__step is-active">{{ __('2. Select return flight') }}</span>
                </span>
            </div>

            @if ($errors->has('flight_id'))
                <div class="jp-alert jp-alert--warning" role="alert">{{ $errors->first('flight_id') }}</div>
            @endif

            <div
                data-return-options-root
                data-search-id="{{ $searchId }}"
                data-outbound-key="{{ $outboundKey }}"
                data-return-options-data-url="{{ $returnOptionsDataUrl }}"
                data-select-return-combo-url="{{ $selectReturnComboUrl }}"
            >
                <div class="ota-return-split-selected-outbound" data-outbound-summary>
                    <h2 class="jp-return-split-selected-outbound__title">{{ __('Selected outbound') }}</h2>
                    <div class="ota-return-split-selected-outbound__body" data-outbound-summary-body></div>
                </div>

                <h2 class="jp-return-split-list-heading">{{ __('Select return flight') }}</h2>
                <p class="jp-return-options-summary" data-return-options-summary>{{ __('Loading return options…') }}</p>

                <div data-return-options-list class="ota-return-split-list jp-results-list"></div>

                <div class="jp-load-more-wrap">
                    <button type="button" class="btn btn-ghost" data-return-load-more disabled>{{ __('Load more') }}</button>
                </div>
                <p class="jp-return-options-note" data-return-expired-message hidden>{{ __('This fare search has expired. Please search again.') }}</p>
                <p class="jp-return-options-note" data-return-empty-message hidden>{{ __('No compatible return options for this outbound flight. Please choose another outbound option.') }}</p>
                <p class="jp-return-options-back"><a href="{{ $resultsUrl }}">← {{ __('Change outbound flight') }}</a></p>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    @include('frontend.partials.ota-flight-details-modal')
    @include('frontend.partials.ota-fare-summary-modal')
@endpush

@push('scripts')
<script src="{{ ui_asset('js/ota-flight-detail-builders.js') }}"></script>
<script src="{{ ui_asset('js/ota-flight-details-modal.js') }}"></script>
<script src="{{ ui_asset('js/ota-branded-fares.js') }}"></script>
<script src="{{ ui_asset('js/ota-fare-breakdown-modal.js') }}"></script>
<script src="{{ ui_asset('js/ota-return-split-cards.js') }}"></script>
<script src="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/js/results.js?v=32"></script>
<script src="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/js/flight-cards.js?v=7"></script>
@include('frontend.flights.partials.return-options-script')
@endpush
