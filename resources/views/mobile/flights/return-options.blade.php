@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Select return flight')

@section('content')
    @php
        $criteria = $criteria ?? [];
        $origin = strtoupper(trim((string) ($criteria['origin'] ?? '')));
        $destination = strtoupper(trim((string) ($criteria['destination'] ?? '')));
    @endphp
    <div
        class="ota-mobile-results ota-mobile-return-options"
        data-testid="ota-mobile-return-options"
        data-return-options-root
        data-search-id="{{ $searchId }}"
        data-outbound-key="{{ $outboundKey }}"
        data-return-options-data-url="{{ $returnOptionsDataUrl }}"
        data-select-return-combo-url="{{ $selectReturnComboUrl }}"
    >
        <header class="ota-mobile-results__header">
            <div class="ota-mobile-results__header-row">
                <a href="{{ $resultsUrl }}" class="ota-mobile-results__back" aria-label="{{ __('Change outbound') }}">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true">
                        <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                    </svg>
                </a>
                <div class="ota-mobile-results__summary">
                    <p class="ota-mobile-results__route">{{ $origin }} ⇄ {{ $destination }}</p>
                    <p class="ota-mobile-results__meta">{{ __('Round-trip selection') }}</p>
                </div>
                <div class="ota-mobile-results__header-spacer" aria-hidden="true"></div>
            </div>
        </header>

        <div class="ota-mobile-return-split-steps" role="status">
            <span class="ota-mobile-return-split-steps__step is-done">{{ __('1. Outbound selected') }}</span>
            <span class="ota-mobile-return-split-steps__sep" aria-hidden="true">·</span>
            <span class="ota-mobile-return-split-steps__step is-active">{{ __('2. Select return') }}</span>
        </div>

        @if ($errors->has('flight_id'))
            <div class="ota-mobile-results__warnings" role="alert">
                <p>{{ $errors->first('flight_id') }}</p>
            </div>
        @endif

        <div class="ota-return-split-selected-outbound ota-mobile-return-split-selected" data-outbound-summary hidden>
            <h2 class="ota-mobile-return-split-selected__title">{{ __('Selected outbound') }}</h2>
            <div class="ota-mobile-return-split-selected__body" data-outbound-summary-body></div>
        </div>

        <p class="ota-mobile-results__count" data-return-options-summary>{{ __('Loading return options…') }}</p>
        <div class="ota-mobile-results__list" data-return-options-list></div>
        <button type="button" class="ota-mobile-results__load-more" data-return-load-more disabled>{{ __('Load more') }}</button>
        <p class="ota-mobile-results__empty" data-return-empty-message hidden>{{ __('No compatible return options.') }}</p>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    function initReturnOptions() {
        if (!window.OtaMobileSplitCards) {
            return;
        }
        var root = document.querySelector('[data-return-options-root]');
        if (!root) return;
        var searchId = root.getAttribute('data-search-id') || '';
        var outboundKey = root.getAttribute('data-outbound-key') || '';
        var dataUrl = root.getAttribute('data-return-options-data-url') || '';
        var selectUrl = root.getAttribute('data-select-return-combo-url') || '';
        var list = root.querySelector('[data-return-options-list]');
        var summary = root.querySelector('[data-return-options-summary]');
        var loadMore = root.querySelector('[data-return-load-more]');
        var emptyMsg = root.querySelector('[data-return-empty-message]');
        var outboundSummary = root.querySelector('[data-outbound-summary]');
        var outboundSummaryBody = root.querySelector('[data-outbound-summary-body]');
        var csrf = document.querySelector('meta[name="csrf-token"]');
        var page = 1;
        var loading = false;
        var hasMore = true;

        function returnCardHtml(option) {
            return OtaMobileSplitCards.buildSplitLegCard(option, {
                modifier: 'ota-mobile-result-card--returntrip',
                legLabel: @json(__('Return')),
                ctaMode: 'form',
                formAction: selectUrl,
                csrf: csrf ? csrf.getAttribute('content') : '',
                searchId: searchId,
                outboundKey: outboundKey,
                ctaLabel: @json(__('Select return')),
                priceAmount: option.total_amount,
                priceNote: @json(__('total return fare')),
            });
        }

        function fetchPage(reset) {
            if (loading || !hasMore) return;
            loading = true;
            if (loadMore) loadMore.disabled = true;
            var targetPage = reset ? 1 : page;
            fetch(dataUrl + '?search_id=' + encodeURIComponent(searchId) + '&outbound_key=' + encodeURIComponent(outboundKey) + '&page=' + targetPage, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) { return res.json(); }).then(function (json) {
                if (reset && list) list.innerHTML = '';
                if (json.outbound_journey && outboundSummary && outboundSummaryBody) {
                    outboundSummary.hidden = false;
                    outboundSummaryBody.innerHTML = OtaMobileSplitCards.buildOutboundSummaryHtml(
                        json.outbound_journey,
                        json.outbound_meta || {}
                    );
                }
                var options = json.return_options || [];
                if (!options.length && targetPage === 1) {
                    if (emptyMsg) emptyMsg.hidden = false;
                    hasMore = false;
                    return;
                }
                if (list) list.insertAdjacentHTML('beforeend', options.map(returnCardHtml).join(''));
                if (window.OtaMobileSplitCards && window.OtaMobileSplitCards.bindSplitCardDetails) {
                    OtaMobileSplitCards.bindSplitCardDetails(list);
                }
                if (summary) summary.textContent = options.length + ' ' + @json(__('return options'));
                hasMore = !!json.has_more;
                if (hasMore) page = targetPage + 1;
            }).finally(function () {
                loading = false;
                if (loadMore) loadMore.disabled = !hasMore;
            });
        }

        if (loadMore) loadMore.addEventListener('click', function () { fetchPage(false); });
        fetchPage(true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initReturnOptions);
    } else {
        initReturnOptions();
    }
})();
</script>
@endpush
