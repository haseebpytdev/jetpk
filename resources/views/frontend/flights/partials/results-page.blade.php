@section('content')
    @php
        $inlineDisplay = $inlineDisplay ?? [
            'origin_code' => strtoupper(trim((string) ($criteria['origin'] ?? ''))),
            'destination_code' => strtoupper(trim((string) ($criteria['destination'] ?? ''))),
            'origin_subtitle' => '',
            'destination_subtitle' => '',
            'depart_main' => '',
            'depart_day' => '',
            'return_main' => '',
            'return_day' => '',
        ];
    @endphp
    @include('partials.agent-booking-mode-banner')
    <div class="ota-results-pro">
        <header class="ota-results-pro-head" aria-labelledby="ota-results-heading">
            <div class="container jp-flights-results__container">
                <div class="ota-results-pro-head-grid">
                    <div class="ota-results-pro-head-main">
                        <p class="ota-results-pro-kicker"><i class="fa fa-plane" aria-hidden="true"></i> Flight results</p>
                        <h1 id="ota-results-heading" class="ota-results-pro-title">Available flights</h1>
                        <p class="ota-results-pro-sub">
                            <span data-hero-route-summary>{{ $searchSummary ?? '' }}</span>
                            <span class="ota-results-pro-pill">Fares in PKR</span>
                        </p>
                    </div>
                </div>
            </div>
        </header>
        <div class="container ota-results-pro-body ota-results-pro-body--pullup ota-results-pro-body--wide jp-flights-results__container">
            @if(current_client_slug() === 'jetpk')
                <div class="jp-results-search-placement">
                    @include('themes.frontend.jetpakistan.components.search.home-flights-search', [
                        'context' => 'results',
                        'criteria' => $criteria,
                        'inlineDisplay' => $inlineDisplay,
                        'minDate' => now()->format('Y-m-d'),
                    ])
                </div>
            @else
                <div class="ota-results-widget-wide">
                    @include('frontend.partials.ota-hero-flight-search', [
                        'context' => 'results',
                        'defaultDepart' => $criteria['depart_date'] ?? '',
                        'defaultOrigin' => $criteria['origin'] ?? '',
                        'defaultDestination' => $criteria['destination'] ?? '',
                        'defaultOriginDisplay' => $inlineDisplay['origin_code'] ?? ($criteria['origin'] ?? ''),
                        'defaultDestinationDisplay' => $inlineDisplay['destination_code'] ?? ($criteria['destination'] ?? ''),
                        'defaultReturnDate' => $criteria['return_date'] ?? '',
                        'defaultTripType' => $criteria['trip_type'] ?? 'one_way',
                        'minDate' => now()->format('Y-m-d'),
                        'adults' => $criteria['adults'] ?? 1,
                        'children' => $criteria['children'] ?? 0,
                        'infants' => $criteria['infants'] ?? 0,
                        'cabin' => $criteria['cabin'] ?? 'economy',
                    ])
                </div>
            @endif
            <div class="row">
                <aside class="col-md-3 ota-results-filters" data-filter-panel>
                    <div class="ota-results-mobile-bar ota-mobile-bottom-bar">
                        <button type="button" class="btn btn-default" data-mobile-open-sort aria-label="Open sort and filters">Sort &amp; filters</button>
                        <button type="button" class="btn btn-primary" data-mobile-filter-open>Filter results <span class="badge" data-active-filter-count>0</span></button>
                    </div>
                    <div class="ota-filter-backdrop" data-filter-backdrop aria-hidden="true"></div>
                    <div class="ota-filter-card jp-filter-panel" data-filter-drawer>
                        <div class="ota-filter-card-head jp-filter-panel__head">
                            <h4 class="ota-filter-title jp-filter-panel__title">Refine results</h4>
                            <div class="ota-filter-card-head-actions">
                                <button type="button" class="btn btn-link btn-sm ota-filter-close-btn jp-filter-panel__close" data-mobile-filter-close aria-label="Close">Close</button>
                            </div>
                        </div>
                        <details class="ota-filter-section jp-filter-section" open>
                            <summary class="ota-filter-section-title jp-filter-accordion-trigger">Sort &amp; journey</summary>
                        <div class="form-group jp-filter-field">
                            <label class="control-label jp-filter-label">Sort</label>
                            <select class="form-control jp-filter-control jp-filter-select" data-filter-sort data-filter-key="sort" id="ota-filter-sort">
                                <option value="recommended">Recommended</option>
                                <option value="cheapest">Cheapest</option>
                                <option value="fastest">Fastest</option>
                                <option value="earliest_departure">Earliest departure</option>
                                <option value="latest_departure">Latest departure</option>
                                <option value="airline_az">Airline A-Z</option>
                                <option value="price_desc">Price: high to low</option>
                                <option value="arrival_time">Arrival time</option>
                            </select>
                        </div>
                        <div class="form-group jp-filter-field">
                            <label class="control-label jp-filter-label">Stops</label>
                            <select class="form-control jp-filter-control jp-filter-select" data-filter-stops data-filter-key="stops">
                                <option value="">Any</option>
                                <option value="direct">Direct</option>
                                <option value="1_stop">1 stop</option>
                                <option value="2_plus">2+ stops</option>
                            </select>
                        </div>
                        </details>
                        <details class="ota-filter-section jp-filter-section" open>
                            <summary class="ota-filter-section-title jp-filter-accordion-trigger">Airline &amp; fare</summary>
                        <div class="form-group jp-filter-field">
                            <label class="control-label jp-filter-label">Airlines</label>
                            <select class="form-control jp-filter-control jp-filter-select" data-filter-airline data-filter-key="airline">
                                <option>All carriers</option>
                            </select>
                        </div>
                        <div class="form-group jp-filter-field">
                            <label class="control-label jp-filter-label">Cabin</label>
                            <select class="form-control jp-filter-control jp-filter-select" data-filter-cabin data-filter-key="cabin"><option value="">Any</option></select>
                        </div>
                        <div class="form-group jp-filter-field">
                            <label class="control-label jp-filter-label">Baggage</label>
                            <select class="form-control jp-filter-control jp-filter-select" data-filter-baggage data-filter-key="baggage"><option value="">Any</option></select>
                        </div>
                        <div class="form-group jp-filter-field">
                            <label class="control-label jp-filter-label">Fare family</label>
                            <select class="form-control jp-filter-control jp-filter-select" data-filter-fare-family data-filter-key="fare_family"><option value="">Any</option></select>
                        </div>
                        <div class="form-group jp-filter-field">
                            <label class="control-label jp-filter-label">Refundable only</label>
                            <div class="checkbox jp-filter-check-row"><label><input type="checkbox" class="jp-filter-check" data-filter-refundable data-filter-key="refundable"> Yes</label></div>
                        </div>
                        <div class="form-group checkbox jp-filter-check-row">
                            <label><input type="checkbox" class="jp-filter-check" data-filter-bookable-only data-filter-key="bookable_only"> Bookable only</label>
                        </div>
                        </details>
                        <details class="ota-filter-section jp-filter-section" open>
                            <summary class="ota-filter-section-title jp-filter-accordion-trigger">Schedule &amp; connections</summary>
                        <div class="form-group jp-filter-field">
                            <label class="control-label jp-filter-label">Departure time</label>
                            <select class="form-control jp-filter-control jp-filter-select" data-filter-departure-window data-filter-key="departure_window"><option value="">Any</option></select>
                        </div>
                        <div class="form-group jp-filter-field">
                            <label class="control-label jp-filter-label">Arrival time</label>
                            <select class="form-control jp-filter-control jp-filter-select" data-filter-arrival-window data-filter-key="arrival_window"><option value="">Any</option></select>
                        </div>
                        <div class="form-group jp-filter-field">
                            <label class="control-label jp-filter-label">Duration bucket</label>
                            <select class="form-control jp-filter-control jp-filter-select" data-filter-duration-bucket data-filter-key="duration_bucket"><option value="">Any</option></select>
                        </div>
                        <div class="form-group jp-filter-field">
                            <label class="control-label jp-filter-label">Layover airport</label>
                            <select class="form-control jp-filter-control jp-filter-select" data-filter-layover-airport data-filter-key="layover_airport"><option value="">Any</option></select>
                        </div>
                        <div class="form-group jp-filter-field">
                            <label class="control-label jp-filter-label">Operating airline</label>
                            <select class="form-control jp-filter-control jp-filter-select" data-filter-operating-airline data-filter-key="operating_airline"><option value="">Any</option></select>
                        </div>
                        </details>
                        <div class="ota-filter-actions jp-filter-actions">
                            <button type="button" class="btn btn-link btn-sm btn-block ota-filter-clear-all jp-filter-clear" data-filter-reset>Clear all filters</button>
                            <button type="button" class="btn btn-primary btn-block visible-xs visible-sm" data-mobile-filter-apply>Apply filters</button>
                        </div>
                    </div>
                </aside>
                <div class="col-md-9" data-results-root data-flight-results data-jp-home-url="{{ client_route('home') }}" data-results-url="{{ client_route('flights.results') }}" data-results-data-url="{{ client_route('flights.results.data') }}" data-results-search-url="{{ client_route('flights.results.search') }}" data-booking-passengers-url="{{ client_route('booking.passengers') }}" data-search-id="{{ $searchId }}" @if(current_client_slug() === 'jetpk') data-can-see-supplier-source="{{ \App\Support\Suppliers\SupplierSourceVisibility::canCurrentUser() ? '1' : '0' }}" @endif data-empty-policy-message="{{ e($resultsEmptyPolicyMessage ?? '') }}" data-return-split-flow="{{ !empty($returnSplitFlow) ? '1' : '0' }}" data-return-options-data-url="{{ $returnOptionsDataUrl ?? '' }}" data-select-return-combo-url="{{ $selectReturnComboUrl ?? '' }}" data-airports-search-url="{{ url('/airports/search') }}" data-freshness-refresh-due="{{ (int) config('ota.offer_freshness.refresh_due_seconds', 300) }}" data-freshness-stale-after="{{ (int) config('ota.offer_freshness.stale_after_seconds', 600) }}" data-revalidate-offer-url="{{ $revalidateOfferUrl ?? client_route('flights.results.revalidate-offer') }}" data-nearby-dates-url="{{ $nearbyDatesUrl ?? client_route('flights.results.nearby-dates') }}" data-airline-logo-fallback="{{ e(app(\App\Services\TravelData\AirlineBrandingService::class)->genericFallbackLogoUrl()) }}" data-results-refresh-timeout="{{ (int) config('ota.offer_freshness.stale_after_seconds', 600) > 0 ? min(12000, (int) config('ota.offer_freshness.refresh_due_seconds', 300) * 4) : 12000 }}" data-multicity-inquiry-url="{{ $multicityInquiryUrl ?? client_route('flights.multicity.inquiry') }}" data-multicity-inquiry-notice="{{ e($multicityInquiryNotice ?? '') }}">
                        @if (!empty($warnings ?? []))
                        <div class="alert alert-warning">
                            <strong>Search notice:</strong>
                            <ul style="margin:8px 0 0 18px;">
                                @foreach ($warnings as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @php
                        $offerFreshnessRefresh = $offerFreshnessRefresh ?? [];
                        $freshnessCheckoutMessage = trim((string) ($offerFreshnessRefresh['message'] ?? ''));
                        if ($freshnessCheckoutMessage === '' && $errors->has('flight_id')) {
                            $freshnessCheckoutMessage = trim((string) $errors->first('flight_id'));
                        }
                    @endphp
                    @if (! empty($offerFreshnessRefresh['required'] ?? false))
                        <div hidden data-selected-offer-refresh-init data-offer-id="{{ $offerFreshnessRefresh['selected_offer_id'] ?? '' }}"></div>
                    @elseif ($freshnessCheckoutMessage !== '')
                        <div class="alert alert-warning" role="alert" aria-live="polite" style="margin-bottom:12px;">
                            {{ $freshnessCheckoutMessage }}
                        </div>
                    @endif
                    <p class="alert alert-danger" data-offer-freshness-error style="display:none;margin-bottom:12px;" role="alert" aria-live="assertive"></p>
                    <div class="alert alert-warning" data-iati-price-change-prompt style="display:none;margin-bottom:12px;" role="dialog" aria-live="polite" hidden>
                        <p style="margin:0 0 6px;" data-iati-price-change-message>Fare price has changed. Please review before continuing.</p>
                        <p style="margin:0 0 8px;"><span data-iati-price-change-old></span> &rarr; <strong data-iati-price-change-new></strong></p>
                        <button type="button" class="btn btn-sm btn-primary" data-iati-price-change-continue>Continue</button>
                        <button type="button" class="btn btn-sm btn-default" data-iati-price-change-cancel>Cancel</button>
                    </div>
                    @if (!empty($returnSplitFlow))
                        <div class="ota-return-split-steps alert alert-info" data-return-split-steps role="status">
                            <strong>{{ __('Round-trip selection') }}</strong>
                            <span class="ota-return-split-steps__track">
                                <span class="ota-return-split-steps__step is-active">{{ __('1. Select outbound flight') }}</span>
                                <span class="ota-return-split-steps__sep" aria-hidden="true">·</span>
                                <span class="ota-return-split-steps__step">{{ __('2. Select return') }}</span>
                            </span>
                        </div>
                    @endif
                    <p class="alert alert-danger" data-return-split-error style="display:none;margin-bottom:12px;" role="alert" aria-live="assertive"></p>
                    <div class="ota-return-split-selected-outbound" data-outbound-summary hidden>
                        <h2 class="h5 ota-return-split-selected-outbound__title">{{ __('Selected outbound') }}</h2>
                        <div class="ota-return-split-selected-outbound__body" data-outbound-summary-body></div>
                        <p style="margin-top:8px;"><button type="button" class="btn btn-link btn-sm" data-change-outbound>? {{ __('Change outbound flight') }}</button></p>
                    </div>
                    <p class="text-muted" data-results-summary>Showing fares...</p>
                    <div class="ota-date-price-strip" data-date-price-strip hidden aria-label="{{ __('Nearby dates and fares') }}">
                        <div class="ota-date-price-strip__inner" data-date-price-strip-inner></div>
                    </div>
                    <div data-results-list class="ota-mobile-results-list" aria-busy="true" aria-live="polite">
                        @for ($i = 0; $i < 4; $i++)
                            <article class="jp-result-card ota-result-pro-card ota-result-card-v3 ota-result-skeleton-card" aria-hidden="true">
                                <div class="ota-result-card-main">
                                    <div class="ota-result-col-brand">
                                        <div class="ota-skeleton ota-skeleton--logo"></div>
                                        <div class="ota-skeleton ota-skeleton--line ota-skeleton--line-sm"></div>
                                    </div>
                                    <div class="ota-result-col-route ota-result-col-route--oneway">
                                        <div class="ota-skeleton-block">
                                            <div class="ota-skeleton ota-skeleton--line ota-skeleton--line-lg"></div>
                                            <div class="ota-skeleton ota-skeleton--line ota-skeleton--line-xs"></div>
                                        </div>
                                        <div class="ota-skeleton-block ota-skeleton-block--mid">
                                            <div class="ota-skeleton ota-skeleton--line ota-skeleton--line-sm"></div>
                                            <div class="ota-skeleton ota-skeleton--pill"></div>
                                        </div>
                                        <div class="ota-skeleton-block ota-skeleton-block--end">
                                            <div class="ota-skeleton ota-skeleton--line ota-skeleton--line-lg"></div>
                                            <div class="ota-skeleton ota-skeleton--line ota-skeleton--line-xs"></div>
                                        </div>
                                    </div>
                                    <div class="ota-result-col-price">
                                        <div class="ota-skeleton ota-skeleton--btn"></div>
                                        <div class="ota-skeleton ota-skeleton--line ota-skeleton--line-xs ota-skeleton--price-meta"></div>
                                    </div>
                                </div>
                            </article>
                        @endfor
                    </div>
                    <div class="text-center" style="margin-top:12px;">
                        <button type="button" class="btn btn-default" data-load-more disabled>Load more</button>
                    </div>
                    <p class="text-muted" data-expired-message style="display:none;margin-top:12px;">This fare search has expired. Please search again.</p>
                    <p class="text-muted" data-empty-filtered-message style="display:none;margin-top:12px;">No fares match your filters. Try clearing filters.</p>
                    <p style="margin-top: 16px;"><a href="{{ client_route('home') }}#jp-flight-search">Back to flight search</a> · <a href="{{ client_route('home') }}">Home</a></p>
                </div>
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
<script src="{{ ui_asset('js/ota-flight-fallback-details.js') }}"></script>
<script src="{{ ui_asset('js/ota-flight-details-modal.js') }}"></script>
<script src="{{ ui_asset('js/ota-branded-fares.js') }}"></script>
<script src="{{ ui_asset('js/ota-fare-breakdown-modal.js') }}"></script>
<script src="{{ ui_asset('js/ota-return-split-cards.js') }}"></script>
<script>
(function () {
    var root = document.querySelector('[data-results-root]');
    if (!root) return;
    var isJetPkResults = document.body.classList.contains('jp-flights-results');
    var canSeeSupplierSource = !isJetPkResults || root.getAttribute('data-can-see-supplier-source') === '1';
    var searchId = (root.getAttribute('data-search-id') || '').trim();
    var urlSearchIdParam = new URLSearchParams(window.location.search).get('search_id');
    if (!searchId && urlSearchIdParam && String(urlSearchIdParam).trim() !== '') {
        searchId = String(urlSearchIdParam).trim();
        root.setAttribute('data-search-id', searchId);
    }
    var returnSplitFlow = root.getAttribute('data-return-split-flow') === '1';
    var returnOptionsDataUrl = root.getAttribute('data-return-options-data-url') || '';
    var selectReturnComboUrl = root.getAttribute('data-select-return-combo-url') || '';
    var resultsPageUrl = (root.getAttribute('data-results-url') || '/flights/results').replace(/\?.*$/, '');
    var resultsSearchUrl = (root.getAttribute('data-results-search-url') || (resultsPageUrl + '/search')).replace(/\?.*$/, '');
    var resultsDataUrl = (root.getAttribute('data-results-data-url') || (resultsPageUrl + '/data')).replace(/\?.*$/, '');
    var returnSplitStepsEl = root.querySelector('[data-return-split-steps]');
    var returnSplitErrorEl = root.querySelector('[data-return-split-error]');
    var outboundSummaryWrap = root.querySelector('[data-outbound-summary]');
    var outboundSummaryBody = root.querySelector('[data-outbound-summary-body]');
    var changeOutboundBtn = root.querySelector('[data-change-outbound]');
    var selectedOutbound = null;
    var returnStepActive = false;
    var returnOptionsPage = 1;
    var returnOptionsHasMore = true;
    var returnOptionsLoading = false;
    var splitCheckoutInFlight = false;
    var selectedReturnAmount = null;
    var offersById = {};
    var selectedFareOptionByOfferId = {};
    var oneWayCheckoutInFlight = false;
    var splitBrandedState = window.OtaBrandedFares ? OtaBrandedFares.createState() : null;

    function pruneSelectedFareOptions() {
        Object.keys(selectedFareOptionByOfferId).forEach(function (oid) {
            if (!offersById[oid]) {
                delete selectedFareOptionByOfferId[oid];
            }
        });
    }

    function clearOtherOfferFareSelections(activeOfferId) {
        Object.keys(selectedFareOptionByOfferId).forEach(function (oid) {
            if (oid !== activeOfferId) {
                delete selectedFareOptionByOfferId[oid];
            }
        });
    }
    var list = root.querySelector('[data-results-list]');
    var loadMore = root.querySelector('[data-load-more]') || document.querySelector('[data-load-more]');
    var expired = root.querySelector('[data-expired-message]');
    var summary = root.querySelector('[data-results-summary]');
    var chips = null;
    var filteredEmpty = root.querySelector('[data-empty-filtered-message]');
    var freshnessError = root.querySelector('[data-offer-freshness-error]');
    var iatiPriceChangePrompt = root.querySelector('[data-iati-price-change-prompt]');
    var iatiPriceChangeOldEl = iatiPriceChangePrompt ? iatiPriceChangePrompt.querySelector('[data-iati-price-change-old]') : null;
    var iatiPriceChangeNewEl = iatiPriceChangePrompt ? iatiPriceChangePrompt.querySelector('[data-iati-price-change-new]') : null;
    var iatiPriceChangeContinueBtn = iatiPriceChangePrompt ? iatiPriceChangePrompt.querySelector('[data-iati-price-change-continue]') : null;
    var iatiPriceChangeCancelBtn = iatiPriceChangePrompt ? iatiPriceChangePrompt.querySelector('[data-iati-price-change-cancel]') : null;
    var iatiPriceChangeOnContinue = null;
    var iatiPriceChangeOnCancel = null;
    var iatiSelectRevalidationInFlight = false;
    var selectedOfferRefreshInit = root.querySelector('[data-selected-offer-refresh-init]');
    var revalidateOfferUrl = root.getAttribute('data-revalidate-offer-url') || '';
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var freshnessRefreshDueSec = parseInt(root.getAttribute('data-freshness-refresh-due') || '300', 10);
    var freshnessStaleAfterSec = parseInt(root.getAttribute('data-freshness-stale-after') || '600', 10);
    var searchFreshnessMeta = null;
    var freshnessTimer = null;
    var freshnessAutoRefreshInFlight = false;
    var freshnessAutoRefreshState = { refresh_due: false, stale: false };
    var mobileOpenBtns = document.querySelectorAll('[data-mobile-filter-open]');
    var mobileApply = document.querySelector('[data-mobile-filter-apply]');
    var mobileCloseBtns = document.querySelectorAll('[data-mobile-filter-close]');
    var mobileOpenSort = document.querySelector('[data-mobile-open-sort]');
    var drawer = document.querySelector('[data-filter-drawer]');
    var backdrop = document.querySelector('[data-filter-backdrop]');
    var page = 1;
    var loading = false;
    var hasMore = true;
    var inlinePanel = document.querySelector('[data-inline-search]');
    var inlineForm = inlinePanel ? inlinePanel.querySelector('[data-inline-form]') : null;
    if (!inlineForm) {
        inlineForm = document.querySelector('[data-inline-form]');
        if (inlineForm && !inlinePanel) inlinePanel = inlineForm.closest('[data-inline-search]');
    }
    var inlineStatus = inlinePanel ? inlinePanel.querySelector('[data-inline-status]') : null;
    var inlineError = inlinePanel ? inlinePanel.querySelector('[data-inline-error]') : null;
    var heroRouteSummary = document.querySelector('[data-hero-route-summary]');
    var currentCriteria = @json($criteria ?? []);
    var airlineLogoFallbackUrl = root.getAttribute('data-airline-logo-fallback') || '/images/airline-generic.svg';
    var airlineLogoCdnTemplate = '';
    var resultsRefreshTimeoutMs = parseInt(root.getAttribute('data-results-refresh-timeout') || '12000', 10);
    var checkoutNavStorageKey = 'ota_results_left_for_checkout';
    var bfcacheRefreshInFlight = false;
    if (window.OtaReturnSplitCards) {
        OtaReturnSplitCards.init({ airlineLogoCdnTemplate: airlineLogoCdnTemplate, airlineLogoFallbackUrl: airlineLogoFallbackUrl });
    }
    if (window.OtaFlightDetailBuilders) {
        OtaFlightDetailBuilders.init({ airlineLogoCdnTemplate: airlineLogoCdnTemplate, airlineLogoFallbackUrl: airlineLogoFallbackUrl });
    }
    if (window.OtaBrandedFares) {
        OtaBrandedFares.init({ criteria: currentCriteria });
    }
    if (window.OtaFareBreakdownModal) {
        OtaFareBreakdownModal.init();
    }
    if (window.OtaFlightDetailsModal) {
        OtaFlightDetailsModal.init();
    }
    var debugFares = @json(! empty($debugFares ?? false));
    function wantsDebugFares() {
        return debugFares;
    }
    var currentFilters = {
        airline: '',
        stops: '',
        refundable: '',
        cabin: '',
        baggage: '',
        departure_window: '',
        arrival_window: '',
        duration_bucket: '',
        layover_airport: '',
        fare_family: '',
        bookable_only: '',
        operating_airline: '',
        sort: 'recommended'
    };
    var filterAirline = document.querySelector('[data-filter-airline]');
    var filterStops = document.querySelector('[data-filter-stops]');
    var filterRefundable = document.querySelector('[data-filter-refundable]');
    var filterSort = document.querySelector('[data-filter-sort]');
    var filterResetButtons = document.querySelectorAll('[data-filter-reset]');

    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function markResultsCheckoutNavigation() {
        try {
            sessionStorage.setItem(checkoutNavStorageKey, String(searchId || '1'));
        } catch (err) {}
    }

    function navigationWasBackForward() {
        try {
            var nav = performance.getEntriesByType && performance.getEntriesByType('navigation');
            if (nav && nav[0] && nav[0].type === 'back_forward') {
                return true;
            }
        } catch (err) {}
        return false;
    }

    function expireStaleOfferSelection() {
        offersById = {};
        selectedFareOptionByOfferId = {};
        expandedBrandedFaresByOfferId = {};
        if (splitBrandedState) {
            splitBrandedState.offersById = {};
        }
    }

    function buildResultsSkeletonCardHtml() {
        if (isJetPkResults && window.JetPkResultCards && typeof JetPkResultCards.buildSkeletonCard === 'function') {
            return JetPkResultCards.buildSkeletonCard();
        }
        return '<article class="jp-result-card ota-result-pro-card ota-result-card-v3 ota-result-skeleton-card" aria-hidden="true">' +
            '<div class="ota-result-card-main">' +
            '<div class="ota-result-col-brand">' +
            '<div class="ota-skeleton ota-skeleton--logo"></div>' +
            '<div class="ota-skeleton ota-skeleton--line ota-skeleton--line-sm"></div>' +
            '</div>' +
            '<div class="ota-result-col-route ota-result-col-route--oneway">' +
            '<div class="ota-skeleton-block"><div class="ota-skeleton ota-skeleton--line ota-skeleton--line-lg"></div><div class="ota-skeleton ota-skeleton--line ota-skeleton--line-xs"></div></div>' +
            '<div class="ota-skeleton-block ota-skeleton-block--mid"><div class="ota-skeleton ota-skeleton--line ota-skeleton--line-sm"></div><div class="ota-skeleton ota-skeleton--pill"></div></div>' +
            '<div class="ota-skeleton-block ota-skeleton-block--end"><div class="ota-skeleton ota-skeleton--line ota-skeleton--line-lg"></div><div class="ota-skeleton ota-skeleton--line ota-skeleton--line-xs"></div></div>' +
            '</div>' +
            '<div class="ota-result-col-price">' +
            '<div class="ota-skeleton ota-skeleton--btn"></div>' +
            '<div class="ota-skeleton ota-skeleton--line ota-skeleton--line-xs ota-skeleton--price-meta"></div>' +
            '</div></div></article>';
    }

    function showResultsSkeleton(count) {
        if (!list) return;
        var n = Math.max(3, parseInt(count, 10) || 4);
        var html = '';
        for (var i = 0; i < n; i++) {
            html += buildResultsSkeletonCardHtml();
        }
        list.innerHTML = html;
        list.setAttribute('aria-busy', 'true');
        if (filteredEmpty) filteredEmpty.style.display = 'none';
        if (expired) expired.style.display = 'none';
    }

    function clearResultsSkeletonState() {
        if (list) list.removeAttribute('aria-busy');
    }

    var searchLoadTimeoutMs = 45000;
    var searchLoadTimeoutId = null;

    function hasSearchCriteria() {
        var origin = String(currentCriteria.origin || '').trim();
        var dest = String(currentCriteria.destination || '').trim();
        var depart = String(currentCriteria.depart_date || '').trim();
        return origin !== '' && dest !== '' && depart !== '';
    }

    function buildSearchParamsFromCriteria() {
        var params = new URLSearchParams();
        params.set('trip_type', currentCriteria.trip_type || 'one_way');
        params.set('from', currentCriteria.origin || '');
        params.set('to', currentCriteria.destination || '');
        params.set('depart', currentCriteria.depart_date || '');
        if ((currentCriteria.trip_type || 'one_way') === 'round_trip' && currentCriteria.return_date) {
            params.set('return_date', currentCriteria.return_date);
        }
        params.set('cabin', currentCriteria.cabin || 'economy');
        params.set('adults', String(currentCriteria.adults || 1));
        params.set('children', String(currentCriteria.children || 0));
        params.set('infants', String(currentCriteria.infants || 0));
        return params;
    }

    function buildSearchParamsForResolve() {
        if (inlineForm) {
            var formParams = new URLSearchParams(new FormData(inlineForm));
            if (formParams.get('from') && formParams.get('to') && formParams.get('depart')) {
                return formParams;
            }
        }
        if (hasSearchCriteria()) {
            return buildSearchParamsFromCriteria();
        }
        return null;
    }

    function clearSearchLoadTimeout() {
        if (searchLoadTimeoutId) {
            window.clearTimeout(searchLoadTimeoutId);
            searchLoadTimeoutId = null;
        }
    }

    function armSearchLoadTimeout() {
        clearSearchLoadTimeout();
        searchLoadTimeoutId = window.setTimeout(function () {
            if (!loading) return;
            loading = false;
            clearSearchLoadTimeout();
            showResultsError('Search is taking longer than expected. Please retry.');
            if (loadMore) loadMore.disabled = true;
        }, searchLoadTimeoutMs);
    }

    function hideResultsSkeleton(forceClearList) {
        clearResultsSkeletonState();
        if (!list || !forceClearList) return;
        var skeletonCards = list.querySelectorAll('.ota-result-skeleton-card');
        if (skeletonCards.length && skeletonCards.length === list.children.length) {
            list.innerHTML = '';
        }
    }

    function stripSkeletonCardsFromList() {
        if (!list) return;
        list.querySelectorAll('.ota-result-skeleton-card').forEach(function (node) {
            node.remove();
        });
        clearResultsSkeletonState();
    }

    function otaResultsDebugLog(label, detail) {
        if (typeof console !== 'undefined' && console.debug) {
            if (detail !== undefined) {
                console.debug('[OTA_RESULTS] ' + label, detail);
            } else {
                console.debug('[OTA_RESULTS] ' + label);
            }
        }
    }

    function normalizeResultsOfferRow(row) {
        if (!row || typeof row !== 'object') return null;
        var oid = String(row.offer_id || row.id || '').trim();
        if (!oid) return null;
        row.offer_id = oid;
        return row;
    }

    function showMissingOfferSelectionError() {
        showOneWayCheckoutError('Please select a fare again.');
    }

    function showResultsError(message) {
        var msg = message || 'Unable to load fares. Please try again.';
        hideResultsSkeleton(true);
        if (summary) summary.textContent = '';
        if (expired) expired.style.display = 'none';
        if (list) {
            list.innerHTML = '<p class="alert alert-danger" role="alert">' + esc(msg) + '</p>';
        }
        if (inlineError && inlineForm) {
            inlineError.textContent = msg;
            inlineError.hidden = false;
        }
    }

    function applySearchBootstrapJson(json) {
        searchId = String(json.search_id || '').trim();
        if (!searchId) {
            throw { message: 'Search did not return a search_id.' };
        }
        root.setAttribute('data-search-id', searchId);
        currentCriteria = json.criteria || currentCriteria;
        if (heroRouteSummary && json.summary && json.summary.text) {
            heroRouteSummary.textContent = json.summary.text;
        }
        if (json.inline_display) applyInlineDisplayFromServer(json.inline_display);
        syncInlineDateLabels();
        updateResultsUrl();
    }

    function ensureSearchId() {
        var resolved = (searchId || '').trim();
        if (resolved) {
            return Promise.resolve(resolved);
        }
        var params = buildSearchParamsForResolve();
        if (!params) {
            return Promise.reject({ message: 'Missing search parameters. Please search again.' });
        }
        return fetch(resultsSearchUrl + '?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.json().then(function (json) {
                if (!res.ok) {
                    throw { message: (json && json.message) ? json.message : 'Unable to start search.' };
                }
                applySearchBootstrapJson(json);
                return searchId;
            });
        });
    }

    function buildAirlineLogoHtml(offer) {
        var airlineDisplayName = (offer.airline_name || offer.primary_display_carrier_name || '').trim();
        var airlineCodeLabel = (offer.airline_code || offer.primary_display_carrier || '').trim();
        var logoUrl = (offer.airline_logo_url || '').trim();
        if (!logoUrl && airlineLogoFallbackUrl) {
            logoUrl = airlineLogoFallbackUrl;
        }
        if (logoUrl) {
            return '<div class="ota-result-brand-logo ota-airline-logo ota-airline-logo--img"><img src="' + esc(logoUrl) + '" alt="' + esc(airlineDisplayName || 'Airline') + ' logo" loading="lazy" decoding="async"></div>';
        }
        return '<div class="ota-result-brand-logo ota-airline-logo">' + esc(airlineCodeLabel || 'XX') + '</div>';
    }

    function triggerSafeResultsRefresh() {
        if (bfcacheRefreshInFlight || freshnessAutoRefreshInFlight) return;
        bfcacheRefreshInFlight = true;
        freshnessAutoRefreshInFlight = true;
        expireStaleOfferSelection();
        showResultsSkeleton(4);
        page = 1;
        hasMore = true;
        loading = false;
        hideFreshnessError();
        if (inlineForm) {
            inlineForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            window.setTimeout(function () {
                bfcacheRefreshInFlight = false;
                freshnessAutoRefreshInFlight = false;
            }, resultsRefreshTimeoutMs + 500);
            return;
        }
        rerunResultsSearch().finally(function () {
            bfcacheRefreshInFlight = false;
            freshnessAutoRefreshInFlight = false;
        });
    }

    function rerunResultsSearch() {
        var params = buildSearchParamsForResolve();
        if (!params) {
            return fetchPage(true);
        }
        expireStaleOfferSelection();
        showResultsSkeleton(4);
        page = 1;
        hasMore = true;
        loading = true;
        armSearchLoadTimeout();
        hideFreshnessError();
        if (summary) summary.textContent = 'Refreshing fares...';
        return fetch(resultsSearchUrl + '?' + params.toString(), {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (res) {
            return res.json().then(function (json) {
                if (!res.ok) throw json;
                return json;
            });
        }).then(function (json) {
            applySearchBootstrapJson(json);
            resetFiltersForNewSearch();
            searchFreshnessMeta = null;
            freshnessAutoRefreshState = { refresh_due: false, stale: false };
            page = 1;
            hasMore = true;
            if (summary) summary.textContent = 'Showing fares...';
            return fetchPage(true);
        }).catch(function (err) {
            showResultsError((err && err.message) ? err.message : 'Unable to refresh fares. Please try again.');
        });
    }

    function handleResultsPageShow(event) {
        var leftForCheckout = false;
        try {
            leftForCheckout = !!sessionStorage.getItem(checkoutNavStorageKey);
        } catch (err) {}
        var ref = document.referrer || '';
        var fromCheckoutFlow = leftForCheckout || /\/booking\/|passenger|checkout/i.test(ref);
        var backNav = !!event.persisted || navigationWasBackForward();
        if (!fromCheckoutFlow || !backNav) {
            return;
        }
        try {
            sessionStorage.removeItem(checkoutNavStorageKey);
        } catch (err) {}
        triggerSafeResultsRefresh();
    }

    window.addEventListener('pageshow', handleResultsPageShow);

    function formatRs(amount) {
        if (amount === null || amount === undefined || !isFinite(Number(amount))) return '--';
        return 'PKR ' + Math.round(Number(amount)).toLocaleString('en-US');
    }

    function formatCardButtonRs(amount) {
        if (amount === null || amount === undefined || !isFinite(Number(amount))) return '--';
        return 'Rs. ' + Math.round(Number(amount)).toLocaleString('en-US');
    }

    function formatBrandedFarePrice(opt) {
        if (!opt) return '';
        if (opt.displayed_price != null && Number(opt.displayed_price) > 0) {
            return formatRs(opt.displayed_price);
        }
        if (opt.price_display) {
            return String(opt.price_display).replace(/^Approx\.\s*/i, '').trim();
        }
        return '';
    }

    function buildStopsLabelHtml(stopsLabel, layoverSummary) {
        return window.OtaReturnSplitCards
            ? OtaReturnSplitCards.buildStopsLabelHtml(stopsLabel, layoverSummary)
            : '';
    }

    function buildJourneyScheduleRow(j, options) {
        return window.OtaFlightDetailBuilders
            ? OtaFlightDetailBuilders.buildJourneyScheduleRow(j, options)
            : '';
    }

    function buildJourneyFlightInfoHtml(journey, offer, kvFn) {
        return window.OtaFlightDetailBuilders
            ? OtaFlightDetailBuilders.buildJourneyFlightInfoHtml(journey, offer, kvFn)
            : '';
    }

    function buildJourneyDetailHeaderHtml(journey, offer) {
        return window.OtaFlightDetailBuilders
            ? OtaFlightDetailBuilders.buildJourneyDetailHeaderHtml(journey, offer)
            : '';
    }

    function buildJourneyRouteSummaryHtml(journey) {
        return window.OtaFlightDetailBuilders
            ? OtaFlightDetailBuilders.buildJourneyRouteSummaryHtml(journey)
            : '';
    }

    function buildSegmentDetailCardsHtml(segs, layoversDisplay, connectionUnavailable) {
        return window.OtaFlightDetailBuilders
            ? OtaFlightDetailBuilders.buildSegmentDetailCardsHtml(segs, layoversDisplay, connectionUnavailable)
            : '';
    }

    function buildJourneyDetailCardHtml(journey, offer, kvFn) {
        return window.OtaFlightDetailBuilders
            ? OtaFlightDetailBuilders.buildJourneyDetailCardHtml(journey, offer, kvFn)
            : '';
    }

    function buildFlightDetailJourneysHtml(offer, options) {
        return window.OtaFlightDetailBuilders
            ? OtaFlightDetailBuilders.buildFlightDetailJourneysHtml(offer, options)
            : '';
    }

    function openFareBreakdownModal(data) {
        if (window.OtaFareBreakdownModal) {
            OtaFareBreakdownModal.open(data);
        }
    }

    var expandedBrandedFaresByOfferId = {};

    function buildFlightDetailsPayload(offer) {
        var journeys = Array.isArray(offer.journeys_display) ? offer.journeys_display.slice() : [];
        var tripType = currentCriteria.trip_type || 'one_way';
        if (!journeys.length && Array.isArray(offer.segments) && offer.segments.length) {
            var stopCount = Number(offer.stops || 0);
            journeys.push({
                segments_display: offer.segments,
                layovers_display: offer.layovers_display || [],
                connection_details_unavailable: !!offer.connection_details_unavailable,
                origin: offer.departure_airport_code || '',
                destination: offer.arrival_airport_code || '',
                origin_city: offer.departure_city || '',
                destination_city: offer.arrival_city || '',
                departure_time_display: offer.departure_time_display || offer.departure_time || '',
                departure_date_display: offer.departure_date_display || '',
                arrival_time_display: offer.arrival_time_display || offer.arrival_time || '',
                arrival_date_display: offer.arrival_date_display || '',
                arrival_day_offset: offer.arrival_day_offset || null,
                duration_display: offer.itinerary_duration_display || offer.duration || '',
                stops_display: stopCount === 0 ? 'Direct' : (stopCount + ' Stop' + (stopCount === 1 ? '' : 's')),
            });
        }
        return {
            route_label: String(offer.route || '').trim(),
            trip_type: tripType,
            has_journey_grouping: journeys.length >= 2 && !offer.journey_grouping_unavailable,
            journey_grouping_unavailable: !!offer.journey_grouping_unavailable,
            journeys_display: journeys,
            segments: Array.isArray(offer.segments) ? offer.segments : [],
            layovers_display: Array.isArray(offer.layovers_display) ? offer.layovers_display : [],
            connection_details_unavailable: !!offer.connection_details_unavailable,
            summary_origin: offer.departure_airport_code || '',
            summary_destination: offer.arrival_airport_code || '',
            summary_origin_city: offer.departure_city || '',
            summary_destination_city: offer.arrival_city || '',
            summary_dep_time: offer.departure_time_display || offer.departure_time || '',
            summary_dep_date: offer.departure_date_display || '',
            summary_arr_time: offer.arrival_time_display || offer.arrival_time || '',
            summary_arr_date: offer.arrival_date_display || '',
            summary_arr_offset: offer.arrival_day_offset || null,
            summary_duration: offer.itinerary_duration_display || offer.duration || '',
            summary_stops: (Number(offer.stops || 0) === 0 ? 'Direct' : (Number(offer.stops || 0) + ' Stop' + (Number(offer.stops || 0) === 1 ? '' : 's'))),
            airline_logo_url: offer.airline_logo_url || '',
            airline_name: offer.airline_name || offer.primary_display_carrier_name || '',
            airline_code: offer.airline_code || offer.primary_display_carrier || '',
            cabin: offer.cabin || '',
            baggage_summary_display: offer.baggage_summary_display || offer.baggage || '',
            baggage_cabin_display: offer.baggage_cabin_display || '',
            baggage_checked_display: offer.baggage_checked_display || '',
            refundable: !!offer.refundable,
            has_fallback_details: !!offer.has_fallback_details,
            fallback_details: offer.fallback_details || null,
            offer_freshness: offer.offer_freshness || null,
        };
    }

    function scaleAmountForFareOption(amount, mainDisplayed, optionDisplayed) {
        var base = Number(amount || 0);
        var main = Number(mainDisplayed || 0);
        var option = Number(optionDisplayed || 0);
        if (!isFinite(base) || main <= 0 || option <= 0 || Math.abs(main - option) < 1) {
            return Math.round(base);
        }
        return Math.round(base * (option / main));
    }

    function normalizeFareLabel(value) {
        return String(value || '').trim().toLowerCase().replace(/[\s\-_.,/'"]/g, '');
    }

    function fareLabelsEquivalent(a, b) {
        var left = normalizeFareLabel(a);
        var right = normalizeFareLabel(b);
        if (!left || !right) {
            return true;
        }
        return left === right;
    }

    function normalizeCurrencyCode(code) {
        var normalized = String(code || '').trim().toUpperCase();
        return normalized || 'PKR';
    }

    function isPkrCurrency(code) {
        var normalized = normalizeCurrencyCode(code);
        return normalized === 'PKR' || normalized === 'RS';
    }

    function roundPkr(amount) {
        return Math.round(Number(amount) || 0);
    }

    function convertAmount(amount, factor) {
        if (!factor || factor <= 0) {
            return roundPkr(amount);
        }
        return roundPkr((Number(amount) || 0) * factor);
    }

    function derivePkrConversionFactor(offer, passengerPricing, pkrComponentTarget, optionRatio) {
        if (pkrComponentTarget <= 0) {
            return null;
        }
        var pricingCurrency = normalizeCurrencyCode(offer.pricing_currency || 'PKR');
        var supplierCurrency = normalizeCurrencyCode(offer.supplier_currency || pricingCurrency);
        if (isPkrCurrency(supplierCurrency) && isPkrCurrency(pricingCurrency)) {
            return 1;
        }

        var ratio = Number(optionRatio || 1);
        if (!isFinite(ratio) || ratio <= 0) {
            ratio = 1;
        }

        var supplierTotal = Number(offer.supplier_total || 0) * ratio;
        if (supplierTotal > 0 && !isPkrCurrency(supplierCurrency)) {
            return pkrComponentTarget / supplierTotal;
        }

        var componentTotal = (Number(offer.base_fare || 0) + Number(offer.taxes || 0)) * ratio;
        if (componentTotal > 0) {
            return pkrComponentTarget / componentTotal;
        }

        if (Array.isArray(passengerPricing) && passengerPricing.length) {
            var foreignSum = passengerPricing.reduce(function (sum, row) {
                return sum + Number((row && row.total_amount) || 0);
            }, 0);
            if (foreignSum > 0) {
                return pkrComponentTarget / foreignSum;
            }
        }

        var fxRate = Number(offer.fx_rate || 0);
        if (fxRate > 0) {
            return fxRate;
        }

        return null;
    }

    function convertPassengerPricingRows(rows, factor) {
        return (rows || []).map(function (row) {
            if (!row || typeof row !== 'object') {
                return row;
            }
            return Object.assign({}, row, {
                base_amount: convertAmount(row.base_amount, factor),
                tax_amount: convertAmount(row.tax_amount, factor),
                total_amount: convertAmount(row.total_amount, factor),
                currency: 'PKR',
            });
        });
    }

    function resolveSearchPassengerCounts(offer) {
        var fromOffer = offer && offer.passenger_counts && typeof offer.passenger_counts === 'object'
            ? offer.passenger_counts
            : {};
        var adults = fromOffer.adults != null ? Number(fromOffer.adults) : (fromOffer.adult != null ? Number(fromOffer.adult) : NaN);
        var children = fromOffer.children != null ? Number(fromOffer.children) : (fromOffer.child != null ? Number(fromOffer.child) : NaN);
        var infants = fromOffer.infants != null ? Number(fromOffer.infants) : (fromOffer.infant != null ? Number(fromOffer.infant) : NaN);
        var offerTotal = (isFinite(adults) ? Math.max(0, adults) : 0)
            + (isFinite(children) ? Math.max(0, children) : 0)
            + (isFinite(infants) ? Math.max(0, infants) : 0);
        if (offerTotal <= 0) {
            return {
                adults: Number(currentCriteria.adults || 1),
                children: Number(currentCriteria.children || 0),
                infants: Number(currentCriteria.infants || 0),
            };
        }

        return {
            adults: Math.max(0, isFinite(adults) ? adults : 0),
            children: Math.max(0, isFinite(children) ? children : 0),
            infants: Math.max(0, isFinite(infants) ? infants : 0),
        };
    }

    function buildFareSummaryPayload(offer, option) {
        option = option || null;
        var mainDisplayed = Number(offer.displayed_price || offer.final_customer_price || 0);
        var displayedPrice = option && option.displayed_price != null && Number(option.displayed_price) > 0
            ? Number(option.displayed_price)
            : mainDisplayed;
        var adminMarkup = Number(offer.markup || 0);
        var serviceFee = Number(offer.service_fee || 0);
        var baseFare = Number(offer.base_fare || 0);
        var taxes = Number(offer.taxes || 0);
        var passengerPricing = Array.isArray(offer.passenger_pricing) ? offer.passenger_pricing.slice() : null;
        var passengerPricingTrusted = !!offer.passenger_pricing_trusted;
        var passengerPricingAvailable = !!(offer.passenger_pricing_available && passengerPricing && passengerPricing.length);
        var optionRatio = 1;
        if (option && mainDisplayed > 0 && displayedPrice > 0 && Math.abs(mainDisplayed - displayedPrice) >= 1) {
            optionRatio = displayedPrice / mainDisplayed;
        }

        if (optionRatio !== 1) {
            adminMarkup = scaleAmountForFareOption(adminMarkup, mainDisplayed, displayedPrice);
            serviceFee = scaleAmountForFareOption(serviceFee, mainDisplayed, displayedPrice);
        }

        var pkrComponentTarget = displayedPrice - adminMarkup - serviceFee;
        var pricingCurrency = normalizeCurrencyCode(offer.pricing_currency || 'PKR');
        var supplierCurrency = normalizeCurrencyCode(offer.supplier_currency || pricingCurrency);
        var needsConversion = !isPkrCurrency(supplierCurrency) || !isPkrCurrency(pricingCurrency) || offer.conversion_status === 'converted';
        var conversionFactor = needsConversion || !passengerPricingTrusted
            ? derivePkrConversionFactor(offer, passengerPricing, pkrComponentTarget, optionRatio)
            : 1;

        if (conversionFactor && conversionFactor > 0 && conversionFactor !== 1) {
            baseFare = convertAmount(baseFare * optionRatio, conversionFactor);
            taxes = convertAmount(taxes * optionRatio, conversionFactor);
            if (passengerPricing) {
                if (optionRatio !== 1) {
                    passengerPricing = passengerPricing.map(function (row) {
                        if (!row || typeof row !== 'object') {
                            return row;
                        }
                        return Object.assign({}, row, {
                            base_amount: Number(row.base_amount || 0) * optionRatio,
                            tax_amount: Number(row.tax_amount || 0) * optionRatio,
                            total_amount: Number(row.total_amount || 0) * optionRatio,
                        });
                    });
                }
                passengerPricing = convertPassengerPricingRows(passengerPricing, conversionFactor);
                passengerPricingAvailable = passengerPricing.length > 0;
                passengerPricingTrusted = true;
            }
        } else if (optionRatio !== 1) {
            if (passengerPricingTrusted && passengerPricing) {
                passengerPricing = passengerPricing.map(function (row) {
                    if (!row || typeof row !== 'object') {
                        return row;
                    }
                    return Object.assign({}, row, {
                        base_amount: scaleAmountForFareOption(row.base_amount, mainDisplayed, displayedPrice),
                        tax_amount: scaleAmountForFareOption(row.tax_amount, mainDisplayed, displayedPrice),
                        total_amount: scaleAmountForFareOption(row.total_amount, mainDisplayed, displayedPrice),
                    });
                });
            } else {
                baseFare = scaleAmountForFareOption(baseFare, mainDisplayed, displayedPrice);
                taxes = scaleAmountForFareOption(taxes, mainDisplayed, displayedPrice);
                passengerPricingTrusted = false;
                passengerPricingAvailable = false;
                passengerPricing = null;
            }
        }

        var componentsTrusted = false;
        if (passengerPricingTrusted && passengerPricingAvailable && passengerPricing) {
            var passengerTotal = passengerPricing.reduce(function (sum, row) {
                return sum + Number((row && row.total_amount) || 0);
            }, 0);
            componentsTrusted = passengerTotal > 0
                && Math.abs(passengerTotal + adminMarkup + serviceFee - displayedPrice) <= 2;
        } else if (baseFare > 0 || taxes > 0) {
            componentsTrusted = Math.abs(baseFare + taxes + adminMarkup + serviceFee - displayedPrice) <= 2;
            if (!componentsTrusted) {
                passengerPricingTrusted = false;
            }
        }

        if (needsConversion && (!conversionFactor || conversionFactor <= 0)) {
            componentsTrusted = false;
            passengerPricingTrusted = false;
        }

        return {
            base_fare: baseFare,
            taxes: taxes,
            admin_markup: adminMarkup,
            service_fee: serviceFee,
            displayed_price: displayedPrice,
            final_customer_price: displayedPrice,
            final_total: displayedPrice,
            passenger_pricing: passengerPricing,
            passenger_pricing_available: passengerPricingAvailable,
            passenger_pricing_trusted: passengerPricingTrusted && componentsTrusted,
            components_trusted: componentsTrusted,
            passenger_counts: resolveSearchPassengerCounts(offer),
            search_passengers: {
                adults: Number(currentCriteria.adults || 1),
                children: Number(currentCriteria.children || 0),
                infants: Number(currentCriteria.infants || 0),
            },
            conversion_status: offer.conversion_status || '',
            pricing_currency: 'PKR',
            supplier_currency: offer.supplier_currency || '',
            supplier_total: Number(offer.supplier_total || 0),
            fx_rate: offer.fx_rate || null,
            admin_markup_only: true,
            route_label: String(offer.route || '').trim(),
            journeys_display: Array.isArray(offer.journeys_display) ? offer.journeys_display : [],
            airline_logo_url: offer.airline_logo_url || '',
            airline_name: offer.airline_name || offer.primary_display_carrier_name || '',
            airline_code: offer.airline_code || offer.primary_display_carrier || '',
            baggage_summary_display: option ? (option.baggage_summary || '') : (offer.baggage_summary_display || offer.baggage || ''),
            baggage_cabin_display: option ? (option.carry_on_summary || option.carry_on || '') : (offer.baggage_cabin_display || ''),
            baggage_checked_display: option ? (option.check_in_summary || option.checked_baggage || '') : (offer.baggage_checked_display || ''),
            baggage_lines: option && Array.isArray(option.baggage_lines) ? option.baggage_lines : (Array.isArray(offer.baggage_lines) ? offer.baggage_lines : []),
            refundable: option ? undefined : !!offer.refundable,
            refund_rule: option ? (option.refund_rule || option.refundable_display || '') : (offer.refund_rule || ''),
            change_rule: option ? (option.modification_rule || '') : (offer.change_rule || ''),
            cancellation_rule: option ? (option.cancellation_rule || '') : '',
            modification_rule: option ? (option.modification_rule || '') : '',
            no_show_rule: option ? (option.no_show_rule || '') : '',
            exchange_rule: option ? (option.exchange_rule || option.reissue_rule || '') : '',
            refundable_display: option ? (option.refundable_display || '') : '',
            fare_family_name: option ? (option.name || '') : '',
            brand_name: option ? (option.name || '') : '',
            cabin: option ? (option.cabin || offer.cabin || '') : (offer.cabin || ''),
        };
    }

    function payloadAttr(obj) {
        return esc(JSON.stringify(obj));
    }

    function cardDisplayPrice(offer) {
        var selectedKey = selectedFareOptionByOfferId[offer.offer_id] || '';
        if (selectedKey && offer.fare_family_options_display) {
            for (var i = 0; i < offer.fare_family_options_display.length; i++) {
                var opt = offer.fare_family_options_display[i];
                if (opt && opt.option_key === selectedKey && opt.displayed_price != null && Number(opt.displayed_price) > 0) {
                    return Number(opt.displayed_price);
                }
            }
        }
        if (offer.has_confirmed_pkr_quote && offer.displayed_price != null && Number(offer.displayed_price) > 0) {
            return Number(offer.displayed_price);
        }
        return null;
    }

    function buildLegBlockHtml(time, date, code, city, align, dayOffsetHtml) {
        var cityLine = city
            ? '<span class="ota-result-leg__city">' + esc(code) + ' · ' + esc(city) + '</span>'
            : '<span class="ota-result-leg__city">' + esc(code) + '</span>';
        return '<div class="ota-result-leg ota-result-leg--' + align + '">' +
            '<div class="ota-time-lg">' + esc(time) + '</div>' +
            '<div class="ota-result-leg__date">' + esc(date) + (dayOffsetHtml || '') + '</div>' +
            cityLine +
            '</div>';
    }

    function buildCardRouteMidHtml(durationLabel, stopsLabelHtml) {
        return '<div class="ota-result-col-mid">' +
            '<div class="ota-result-route-line">' +
            '<span class="ota-result-route-line__dur">' + durationLabel + '</span>' +
            '<span class="ota-result-route-line__track" aria-hidden="true"><span class="ota-result-route-line__dot"></span></span>' +
            '<span class="ota-result-route-line__stops">' + stopsLabelHtml + '</span>' +
            '</div></div>';
    }

    function journeyCarrierInfo(journey, offer) {
        return window.OtaReturnSplitCards
            ? OtaReturnSplitCards.journeyCarrierInfo(journey, offer)
            : { code: '', name: '', flightNumbers: '', mixedWithin: false, carrierChain: '', logoUrl: '' };
    }

    function buildCompactRoundTripSegmentHtml(journey, offer, cabinLabel, baggageLabel) {
        return window.OtaReturnSplitCards
            ? OtaReturnSplitCards.buildCompactRoundTripSegmentHtml(journey, offer, cabinLabel, baggageLabel)
            : '';
    }

    function outboundSplitCardHtml(option) {
        if (!window.OtaReturnSplitCards || !splitBrandedState) {
            return '';
        }
        var offer = OtaReturnSplitCards.normalizeOptionForBrandedFares(option, 'outbound');
        OtaBrandedFares.registerOffer(splitBrandedState, offer);
        var selectedKey = splitBrandedState.selectedFareOptionByOfferId[offer.offer_id] || '';
        var brandedHtml = isJetPkResults && window.JetPkResultCards && typeof JetPkResultCards.buildBrandedFaresPanelHtml === 'function'
            ? buildBrandedFaresPanelHtml(offer)
            : OtaBrandedFares.buildPanelHtml(offer, splitBrandedState);
        if (isJetPkResults && window.JetPkResultCards && typeof JetPkResultCards.buildOutboundSplitCard === 'function') {
            return JetPkResultCards.buildOutboundSplitCard(option, {
                selectOutbound: @json(__('Select outbound flight')),
                fromNote: @json(__('total return fare')),
                legLabel: @json(__('Outbound')),
            }, brandedHtml, selectedKey, splitBrandedState, {
                esc: esc,
                formatCardButtonRs: formatCardButtonRs,
                currentCriteria: currentCriteria,
                buildAirlineLogoHtml: buildAirlineLogoHtml,
                buildStandardCardFaceCarrierHtml: buildStandardCardFaceCarrierHtml,
            });
        }
        return OtaReturnSplitCards.buildOutboundSplitCardHtml(option, {
            selectOutbound: @json(__('Select outbound flight')),
            fromNote: @json(__('total return fare')),
        }, brandedHtml, selectedKey, splitBrandedState);
    }

    function getShortAirlineName(name, code) {
        var airlineCode = String(code || '').trim().toUpperCase();
        var airlineName = String(name || '').trim();
        var upperName = airlineName.toUpperCase();
        if (airlineCode === 'PK' || upperName.indexOf('PAKISTAN INTERNATIONAL') !== -1) {
            return 'PIA';
        }
        if (airlineCode === 'EK' || upperName.indexOf('EMIRATES') !== -1) {
            return 'Emirates';
        }
        if (airlineName) {
            return airlineName;
        }
        if (airlineCode) {
            return airlineCode;
        }
        return '';
    }

    function offerCardAirlineNamesLine(offer) {
        var segs = offer.segments || [];
        var names = [];
        var seen = {};
        segs.forEach(function (seg) {
            var code = String(seg.airline_code || '').trim().toUpperCase();
            var key = code || String(seg.airline_name || '').trim();
            if (!key || seen[key]) {
                return;
            }
            seen[key] = true;
            var shortName = getShortAirlineName(seg.airline_name, seg.airline_code);
            if (shortName) {
                names.push(shortName);
            }
        });
        if (names.length) {
            return names.join(' + ');
        }
        var codes = Array.isArray(offer.all_airline_codes) ? offer.all_airline_codes : [];
        return uniqueNonEmptyOrdered(codes.map(function (code) {
            return getShortAirlineName('', code);
        })).join(' + ');
    }

    function buildStandardCardFaceCarrierHtml(offer) {
        var airlineDisplayName = (offer.airline_name || offer.primary_display_carrier_name || '').trim();
        var airlineCodeLabel = (offer.airline_code || offer.primary_display_carrier || '').trim();
        var carrierChain = (offer.marketing_carrier_chain_display || '').trim();
        if (offer.mixed_carrier && carrierChain.indexOf('+') !== -1) {
            var namesLine = offerCardAirlineNamesLine(offer);
            if (!namesLine) {
                namesLine = getShortAirlineName(airlineDisplayName, airlineCodeLabel) || airlineDisplayName || airlineCodeLabel;
            }
            return '<div class="ota-result-carrier-face">' +
                '<div class="ota-result-carrier-face__names">' + esc(namesLine) + '</div>' +
                '<div class="ota-result-carrier-face__chain">' + esc(carrierChain) + '</div>' +
                '</div>';
        }
        var singleName = getShortAirlineName(airlineDisplayName, airlineCodeLabel) || airlineDisplayName || airlineCodeLabel || '';
        return '<div class="ota-airline-name">' + esc(singleName) + '</div>';
    }

    function uniqueNonEmptyOrdered(values) {
        var seen = {};
        var out = [];
        (values || []).forEach(function (value) {
            var key = String(value || '').trim();
            if (!key || seen[key]) {
                return;
            }
            seen[key] = true;
            out.push(key);
        });
        return out;
    }

    function readBrandedFareDataAttr(el, names) {
        if (!el) {
            return '';
        }
        for (var i = 0; i < names.length; i++) {
            var val = el.getAttribute(names[i]);
            if (val !== null && String(val).trim() !== '') {
                return String(val).trim();
            }
        }
        return '';
    }

    function resolveFlightCard(el) {
        if (!el || !el.closest) {
            return null;
        }
        return el.closest('[data-flight-card]');
    }

    function normalizeBrandedFareOptionKey(offer, fareOptionKey) {
        var key = String(fareOptionKey || '').trim();
        if (!key || !offer) {
            return key;
        }
        var opts = offer.fare_family_options_display || offer.branded_fares_display_options || [];
        for (var i = 0; i < opts.length; i++) {
            var opt = opts[i];
            if (!opt) {
                continue;
            }
            var aliases = [
                opt.option_key,
                opt.fare_option_key,
                opt.fareOptionKey,
                opt.id,
                opt.selected_offer_reference,
                opt.selectedOfferReference,
            ];
            for (var j = 0; j < aliases.length; j++) {
                if (aliases[j] !== null && aliases[j] !== undefined && String(aliases[j]).trim() === key) {
                    return String(opt.option_key || key).trim();
                }
            }
        }
        return key;
    }

    function buildBrandedFareSelectionPayload(flightCard, fareOptionKey, triggerEl) {
        var card = resolveFlightCard(flightCard || triggerEl);
        var trigger = triggerEl || null;
        var offerId = '';
        var fareKey = String(fareOptionKey || '').trim();
        var searchIdVal = String(searchId || '').trim();

        if (trigger) {
            offerId = readBrandedFareDataAttr(trigger, ['data-offer-id', 'data-selected-offer-reference']);
            if (!fareKey) {
                fareKey = readBrandedFareDataAttr(trigger, ['data-fare-option-key', 'data-option-key']);
            }
            if (!searchIdVal) {
                searchIdVal = readBrandedFareDataAttr(trigger, ['data-search-id']);
            }
        }
        if (card) {
            if (!offerId) {
                offerId = readBrandedFareDataAttr(card, ['data-offer-id', 'data-selected-offer-reference']);
            }
            if (!fareKey) {
                fareKey = readBrandedFareDataAttr(card, ['data-fare-option-key', 'data-option-key']);
            }
        }
        var offer = offerId ? (offersById[offerId] || null) : null;
        if (offer && !offerId) {
            offerId = String(offer.offer_id || offer.id || '').trim();
        }
        if (offer) {
            fareKey = normalizeBrandedFareOptionKey(offer, fareKey);
        }
        var selectUrl = offer && offer.select_url ? String(offer.select_url) : readBrandedFareDataAttr(trigger, ['data-select-url']);
        var valid = !!(offerId && fareKey && selectUrl);
        return {
            offer_id: offerId,
            selected_offer_reference: offerId,
            flight_id: offerId,
            fare_option_key: fareKey,
            search_id: searchIdVal,
            select_url: selectUrl,
            valid: valid,
            flight_card: card,
            offer: offer,
        };
    }

    function showIncompleteBrandedFarePayloadError(payload, context) {
        if (window.OTA_BOOKING_DEBUG) {
            console.warn('[OTA_BRANDED_FARE_CHECKOUT] Incomplete selection payload', context || '', payload || {});
        }
        showOneWayCheckoutError('Please select a fare option before continuing.');
    }

    window.otaResolveFlightCard = resolveFlightCard;
    window.otaBuildBrandedFareSelectionPayload = buildBrandedFareSelectionPayload;
    window.otaWarnIncompleteBrandedFarePayload = showIncompleteBrandedFarePayloadError;

    function ensureClientPrefixedCheckoutUrl(selectUrl) {
        var root = document.querySelector('[data-results-root]');
        var clientPassengersBase = root ? (root.getAttribute('data-booking-passengers-url') || '') : '';
        var path = window.location.pathname || '';
        var isJetPk = path.indexOf('/jetpk') === 0 || clientPassengersBase.indexOf('/jetpk/') === 0;
        if (!isJetPk) {
            return selectUrl;
        }
        try {
            var url = new URL(selectUrl, window.location.origin);
            if (url.pathname.indexOf('/jetpk/') !== 0) {
                if (clientPassengersBase) {
                    var base = new URL(clientPassengersBase, window.location.origin);
                    url.pathname = base.pathname;
                } else if (url.pathname.indexOf('/booking/') === 0) {
                    url.pathname = '/jetpk' + url.pathname;
                }
            }
            return url.pathname + url.search + url.hash;
        } catch (err) {
            if (selectUrl.indexOf('/jetpk/') !== 0 && selectUrl.indexOf('/booking/') === 0) {
                return '/jetpk' + selectUrl;
            }
            return selectUrl;
        }
    }

    function navigateToCheckoutWithFareKey(selectUrl, offerId, fareOptionKey, searchIdOverride) {
        var payload = buildBrandedFareSelectionPayload(null, fareOptionKey, null);
        if (offerId) {
            payload.offer_id = String(offerId).trim();
            payload.selected_offer_reference = payload.offer_id;
            payload.flight_id = payload.offer_id;
        }
        if (selectUrl) {
            payload.select_url = ensureClientPrefixedCheckoutUrl(String(selectUrl));
        }
        if (searchIdOverride) {
            payload.search_id = String(searchIdOverride).trim();
        }
        payload.valid = !!(payload.offer_id && payload.fare_option_key && payload.select_url);
        if (!payload.valid) {
            showIncompleteBrandedFarePayloadError(payload, 'navigateToCheckoutWithFareKey');
            return;
        }
        markResultsCheckoutNavigation();
        try {
            var url = new URL(payload.select_url, window.location.origin);
            url.searchParams.set('offer_id', payload.offer_id);
            url.searchParams.set('flight_id', payload.flight_id);
            url.searchParams.set('fare_option_key', payload.fare_option_key);
            if (payload.search_id) {
                url.searchParams.set('search_id', payload.search_id);
            }
            window.location.href = url.toString();
        } catch (err) {
            var sep = payload.select_url.indexOf('?') >= 0 ? '&' : '?';
            var href = payload.select_url + sep +
                'offer_id=' + encodeURIComponent(payload.offer_id) +
                '&flight_id=' + encodeURIComponent(payload.flight_id) +
                '&fare_option_key=' + encodeURIComponent(payload.fare_option_key);
            if (payload.search_id) {
                href += '&search_id=' + encodeURIComponent(payload.search_id);
            }
            window.location.href = href;
        }
    }

    function setOneWayCheckoutLoading(triggerEl, isLoading) {
        if (!triggerEl) return;
        if (isLoading) {
            if (!triggerEl.getAttribute('data-checkout-prev-label')) {
                triggerEl.setAttribute('data-checkout-prev-label', (triggerEl.textContent || '').trim());
            }
            triggerEl.setAttribute('data-checkout-loading', '1');
            triggerEl.setAttribute('aria-busy', 'true');
            if (triggerEl.tagName === 'BUTTON' || triggerEl.tagName === 'A') {
                triggerEl.disabled = true;
            }
            if (triggerEl.hasAttribute('data-fare-option-card') || triggerEl.getAttribute('data-fare-summary-select') === '1') {
                triggerEl.textContent = 'Continuing...';
            }
            return;
        }
        triggerEl.removeAttribute('data-checkout-loading');
        triggerEl.removeAttribute('aria-busy');
        if (triggerEl.tagName === 'BUTTON' || triggerEl.tagName === 'A') {
            triggerEl.disabled = false;
        }
        var prev = triggerEl.getAttribute('data-checkout-prev-label');
        if (prev) {
            triggerEl.textContent = prev;
            triggerEl.removeAttribute('data-checkout-prev-label');
        }
    }

    function showOneWayCheckoutError(message) {
        if (window.OTA_BOOKING_DEBUG) {
            console.debug('[OTA_ONEWAY_CHECKOUT]', message);
        }
        window.alert(message || 'Unable to continue to checkout. Please try again.');
    }

    function getSelectedFareOptionKey(offerId) {
        return offerId ? (selectedFareOptionByOfferId[offerId] || '') : '';
    }

    function isIatiProviderOffer(offer) {
        if (!offer) return false;
        var provider = String(offer.supplier_provider || offer.provider || '').toLowerCase();
        return provider === 'iati';
    }

    function formatRevalidationPrice(amount) {
        if (amount === null || amount === undefined || !isFinite(Number(amount))) {
            return '—';
        }
        return 'PKR ' + Math.round(Number(amount)).toLocaleString('en-US');
    }

    function hideIatiPriceChangePrompt() {
        if (!iatiPriceChangePrompt) return;
        iatiPriceChangePrompt.style.display = 'none';
        iatiPriceChangePrompt.hidden = true;
        iatiPriceChangeOnContinue = null;
        iatiPriceChangeOnCancel = null;
    }

    function showIatiPriceChangePrompt(oldTotal, newTotal, onContinue, onCancel) {
        if (!iatiPriceChangePrompt) {
            if (typeof onContinue === 'function') onContinue();
            return;
        }
        if (iatiPriceChangeOldEl) iatiPriceChangeOldEl.textContent = formatRevalidationPrice(oldTotal);
        if (iatiPriceChangeNewEl) iatiPriceChangeNewEl.textContent = formatRevalidationPrice(newTotal);
        iatiPriceChangeOnContinue = onContinue;
        iatiPriceChangeOnCancel = onCancel;
        hideFreshnessError();
        iatiPriceChangePrompt.hidden = false;
        iatiPriceChangePrompt.style.display = '';
    }

    function setIatiSelectLoading(triggerEl, isLoading) {
        if (!triggerEl) return;
        if (isLoading) {
            if (!triggerEl.getAttribute('data-iati-prev-label')) {
                var priceNode = triggerEl.querySelector('[data-card-price]');
                triggerEl.setAttribute('data-iati-prev-label', ((priceNode ? priceNode.textContent : triggerEl.textContent) || '').trim());
            }
            triggerEl.setAttribute('data-iati-revalidation-loading', '1');
            triggerEl.setAttribute('aria-busy', 'true');
            if (triggerEl.tagName === 'BUTTON' || triggerEl.tagName === 'A') {
                triggerEl.disabled = true;
            }
            var loadingNode = triggerEl.querySelector('[data-card-price]');
            if (loadingNode) {
                loadingNode.textContent = 'Checking...';
            } else if (!triggerEl.hasAttribute('data-fare-option-card')) {
                triggerEl.textContent = 'Checking...';
            }
            return;
        }
        triggerEl.removeAttribute('data-iati-revalidation-loading');
        triggerEl.removeAttribute('aria-busy');
        if (triggerEl.tagName === 'BUTTON' || triggerEl.tagName === 'A') {
            triggerEl.disabled = false;
        }
        var prevLabel = triggerEl.getAttribute('data-iati-prev-label');
        if (prevLabel) {
            var restoreNode = triggerEl.querySelector('[data-card-price]');
            if (restoreNode) {
                restoreNode.textContent = prevLabel;
            } else {
                triggerEl.textContent = prevLabel;
            }
            triggerEl.removeAttribute('data-iati-prev-label');
        }
    }

    function navigateToPassengersAfterRevalidation(offerId, json, fareOptionKey, fallbackSelectUrl) {
        var passengersUrl = (json && json.passengers_url) ? json.passengers_url : fallbackSelectUrl;
        if (!passengersUrl) {
            showFreshnessError('Fare could not be confirmed. Please search again.');
            return;
        }
        if (fareOptionKey) {
            navigateToCheckoutWithFareKey(passengersUrl, offerId, fareOptionKey);
            return;
        }
        window.location.href = passengersUrl;
    }

    function iatiRevalidationCustomerMessage(result) {
        var json = result && result.json ? result.json : {};
        var reval = json.revalidation || {};
        var status = String(reval.revalidation_status || json.status || '').toLowerCase();
        if (status === 'expired') {
            return 'This fare is no longer available. Please search again or choose another fare.';
        }
        var msg = String(reval.safe_customer_message || json.message || '').trim();
        return msg || 'Fare could not be confirmed. Please search again.';
    }

    function finishIatiSelectRevalidation(triggerEl) {
        iatiSelectRevalidationInFlight = false;
        oneWayCheckoutInFlight = false;
        setIatiSelectLoading(triggerEl, false);
    }

    function beginIatiSelectRevalidation(offerId, fareOptionKey, triggerEl, fallbackSelectUrl) {
        if (!offerId || !String(offerId).trim()) {
            showMissingOfferSelectionError();
            return;
        }
        if (!revalidateOfferUrl || iatiSelectRevalidationInFlight) return;
        var offer = offerId ? offersById[offerId] : null;
        iatiSelectRevalidationInFlight = true;
        hideFreshnessError();
        hideIatiPriceChangePrompt();
        setIatiSelectLoading(triggerEl, true);

        var body = new URLSearchParams();
        body.set('search_id', searchId);
        body.set('offer_id', offerId);
        body.set('provider', 'iati');
        if (fareOptionKey && offer && window.OtaBrandedFares && typeof OtaBrandedFares.isSyntheticDefaultFareOption === 'function' && OtaBrandedFares.isSyntheticDefaultFareOption(offer, fareOptionKey)) {
            fareOptionKey = '';
        }
        if (fareOptionKey) {
            body.set('selected_fare_option_id', fareOptionKey);
        }
        if (csrfToken && csrfToken.getAttribute('content')) {
            body.set('_token', csrfToken.getAttribute('content'));
        }

        fetch(revalidateOfferUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: body.toString()
        }).then(function (res) {
            return res.json().then(function (json) {
                return { ok: res.ok, status: res.status, json: json };
            }).catch(function () {
                return { ok: false, status: 0, json: {} };
            });
        }).then(function (result) {
            if (result.ok && result.json && result.json.success) {
                var reval = result.json.revalidation || {};
                var revalStatus = String(reval.revalidation_status || '').toLowerCase();
                if (revalStatus === 'valid') {
                    navigateToPassengersAfterRevalidation(offerId, result.json, fareOptionKey, fallbackSelectUrl);
                    return;
                }
                if (revalStatus === 'changed') {
                    finishIatiSelectRevalidation(triggerEl);
                    showIatiPriceChangePrompt(reval.original_total, reval.confirmed_total, function () {
                        navigateToPassengersAfterRevalidation(offerId, result.json, fareOptionKey, fallbackSelectUrl);
                    }, function () {});
                    return;
                }
            }
            showFreshnessError(iatiRevalidationCustomerMessage(result));
            finishIatiSelectRevalidation(triggerEl);
        }).catch(function () {
            showFreshnessError('Fare could not be confirmed. Please search again.');
            finishIatiSelectRevalidation(triggerEl);
        });
    }

    function offerHasSelectableBrandedFares(offerId, card) {
        var cardRef = card;
        if (!cardRef && offerId && list) {
            cardRef = list.querySelector('[data-flight-card][data-offer-id="' + offerId + '"]');
        }
        if (cardRef) {
            var panel = cardRef.querySelector('[data-branded-fares-panel]');
            if (panel) {
                var selectable = panel.querySelectorAll('[data-fare-option-card][data-fare-option-key]');
                if (selectable.length > 0) {
                    return true;
                }
            }
        }
        var offer = offerId ? offersById[offerId] : null;
        return offerRequiresFareFamilySelection(offer);
    }

    function openBrandedFarePanelForOffer(offerId, card) {
        if (!card && offerId && list) {
            card = list.querySelector('[data-flight-card][data-offer-id="' + offerId + '"]');
        }
        if (!card || !offerId) {
            return;
        }
        collapseOtherBrandedFares(offerId);
        setBrandedFaresExpanded(card, offerId, true);
        var panel = card.querySelector('[data-branded-fares-panel]');
        if (panel) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            var firstSelect = panel.querySelector('[data-fare-option-card][data-fare-option-key]');
            if (firstSelect && typeof firstSelect.focus === 'function') {
                try {
                    firstSelect.focus({ preventScroll: true });
                } catch (focusErr) {
                    firstSelect.focus();
                }
            }
        }
        var hint = card.querySelector('.ota-fare-family-selection-hint');
        if (hint) {
            hint.hidden = false;
            hint.textContent = 'Select a fare option to continue';
            hint.setAttribute('aria-live', 'polite');
        }
    }

    function handleMainBookNowClick(e, link) {
        if (!link || returnSplitFlow) {
            return;
        }
        var card = link.closest('[data-flight-card]');
        var oid = card ? String(card.getAttribute('data-offer-id') || '').trim() : '';
        if (!oid) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            showMissingOfferSelectionError();
            return;
        }
        var offer = offersById[oid] || null;
        if (offer && offerNeedsFareChoiceBeforeCheckout(offer)) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            openBrandedFarePanelForOffer(oid, card);
            return;
        }
        if (offer && isIatiProviderOffer(offer)) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            if (!offer.select_url) {
                showOneWayCheckoutError('Unable to continue to checkout. Please try again.');
                return;
            }
            var iatiFareKey = getSelectedFareOptionKey(oid) || '';
            if (window.OtaBrandedFares && typeof OtaBrandedFares.isSyntheticDefaultFareOption === 'function' && OtaBrandedFares.isSyntheticDefaultFareOption(offer, iatiFareKey)) {
                iatiFareKey = '';
            }
            beginIatiSelectRevalidation(oid, iatiFareKey, link, offer.select_url);
            return;
        }
        if (!offerHasSelectableBrandedFares(oid, card)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var selectedKey = getSelectedFareOptionKey(oid);
        if (!selectedKey) {
            openBrandedFarePanelForOffer(oid, card);
            return;
        }
        if (!offer || !offer.select_url) {
            showOneWayCheckoutError('Unable to continue to checkout. Please try again.');
            return;
        }
        if (!proceedOneWayToCheckout(card, selectedKey, link)) {
            showOneWayCheckoutError('Unable to continue to checkout. Please try again.');
        }
    }

    function proceedOneWayToCheckout(flightCard, fareOptionKey, triggerEl) {
        if (oneWayCheckoutInFlight || returnSplitFlow) {
            return false;
        }
        var payload = buildBrandedFareSelectionPayload(flightCard, fareOptionKey, triggerEl);
        if (!payload.valid || !payload.flight_card) {
            showIncompleteBrandedFarePayloadError(payload, 'proceedOneWayToCheckout');
            return false;
        }
        flightCard = payload.flight_card;
        fareOptionKey = payload.fare_option_key;
        var oid = payload.offer_id;
        var offer = payload.offer || offersById[oid] || null;
        if (!offer || !offer.select_url || !offerHasSelectableBrandedFares(oid, flightCard)) {
            return false;
        }
        if (isIatiProviderOffer(offer)) {
            try {
                clearOtherOfferFareSelections(oid);
                selectedFareOptionByOfferId[oid] = fareOptionKey;
                refreshBrandedFareSelectionUi(oid);
                oneWayCheckoutInFlight = true;
                setOneWayCheckoutLoading(triggerEl, true);
                var iatiRevalKey = fareOptionKey;
                if (window.OtaBrandedFares && typeof OtaBrandedFares.isSyntheticDefaultFareOption === 'function' && OtaBrandedFares.isSyntheticDefaultFareOption(offer, iatiRevalKey)) {
                    iatiRevalKey = '';
                }
                beginIatiSelectRevalidation(oid, iatiRevalKey, triggerEl, offer.select_url);
                return true;
            } catch (err) {
                oneWayCheckoutInFlight = false;
                setOneWayCheckoutLoading(triggerEl, false);
                if (window.OTA_BOOKING_DEBUG) {
                    console.debug('[OTA_ONEWAY_CHECKOUT]', err);
                }
                showOneWayCheckoutError('Unable to continue to checkout. Please try again.');
                return false;
            }
        }
        try {
            clearOtherOfferFareSelections(oid);
            selectedFareOptionByOfferId[oid] = fareOptionKey;
            refreshBrandedFareSelectionUi(oid);
            oneWayCheckoutInFlight = true;
            setOneWayCheckoutLoading(triggerEl, true);
            if (window.OtaBrandedFares && typeof OtaBrandedFares.isSyntheticDefaultFareOption === 'function' && OtaBrandedFares.isSyntheticDefaultFareOption(offer, fareOptionKey)) {
                navigateToCheckoutWithFareKey(offer.select_url, oid, fareOptionKey, payload.search_id);
                return true;
            }
            navigateToCheckoutWithFareKey(offer.select_url, oid, fareOptionKey, payload.search_id);
            return true;
        } catch (err) {
            oneWayCheckoutInFlight = false;
            setOneWayCheckoutLoading(triggerEl, false);
            if (window.OTA_BOOKING_DEBUG) {
                console.debug('[OTA_ONEWAY_CHECKOUT]', err);
            }
            showOneWayCheckoutError('Unable to continue to checkout. Please try again.');
            return false;
        }
    }

    if (!returnSplitFlow) {
        window.otaProceedBrandedFareCheckout = proceedOneWayToCheckout;
    }

    function returnSplitDebug() {
        return window.OTA_RETURN_DEBUG === true;
    }

    function showReturnSplitError(message) {
        if (!returnSplitErrorEl) return;
        returnSplitErrorEl.textContent = message || 'Unable to load return flight options. Please try another flight or refresh.';
        returnSplitErrorEl.style.display = '';
    }

    function hideReturnSplitError() {
        if (!returnSplitErrorEl) return;
        returnSplitErrorEl.style.display = 'none';
        returnSplitErrorEl.textContent = '';
    }

    function setSplitActionLoading(el, isLoading) {
        if (!el) return;
        if (isLoading) {
            el.setAttribute('data-checkout-loading', '1');
            if (el.tagName === 'BUTTON' || el.tagName === 'A') {
                if (!el.getAttribute('data-split-loading-label')) {
                    el.setAttribute('data-split-loading-label', (el.textContent || '').trim());
                }
                var priceEl = el.querySelector('[data-card-price]');
                if (priceEl) {
                    if (!priceEl.getAttribute('data-split-loading-label')) {
                        priceEl.setAttribute('data-split-loading-label', priceEl.textContent);
                    }
                    priceEl.textContent = 'Continuing...';
                } else {
                    el.textContent = 'Continuing...';
                }
                if (el.tagName === 'BUTTON') el.disabled = true;
            }
            return;
        }
        el.removeAttribute('data-checkout-loading');
        if (el.tagName === 'BUTTON') el.disabled = false;
        var priceRestore = el.querySelector('[data-card-price]');
        if (priceRestore && priceRestore.getAttribute('data-split-loading-label')) {
            priceRestore.textContent = priceRestore.getAttribute('data-split-loading-label');
            priceRestore.removeAttribute('data-split-loading-label');
        } else if (el.getAttribute('data-split-loading-label')) {
            el.textContent = el.getAttribute('data-split-loading-label');
            el.removeAttribute('data-split-loading-label');
        }
    }

    function updateSplitStepsUi() {
        if (!returnSplitStepsEl) return;
        var steps = returnSplitStepsEl.querySelectorAll('.ota-return-split-steps__step');
        if (steps.length < 2) return;
        steps[0].classList.toggle('is-active', !returnStepActive);
        steps[0].classList.toggle('is-done', returnStepActive);
        steps[1].classList.toggle('is-active', returnStepActive);
    }

    function renderSelectedOutboundSummary(journey, meta) {
        if (!outboundSummaryBody || !window.OtaReturnSplitCards || !journey) return;
        var fareKey = selectedOutbound && selectedOutbound.fare_option_key ? selectedOutbound.fare_option_key : '';
        outboundSummaryBody.innerHTML = OtaReturnSplitCards.buildOutboundSummaryHtml(journey, meta || {}, fareKey);
    }

    function resetToOutboundStep() {
        returnStepActive = false;
        selectedOutbound = null;
        selectedReturnAmount = null;
        returnOptionsPage = 1;
        returnOptionsHasMore = true;
        splitCheckoutInFlight = false;
        updateSplitStepsUi();
        if (outboundSummaryWrap) outboundSummaryWrap.hidden = true;
        hideReturnSplitError();
        page = 1;
        hasMore = true;
        if (list) list.innerHTML = '';
        if (splitBrandedState) {
            splitBrandedState.offersById = {};
            splitBrandedState.selectedFareOptionByOfferId = {};
            splitBrandedState.expandedBrandedFaresByOfferId = {};
        }
        if (summary) summary.textContent = 'Showing fares...';
        fetchPage(true);
    }

    function handleOutboundChosen(link, offer, fareOptionKey) {
        hideReturnSplitError();
        var outboundKey = (link && link.getAttribute('data-outbound-key')) || (offer && offer.outbound_key) || '';
        if (!outboundKey) {
            showReturnSplitError('Unable to load return flight options. Please try another flight or refresh.');
            return;
        }
        var price = null;
        if (offer && window.OtaBrandedFares) {
            price = OtaBrandedFares.cardDisplayPrice(offer, splitBrandedState, fareOptionKey);
        } else if (offer) {
            price = offer.from_total_amount;
        }
        selectedOutbound = {
            outboundKey: outboundKey,
            outbound_key: outboundKey,
            fare_option_key: fareOptionKey || '',
            fareOptionKey: fareOptionKey || '',
            price: price,
            offer: offer,
            combo_id: offer && (offer.sample_combo_id || offer.combo_id || ''),
        };
        if (window.OtaReturnSplitCards) {
            OtaReturnSplitCards.saveOutboundSelection(searchId, selectedOutbound);
        }
        if (returnSplitDebug()) {
            console.debug('[OTA_RETURN_SPLIT] outbound selected', selectedOutbound);
        }
        enterReturnStep();
    }

    function enterReturnStep() {
        returnStepActive = true;
        returnOptionsPage = 1;
        returnOptionsHasMore = true;
        updateSplitStepsUi();
        if (outboundSummaryWrap) outboundSummaryWrap.hidden = false;
        if (list) list.innerHTML = '';
        if (splitBrandedState) {
            splitBrandedState.offersById = {};
            splitBrandedState.selectedFareOptionByOfferId = {};
            splitBrandedState.expandedBrandedFaresByOfferId = {};
        }
        fetchReturnOptions(true);
    }

    function returnSplitCardHtml(option) {
        if (!window.OtaReturnSplitCards || !splitBrandedState || !selectedOutbound) return '';
        var offer = OtaReturnSplitCards.normalizeOptionForBrandedFares(option, 'return');
        OtaBrandedFares.registerOffer(splitBrandedState, offer);
        var selectedKey = splitBrandedState.selectedFareOptionByOfferId[offer.offer_id] || '';
        var brandedHtml = isJetPkResults && window.JetPkResultCards && typeof JetPkResultCards.buildBrandedFaresPanelHtml === 'function'
            ? buildBrandedFaresPanelHtml(offer)
            : OtaBrandedFares.buildPanelHtml(offer, splitBrandedState);
        if (isJetPkResults && window.JetPkResultCards && typeof JetPkResultCards.buildReturnSplitCard === 'function') {
            return JetPkResultCards.buildReturnSplitCard(option, {
                selectUrl: selectReturnComboUrl,
                csrf: csrfToken ? csrfToken.getAttribute('content') : '',
                searchId: searchId,
                outboundKey: selectedOutbound.outboundKey,
            }, {
                selectReturn: @json(__('Select return')),
                totalReturnFare: @json(__('Total return fare')),
                returnLabel: @json(__('Return')),
                legLabel: @json(__('Return')),
            }, brandedHtml, selectedKey, splitBrandedState, {
                esc: esc,
                formatCardButtonRs: formatCardButtonRs,
                currentCriteria: currentCriteria,
                buildAirlineLogoHtml: buildAirlineLogoHtml,
                buildStandardCardFaceCarrierHtml: buildStandardCardFaceCarrierHtml,
            });
        }
        return OtaReturnSplitCards.buildReturnSplitCardHtml(option, {
            selectUrl: selectReturnComboUrl,
            csrf: csrfToken ? csrfToken.getAttribute('content') : '',
            searchId: searchId,
            outboundKey: selectedOutbound.outboundKey,
        }, {
            selectReturn: @json(__('Select return')),
            totalReturnFare: @json(__('Total return fare')),
            returnLabel: @json(__('Return')),
            legLabel: @json(__('Return')),
        }, brandedHtml, selectedKey, splitBrandedState);
    }

    function syncSelectedReturnFromState() {
        selectedReturnAmount = null;
        if (!splitBrandedState || !list) return;
        Object.keys(splitBrandedState.selectedFareOptionByOfferId).forEach(function (oid) {
            var key = splitBrandedState.selectedFareOptionByOfferId[oid];
            if (!key) return;
            var offer = splitBrandedState.offersById[oid];
            if (offer) {
                selectedReturnAmount = OtaBrandedFares.cardDisplayPrice(offer, splitBrandedState);
            }
        });
    }

    function proceedReturnCheckout(card, fareOptionKey, triggerEl) {
        if (splitCheckoutInFlight || !selectedOutbound || !card) return false;
        var form = card.querySelector('.ota-return-split-card__form');
        if (!form) return false;
        var outboundFareKey = selectedOutbound.fare_option_key || selectedOutbound.fareOptionKey || '';
        if (window.OtaReturnSplitCards && OtaReturnSplitCards.prepareReturnSplitCheckoutForm) {
            OtaReturnSplitCards.prepareReturnSplitCheckoutForm(form, fareOptionKey || '', outboundFareKey);
        } else {
            var fareInput = form.querySelector('[data-split-fare-option-key]');
            if (fareInput) fareInput.value = fareOptionKey || '';
            var outboundInput = form.querySelector('[data-split-outbound-fare-option-key]');
            if (outboundInput) outboundInput.value = outboundFareKey;
        }
        splitCheckoutInFlight = true;
        setSplitActionLoading(triggerEl || form.querySelector('button[type="submit"]'), true);
        if (returnSplitDebug()) {
            console.debug('[OTA_RETURN_SPLIT] return checkout', {
                combo_id: form.querySelector('[name="combo_id"]') ? form.querySelector('[name="combo_id"]').value : '',
                outbound_key: selectedOutbound.outboundKey,
                fare_option_key: fareOptionKey || '',
            });
        }
        form.submit();
        return true;
    }

    function proceedSplitBrandedFareCheckout(card, fareOptionKey, triggerEl) {
        if (!returnSplitFlow || !card) return false;
        var leg = card.getAttribute('data-split-leg') || '';
        if (leg === 'outbound') {
            var link = card.querySelector('[data-split-select-outbound]');
            var oid = card.getAttribute('data-offer-id') || '';
            var offer = splitBrandedState && splitBrandedState.offersById[oid] ? splitBrandedState.offersById[oid] : null;
            if (!link || !offer) return false;
            setSplitActionLoading(triggerEl, true);
            handleOutboundChosen(link, offer, fareOptionKey);
            setSplitActionLoading(triggerEl, false);
            return true;
        }
        if (leg === 'return') {
            return proceedReturnCheckout(card, fareOptionKey, triggerEl);
        }
        return false;
    }

    if (returnSplitFlow) {
        window.otaProceedBrandedFareCheckout = proceedSplitBrandedFareCheckout;
    }

    function fetchReturnOptions(reset) {
        if (!returnSplitFlow || !returnOptionsDataUrl || !selectedOutbound) return;
        if (returnOptionsLoading || !returnOptionsHasMore) return;
        returnOptionsLoading = true;
        loadMore.disabled = true;
        hideReturnSplitError();
        var targetPage = reset ? 1 : returnOptionsPage;
        var qs = 'search_id=' + encodeURIComponent(searchId) +
            '&outbound_key=' + encodeURIComponent(selectedOutbound.outboundKey) +
            '&page=' + targetPage + '&per_page=12';
        fetch(returnOptionsDataUrl + '?' + qs, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) {
                if (res.status === 410) {
                    showReturnSplitError('Unable to load return flight options. Please try another flight or refresh.');
                    returnOptionsHasMore = false;
                    throw new Error('expired');
                }
                if (!res.ok) {
                    showReturnSplitError('Unable to load return flight options. Please try another flight or refresh.');
                    throw new Error('http_' + res.status);
                }
                return res.json();
            })
            .then(function (json) {
                if (!json || json.success === false) {
                    showReturnSplitError((json && json.message) || 'Unable to load return flight options. Please try another flight or refresh.');
                    returnOptionsHasMore = false;
                    return;
                }
                if (reset && list) list.innerHTML = '';
                if (json.outbound_journey) {
                    var meta = json.outbound_meta || {};
                    meta = Object.assign({}, meta, selectedOutbound || {});
                    renderSelectedOutboundSummary(json.outbound_journey, meta);
                }
                var options = json.return_options || [];
                if (!options.length && targetPage === 1) {
                    if (summary) summary.textContent = '';
                    returnOptionsHasMore = false;
                    showReturnSplitError('No compatible return options for this outbound flight. Please choose another outbound option.');
                    return;
                }
                if (list) list.insertAdjacentHTML('beforeend', options.map(returnSplitCardHtml).join(''));
                OtaBrandedFares.normalizeAllBrandedFaresPanels(list);
                syncSelectedReturnFromState();
                if (summary) {
                    summary.textContent = @json(__('Showing')) + ' ' + options.length + ' ' + @json(__('of')) + ' ' + (json.total || 0) + ' ' + @json(__('return options'));
                }
                returnOptionsHasMore = !!json.has_more;
                if (returnOptionsHasMore) returnOptionsPage = targetPage + 1;
                if (returnSplitDebug()) {
                    console.debug('[OTA_RETURN_SPLIT] return options loaded', { page: targetPage, count: options.length, total: json.total });
                }
            })
            .catch(function (err) {
                if (returnSplitDebug()) {
                    console.debug('[OTA_RETURN_SPLIT] fetch error', err);
                }
            })
            .finally(function () {
                returnOptionsLoading = false;
                loadMore.disabled = !returnOptionsHasMore;
            });
    }

    function collapseOtherBrandedFares(exceptOid) {
        if (!list) return;
        Object.keys(expandedBrandedFaresByOfferId).forEach(function (oid) {
            if (oid === exceptOid || !expandedBrandedFaresByOfferId[oid]) {
                return;
            }
            var otherCard = list.querySelector('[data-flight-card][data-offer-id="' + oid + '"]');
            if (otherCard) {
                setBrandedFaresExpanded(otherCard, oid, false);
            } else {
                delete expandedBrandedFaresByOfferId[oid];
            }
        });
    }

    function setBrandedFaresExpanded(card, oid, expanded) {
        if (!card || !oid) return;
        expandedBrandedFaresByOfferId[oid] = !!expanded;
        card.classList.toggle('is-fare-options-open', !!expanded);
        var summary = card.querySelector('[data-flight-card-summary]');
        if (summary) {
            summary.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
        var panel = card.querySelector('[data-branded-fares-panel]');
        if (!panel) return;
        var body = panel.querySelector('[data-branded-fares-body]');
        var grid = panel.querySelector('[data-branded-fares-grid]');
        var toggle = panel.querySelector('[data-branded-fares-toggle]');
        if (body) {
            body.hidden = !expanded;
            body.classList.toggle('is-open', !!expanded);
        } else if (grid) {
            grid.hidden = !expanded;
            grid.classList.toggle('is-open', !!expanded);
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
        card.querySelectorAll('[data-branded-fares-toggle]').forEach(function (fareToggle) {
            fareToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
        if (expanded) {
            normalizeBrandedFaresPanel(panel);
        }
    }

    function toggleBrandedFaresExpand(card, oid) {
        if (!card || !oid) return;
        var isOpen = !!expandedBrandedFaresByOfferId[oid];
        var willOpen = !isOpen;
        if (willOpen) {
            collapseOtherBrandedFares(oid);
        }
        setBrandedFaresExpanded(card, oid, willOpen);
    }

    function promptFareFamilySelection(card, offer) {
        if (!card || !offer) return;
        openBrandedFarePanelForOffer(offer.offer_id || '', card);
    }

    function offerRequiresFareFamilySelection(offer) {
        if (window.OtaBrandedFares && typeof OtaBrandedFares.offerNeedsFareChoiceBeforeCheckout === 'function') {
            return OtaBrandedFares.offerNeedsFareChoiceBeforeCheckout(offer);
        }
        if (!offer || !offer.branded_fares_selection_active || !offer.has_branded_fares) return false;
        var opts = offer.fare_family_options_display || [];
        return opts.length > 0;
    }

    function offerNeedsFareChoiceBeforeCheckout(offer) {
        if (window.OtaBrandedFares && typeof OtaBrandedFares.offerNeedsFareChoiceBeforeCheckout === 'function') {
            return OtaBrandedFares.offerNeedsFareChoiceBeforeCheckout(offer);
        }
        if (!offer) {
            return false;
        }
        if (offer.has_fare_choice_options || offer.has_synthetic_default_fare || offer.universal_fare_selection_active) {
            return (offer.fare_family_options_display || []).length > 0;
        }
        return offerRequiresFareFamilySelection(offer);
    }

    function offerRequiresBrandedFareChoice(offer) {
        if (!offer || !offer.has_branded_fares || !offer.branded_fares_display_enabled) {
            return false;
        }
        var opts = offer.fare_family_options_display || [];
        return opts.length >= 2;
    }

    function offerNeedsBrandedFarePickBeforeCheckout(offer, offerId, card) {
        if (offerNeedsFareChoiceBeforeCheckout(offer)) {
            return true;
        }
        if (offerRequiresFareFamilySelection(offer)) {
            return true;
        }
        if (isIatiProviderOffer(offer) && offerRequiresBrandedFareChoice(offer)) {
            return true;
        }
        return offerHasSelectableBrandedFares(offerId, card);
    }

    function bindBrandedFareSelection() {
        if (!list) return;
        if (list.getAttribute('data-bound-branded-fare') === '1') return;
        list.setAttribute('data-bound-branded-fare', '1');
        list.addEventListener('click', function (e) {
            if (e.target.closest('[data-fare-summary-open], [data-branded-fares-toggle], [data-flight-details-open], [data-branded-fares-prev], [data-branded-fares-next]')) {
                return;
            }
            var fareWrap = e.target.closest('[data-fare-option-card-wrap]');
            var fareBtn = e.target.closest('[data-fare-option-card]');
            if (fareBtn && fareBtn.closest('[data-fare-summary-open]')) return;
            var fareTarget = fareWrap || fareBtn;
            if (!fareTarget || !list.contains(fareTarget)) return;
            var flightCard = resolveFlightCard(fareTarget);
            if (!flightCard) return;
            var payload = buildBrandedFareSelectionPayload(flightCard, '', fareTarget);
            if (!payload.valid) {
                showIncompleteBrandedFarePayloadError(payload, 'bindBrandedFareSelection');
                return;
            }
            var oid = payload.offer_id;
            var key = payload.fare_option_key;
            e.preventDefault();
            var triggerEl = fareBtn || fareWrap;
            if (!proceedOneWayToCheckout(flightCard, key, triggerEl)) {
                showOneWayCheckoutError('Unable to continue to checkout. Please try again.');
            }
        });
        list.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var fareWrap = e.target.closest('[data-fare-option-card-wrap][role="button"]');
            if (!fareWrap || !list.contains(fareWrap)) return;
            e.preventDefault();
            var flightCard = resolveFlightCard(fareWrap);
            if (!flightCard) return;
            var payload = buildBrandedFareSelectionPayload(flightCard, '', fareWrap);
            if (!payload.valid) {
                showIncompleteBrandedFarePayloadError(payload, 'bindBrandedFareSelection_keydown');
                return;
            }
            if (!proceedOneWayToCheckout(flightCard, payload.fare_option_key, fareWrap)) {
                showOneWayCheckoutError('Unable to continue to checkout. Please try again.');
            }
        });
    }

    function refreshBrandedFareSelectionUi(offerId) {
        if (!list) return;
        var card = list.querySelector('[data-flight-card][data-offer-id="' + offerId + '"]');
        if (!card) return;
        var offer = offersById[offerId];
        var selectedKey = selectedFareOptionByOfferId[offerId] || '';
        var hasSelection = selectedKey !== '';
        Array.prototype.forEach.call(card.querySelectorAll('[data-fare-option-card], [data-fare-option-card-wrap]'), function (el) {
            var elKey = el.getAttribute('data-fare-option-key') || el.getAttribute('data-option-key') || '';
            var isSel = elKey === selectedKey && hasSelection;
            el.classList.toggle('is-selected', isSel);
            if (el.hasAttribute('data-fare-option-card')) {
                el.setAttribute('aria-pressed', isSel ? 'true' : 'false');
                el.textContent = isSel ? 'Selected' : 'Select';
            }
            if (el.hasAttribute('data-fare-option-card-wrap') && el.getAttribute('role') === 'button') {
                el.setAttribute('aria-pressed', isSel ? 'true' : 'false');
            }
            var selectedBadge = el.querySelector('[data-fare-selected-badge]');
            if (selectedBadge) {
                selectedBadge.hidden = !isSel;
            }
        });
        var priceEl = card.querySelector('[data-card-price]');
        if (priceEl && offer) {
            var dp = cardDisplayPrice(offer);
            priceEl.textContent = dp != null ? formatCardButtonRs(dp) : 'Fare unavailable';
        }
        if (hasSelection) {
            var hint = card.querySelector('.ota-fare-family-selection-hint');
            if (hint) {
                hint.hidden = true;
            }
        }
    }

    function bindBrandedFaresToggle() {
        if (!list) return;
        if (list.getAttribute('data-bound-branded-toggle') === '1') return;
        list.setAttribute('data-bound-branded-toggle', '1');
        list.addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-branded-fares-toggle]');
            if (!toggle || !list.contains(toggle)) return;
            e.preventDefault();
            var card = toggle.closest('[data-flight-card]');
            var oid = toggle.getAttribute('data-offer-id') || (card ? card.getAttribute('data-offer-id') : '');
            if (!card || !oid) return;
            toggleBrandedFaresExpand(card, oid);
        });
    }

    function bindFlightCardFareExpand() {
        if (!list) return;
        if (list.getAttribute('data-bound-card-fare-expand') === '1') return;
        list.setAttribute('data-bound-card-fare-expand', '1');
        list.addEventListener('click', function (e) {
            if (e.target.closest('a, button, [data-fare-summary-open], [data-flight-details-open], [data-fare-option-card], [data-book-now], [data-branded-fares-panel], .ota-branded-fares-panel, .ota-result-action-meta, .ota-result-card-v3__flight-details-row, .flight-card-source-badge')) {
                return;
            }
            var card = e.target.closest('[data-flight-card][data-has-fare-choice], [data-flight-card][data-has-branded-fares]');
            if (!card || !list.contains(card)) return;
            var summary = e.target.closest('[data-flight-card-summary]');
            if (!summary || !card.contains(summary)) return;
            var oid = card.getAttribute('data-offer-id') || '';
            if (!oid) return;
            toggleBrandedFaresExpand(card, oid);
        });
        list.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var summary = e.target.closest('[data-flight-card-summary]');
            if (!summary || !list.contains(summary)) return;
            var card = summary.closest('[data-flight-card][data-has-branded-fares]');
            if (!card) return;
            e.preventDefault();
            var oid = card.getAttribute('data-offer-id') || '';
            if (!oid) return;
            toggleBrandedFaresExpand(card, oid);
        });
    }

    function bindDirectFareContinueButtons() {
        if (!list) return;
        if (list.getAttribute('data-bound-direct-fare-continue') === '1') return;
        list.setAttribute('data-bound-direct-fare-continue', '1');
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-direct-fare-continue]');
            if (!btn || !list.contains(btn) || btn.disabled) return;
            e.preventDefault();
            e.stopPropagation();
            var flightCard = btn.closest('[data-flight-card]');
            if (!flightCard) return;
            var oid = String(flightCard.getAttribute('data-offer-id') || '').trim();
            if (!oid) {
                showMissingOfferSelectionError();
                return;
            }
            var fareKey = btn.getAttribute('data-fare-option-key') || '';
            if (!fareKey) return;
            if (!proceedOneWayToCheckout(flightCard, fareKey, btn)) {
                showOneWayCheckoutError('Unable to continue to checkout. Please try again.');
            }
        });
    }

    function bindBookSelectedFareButtons() {
        if (!list) return;
        if (list.getAttribute('data-bound-book-selected-fare') === '1') return;
        list.setAttribute('data-bound-book-selected-fare', '1');
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-book-selected-fare]');
            if (!btn || !list.contains(btn) || btn.disabled) return;
            e.preventDefault();
            var flightCard = btn.closest('[data-flight-card]');
            var oid = flightCard ? String(flightCard.getAttribute('data-offer-id') || '').trim() : '';
            if (!oid) {
                showMissingOfferSelectionError();
                return;
            }
            var offer = offersById[oid] || null;
            var selectedKey = oid ? (selectedFareOptionByOfferId[oid] || '') : '';
            if (!selectedKey) {
                var selectedCard = flightCard ? flightCard.querySelector('[data-fare-option-card].is-selected') : null;
                selectedKey = selectedCard
                    ? (selectedCard.getAttribute('data-fare-option-key') || selectedCard.getAttribute('data-option-key') || '')
                    : '';
            }
            if (!offer || !selectedKey || !offer.select_url) return;
            if (isIatiProviderOffer(offer)) {
                beginIatiSelectRevalidation(oid, selectedKey, btn, offer.select_url);
                return;
            }
            navigateToCheckoutWithFareKey(offer.select_url, oid, selectedKey);
        });
    }

    function bindBookNowLinks() {
        if (!list) return;
        if (list.getAttribute('data-bound-book-now') === '1') return;
        list.setAttribute('data-bound-book-now', '1');
        list.addEventListener('click', function (e) {
            var link = e.target.closest('a[data-book-now]');
            if (!link || !list.contains(link) || link.getAttribute('data-checkout-loading') === '1') return;
            handleMainBookNowClick(e, link);
            if (!e.defaultPrevented && link.href) {
                markResultsCheckoutNavigation();
            }
        }, true);
    }

    function brandedFareFieldValue(opt, keys) {
        if (!opt) return '';
        for (var i = 0; i < keys.length; i++) {
            var raw = opt[keys[i]];
            if (raw === null || raw === undefined) continue;
            var text = String(raw).trim();
            if (text !== '') return text;
        }
        return '';
    }

    function brandedFareBenefitRow(label, value, iconType) {
        var valueText = String(value || '').trim();
        var longClass = valueText.length > 48 ? ' is-long-policy' : '';
        return '<li class="ota-branded-fare-card__row">' +
            '<span class="ota-branded-fare-card__row-icon ota-branded-fare-card__row-icon--' + esc(iconType) + '" aria-hidden="true"></span>' +
            '<span class="ota-branded-fare-card__row-label">' + esc(label) + '</span>' +
            '<span class="ota-branded-fare-card__row-value' + longClass + '">' + esc(valueText) + '</span></li>';
    }

    function brandedFareBenefitIsFareBasis(label, value) {
        var text = (String(label || '') + ' ' + String(value || '')).toLowerCase();
        return /\bfare[\s_-]*basis\b/.test(text) || /\bfbc\b/.test(text);
    }

    function brandedFareValueMeansNotIncluded(raw) {
        var text = String(raw || '').trim().toLowerCase().replace(/\s+/g, ' ');
        if (!text) return false;
        if (/\bnot\s+included\b/.test(text)) return true;
        if (/\bno\s+baggage\b/.test(text) || /\bwithout\s+baggage\b/.test(text) || /\bno\s+checked\b/.test(text)) return true;
        if (/\b0\s*kg\b/.test(text) || /^0\s*pc/.test(text) || text === '0') return true;
        if (/\bno\s+meal/.test(text) || /\bmeals?\s+not\s+included\b/.test(text)) return true;
        if (text === 'unavailable' || text === 'not available' || text === 'no service') return true;
        return false;
    }

    function brandedFareCleanBenefitValue(raw) {
        var text = String(raw || '').trim().replace(/\s+/g, ' ');
        if (!text || brandedFareBenefitIsFareBasis('', text)) return '';
        var lower = text.toLowerCase();
        if (brandedFareValueMeansNotIncluded(text)) {
            return 'Not included';
        }
        if (lower === 'n/a' || lower === 'na' || lower === 'nil' || lower === 'none' || lower === '--' || lower === '-' || lower === 'no') {
            return '';
        }
        if (lower === 'yes' || lower === 'y') return 'Included';
        if (lower === 'non refundable' || lower === 'nonrefundable' || lower === 'not refundable') return 'Non-refundable';
        text = text.replace(/(\d+(?:\.\d+)?)\s*kg\b/gi, function (match, amount) {
            return amount + ' KG';
        });
        if (/^0\s*KG$/i.test(text)) return 'Not included';
        text = text.replace(/(\d+)\s*(?:pc|pcs|piece|pieces)\b/gi, function (match, amount) {
            return amount + ' PC';
        });
        if (/^0\s*PC$/i.test(text)) return 'Not included';
        if (text.length > 56) {
            text = text.slice(0, 53).trim() + '...';
        }
        return text;
    }

    function brandedFareNormalizeBenefitKey(label) {
        var norm = normalizeFareLabel(label);
        if (!norm) return null;
        if (norm.indexOf('carryon') !== -1 || norm.indexOf('carrybaggage') !== -1 ||
            norm.indexOf('cabinbaggage') !== -1 || norm.indexOf('cabinbag') !== -1 ||
            norm.indexOf('handbaggage') !== -1 || norm.indexOf('handbag') !== -1) {
            return 'carry_on';
        }
        if (norm.indexOf('carry') !== -1 && (norm.indexOf('on') !== -1 || norm.indexOf('bag') !== -1 || norm.indexOf('hand') !== -1 || norm.indexOf('cabin') !== -1)) {
            return 'carry_on';
        }
        if (norm.indexOf('checkedbaggage') !== -1 || norm.indexOf('checkinbaggage') !== -1 ||
            norm.indexOf('checkin') !== -1 || norm.indexOf('baggageallowance') !== -1) {
            return 'check_in';
        }
        if (norm === 'baggage' || (norm.indexOf('baggage') !== -1 && norm.indexOf('carry') === -1 && norm.indexOf('cabin') === -1 && norm.indexOf('hand') === -1)) {
            return 'check_in';
        }
        if (norm.indexOf('meal') !== -1 || norm === 'food' || norm.indexOf('hotmeal') !== -1 ||
            norm.indexOf('sandwich') !== -1 || norm.indexOf('includedmeal') !== -1 || norm.indexOf('mealincluded') !== -1) {
            return 'meal';
        }
        if (norm.indexOf('refund') !== -1 || norm.indexOf('refundable') !== -1 || norm.indexOf('nonrefundable') !== -1 ||
            norm.indexOf('cancellationrefund') !== -1 || norm.indexOf('cancelrefund') !== -1) {
            return 'refund';
        }
        return null;
    }

    function brandedFareExtractValueFromBenefitLine(text) {
        var raw = String(text || '').trim();
        if (!raw) return '';
        if (brandedFareValueMeansNotIncluded(raw)) return 'Not included';
        if (/\badditional\s+cost\b/i.test(raw) || /\bat\s+(?:extra\s+)?(?:cost|charge)\b/i.test(raw) || /\bpay(?:able)?\b/i.test(raw)) {
            return 'Additional cost';
        }
        if (/\bnon[\s-]?refundable\b/i.test(raw)) return 'Non-refundable';
        if (/\brefundable\b/i.test(raw)) return 'Refundable';
        if (/\b(?:meal|food|snack|sandwich)[\s\w-]*included\b/i.test(raw) || /\bincluded\s+(?:meal|food|snack)\b/i.test(raw)) {
            return 'Included';
        }
        var kgMatch = raw.match(/(\d+(?:\.\d+)?)\s*kg/i);
        if (kgMatch) return kgMatch[1] + ' KG';
        var pcMatch = raw.match(/(\d+)\s*(?:pc|pcs|piece|pieces)/i);
        if (pcMatch) return pcMatch[1] + ' PC';
        return raw;
    }

    function brandedFareParseBenefitLine(raw) {
        var text = String(raw || '').trim();
        if (!text || brandedFareBenefitIsFareBasis('', text)) return null;
        var colon = text.indexOf(':');
        if (colon > 0 && colon < text.length - 1) {
            return {
                label: text.slice(0, colon).trim(),
                value: text.slice(colon + 1).trim()
            };
        }
        var key = brandedFareNormalizeBenefitKey(text);
        if (!key) return null;
        return {
            label: text,
            value: brandedFareExtractValueFromBenefitLine(text)
        };
    }

    function brandedFareBenefitFallback(rowKey, hintText) {
        var hint = String(hintText || '').toLowerCase();
        if (rowKey === 'carry_on') {
            return 'Airline policy';
        }
        if (rowKey === 'meal') {
            if (/\bcharge\b/.test(hint) || /\bpaid\b/.test(hint) || /\bbuy\b/.test(hint) || /\bat\s+cost\b/.test(hint)) {
                return 'Additional cost';
            }
            return 'Not specified';
        }
        if (rowKey === 'check_in') {
            if (brandedFareValueMeansNotIncluded(hint)) {
                return 'Not included';
            }
            return 'Not specified';
        }
        if (rowKey === 'refund') {
            return 'Check fare rules';
        }
        return 'Not specified';
    }

    function brandedFareBenefitRows(opt) {
        var resolved = {
            carry_on: '',
            check_in: '',
            meal: '',
            refund: ''
        };
        var hints = {
            carry_on: '',
            check_in: '',
            meal: '',
            refund: ''
        };

        function setRow(rowKey, value, hint) {
            if (resolved[rowKey]) return;
            var clean = brandedFareCleanBenefitValue(value);
            if (!clean) return;
            resolved[rowKey] = clean;
            if (hint) hints[rowKey] = String(hint);
        }

        function absorbLabelValue(label, value) {
            var rowKey = brandedFareNormalizeBenefitKey(label);
            if (!rowKey) return;
            setRow(rowKey, value, label + ' ' + value);
        }

        function scanLines(lines) {
            (lines || []).forEach(function (line) {
                var parsed = brandedFareParseBenefitLine(line);
                if (!parsed) return;
                absorbLabelValue(parsed.label, parsed.value);
            });
        }

        setRow('carry_on', brandedFareFieldValue(opt, ['carry_on_summary', 'carry_on', 'hand_carry', 'cabin_baggage', 'hand_baggage']));
        setRow('check_in', brandedFareFieldValue(opt, ['check_in_summary', 'checked_baggage', 'check_in']));
        if (!resolved.check_in) {
            setRow('check_in', brandedFareFieldValue(opt, ['baggage_summary', 'baggage']));
        }
        setRow('meal', brandedFareFieldValue(opt, ['meal_included', 'meal', 'meals', 'meal_display']));
        setRow('refund', brandedFareFieldValue(opt, ['refundable_display', 'refund_rule', 'refund', 'refundable']));

        scanLines(opt.perks);
        scanLines(opt.included_benefits);
        scanLines(opt.amenities);
        scanLines(opt.restrictions);
        if (Array.isArray(opt.baggage_lines)) {
            opt.baggage_lines.forEach(function (line) {
                var parsed = brandedFareParseBenefitLine(line);
                if (parsed) {
                    absorbLabelValue(parsed.label, parsed.value);
                }
            });
        }

        var compactRows = [
            ['Carry-on baggage', resolved.carry_on || brandedFareBenefitFallback('carry_on', hints.carry_on), 'carry'],
            ['Check-in baggage', resolved.check_in || brandedFareBenefitFallback('check_in', hints.check_in), 'checked'],
            ['Meal', resolved.meal || brandedFareBenefitFallback('meal', hints.meal), 'meal'],
            ['Refund', resolved.refund || brandedFareBenefitFallback('refund', hints.refund), 'refund']
        ];

        return compactRows.map(function (item) {
            return brandedFareBenefitRow(item[0], item[1], item[2]);
        }).join('');
    }

    function brandedFareHasDisplayPrice(opt) {
        if (!opt) return false;
        if (opt.displayed_price != null && isFinite(Number(opt.displayed_price)) && Number(opt.displayed_price) > 0) {
            return true;
        }
        var priceDisplay = opt.price_display ? String(opt.price_display).replace(/^Approx\.\s*/i, '').trim() : '';
        return priceDisplay !== '' && priceDisplay !== '--' && priceDisplay !== '-' && priceDisplay !== '0';
    }

    function brandedFareHasDisplayName(opt) {
        if (!opt) return false;
        var name = String(opt.name || '').trim();
        return name !== '' && name !== '--' && name !== '-' && !/^placeholder$/i.test(name);
    }

    function brandedFareIsPlaceholder(opt) {
        if (!opt) return true;
        if (!brandedFareHasDisplayName(opt)) return true;
        return !brandedFareHasDisplayPrice(opt);
    }

    function brandedFareDedupeKey(opt) {
        var optionKey = normalizeFareLabel(opt.option_key || '');
        var name = normalizeFareLabel(opt.name || '');
        var price = normalizeFareLabel(formatBrandedFarePrice(opt) || String(opt.displayed_price || ''));
        return (optionKey || name) + '|' + name + '|' + price;
    }

    function buildRenderedFareOptions(opts) {
        var seen = {};
        var rendered = [];
        (opts || []).forEach(function (opt) {
            if (brandedFareIsPlaceholder(opt)) return;
            var dedupeKey = brandedFareDedupeKey(opt);
            if (seen[dedupeKey]) return;
            seen[dedupeKey] = true;
            rendered.push(opt);
        });
        return rendered;
    }

    function brandedFaresHideCarouselNav(panel) {
        if (!panel) return;
        panel.querySelectorAll('.ota-branded-fares-carousel__nav, [data-branded-fares-prev], [data-branded-fares-next]').forEach(function (btn) {
            btn.hidden = true;
            btn.setAttribute('aria-hidden', 'true');
            btn.style.display = 'none';
        });
        var carousel = panel.querySelector('[data-branded-fares-carousel]');
        if (carousel) {
            carousel.setAttribute('data-nav-hidden', 'true');
        }
    }

    function brandedFaresForceGridMode(panel, grid, carousel, count) {
        if (!panel || !grid) return;
        grid.classList.remove('ota-branded-fares-panel__grid--slider');
        grid.classList.add('ota-branded-fares-panel__grid--grid');
        grid.setAttribute('data-fare-count', String(count));
        panel.setAttribute('data-slider-active', 'false');
        panel.setAttribute('data-rendered-fare-count', String(count));
        if (carousel && carousel.parentNode) {
            var body = panel.querySelector('[data-branded-fares-body]');
            var heading = body ? body.querySelector('.ota-branded-fares-panel__heading') : null;
            if (grid.parentNode && grid.parentNode !== body) {
                grid.parentNode.removeChild(grid);
            }
            carousel.remove();
            if (body) {
                if (heading) {
                    heading.insertAdjacentElement('afterend', grid);
                } else {
                    body.insertBefore(grid, body.firstChild);
                }
            }
        }
        brandedFaresHideCarouselNav(panel);
    }

    function brandedFaresSyncCarouselNav(panel) {
        if (!panel) return;
        var carousel = panel.querySelector('[data-branded-fares-carousel]');
        if (!carousel) return;
        var viewport = carousel.querySelector('.ota-branded-fares-carousel__viewport');
        if (!viewport) return;
        var hideNav = viewport.scrollWidth <= viewport.clientWidth + 2;
        carousel.setAttribute('data-nav-hidden', hideNav ? 'true' : 'false');
        carousel.querySelectorAll('[data-branded-fares-prev], [data-branded-fares-next]').forEach(function (btn) {
            btn.hidden = hideNav;
        });
    }

    function normalizeBrandedFaresPanel(panel) {
        if (!panel) return;
        var grid = panel.querySelector('[data-branded-fares-grid]');
        if (!grid) return;
        var cards = grid.querySelectorAll('.ota-branded-fare-card');
        var renderedCount = cards.length;
        var carousel = panel.querySelector('[data-branded-fares-carousel]');
        panel.setAttribute('data-rendered-fare-count', String(renderedCount));
        if (renderedCount <= 3) {
            brandedFaresForceGridMode(panel, grid, carousel, renderedCount);
            return;
        }
        panel.setAttribute('data-slider-active', 'true');
        grid.classList.remove('ota-branded-fares-panel__grid--grid');
        grid.classList.add('ota-branded-fares-panel__grid--slider');
        grid.removeAttribute('data-fare-count');
        brandedFaresSyncCarouselNav(panel);
    }

    function normalizeAllBrandedFaresPanels(root) {
        var scope = root || document;
        scope.querySelectorAll('[data-branded-fares-panel]').forEach(normalizeBrandedFaresPanel);
    }

    function brandedFaresCarouselStep(carousel, direction) {
        var viewport = carousel.querySelector('.ota-branded-fares-carousel__viewport');
        if (!viewport) return;
        var card = viewport.querySelector('.ota-branded-fare-card');
        if (!card) return;
        var gap = parseFloat(window.getComputedStyle(viewport.querySelector('[data-branded-fares-grid]') || viewport).gap || '10') || 10;
        var step = card.offsetWidth + gap;
        var maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
        var target = viewport.scrollLeft + (direction * step);
        viewport.scrollTo({ left: Math.max(0, Math.min(maxScroll, target)), behavior: 'smooth' });
    }

    function bindBrandedFaresCarousel() {
        if (!list) return;
        if (list.getAttribute('data-bound-branded-carousel') === '1') return;
        list.setAttribute('data-bound-branded-carousel', '1');
        list.addEventListener('click', function (e) {
            var prev = e.target.closest('[data-branded-fares-prev]');
            var next = e.target.closest('[data-branded-fares-next]');
            if (!prev && !next) return;
            e.preventDefault();
            e.stopPropagation();
            var carousel = (prev || next).closest('[data-branded-fares-carousel]');
            if (!carousel || !list.contains(carousel)) return;
            brandedFaresCarouselStep(carousel, prev ? -1 : 1);
        });
        var resizeTimer = null;
        window.addEventListener('resize', function () {
            if (resizeTimer) clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                normalizeAllBrandedFaresPanels(document);
            }, 120);
        });
    }

    function buildBrandedFaresPanelHtml(offer) {
        if (isJetPkResults && window.JetPkResultCards && typeof JetPkResultCards.buildBrandedFaresPanelHtml === 'function') {
            return JetPkResultCards.buildBrandedFaresPanelHtml(offer, {
                offersById: offersById,
                selectedFareOptionByOfferId: selectedFareOptionByOfferId,
                expandedBrandedFaresByOfferId: expandedBrandedFaresByOfferId,
            }, {
                esc: esc,
                payloadAttr: payloadAttr,
                buildFareSummaryPayload: buildFareSummaryPayload,
                brandedFareBenefitRows: brandedFareBenefitRows,
                formatBrandedFarePrice: formatBrandedFarePrice,
                formatCardButtonRs: formatCardButtonRs,
                buildRenderedFareOptions: buildRenderedFareOptions,
                searchId: searchId,
            });
        }
        if (window.OtaBrandedFares && typeof OtaBrandedFares.buildPanelHtml === 'function') {
            return OtaBrandedFares.buildPanelHtml(offer, {
                offersById: offersById,
                selectedFareOptionByOfferId: selectedFareOptionByOfferId,
                expandedBrandedFaresByOfferId: expandedBrandedFaresByOfferId,
            });
        }
        if (!offer || !(offer.has_fare_choice_options || (offer.branded_fares_display_enabled && offer.has_branded_fares))) return '';
        var opts = offer.fare_family_options_display || offer.branded_fares_display_options || [];
        var renderedFareOptions = buildRenderedFareOptions(opts);
        if (!renderedFareOptions.length) return '';
        var selectionActive = !!(offer.branded_fares_selection_active || offer.universal_fare_selection_active);
        var selectedKey = selectedFareOptionByOfferId[offer.offer_id] || '';
        var isExpanded = !!expandedBrandedFaresByOfferId[offer.offer_id];
        var renderedCount = renderedFareOptions.length;
        var useSlider = renderedCount > 3;
        var cards = renderedFareOptions.map(function (opt) {
            var key = String(opt.option_key || '');
            var isSelected = selectionActive && selectedKey === key;
            var cardClass = 'ota-branded-fare-card' +
                (isSelected ? ' is-selected' : '') +
                (opt.is_cheapest ? ' is-cheapest' : '');
            var summaryPayload = payloadAttr(buildFareSummaryPayload(offer, opt));
            var features = brandedFareBenefitRows(opt);
            var priceText = formatBrandedFarePrice(opt);
            var cheapestBadge = opt.is_cheapest
                ? '<span class="ota-branded-fare-card__badge ota-branded-fare-card__badge--cheapest">Cheapest</span>'
                : '';
            var selectedBadge = isSelected
                ? '<span class="ota-branded-fare-card__badge ota-branded-fare-card__badge--selected" data-fare-selected-badge>Selected</span>'
                : '<span class="ota-branded-fare-card__badge ota-branded-fare-card__badge--selected" data-fare-selected-badge hidden>Selected</span>';
            var selectControl = selectionActive
                ? '<button type="button" class="ota-branded-fare-card__cta btn btn-primary" data-fare-option-card data-offer-id="' + esc(offer.offer_id || '') + '" data-fare-option-key="' + esc(key) + '" aria-pressed="' + (isSelected ? 'true' : 'false') + '">' + (isSelected ? 'Selected' : 'Select') + '</button>'
                : '';
            var wrapAttrs = ' data-fare-option-card-wrap data-offer-id="' + esc(offer.offer_id || '') + '" data-fare-option-key="' + esc(key) + '" data-option-key="' + esc(key) + '"';
            if (selectionActive) {
                wrapAttrs += ' role="button" tabindex="0" aria-pressed="' + (isSelected ? 'true' : 'false') + '"';
            }
            return '<article class="' + cardClass + '"' + wrapAttrs + '>' +
                '<div class="ota-branded-fare-card__header">' +
                '<div class="ota-branded-fare-card__title-block">' +
                '<h5 class="ota-branded-fare-card__name">' + esc(opt.name) + '</h5>' +
                '</div>' +
                '<div class="ota-branded-fare-card__badges">' + cheapestBadge + selectedBadge + '</div>' +
                '</div>' +
                (features ? '<ul class="ota-branded-fare-card__matrix">' + features + '</ul>' : '') +
                '<div class="ota-branded-fare-card__footer">' +
                (priceText ? '<p class="ota-branded-fare-card__price">' + esc(priceText) + '</p>' : '') +
                '<div class="ota-branded-fare-card__actions">' +
                '<button type="button" class="ota-branded-fare-card__details ota-fare-summary-trigger" data-fare-summary-open data-fare-summary-payload="' + summaryPayload + '" data-fare-option-key="' + esc(key) + '">View details</button>' +
                selectControl +
                '</div></div></article>';
        }).join('');
        var gridClass = 'ota-branded-fares-panel__grid' + (useSlider ? ' ota-branded-fares-panel__grid--slider' : ' ota-branded-fares-panel__grid--grid');
        var gridCountAttr = useSlider ? '' : (' data-fare-count="' + renderedCount + '"');
        var gridHtml = '<div class="' + gridClass + '" data-branded-fares-grid' + gridCountAttr + '>' + cards + '</div>';
        var bodyInner = useSlider
            ? ('<div class="ota-branded-fares-carousel" data-branded-fares-carousel data-nav-hidden="false">' +
                '<button type="button" class="ota-branded-fares-carousel__nav ota-branded-fares-carousel__nav--prev" data-branded-fares-prev aria-label="Previous fare options"><span aria-hidden="true">&lsaquo;</span></button>' +
                '<div class="ota-branded-fares-carousel__viewport">' + gridHtml + '</div>' +
                '<button type="button" class="ota-branded-fares-carousel__nav ota-branded-fares-carousel__nav--next" data-branded-fares-next aria-label="Next fare options"><span aria-hidden="true">&rsaquo;</span></button>' +
                '</div>')
            : gridHtml;
        var headingHtml = '<p class="ota-branded-fares-panel__heading">Select a fare option</p>';
        var hintHtml = selectionActive
            ? '<p class="ota-fare-family-selection-hint" hidden role="status">Select a fare option to continue</p>'
            : '';
        return '<div class="ota-branded-fares-panel" data-branded-fares-panel data-rendered-fare-count="' + renderedCount + '" data-slider-active="' + (useSlider ? 'true' : 'false') + '" data-offer-id="' + esc(offer.offer_id || '') + '">' +
            '<button type="button" class="ota-branded-fares-panel__toggle ota-visually-hidden" data-branded-fares-toggle data-offer-id="' + esc(offer.offer_id || '') + '" aria-expanded="' + (isExpanded ? 'true' : 'false') + '" aria-label="Toggle fare options"></button>' +
            hintHtml +
            '<div class="ota-branded-fares-panel__body" data-branded-fares-body' + (isExpanded ? '' : ' hidden') + '>' + headingHtml + bodyInner + '</div></div>';
    }

    function buildFlightCardSourceBadgeHtml(offer) {
        if (!canSeeSupplierSource) {
            return '';
        }
        if (window.OtaFlightDetailBuilders && typeof OtaFlightDetailBuilders.buildFlightCardSourceBadgeHtml === 'function') {
            return OtaFlightDetailBuilders.buildFlightCardSourceBadgeHtml(offer);
        }
        var label = (offer && offer.supplier_source_label)
            ? String(offer.supplier_source_label)
            : 'Supplier';
        return '<span class="flight-card-source-badge">Source: ' + esc(label) + '</span>';
    }

    function buildResultActionColumnHtml(passengerMix, bookNowHtml, flightDetailsBtn, offer) {
        var flightDetailsMeta = flightDetailsBtn
            ? '<div class="ota-result-action-meta">' + flightDetailsBtn + '</div>'
            : '';
        var sourceBadgeHtml = buildFlightCardSourceBadgeHtml(offer || {});
        return '<div class="ota-result-col-price">' +
            '<div class="ota-result-actions-book">' + bookNowHtml + '</div>' +
            '<div class="ota-price-sub ota-result-passenger-mix">' + esc(passengerMix) + '</div>' +
            flightDetailsMeta +
            sourceBadgeHtml +
            '</div>';
    }

    function cardHtml(offer) {
        offer = normalizeResultsOfferRow(offer);
        if (!offer) {
            return '';
        }
        if (isJetPkResults && window.JetPkResultCards && typeof JetPkResultCards.buildCard === 'function') {
            var jpDisplayPrice = cardDisplayPrice(offer);
            var jpCardPricePlain = jpDisplayPrice != null ? formatCardButtonRs(jpDisplayPrice) : 'Fare unavailable';
            var jpCardPrice = esc(jpCardPricePlain);
            var jpPriceBtnAria = esc('Continue with fare ' + jpCardPricePlain);
            var jpHasBrandedOptions = !!(offer.has_fare_choice_options || (offer.branded_fares_display_enabled && offer.has_branded_fares));
            var jpHasFlightDetails = (
                !!offer.has_fallback_details
                || !!offer.fallback_details
                || (Array.isArray(offer.journeys_display) && offer.journeys_display.length > 0)
                || (Array.isArray(offer.segments) && offer.segments.length > 0)
            );
            var jpFlightDetailsPayload = payloadAttr(buildFlightDetailsPayload(offer));
            var jpFlightDetailsBtn = jpHasFlightDetails
                ? ('<button class="ota-flight-details-trigger ota-flight-detail-link jp-flight-card__details-btn" type="button" data-flight-details-open data-flight-details-payload="' + jpFlightDetailsPayload + '">View details</button>')
                : '';
            var jpFareDebugLine = '';
            if (wantsDebugFares() && offer.fare_debug) {
                var jpFd = offer.fare_debug;
                jpFareDebugLine = '<p class="small text-muted ota-fare-debug-line">' +
                    'dbg ' + esc(jpFd.short_offer_id || '') +
                    ' · sup ' + esc(String(jpFd.supplier_total || '')) + ' ' + esc(jpFd.supplier_currency || '') +
                    ' · final ' + esc(String(jpFd.final_customer_price || '')) + ' ' + esc(jpFd.final_currency || '') +
                    ' · shown ' + esc(jpFd.displayed_price != null && jpFd.displayed_price !== '' ? String(jpFd.displayed_price) : '--') +
                    ' · ' + esc(jpFd.fare_verification_status || '') +
                    '</p>';
            }
            var jpBrandedFaresRowHtml = buildBrandedFaresPanelHtml(offer);
            var jpHasBrandedFares = jpBrandedFaresRowHtml !== '';
            var jpBrandedFaresExpanded = jpHasBrandedFares && !!expandedBrandedFaresByOfferId[offer.offer_id];
            var jpProviderCode = String(offer.provider || '').toLowerCase();
            var jpDirectFareOption = (window.OtaBrandedFares && typeof OtaBrandedFares.offerSingleDirectFareOption === 'function')
                ? OtaBrandedFares.offerSingleDirectFareOption(offer)
                : null;
            var jpIsMulticityInquiry = (currentCriteria.trip_type || 'one_way') === 'multi_city' || offer.multicity_inquiry_only === true;
            var jpInquiryUrl = (root.getAttribute('data-multicity-inquiry-url') || offer.inquiry_url || '').trim();
            var jpInquiryNotice = (offer.inquiry_only_notice || root.getAttribute('data-multicity-inquiry-notice') || 'Multi-city booking requires staff confirmation.').trim();
            var jpMulticityMetaHtml = '';
            if (jpIsMulticityInquiry) {
                var jpRouteSlices = Array.isArray(offer.route_by_slice) ? offer.route_by_slice.join(' · ') : '';
                var jpBrandLabel = (offer.brand_name || offer.brand_code || '').trim();
                jpMulticityMetaHtml = '<div class="ota-multicity-card-meta jp-flight-card__multicity small text-muted">' +
                    (offer.full_route_display ? '<div class="ota-multicity-card-meta__route">' + esc(offer.full_route_display) + '</div>' : '') +
                    (jpRouteSlices ? '<div class="ota-multicity-card-meta__slices">' + esc(jpRouteSlices) + '</div>' : '') +
                    ((offer.carrier_chain || offer.validating_carrier) ? '<div class="ota-multicity-card-meta__carrier">' + esc(offer.carrier_chain || offer.validating_carrier) + '</div>' : '') +
                    (jpBrandLabel ? '<div class="ota-multicity-card-meta__brand">' + esc(jpBrandLabel) + '</div>' : '') +
                    '</div>';
            }
            return JetPkResultCards.buildCard({
                offer: offer,
                esc: esc,
                currentCriteria: currentCriteria,
                originFallback: @json($criteria['origin'] ?? ''),
                destinationFallback: @json($criteria['destination'] ?? ''),
                cardPrice: jpCardPrice,
                priceBtnAria: jpPriceBtnAria,
                providerCode: jpProviderCode,
                directFareOption: jpDirectFareOption,
                isMulticityInquiry: jpIsMulticityInquiry,
                inquiryUrl: jpInquiryUrl,
                inquiryNotice: jpInquiryNotice,
                csrfToken: csrfToken,
                searchId: searchId,
                flightDetailsBtn: jpFlightDetailsBtn,
                sourceBadgeHtml: buildFlightCardSourceBadgeHtml(offer),
                fareDebugLine: jpFareDebugLine,
                multicityMetaHtml: jpMulticityMetaHtml,
                brandedFaresRowHtml: jpBrandedFaresRowHtml,
                brandedFaresExpanded: jpBrandedFaresExpanded,
                buildAirlineLogoHtml: buildAirlineLogoHtml,
                buildStandardCardFaceCarrierHtml: buildStandardCardFaceCarrierHtml,
            });
        }
        var passengerMix = (currentCriteria.adults || 1) + ' adult' + ((currentCriteria.adults || 1) === 1 ? '' : 's') +
            ', ' + (currentCriteria.children || 0) + ' child, ' + (currentCriteria.infants || 0) + ' infant';
        var displayPrice = cardDisplayPrice(offer);
        var cardPricePlain = displayPrice != null ? formatCardButtonRs(displayPrice) : 'Fare unavailable';
        var cardPrice = esc(cardPricePlain);
        var priceBtnAria = esc('Continue with fare ' + cardPricePlain);
        var hasBrandedOptions = !!(offer.has_fare_choice_options || (offer.branded_fares_display_enabled && offer.has_branded_fares));
        var hasFlightDetails = (
            !!offer.has_fallback_details
            || !!offer.fallback_details
            || (Array.isArray(offer.journeys_display) && offer.journeys_display.length > 0)
            || (Array.isArray(offer.segments) && offer.segments.length > 0)
        );
        var flightDetailsPayload = payloadAttr(buildFlightDetailsPayload(offer));
        var flightDetailsBtn = hasFlightDetails
            ? ('<button class="ota-flight-details-trigger ota-flight-detail-link" type="button" data-flight-details-open data-flight-details-payload="' + flightDetailsPayload + '">Flight details</button>')
            : '';
        var fareDebugLine = '';
        if (wantsDebugFares() && offer.fare_debug) {
            var fd = offer.fare_debug;
            fareDebugLine = '<p class="small text-muted ota-fare-debug-line">' +
                'dbg ' + esc(fd.short_offer_id || '') +
                ' · sup ' + esc(String(fd.supplier_total || '')) + ' ' + esc(fd.supplier_currency || '') +
                ' · final ' + esc(String(fd.final_customer_price || '')) + ' ' + esc(fd.final_currency || '') +
                ' · shown ' + esc(fd.displayed_price != null && fd.displayed_price !== '' ? String(fd.displayed_price) : '--') +
                ' · ' + esc(fd.fare_verification_status || '') +
                '</p>';
        }
        var airlineDisplayName = (offer.airline_name || offer.primary_display_carrier_name || '').trim();
        var airlineCodeLabel = (offer.airline_code || offer.primary_display_carrier || '').trim();
        var logoHtml = buildAirlineLogoHtml(offer);
        var cardFaceCarrierHtml = buildStandardCardFaceCarrierHtml(offer);
        var stopCount = offer.stops || 0;
        var stopsLabel = stopCount === 0 ? 'Direct' : (stopCount + ' Stop' + (stopCount === 1 ? '' : 's'));
        var stopsLabelHtml = buildStopsLabelHtml(stopsLabel, offer.layover_summary);
        var depTime = offer.departure_time_display || offer.departure_time || '';
        var depDate = offer.departure_date_display || '';
        var depCode = offer.departure_airport_code || @json($criteria['origin'] ?? '');
        var depCity = offer.departure_city || '';
        var arrTime = offer.arrival_time_display || offer.arrival_time || '';
        var arrDate = offer.arrival_date_display || '';
        var arrCode = offer.arrival_airport_code || @json($criteria['destination'] ?? '');
        var arrCity = offer.arrival_city || '';
        var arrOff = offer.arrival_day_offset ? '<span class="ota-arr-offset">' + esc(offer.arrival_day_offset) + '</span>' : '';
        var cardDurLabel = esc(offer.itinerary_duration_display || offer.duration || '');
        var journeysForDisplay = offer.journeys_display || [];
        var tripType = currentCriteria.trip_type || 'one_way';
        var isMultiCityTrip = tripType === 'multi_city';
        var hasJourneyGrouping = journeysForDisplay.length >= 2 && !offer.journey_grouping_unavailable;
        var useRoundTripCompact = tripType === 'round_trip' && hasJourneyGrouping && !isMultiCityTrip;
        var brandedFaresRowHtml = buildBrandedFaresPanelHtml(offer);
        var hasBrandedFares = brandedFaresRowHtml !== '';
        var brandedFaresExpanded = hasBrandedFares && !!expandedBrandedFaresByOfferId[offer.offer_id];
        var brandedFaresAttrs = hasBrandedFares ? ' data-has-fare-choice="1" data-has-branded-fares="1"' : '';
        var brandedFaresOpenClass = brandedFaresExpanded ? ' is-fare-options-open' : '';
        var summaryA11yAttrs = hasBrandedFares
            ? ' data-flight-card-summary role="button" tabindex="0" aria-expanded="' + (brandedFaresExpanded ? 'true' : 'false') + '" aria-label="Toggle fare options"'
            : ' data-flight-card-summary';
        var providerCode = String(offer.provider || '').toLowerCase();
        var directFareOption = (window.OtaBrandedFares && typeof OtaBrandedFares.offerSingleDirectFareOption === 'function')
            ? OtaBrandedFares.offerSingleDirectFareOption(offer)
            : null;
        var isMulticityInquiry = isMultiCityTrip || offer.multicity_inquiry_only === true;
        var inquiryUrl = (root.getAttribute('data-multicity-inquiry-url') || offer.inquiry_url || '').trim();
        var inquiryNotice = (offer.inquiry_only_notice || root.getAttribute('data-multicity-inquiry-notice') || 'Multi-city booking requires staff confirmation.').trim();
        var multicityMetaHtml = '';
        if (isMulticityInquiry) {
            var routeSlices = Array.isArray(offer.route_by_slice) ? offer.route_by_slice.join(' · ') : '';
            var brandLabel = (offer.brand_name || offer.brand_code || '').trim();
            multicityMetaHtml = '<div class="ota-multicity-card-meta small text-muted">' +
                (offer.full_route_display ? '<div class="ota-multicity-card-meta__route">' + esc(offer.full_route_display) + '</div>' : '') +
                (routeSlices ? '<div class="ota-multicity-card-meta__slices">' + esc(routeSlices) + '</div>' : '') +
                ((offer.carrier_chain || offer.validating_carrier) ? '<div class="ota-multicity-card-meta__carrier">' + esc(offer.carrier_chain || offer.validating_carrier) + '</div>' : '') +
                (brandLabel ? '<div class="ota-multicity-card-meta__brand">' + esc(brandLabel) + '</div>' : '') +
                '</div>';
        }
        var bookNowHtml = offer.can_book
            ? (directFareOption && directFareOption.option_key
                ? '<button type="button" class="btn btn-primary ota-select-primary ota-btn-book ota-flight-book-button ota-result-price-btn" data-direct-fare-continue data-fare-option-key="' + esc(directFareOption.option_key) + '" aria-label="' + priceBtnAria + '"><span class="ota-result-price-btn__amount" data-card-price>' + cardPrice + '</span></button>'
                : '<a class="btn btn-primary ota-select-primary ota-btn-book ota-flight-book-button ota-result-price-btn" data-book-now data-provider="' + esc(providerCode) + '" href="' + String(offer.select_url || '').replace(/"/g, '&quot;') + '" aria-label="' + priceBtnAria + '"><span class="ota-result-price-btn__amount" data-card-price>' + cardPrice + '</span></a>')
            : '<button type="button" class="btn btn-default ota-btn-book ota-flight-book-button ota-result-price-btn" disabled aria-label="Fare unavailable"><span class="ota-result-price-btn__amount">Fare unavailable</span></button>';
        if (isMulticityInquiry && inquiryUrl) {
            bookNowHtml = '<form method="post" action="' + esc(inquiryUrl) + '" class="ota-multicity-inquiry-form">' +
                '<input type="hidden" name="_token" value="' + esc(csrfToken ? csrfToken.getAttribute('content') : '') + '">' +
                '<input type="hidden" name="search_id" value="' + esc(searchId) + '">' +
                '<input type="hidden" name="offer_id" value="' + esc(offer.offer_id || '') + '">' +
                '<button type="submit" class="btn btn-primary ota-select-primary ota-btn-book ota-flight-book-button ota-multicity-inquiry-btn" aria-label="Request booking for multi-city fare">' +
                '<span class="ota-result-price-btn__amount" data-card-price>' + cardPrice + '</span>' +
                '<span class="ota-multicity-inquiry-btn__label">Request Booking</span></button>' +
                '<p class="ota-multicity-inquiry-note small text-muted">' + esc(inquiryNotice) + '</p>' +
                '</form>';
        }
        var actionColumnHtml = buildResultActionColumnHtml(passengerMix, bookNowHtml, flightDetailsBtn, offer);

        if (useRoundTripCompact) {
            var cabinTag = offer.cabin || 'Economy';
            var baggageTag = offer.baggage_summary_display || offer.baggage || '';
            var outboundJ = journeysForDisplay[0];
            var returnJ = journeysForDisplay[1];
            var compactBody = '<div class="ota-result-round-compact ota-result-card-v3__round"' + summaryA11yAttrs + '>' +
                buildCompactRoundTripSegmentHtml(outboundJ, offer, cabinTag, baggageTag) +
                buildCompactRoundTripSegmentHtml(returnJ, offer, cabinTag, baggageTag) +
                actionColumnHtml.replace('ota-result-col-price', 'ota-result-col-price ota-result-round-action') +
                fareDebugLine +
                '</div>';
            return '<article class="jp-result-card ota-result-pro-card ota-result-card-v3 ota-result-card-v3--round-compact' + brandedFaresOpenClass + '"' + brandedFaresAttrs + ' data-flight-card data-offer-id="' + esc(offer.offer_id) + '" data-provider="' + esc(providerCode) + '">' +
                compactBody +
                brandedFaresRowHtml +
                '</article>';
        }

        var cardJourneys = journeysForDisplay;
        var moreLegsNote = '';
        if (hasJourneyGrouping && isMultiCityTrip && cardJourneys.length > 3) {
            moreLegsNote = '<div class="ota-result-more-legs small text-muted">+ ' + (cardJourneys.length - 3) + ' more legs</div>';
            cardJourneys = cardJourneys.slice(0, 3);
        }
        var routeHtml = hasJourneyGrouping
            ? '<div class="ota-result-col-route ota-result-col-route--stacked">' + cardJourneys.map(function (j) {
                return buildJourneyScheduleRow(j, { isMultiCity: isMultiCityTrip });
            }).join('') + moreLegsNote + '</div>'
            : '<div class="ota-result-col-route ota-result-col-route--oneway">' +
                buildLegBlockHtml(depTime, depDate, depCode, depCity, 'dep', '') +
                buildCardRouteMidHtml(cardDurLabel, stopsLabelHtml) +
                buildLegBlockHtml(arrTime, arrDate, arrCode, arrCity, 'arr', arrOff) +
                '</div>';

        return '<article class="jp-result-card ota-result-pro-card ota-result-card-v3' + brandedFaresOpenClass + '"' + brandedFaresAttrs + ' data-flight-card data-offer-id="' + esc(offer.offer_id) + '" data-provider="' + esc(providerCode) + '">' +
            '<div class="ota-result-card-main"' + summaryA11yAttrs + '>' +
            '<div class="ota-result-col-brand">' + logoHtml + cardFaceCarrierHtml + multicityMetaHtml + '</div>' +
            routeHtml +
            actionColumnHtml +
            fareDebugLine +
            '</div>' +
            brandedFaresRowHtml +
            '</article>';
    }

    function renderNoFares(customMessage) {
        stripSkeletonCardsFromList();
        var policyMessage = (root.getAttribute('data-empty-policy-message') || '').trim();
        var message = (customMessage || policyMessage || '').trim();
        if (list) {
            if (document.body.classList.contains('jp-flights-results')) {
                var homeUrl = (root.getAttribute('data-jp-home-url') || '/').split('#')[0] + '#jp-flight-search';
                list.innerHTML = '<div class="jp-empty jp-empty--results" role="status">' +
                    '<h2 class="jp-empty__title">No fares found</h2>' +
                    '<p class="jp-empty__desc">' + (message || 'Try different dates or airports for this route.') + '</p>' +
                    '<a class="jp-btn jp-btn--primary" href="' + homeUrl.replace(/"/g, '&quot;') + '">New search</a>' +
                    '</div>';
            } else {
                list.innerHTML = '<p>' + (message || 'No fares found for this route/date. Try a different date or contact support.') + '</p>';
            }
        }
    }

    function syncFilterControls(meta) {
        if (!meta) return;
        function fillSelect(selector, base, rows, valueKey, labelBuilder) {
            var node = document.querySelector(selector);
            if (!node) return;
            var opts = [base];
            (rows || []).forEach(function (row) {
                var v = row[valueKey];
                if (!v) return;
                opts.push('<option value="' + v + '">' + labelBuilder(row) + '</option>');
            });
            node.innerHTML = opts.join('');
            var key = node.getAttribute('data-filter-key');
            if (key && currentFilters[key] !== undefined) node.value = currentFilters[key] || '';
        }
        fillSelect('[data-filter-airline]', '<option value="">All carriers</option>', meta.airlines || [], 'code', function (a) { return (a.name || a.code) + ' (' + a.count + ')'; });
        fillSelect('[data-filter-cabin]', '<option value="">Any</option>', meta.cabin_classes || [], 'value', function (r) { return r.label + ' (' + r.count + ')'; });
        fillSelect('[data-filter-baggage]', '<option value="">Any</option>', meta.baggage_options || [], 'value', function (r) { return r.label + ' (' + r.count + ')'; });
        fillSelect('[data-filter-departure-window]', '<option value="">Any</option>', meta.departure_time_windows || [], 'value', function (r) { return r.label + ' (' + r.count + ')'; });
        fillSelect('[data-filter-arrival-window]', '<option value="">Any</option>', meta.arrival_time_windows || [], 'value', function (r) { return r.label + ' (' + r.count + ')'; });
        fillSelect('[data-filter-duration-bucket]', '<option value="">Any</option>', meta.duration_buckets || [], 'value', function (r) { return r.value + ' (' + r.count + ')'; });
        fillSelect('[data-filter-fare-family]', '<option value="">Any</option>', meta.fare_families || [], 'value', function (r) { return r.label + ' (' + r.count + ')'; });
        fillSelect('[data-filter-layover-airport]', '<option value="">Any</option>', meta.layover_airports || [], 'code', function (r) { return (r.name || r.code) + ' (' + r.count + ')'; });
        fillSelect('[data-filter-operating-airline]', '<option value="">Any</option>', meta.operating_airlines || [], 'code', function (r) { return (r.label || r.code) + ' (' + r.count + ')'; });
    }

    function queryString(pageNo) {
        var params = [
            'search_id=' + encodeURIComponent(searchId),
            'page=' + pageNo,
            'per_page=12',
            'sort=' + encodeURIComponent(currentFilters.sort || 'recommended')
        ];
        if (currentFilters.airline) params.push('airline=' + encodeURIComponent(currentFilters.airline));
        if (currentFilters.stops) params.push('stops=' + encodeURIComponent(currentFilters.stops));
        if (currentFilters.refundable) params.push('refundable=' + encodeURIComponent(currentFilters.refundable));
        if (currentFilters.cabin) params.push('cabin=' + encodeURIComponent(currentFilters.cabin));
        if (currentFilters.baggage) params.push('baggage=' + encodeURIComponent(currentFilters.baggage));
        if (currentFilters.departure_window) params.push('departure_window=' + encodeURIComponent(currentFilters.departure_window));
        if (currentFilters.arrival_window) params.push('arrival_window=' + encodeURIComponent(currentFilters.arrival_window));
        if (currentFilters.duration_bucket) params.push('duration_bucket=' + encodeURIComponent(currentFilters.duration_bucket));
        if (currentFilters.layover_airport) params.push('layover_airport=' + encodeURIComponent(currentFilters.layover_airport));
        if (currentFilters.fare_family) params.push('fare_family=' + encodeURIComponent(currentFilters.fare_family));
        if (currentFilters.bookable_only) params.push('bookable_only=' + encodeURIComponent(currentFilters.bookable_only));
        if (currentFilters.operating_airline) params.push('operating_airline=' + encodeURIComponent(currentFilters.operating_airline));
        if (wantsDebugFares()) params.push('debug_fares=1');
        return params.join('&');
    }

    function updateResultsUrl() {
        var params = new URLSearchParams();
        params.set('trip_type', currentCriteria.trip_type || 'one_way');
        params.set('from', currentCriteria.origin || '');
        params.set('to', currentCriteria.destination || '');
        params.set('depart', currentCriteria.depart_date || '');
        if ((currentCriteria.trip_type || 'one_way') === 'round_trip' && currentCriteria.return_date) {
            params.set('return_date', currentCriteria.return_date);
        }
        params.set('cabin', currentCriteria.cabin || 'economy');
        params.set('adults', String(currentCriteria.adults || 1));
        params.set('children', String(currentCriteria.children || 0));
        params.set('infants', String(currentCriteria.infants || 0));
        if (searchId) {
            params.set('search_id', searchId);
        }
        window.history.pushState({}, '', resultsPageUrl + '?' + params.toString());
    }

    function resetFiltersForNewSearch() {
        currentFilters = {airline: '', stops: '', refundable: '', cabin: '', baggage: '', departure_window: '', arrival_window: '', duration_bucket: '', layover_airport: '', fare_family: '', bookable_only: '', operating_airline: '', sort: 'recommended'};
        Array.prototype.forEach.call(document.querySelectorAll('[data-filter-key]'), function (node) {
            if (node.type === 'checkbox') node.checked = false;
            else node.value = (node.getAttribute('data-filter-key') === 'sort' ? 'recommended' : '');
        });
    }

    function renderChips() {
        var count = 0;
        Object.keys(currentFilters).forEach(function (key) {
            if (currentFilters[key] && key !== 'sort') count++;
        });
        document.querySelectorAll('[data-active-filter-count]').forEach(function (n) {
            n.textContent = String(count);
        });
    }

    function detailsToggleLabel(isOpen) {
        return isOpen
            ? 'Hide details <span class="ota-btn-details-caret" aria-hidden="true">?</span>'
            : 'Flight details <span class="ota-btn-details-caret" aria-hidden="true">?</span>';
    }

    function toggleOfferDetails(btn, block) {
        if (!btn || !block) return;
        var isOpen = !block.hasAttribute('hidden');
        if (isOpen) {
            block.style.display = 'none';
            block.setAttribute('hidden', 'hidden');
            btn.setAttribute('aria-expanded', 'false');
            btn.innerHTML = detailsToggleLabel(false);
        } else {
            block.style.display = 'block';
            block.removeAttribute('hidden');
            btn.setAttribute('aria-expanded', 'true');
            btn.innerHTML = detailsToggleLabel(true);
        }
    }

    function bindDetailsToggles() {
        if (window.OtaFlightDetailBuilders) {
            OtaFlightDetailBuilders.bindDetailsToggles(list);
        }
    }

    function bindFlightDetailTabs() {
        if (window.OtaFlightDetailBuilders) {
            OtaFlightDetailBuilders.bindFlightDetailTabs(list);
        }
    }

    function bindFareBreakdownLinks() {
        if (isJetPkResults) {
            return;
        }
        if (window.OtaFareBreakdownModal) {
            OtaFareBreakdownModal.bindLinks(list);
        }
    }

    function bindFlightDetailsLinks() {
        if (window.OtaFlightDetailsModal) {
            OtaFlightDetailsModal.bindLinks(list);
        }
    }

    var datePriceStrip = root.querySelector('[data-date-price-strip]');
    var datePriceStripInner = root.querySelector('[data-date-price-strip-inner]');
    var nearbyDatesUrl = root.getAttribute('data-nearby-dates-url') || '';
    var nearbyDatesLoaded = false;
    var nearbyDatesRadius = 3;

    function formatNearbyDateLabel(dateStr) {
        var d = new Date(dateStr + 'T12:00:00');
        if (isNaN(d.getTime())) return dateStr;
        var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return days[d.getDay()] + ', ' + d.getDate() + ' ' + months[d.getMonth()];
    }

    function buildNearbyDateSearchUrl(dateStr) {
        var params = new URLSearchParams();
        params.set('trip_type', currentCriteria.trip_type || 'one_way');
        params.set('from', currentCriteria.origin || '');
        params.set('to', currentCriteria.destination || '');
        params.set('depart', dateStr);
        if ((currentCriteria.trip_type || 'one_way') === 'round_trip' && currentCriteria.return_date) {
            params.set('return_date', currentCriteria.return_date);
        }
        params.set('cabin', currentCriteria.cabin || 'economy');
        params.set('adults', String(currentCriteria.adults || 1));
        params.set('children', String(currentCriteria.children || 0));
        params.set('infants', String(currentCriteria.infants || 0));
        return resultsPageUrl + '?' + params.toString();
    }

    function buildNearbyDateSkeletonRows() {
        var tripType = currentCriteria.trip_type || 'one_way';
        if (tripType === 'multi_city') return [];
        var departDate = (currentCriteria.depart_date || '').trim();
        if (!departDate) return [];
        var selected = new Date(departDate + 'T12:00:00');
        if (isNaN(selected.getTime())) return [];
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var rows = [];
        for (var offset = -nearbyDatesRadius; offset <= nearbyDatesRadius; offset++) {
            var candidate = new Date(selected);
            candidate.setDate(candidate.getDate() + offset);
            candidate.setHours(0, 0, 0, 0);
            if (candidate < today) continue;
            var y = candidate.getFullYear();
            var m = String(candidate.getMonth() + 1).padStart(2, '0');
            var day = String(candidate.getDate()).padStart(2, '0');
            var dateStr = y + '-' + m + '-' + day;
            rows.push({
                date: dateStr,
                label: formatNearbyDateLabel(dateStr),
                is_selected: dateStr === departDate,
                search_url: buildNearbyDateSearchUrl(dateStr),
            });
        }
        return rows;
    }

    function stripPriceHtml(row, priceState) {
        if (priceState === 'loading') {
            return '<span class="ota-date-price-chip__price is-loading" aria-label="Fetching fare">...</span>';
        }
        var priceText;
        if (row.cheapest_pkr != null && Number(row.cheapest_pkr) > 0) {
            priceText = formatCardButtonRs(row.cheapest_pkr);
        } else {
            priceText = 'N/A';
        }
        var priceClass = 'ota-date-price-chip__price' + (priceText === 'N/A' ? ' is-unavailable' : '');
        return '<span class="' + priceClass + '">' + esc(priceText) + '</span>';
    }

    function renderDatePriceChip(row, priceState) {
        if (!row || !row.date) return '';
        var chipClass = 'ota-date-price-chip' + (row.is_selected ? ' is-selected' : '') + (priceState === 'loading' ? ' is-price-loading' : '');
        var href = row.search_url ? String(row.search_url) : buildNearbyDateSearchUrl(row.date);
        return '<a class="' + chipClass + '" href="' + esc(href) + '">' +
            '<span class="ota-date-price-chip__date">' + esc(row.label || row.date) + '</span>' +
            stripPriceHtml(row, priceState) + '</a>';
    }

    function renderDatePriceStrip(data, priceState) {
        if (!datePriceStrip || !datePriceStripInner) return;
        if (priceState === 'loading') {
            var skeletonRows = buildNearbyDateSkeletonRows();
            if (!skeletonRows.length) {
                datePriceStrip.hidden = true;
                return;
            }
            datePriceStripInner.innerHTML = skeletonRows.map(function (row) {
                return renderDatePriceChip(row, 'loading');
            }).join('');
            datePriceStrip.hidden = false;
            return;
        }
        if (!data || !data.available || !Array.isArray(data.dates) || !data.dates.length) {
            datePriceStrip.hidden = true;
            return;
        }
        datePriceStripInner.innerHTML = data.dates.map(function (row) {
            return renderDatePriceChip(row, 'resolved');
        }).join('');
        datePriceStrip.hidden = false;
    }

    function fetchNearbyDates() {
        if (!nearbyDatesUrl || nearbyDatesLoaded || returnSplitFlow) return;
        nearbyDatesLoaded = true;
        renderDatePriceStrip(null, 'loading');
        fetch(nearbyDatesUrl + '?search_id=' + encodeURIComponent(searchId), {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (res) {
            return res.ok ? res.json() : null;
        }).then(function (json) {
            renderDatePriceStrip(json, 'resolved');
        }).catch(function () {
            if (datePriceStrip) datePriceStrip.hidden = true;
        });
    }

    function bindSplitFlowCardInteractions() {
        if (!list || !splitBrandedState) return;
        if (window.OtaBrandedFares) {
            OtaBrandedFares.bindAll(list, splitBrandedState, {
                getList: function () { return list; },
                onOutboundNavigate: function (link, offer, selectedKey) {
                    handleOutboundChosen(link, offer, selectedKey);
                },
                onBrandedFareSelect: function (oid, key, offer, card, trigger) {
                    if (!card) return;
                    if (card.getAttribute('data-split-leg') === 'outbound') {
                        var outboundLink = card.querySelector('[data-split-select-outbound]');
                        handleOutboundChosen(outboundLink, offer, key);
                        return;
                    }
                    if (card.getAttribute('data-split-leg') === 'return') {
                        proceedReturnCheckout(card, key, trigger);
                    }
                },
                onReturnCheckout: function (card, fareKey, offer, trigger) {
                    proceedReturnCheckout(card, fareKey, trigger);
                },
            });
            OtaBrandedFares.normalizeAllBrandedFaresPanels(list);
        }
        if (window.OtaReturnSplitCards) {
            OtaReturnSplitCards.bindSplitCardInteractions(list, {
                brandedState: splitBrandedState,
                readOutboundFareOptionKey: function () {
                    if (!selectedOutbound) {
                        return '';
                    }
                    return selectedOutbound.fare_option_key || selectedOutbound.fareOptionKey || '';
                },
                onReturnFormSubmit: function (form) {
                    setSplitActionLoading(form.querySelector('button[type="submit"]'), true);
                },
            });
        }
    }

    function freshnessAgeSeconds(meta) {
        if (!meta) return null;
        if (typeof meta.offer_age_seconds === 'number') return meta.offer_age_seconds;
        var created = meta.search_created_at || meta.selected_offer_created_at;
        if (!created) return null;
        var ts = Date.parse(created);
        if (isNaN(ts)) return null;
        return Math.max(0, Math.floor((Date.now() - ts) / 1000));
    }

    function maybeAutoRefreshFreshness(status) {
        if (!status || status === 'fresh') return;
        if (freshnessAutoRefreshInFlight || loading || bfcacheRefreshInFlight) return;
        if (freshnessAutoRefreshState[status]) return;
        freshnessAutoRefreshState[status] = true;
        triggerSafeResultsRefresh();
    }

    function updateSearchFreshnessBanner(meta) {
        searchFreshnessMeta = meta || searchFreshnessMeta;
        var age = freshnessAgeSeconds(searchFreshnessMeta);
        if (age === null) return;
        var status = (searchFreshnessMeta && searchFreshnessMeta.offer_freshness_status) || '';
        if (!status) {
            if (age >= freshnessStaleAfterSec) status = 'stale';
            else if (age >= freshnessRefreshDueSec) status = 'refresh_due';
            else status = 'fresh';
        }
        if (status === 'fresh') {
            freshnessAutoRefreshState = { refresh_due: false, stale: false };
            return;
        }
        maybeAutoRefreshFreshness(status);
    }

    function showFreshnessError(message) {
        if (!freshnessError) return;
        freshnessError.textContent = message || 'We could not confirm this fare with the airline. Please refresh your search or choose another option.';
        freshnessError.style.display = '';
    }

    function hideFreshnessError() {
        if (freshnessError) {
            freshnessError.style.display = 'none';
            freshnessError.textContent = '';
        }
    }

    if (iatiPriceChangeContinueBtn) {
        iatiPriceChangeContinueBtn.addEventListener('click', function () {
            var onContinue = iatiPriceChangeOnContinue;
            hideIatiPriceChangePrompt();
            if (typeof onContinue === 'function') {
                onContinue();
            }
        });
    }
    if (iatiPriceChangeCancelBtn) {
        iatiPriceChangeCancelBtn.addEventListener('click', function () {
            var onCancel = iatiPriceChangeOnCancel;
            hideIatiPriceChangePrompt();
            if (typeof onCancel === 'function') {
                onCancel();
            }
        });
    }

    function postSelectedOfferRefresh(offerId, onSuccess) {
        if (!revalidateOfferUrl || !offerId) return;
        hideFreshnessError();
        var body = new URLSearchParams();
        body.set('search_id', searchId);
        body.set('offer_id', offerId);
        if (csrfToken && csrfToken.getAttribute('content')) {
            body.set('_token', csrfToken.getAttribute('content'));
        }
        fetch(revalidateOfferUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: body.toString()
        }).then(function (res) {
            return res.json().then(function (json) {
                return { ok: res.ok, json: json };
            }).catch(function () {
                return { ok: false, json: { message: 'We could not confirm this fare with the airline. Please refresh your search or choose another option.' } };
            });
        }).then(function (result) {
            if (result.ok && result.json && result.json.success) {
                if (result.json.search_freshness) {
                    updateSearchFreshnessBanner(result.json.search_freshness);
                }
                if (typeof onSuccess === 'function') {
                    onSuccess(result.json);
                }
                return;
            }
            var msg = (result.json && result.json.message) || 'We could not confirm this fare with the airline. Please refresh your search or choose another option.';
            showFreshnessError(msg);
        }).catch(function () {
            showFreshnessError('We could not confirm this fare with the airline. Please refresh your search or choose another option.');
        });
    }

    function scheduleFreshnessTicker() {
        if (freshnessTimer) clearInterval(freshnessTimer);
        freshnessTimer = setInterval(function () {
            if (!searchFreshnessMeta) return;
            var age = freshnessAgeSeconds(searchFreshnessMeta);
            if (age === null) return;
            searchFreshnessMeta.offer_age_seconds = age;
            if (age >= freshnessStaleAfterSec) searchFreshnessMeta.offer_freshness_status = 'stale';
            else if (age >= freshnessRefreshDueSec) searchFreshnessMeta.offer_freshness_status = 'refresh_due';
            updateSearchFreshnessBanner(searchFreshnessMeta);
        }, 15000);
    }

    if (selectedOfferRefreshInit) {
        var initOfferId = selectedOfferRefreshInit.getAttribute('data-offer-id') || '';
        if (initOfferId) {
            postSelectedOfferRefresh(initOfferId, function (json) {
                if (json.passengers_url) {
                    var refreshKey = initOfferId ? (selectedFareOptionByOfferId[initOfferId] || '') : '';
                    if (refreshKey) {
                        navigateToCheckoutWithFareKey(json.passengers_url, initOfferId, refreshKey);
                    } else {
                        window.location.href = json.passengers_url;
                    }
                    return;
                }
                rerunResultsSearch();
            });
        }
    }

    function renderResultsFromDataJson(json, reset, targetPage) {
        if (!list) {
            throw new Error('Results list container missing.');
        }

        var offers = Array.isArray(json.offers) ? json.offers : [];
        otaResultsDebugLog('offers count', offers.length);

        if (reset) {
            list.innerHTML = '';
            offersById = {};
            selectedFareOptionByOfferId = {};
        }

        if (json.flow === 'return_split_outbound') {
            var outboundOptions = Array.isArray(json.outbound_options) ? json.outbound_options : [];
            if (!outboundOptions.length) {
                if (targetPage === 1) renderNoFares();
                if (filteredEmpty) filteredEmpty.style.display = '';
                hasMore = false;
                if (summary) summary.textContent = '';
                stripSkeletonCardsFromList();
                return;
            }
            if (filteredEmpty) filteredEmpty.style.display = 'none';
            if (reset && splitBrandedState) {
                splitBrandedState.offersById = {};
            }
            var outboundHtml = outboundOptions.map(outboundSplitCardHtml).join('');
            list.insertAdjacentHTML('beforeend', outboundHtml);
            bindSplitFlowCardInteractions();
            if (targetPage === 1) {
                syncFilterControls(json.filters || null);
                renderChips();
            }
            if (summary) {
                summary.textContent = targetPage === 1
                    ? ('Showing ' + outboundOptions.length + ' of ' + (json.total || 0) + ' outbound options')
                    : ('Showing outbound options');
            }
            if (json.search_freshness) {
                updateSearchFreshnessBanner(json.search_freshness);
                scheduleFreshnessTicker();
            }
            hasMore = !!json.has_more;
            if (hasMore) page = targetPage + 1;
            stripSkeletonCardsFromList();
            return;
        }

        if (!offers.length) {
            if (targetPage === 1) renderNoFares(json.empty_message || '');
            if (filteredEmpty) filteredEmpty.style.display = '';
            hasMore = false;
            if (summary) summary.textContent = '';
            stripSkeletonCardsFromList();
            return;
        }

        try {
            if (filteredEmpty) filteredEmpty.style.display = 'none';
            offers.forEach(function (row) {
                var normalized = normalizeResultsOfferRow(row);
                if (normalized) {
                    offersById[normalized.offer_id] = normalized;
                }
            });
            pruneSelectedFareOptions();
            var html = offers.map(function (row) {
                return cardHtml(row);
            }).join('');
            if (!html.trim()) {
                throw new Error('Offer card renderer returned empty HTML.');
            }
            list.insertAdjacentHTML('beforeend', html);
            bindDetailsToggles();
            bindFlightDetailTabs();
            bindFareBreakdownLinks();
            bindFlightDetailsLinks();
            bindBrandedFaresToggle();
            bindFlightCardFareExpand();
            bindBrandedFaresCarousel();
            normalizeAllBrandedFaresPanels(list);
            requestAnimationFrame(function () {
                normalizeAllBrandedFaresPanels(list);
            });
            bindBookNowLinks();
            bindBrandedFareSelection();
            bindBookSelectedFareButtons();
            bindDirectFareContinueButtons();
            if (targetPage === 1 && !returnSplitFlow) {
                fetchNearbyDates();
            }
            if (targetPage === 1) {
                syncFilterControls(json.filters || null);
                renderChips();
            }
            if (summary) {
                var totalCount = json.total != null ? Number(json.total) : offers.length;
                if (!isFinite(totalCount) || totalCount < 0) totalCount = offers.length;
                summary.textContent = targetPage === 1
                    ? (totalCount + ' fare' + (totalCount === 1 ? '' : 's') + ' found')
                    : ('Showing fares');
            }
            if (json.search_freshness) {
                updateSearchFreshnessBanner(json.search_freshness);
                scheduleFreshnessTicker();
            }
            hasMore = !!json.has_more;
            if (hasMore) page = targetPage + 1;
            stripSkeletonCardsFromList();
            otaResultsDebugLog('render complete');
        } catch (renderErr) {
            console.error('[OTA_RESULTS] render failed', renderErr);
            showResultsError('Unable to display fares. Please refresh.');
            hasMore = false;
            throw renderErr;
        }
    }

    function fetchPage(reset) {
        if (!reset && (loading || !hasMore)) return Promise.resolve();
        if (reset && loading) {
            loading = false;
        }
        loading = true;
        armSearchLoadTimeout();
        if (reset) {
            showResultsSkeleton(4);
            if (summary) summary.textContent = 'Showing fares...';
            if (inlineError) {
                inlineError.textContent = '';
                inlineError.hidden = true;
            }
        }
        if (loadMore) loadMore.disabled = true;
        var targetPage = reset ? 1 : page;

        return ensureSearchId().then(function (resolvedSearchId) {
            otaResultsDebugLog('search_id present', !!(resolvedSearchId || searchId || '').trim());
            if (!(searchId || '').trim()) {
                throw { message: 'Missing search_id.' };
            }
            return fetch(resultsDataUrl + '?' + queryString(targetPage), {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
        }).then(function (res) {
            otaResultsDebugLog('data status', res.status);
            if (res.status === 410) {
                if (expired) expired.style.display = '';
                hasMore = false;
                throw { message: 'expired' };
            }
            return res.json().then(function (json) {
                if (!res.ok) {
                    throw {
                        message: (json && json.message) ? json.message : ('Unable to load fares (HTTP ' + res.status + ').')
                    };
                }
                return json;
            });
        }).then(function (json) {
            renderResultsFromDataJson(json, reset, targetPage);
        }).catch(function (err) {
            if (err && err.message === 'expired') {
                hideResultsSkeleton(true);
                stripSkeletonCardsFromList();
                if (summary) summary.textContent = '';
                return;
            }
            if (err && err.message === 'Unable to display fares. Please refresh.') {
                return;
            }
            showResultsError(err && err.message ? err.message : 'Unable to load fares. Please try again.');
            hasMore = false;
        }).finally(function () {
            loading = false;
            clearSearchLoadTimeout();
            stripSkeletonCardsFromList();
            clearResultsSkeletonState();
            if (loadMore) loadMore.disabled = !hasMore;
        });
    }

    function applyFilters() {
        if (returnSplitFlow && returnStepActive) {
            resetToOutboundStep();
            return;
        }
        page = 1;
        hasMore = true;
        fetchPage(true);
    }

    function bindInlineAutocomplete() {
        if (!inlineForm) return;
        var airportsSearchUrl = (root && root.getAttribute('data-airports-search-url')) || '/airports/search';
        var timers = new WeakMap();
        var aborters = new WeakMap();
        function close(box) {
            if (box) {
                box.style.display = 'none';
                box.innerHTML = '';
            }
        }
        function airportItemHeadline(row, code) {
            var city = (row.city || '').trim();
            if (city) return city + ' (' + code + ')';
            var name = (row.name || '').trim();
            if (name) return name + ' (' + code + ')';
            return code;
        }
        function airportItemSubline(row) {
            var name = (row.name || '').trim();
            var city = (row.city || '').trim();
            if (name && name !== city) return name;
            var description = (row.description || '').trim();
            if (description) return description;
            return (row.country || '').trim();
        }
        function airportItemMarkup(row, code) {
            return '<span class="ota-airport-item-icon" aria-hidden="true"><i class="fa fa-plane"></i></span>' +
                '<span class="ota-airport-item-lines">' +
                '<span class="ota-airport-item-main">' + esc(airportItemHeadline(row, code)) + '</span>' +
                '<span class="ota-airport-item-sub">' + esc(airportItemSubline(row)) + '</span>' +
                '</span>';
        }
        Array.prototype.forEach.call(inlineForm.querySelectorAll('.js-inline-airport'), function (input) {
            var cell = input.closest('[data-inline-airport-field]');
            var box = cell ? cell.querySelector('.ota-airport-suggest') : null;
            if (!box || input.getAttribute('data-bound') === '1') return;
            input.setAttribute('data-bound', '1');
            input.addEventListener('input', function () {
                var hidden = document.getElementById(input.getAttribute('data-hidden-target'));
                if (hidden) hidden.value = '';
                var sub = cell ? cell.querySelector('[data-inline-airport-sub]') : null;
                if (sub && !(input.value || '').trim()) sub.textContent = '';
                var oldTimer = timers.get(input);
                if (oldTimer) clearTimeout(oldTimer);
                timers.set(input, setTimeout(function () {
                    var q = (input.value || '').trim();
                    if (q.length < 2) return close(box);
                    var oldCtrl = aborters.get(input);
                    if (oldCtrl) oldCtrl.abort();
                    var ctrl = new AbortController();
                    aborters.set(input, ctrl);
                    fetch(airportsSearchUrl + (airportsSearchUrl.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q) + '&limit=10', { signal: ctrl.signal, headers: {'X-Requested-With': 'XMLHttpRequest'} })
                        .then(function (r) { return r.ok ? r.json() : []; })
                        .then(function (rows) {
                            box.innerHTML = '';
                            (rows || []).forEach(function (row) {
                                var code = (row.iata || row.iata_code || '').toUpperCase();
                                if (!code) return;
                                var btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'ota-airport-item';
                                btn.innerHTML = airportItemMarkup(row, code);
                                btn.addEventListener('pointerdown', function (event) {
                                    event.preventDefault();
                                    input.value = airportItemHeadline(row, code);
                                    var hidden = document.getElementById(input.getAttribute('data-hidden-target'));
                                    if (hidden) hidden.value = code;
                                    var subEl = cell ? cell.querySelector('[data-inline-airport-sub]') : null;
                                    if (subEl) {
                                        var c = (row.city || '').trim();
                                        var co = (row.country || '').trim();
                                        subEl.textContent = (c && co) ? (c + ', ' + co) : (c || co || '');
                                    }
                                    close(box);
                                });
                                box.appendChild(btn);
                            });
                            box.style.display = box.children.length ? 'block' : 'none';
                        })
                        .catch(function () {});
                }, 180));
            });
            input.addEventListener('blur', function () { setTimeout(function () { close(box); }, 100); });
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-filter-key]'), function (node) {
        node.addEventListener('change', function () {
            var key = node.getAttribute('data-filter-key');
            if (!key) return;
            currentFilters[key] = node.type === 'checkbox' ? (node.checked ? '1' : '') : (node.value || '');
            if (window.innerWidth < 992 && key !== 'sort') return;
            applyFilters();
        });
    });
    filterResetButtons.forEach(function (filterReset) {
        filterReset.addEventListener('click', function () {
            currentFilters = {airline: '', stops: '', refundable: '', cabin: '', baggage: '', departure_window: '', arrival_window: '', duration_bucket: '', layover_airport: '', fare_family: '', bookable_only: '', operating_airline: '', sort: 'recommended'};
            Array.prototype.forEach.call(document.querySelectorAll('[data-filter-key]'), function (node) {
                if (node.type === 'checkbox') node.checked = false;
                else node.value = (node.getAttribute('data-filter-key') === 'sort' ? 'recommended' : '');
            });
            applyFilters();
        });
    });
    function openFilterDrawer() {
        if (!drawer) return;
        drawer.classList.add('ota-filter-drawer--open');
        drawer.style.display = 'block';
        if (backdrop && window.innerWidth < 992) {
            backdrop.classList.add('is-open');
            backdrop.setAttribute('aria-hidden', 'false');
            document.body.classList.add('ota-filter-open');
        }
    }
    function closeFilterDrawer() {
        if (!drawer) return;
        drawer.classList.remove('ota-filter-drawer--open');
        if (window.innerWidth < 992) drawer.style.display = 'none';
        document.body.classList.remove('ota-filter-open');
        if (backdrop) {
            backdrop.classList.remove('is-open');
            backdrop.setAttribute('aria-hidden', 'true');
        }
    }
    mobileOpenBtns.forEach(function (btn) {
        btn.addEventListener('click', function () { if (window.innerWidth < 992) openFilterDrawer(); });
    });
    mobileCloseBtns.forEach(function (btn) {
        btn.addEventListener('click', function () { closeFilterDrawer(); });
    });
    if (backdrop) backdrop.addEventListener('click', function () { closeFilterDrawer(); });
    if (mobileApply && drawer) mobileApply.addEventListener('click', function () { applyFilters(); closeFilterDrawer(); });
    if (mobileOpenSort && drawer) {
        mobileOpenSort.addEventListener('click', function () {
            if (window.innerWidth >= 992) return;
            openFilterDrawer();
            var sortEl = document.getElementById('ota-filter-sort');
            if (sortEl) setTimeout(function () { sortEl.focus(); }, 50);
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeFilterDrawer();
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) {
            closeFilterDrawer();
            drawer.style.display = 'block';
        } else {
            drawer.style.display = 'none';
        }
    });

    loadMore.addEventListener('click', function () {
        if (returnSplitFlow && returnStepActive) {
            fetchReturnOptions(false);
            return;
        }
        fetchPage(false);
    });
    if (changeOutboundBtn) {
        changeOutboundBtn.addEventListener('click', function () {
            resetToOutboundStep();
        });
    }
    var inlineTripType = inlineForm ? inlineForm.querySelector('input[name="trip_type"]') : null;
    var tripChoices = inlineForm ? inlineForm.querySelectorAll('[data-trip-choice]') : [];
    var inlineDepart = inlineForm ? inlineForm.querySelector('input[name="depart"]') : null;
    var inlineReturn = inlineForm ? inlineForm.querySelector('input[name="return_date"]') : null;
    function parseYmdParts(iso) {
        if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
            return { main: '', day: '' };
        }
        var p = iso.split('-');
        var y = parseInt(p[0], 10);
        var m = parseInt(p[1], 10) - 1;
        var d = parseInt(p[2], 10);
        var dt = new Date(y, m, d);
        if (isNaN(dt.getTime())) {
            return { main: '', day: '' };
        }
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return {
            main: months[m] + ' ' + d + ', ' + y,
            day: dt.toLocaleDateString(undefined, { weekday: 'long' })
        };
    }
    function syncInlineDateLabels() {
        if (!inlineForm) return;
        var dm = inlineForm.querySelector('[data-inline-depart-main]');
        var dd = inlineForm.querySelector('[data-inline-depart-day]');
        if (inlineDepart && dm && dd) {
            var o = parseYmdParts(inlineDepart.value);
            dm.textContent = o.main;
            dd.textContent = o.day;
        }
        var rm = inlineForm.querySelector('[data-inline-return-main]');
        var rd = inlineForm.querySelector('[data-inline-return-day]');
        var rw = inlineForm.querySelector('[data-inline-return-wrap]');
        if (inlineReturn && rm && rd) {
            if (rw && rw.style.display !== 'none' && inlineReturn.value) {
                var r = parseYmdParts(inlineReturn.value);
                rm.textContent = r.main;
                rd.textContent = r.day;
            } else {
                rm.textContent = '';
                rd.textContent = '';
            }
        }
    }
    function applyInlineDisplayFromServer(d) {
        if (!d || !inlineForm) return;
        var subs = inlineForm.querySelectorAll('[data-inline-airport-sub]');
        if (subs[0] && d.origin_subtitle != null) subs[0].textContent = d.origin_subtitle;
        if (subs[1] && d.destination_subtitle != null) subs[1].textContent = d.destination_subtitle;
        var dm = inlineForm.querySelector('[data-inline-depart-main]');
        var dd = inlineForm.querySelector('[data-inline-depart-day]');
        if (dm && d.depart_main != null) dm.textContent = d.depart_main;
        if (dd && d.depart_day != null) dd.textContent = d.depart_day;
        var rm = inlineForm.querySelector('[data-inline-return-main]');
        var rd = inlineForm.querySelector('[data-inline-return-day]');
        if (rm && d.return_main != null) rm.textContent = d.return_main;
        if (rd && d.return_day != null) rd.textContent = d.return_day;
    }
    function syncInlineReturnMin() {
        if (!inlineDepart || !inlineReturn) return;
        var d = inlineDepart.value || '';
        if (d) {
            inlineReturn.min = d;
            if (inlineReturn.value && inlineReturn.value < d) inlineReturn.value = d;
        }
    }
    if (inlineTripType && inlineForm) {
        Array.prototype.forEach.call(tripChoices, function (btn) {
            btn.addEventListener('click', function () {
                var val = btn.getAttribute('data-trip-choice');
                if (!val || btn.disabled) return;
                inlineTripType.value = val;
                inlineForm.setAttribute('data-trip-mode', val);
                Array.prototype.forEach.call(tripChoices, function (b) { b.classList.toggle('is-active', b === btn); });
                inlineTripType.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
        inlineTripType.addEventListener('change', function () {
            var wrap = inlineForm.querySelector('[data-round-return]');
            var simpleRow = inlineForm.querySelector('[data-hero-search-row="simple"]');
            if (wrap) wrap.hidden = inlineTripType.value !== 'round_trip';
            if (simpleRow) simpleRow.classList.toggle('ota-hero-search-row--return', inlineTripType.value === 'round_trip');
            if (inlineTripType.value === 'round_trip') syncInlineReturnMin();
            syncInlineDateLabels();
        });
    }
    if (inlineDepart) {
        inlineDepart.addEventListener('change', function () {
            syncInlineReturnMin();
            syncInlineDateLabels();
        });
        inlineDepart.addEventListener('input', syncInlineDateLabels);
    }
    if (inlineReturn) {
        inlineReturn.addEventListener('change', syncInlineDateLabels);
        inlineReturn.addEventListener('input', syncInlineDateLabels);
    }
    syncInlineReturnMin();
    syncInlineDateLabels();
    bindInlineAutocomplete();
    if (inlineForm) {
        inlineForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (inlineError) { inlineError.textContent = ''; inlineError.hidden = true; }
            var fromHidden = inlineForm.querySelector('input[name="from"]');
            var toHidden = inlineForm.querySelector('input[name="to"]');
            var fromDisplay = inlineForm.querySelector('input[name="from_display"]');
            var toDisplay = inlineForm.querySelector('input[name="to_display"]');
            if (fromDisplay && fromDisplay.value.trim() && (!fromHidden || !fromHidden.value.trim())) {
                if (inlineError) { inlineError.textContent = 'Please select a valid origin airport from the dropdown.'; inlineError.hidden = false; }
                return;
            }
            if (toDisplay && toDisplay.value.trim() && (!toHidden || !toHidden.value.trim())) {
                if (inlineError) { inlineError.textContent = 'Please select a valid destination airport from the dropdown.'; inlineError.hidden = false; }
                return;
            }
            if (fromHidden && toHidden && fromHidden.value && fromHidden.value === toHidden.value) {
                if (inlineError) { inlineError.textContent = 'Origin and destination cannot be the same.'; inlineError.hidden = false; }
                return;
            }
            var submitBtns = inlineForm.querySelectorAll('[data-inline-submit], [data-flight-search-submit]');
            submitBtns.forEach(function (btn) { btn.disabled = true; });
            if (inlineStatus) inlineStatus.textContent = 'Searching fares...';
            showResultsSkeleton(4);
            page = 1;
            hasMore = true;
            loading = false;
            expireStaleOfferSelection();
            var params = new URLSearchParams(new FormData(inlineForm));
            fetch(resultsSearchUrl + '?' + params.toString(), { headers: {'X-Requested-With': 'XMLHttpRequest'} })
                .then(function (res) { return res.json().then(function (json) { if (!res.ok) throw json; return json; }); })
                .then(function (json) {
                    applySearchBootstrapJson(json);
                    resetFiltersForNewSearch();
                    page = 1;
                    hasMore = true;
                    if (summary) summary.textContent = 'Showing fares...';
                    searchFreshnessMeta = null;
                    freshnessAutoRefreshState = { refresh_due: false, stale: false };
                    fetchPage(true);
                })
                .catch(function (err) {
                    hideResultsSkeleton(true);
                    if (summary) summary.textContent = '';
                    if (inlineError) {
                        inlineError.textContent = (err && err.message) ? err.message : 'Unable to refresh search. Please try again.';
                        inlineError.hidden = false;
                    }
                })
                .finally(function () {
                    submitBtns.forEach(function (btn) { btn.disabled = false; });
                    if (inlineStatus) inlineStatus.textContent = '';
                });
        });
    }
    fetchPage(true);
    closeFilterDrawer();
})();
</script>
@endpush
