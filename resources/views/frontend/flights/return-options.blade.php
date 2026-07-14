@extends(client_layout('frontend', 'frontend'))

@section('title', 'Select return flight')

@section('content')
    <div class="ota-results-pro">
        <header class="ota-results-pro-head" aria-labelledby="ota-return-options-heading">
            <div class="container">
                <div class="ota-results-pro-head-grid">
                    <div class="ota-results-pro-head-main">
                        <p class="ota-results-pro-kicker"><i class="fa fa-plane" aria-hidden="true"></i> {{ __('Return flight') }}</p>
                        <h1 id="ota-return-options-heading" class="ota-results-pro-title">{{ __('Select return flight') }}</h1>
                        <p class="ota-results-pro-sub">
                            <span>{{ $searchSummary ?? '' }}</span>
                            <span class="ota-results-pro-pill">{{ __('Fares in PKR') }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </header>
        <div class="container ota-results-pro-body ota-results-pro-body--pullup ota-results-pro-body--wide">
            <div class="ota-return-split-steps alert alert-info" role="status">
                <strong>{{ __('Round-trip selection') }}</strong>
                <span class="ota-return-split-steps__track">
                    <span class="ota-return-split-steps__step is-done">{{ __('1. Outbound selected') }}</span>
                    <span class="ota-return-split-steps__sep" aria-hidden="true">·</span>
                    <span class="ota-return-split-steps__step is-active">{{ __('2. Select return flight') }}</span>
                </span>
            </div>

            @if ($errors->has('flight_id'))
                <div class="alert alert-warning" role="alert">{{ $errors->first('flight_id') }}</div>
            @endif

            <div
                data-return-options-root
                data-search-id="{{ $searchId }}"
                data-outbound-key="{{ $outboundKey }}"
                data-return-options-data-url="{{ $returnOptionsDataUrl }}"
                data-select-return-combo-url="{{ $selectReturnComboUrl }}"
            >
                <div class="ota-return-split-selected-outbound" data-outbound-summary>
                    <h2 class="h5 ota-return-split-selected-outbound__title">{{ __('Selected outbound') }}</h2>
                    <div class="ota-return-split-selected-outbound__body" data-outbound-summary-body></div>
                </div>

                <h2 class="h5 ota-return-split-list-heading">{{ __('Select return flight') }}</h2>
                <p class="text-muted" data-return-options-summary>{{ __('Loading return options…') }}</p>

                <div data-return-options-list class="ota-return-split-list ota-mobile-results-list"></div>

                <div class="text-center" style="margin-top:12px;">
                    <button type="button" class="btn btn-default" data-return-load-more disabled>{{ __('Load more') }}</button>
                </div>
                <p class="text-muted" data-return-expired-message style="display:none;margin-top:12px;">{{ __('This fare search has expired. Please search again.') }}</p>
                <p class="text-muted" data-return-empty-message style="display:none;margin-top:12px;">{{ __('No compatible return options for this outbound flight. Please choose another outbound option.') }}</p>
                <p style="margin-top: 16px;"><a href="{{ $resultsUrl }}">← {{ __('Change outbound flight') }}</a></p>
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
@include('frontend.flights.partials.return-options-script')
@endpush
