@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Flight results')

@section('content')
    @include('partials.agent-booking-mode-banner')
    @php
        $criteria = $criteria ?? [];
        $inlineDisplay = $inlineDisplay ?? [];
        $origin = strtoupper(trim((string) ($criteria['origin'] ?? ($inlineDisplay['origin_code'] ?? ''))));
        $destination = strtoupper(trim((string) ($criteria['destination'] ?? ($inlineDisplay['destination_code'] ?? ''))));
        $tripType = (string) ($criteria['trip_type'] ?? 'one_way');
        $cabin = strtolower((string) ($criteria['cabin'] ?? 'economy'));
        $adults = max(1, (int) ($criteria['adults'] ?? 1));
        $children = max(0, (int) ($criteria['children'] ?? 0));
        $infants = max(0, (int) ($criteria['infants'] ?? 0));
        $travelerCount = $adults + $children + $infants;
        $travelerLabel = $travelerCount === 1 ? '1 Traveler' : $travelerCount.' Travelers';
        $cabinLabel = match ($cabin) {
            'premium_economy' => 'Premium Economy',
            'business' => 'Business',
            'first' => 'First',
            default => 'Economy',
        };

        $departMain = (string) ($inlineDisplay['depart_main'] ?? '');
        $returnMain = (string) ($inlineDisplay['return_main'] ?? '');
        if ($departMain === '' && ! empty($criteria['depart_date'])) {
            try {
                $departMain = \Carbon\Carbon::parse((string) $criteria['depart_date'])->format('j M, Y');
            } catch (\Throwable) {
                $departMain = (string) $criteria['depart_date'];
            }
        }
        if ($returnMain === '' && ! empty($criteria['return_date'])) {
            try {
                $returnMain = \Carbon\Carbon::parse((string) $criteria['return_date'])->format('j M, Y');
            } catch (\Throwable) {
                $returnMain = (string) $criteria['return_date'];
            }
        }

        $dateLine = $departMain;
        if ($tripType === 'round_trip' && $returnMain !== '') {
            $dateLine = $departMain.' - '.$returnMain;
        }

        $routeArrow = $tripType === 'round_trip' ? '⇄' : '→';
    @endphp

    @php
        $offerFreshnessRefresh = $offerFreshnessRefresh ?? [];
        $freshnessCheckoutMessage = trim((string) ($offerFreshnessRefresh['message'] ?? ''));
        if ($freshnessCheckoutMessage === '' && $errors->has('flight_id')) {
            $freshnessCheckoutMessage = trim((string) $errors->first('flight_id'));
        }
    @endphp
    <div
        class="ota-mobile-results"
        data-testid="ota-mobile-results"
        data-mobile-results-root
        data-search-id="{{ $searchId ?? '' }}"
        data-return-split-flow="{{ !empty($returnSplitFlow) ? '1' : '0' }}"
        data-results-url="{{ route('flights.results.data') }}"
        data-offer-details-url="{{ route('flights.results.offer') }}"
        data-revalidate-offer-url="{{ $revalidateOfferUrl ?? route('flights.results.revalidate-offer') }}"
        data-freshness-refresh-due="{{ (int) config('ota.offer_freshness.refresh_due_seconds', 300) }}"
        data-freshness-stale-after="{{ (int) config('ota.offer_freshness.stale_after_seconds', 600) }}"
        data-criteria='@json($criteria)'
        data-origin="{{ $origin }}"
        data-destination="{{ $destination }}"
    >
        <header class="ota-mobile-results__header">
            <div class="ota-mobile-results__header-row">
                <a href="{{ route('home') }}" class="ota-mobile-results__back" aria-label="Back to search">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true">
                        <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                    </svg>
                </a>
                <div class="ota-mobile-results__summary">
                    <p class="ota-mobile-results__route">{{ $origin }} {{ $routeArrow }} {{ $destination }}</p>
                    @if ($dateLine !== '')
                        <p class="ota-mobile-results__dates">{{ $dateLine }}</p>
                    @endif
                    <p class="ota-mobile-results__meta">{{ $travelerLabel }}, {{ $cabinLabel }}</p>
                </div>
                <div class="ota-mobile-results__header-spacer" aria-hidden="true"></div>
            </div>
        </header>

        <div class="ota-mobile-results__chips-wrap">
            <div class="ota-mobile-results__chips" data-mobile-quick-filters role="toolbar" aria-label="Quick filters">
                <button type="button" class="ota-mobile-results__chip is-active" data-mobile-results-chip="cheapest" data-quick-filter="cheapest">Cheapest</button>
                <button type="button" class="ota-mobile-results__chip" data-quick-filter="fastest" data-mobile-results-chip="fastest">Fastest</button>
                <button type="button" class="ota-mobile-results__chip" data-quick-filter="direct" data-mobile-results-chip="direct">Direct</button>
                <button type="button" class="ota-mobile-results__chip" data-quick-filter="airline" data-mobile-results-chip="airline">Airline</button>
                <button type="button" class="ota-mobile-results__chip" data-quick-filter="stops" data-mobile-results-chip="stops">Stops</button>
            </div>
        </div>

        <p class="ota-mobile-results__notice" role="note">
            @if (!empty($returnSplitFlow))
                <strong>{{ __('Step 1: Select outbound') }}</strong> — {{ __('Prices show total return fare') }}
            @else
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
            Prices are in PKR and include taxes
            @endif
        </p>

        @if (! empty($warnings ?? []))
            <div class="ota-mobile-results__warnings" role="alert">
                @foreach ($warnings as $warning)
                    <p>{{ $warning }}</p>
                @endforeach
            </div>
        @endif

        @if (! empty($offerFreshnessRefresh['required'] ?? false))
            <div class="ota-mobile-results__freshness ota-mobile-results__freshness--checkout" data-mobile-selected-offer-refresh-banner role="alert">
                <p class="ota-mobile-results__freshness-text" data-mobile-selected-offer-refresh-message>{{ $freshnessCheckoutMessage !== '' ? $freshnessCheckoutMessage : __('This fare needs to be refreshed because airline prices and availability can change quickly.') }}</p>
                <button type="button" class="ota-mobile-results__freshness-btn" data-mobile-selected-offer-refresh data-offer-id="{{ $offerFreshnessRefresh['selected_offer_id'] ?? '' }}">{{ __('Check availability again') }}</button>
            </div>
        @elseif ($freshnessCheckoutMessage !== '')
            <div class="ota-mobile-results__freshness ota-mobile-results__freshness--checkout" role="alert">
                <p class="ota-mobile-results__freshness-text">{{ $freshnessCheckoutMessage }}</p>
            </div>
        @endif

        <div class="ota-mobile-results__freshness" data-mobile-offer-freshness-banner hidden>
            <p class="ota-mobile-results__freshness-text" data-mobile-offer-freshness-message></p>
            <button type="button" class="ota-mobile-results__freshness-btn" data-mobile-offer-freshness-refresh hidden>{{ __('Refresh fares') }}</button>
        </div>

        <p class="ota-mobile-results__count" data-mobile-results-summary>Loading flights…</p>
        <p class="ota-mobile-results__inline-error" data-mobile-results-inline-error hidden role="alert">Could not refresh results. Try again.</p>

        <div class="ota-mobile-results__list" data-mobile-results-list>
            @for ($i = 0; $i < 3; $i++)
                @include('mobile.flights.partials.result-card', ['skeletonRoundTrip' => $tripType === 'round_trip'])
            @endfor
        </div>

        <div class="ota-mobile-results__load-more-wrap">
            <button type="button" class="ota-mobile-results__load-more" data-mobile-load-more disabled>Load more</button>
        </div>

        <p class="ota-mobile-results__empty" data-mobile-expired-message hidden>This fare search has expired. Please search again.</p>
        <p class="ota-mobile-results__empty" data-mobile-empty-message hidden>No flights match your filters. Try adjusting filters or search again.</p>
        <p class="ota-mobile-results__empty" data-mobile-no-results-message hidden>No fares found for this route and date. Try different dates or contact support.</p>

        @include('mobile.flights.partials.filter-drawer')

        <nav class="ota-mobile-results__action-bar" aria-label="Results actions">
            <button type="button" class="ota-mobile-results__action" data-mobile-open-sort>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                    <path d="M3 18h6v-2H3v2zM3 6v2h18V6H3zm0 7h12v-2H3v2z"/>
                </svg>
                Sort
            </button>
            <span class="ota-mobile-results__action-divider" aria-hidden="true"></span>
            <button type="button" class="ota-mobile-results__action" data-mobile-open-filter-bar>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                    <path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>
                </svg>
                Filter
            </button>
        </nav>
    </div>

    @include('frontend.partials.ota-fare-summary-modal')
@endsection

@push('scripts')
<script src="{{ ui_asset('js/ota-flight-fallback-details.js') }}"></script>
<script src="{{ ui_asset('js/ota-branded-fares.js') }}"></script>
<script src="{{ ui_asset('js/ota-fare-breakdown-modal.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.OtaFareBreakdownModal) {
        OtaFareBreakdownModal.init();
        var list = document.querySelector('[data-mobile-results-list]');
        if (list && typeof OtaFareBreakdownModal.bindLinks === 'function') {
            OtaFareBreakdownModal.bindLinks(list);
        }
    }
});
</script>
@endpush
