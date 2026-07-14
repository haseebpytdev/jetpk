<script>
(function () {
    var root = document.querySelector('[data-return-options-root]');
    if (!root) return;
    var isJetPkResults = document.body.classList.contains('jp-flights-results');
    var searchId = root.getAttribute('data-search-id') || '';
    var outboundKey = root.getAttribute('data-outbound-key') || '';
    var dataUrl = root.getAttribute('data-return-options-data-url') || '';
    var selectUrl = root.getAttribute('data-select-return-combo-url') || '';
    var list = root.querySelector('[data-return-options-list]');
    var summary = root.querySelector('[data-return-options-summary]');
    var loadMore = root.querySelector('[data-return-load-more]');
    var expired = root.querySelector('[data-return-expired-message]');
    var emptyMsg = root.querySelector('[data-return-empty-message]');
    var outboundSummaryBody = root.querySelector('[data-outbound-summary-body]');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var page = 1;
    var loading = false;
    var hasMore = true;
    var selectedReturnOfferId = '';
    var selectedReturnAmount = null;
    var outboundSelection = null;
    var splitCheckoutInFlight = false;
    var brandedState = window.OtaBrandedFares ? OtaBrandedFares.createState() : null;
    var splitLabels = {
        selectReturn: @json(__('Select return')),
        totalReturnFare: @json(__('Total return fare')),
        returnLabel: @json(__('Return')),
        legLabel: @json(__('Return')),
    };
    var currentCriteria = @json($criteria ?? []);

    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function formatCardButtonRs(amount) {
        if (amount === null || amount === undefined || !isFinite(Number(amount))) {
            return 'Fare unavailable';
        }
        return 'Rs. ' + Math.round(Number(amount)).toLocaleString('en-US');
    }

    if (window.OtaReturnSplitCards) {
        OtaReturnSplitCards.init({
            airlineLogoCdnTemplate: @json(config('ota.airline_logo_cdn_enabled', true) ? config('ota.airline_logo_cdn_template') : ''),
        });
    }
    if (window.OtaFlightDetailBuilders) {
        OtaFlightDetailBuilders.init({
            airlineLogoCdnTemplate: @json(config('ota.airline_logo_cdn_enabled', true) ? config('ota.airline_logo_cdn_template') : ''),
        });
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

    outboundSelection = window.OtaReturnSplitCards
        ? OtaReturnSplitCards.readOutboundSelection(searchId)
        : null;
    try {
        var urlParams = new URLSearchParams(window.location.search);
        var urlFareKey = urlParams.get('fare_option_key') || '';
        if (urlFareKey) {
            outboundSelection = Object.assign({}, outboundSelection || {}, {
                outbound_key: outboundKey,
                fare_option_key: urlFareKey,
            });
        }
    } catch (urlErr) {
        /* ignore URL parse errors */
    }

    function returnCardHtml(option) {
        if (!window.OtaReturnSplitCards || !brandedState) {
            return '';
        }
        var offer = OtaReturnSplitCards.normalizeOptionForBrandedFares(option, 'return');
        OtaBrandedFares.registerOffer(brandedState, offer);
        var selectedKey = brandedState.selectedFareOptionByOfferId[offer.offer_id] || '';
        var brandedHtml = OtaBrandedFares.buildPanelHtml(offer, brandedState);
        if (isJetPkResults && window.JetPkResultCards && typeof JetPkResultCards.buildBrandedFaresPanelHtml === 'function') {
            brandedHtml = JetPkResultCards.buildBrandedFaresPanelHtml(offer, {
                offersById: brandedState.offersById,
                selectedFareOptionByOfferId: brandedState.selectedFareOptionByOfferId,
                expandedBrandedFaresByOfferId: brandedState.expandedBrandedFaresByOfferId || {},
            }, { esc: esc });
        }
        if (isJetPkResults && window.JetPkResultCards && typeof JetPkResultCards.buildReturnSplitCard === 'function') {
            return JetPkResultCards.buildReturnSplitCard(option, {
                selectUrl: selectUrl,
                csrf: csrf ? csrf.getAttribute('content') : '',
                searchId: searchId,
                outboundKey: outboundKey,
            }, splitLabels, brandedHtml, selectedKey, brandedState, {
                esc: esc,
                formatCardButtonRs: formatCardButtonRs,
                currentCriteria: currentCriteria,
            });
        }
        return OtaReturnSplitCards.buildReturnSplitCardHtml(option, {
            selectUrl: selectUrl,
            csrf: csrf ? csrf.getAttribute('content') : '',
            searchId: searchId,
            outboundKey: outboundKey,
        }, splitLabels, brandedHtml, selectedKey, brandedState);
    }

    function syncSelectedReturnFromState() {
        selectedReturnOfferId = '';
        selectedReturnAmount = null;
        if (!brandedState || !list) return;
        Object.keys(brandedState.selectedFareOptionByOfferId).forEach(function (oid) {
            var key = brandedState.selectedFareOptionByOfferId[oid];
            if (!key) return;
            var offer = brandedState.offersById[oid];
            if (offer) {
                selectedReturnOfferId = oid;
                selectedReturnAmount = OtaBrandedFares.cardDisplayPrice(offer, brandedState);
            }
        });
    }

    function renderOutboundSummary(j, meta) {
        if (!outboundSummaryBody || !window.OtaReturnSplitCards) {
            return;
        }
        var fareKey = outboundSelection && outboundSelection.fare_option_key ? outboundSelection.fare_option_key : '';
        outboundSummaryBody.innerHTML = OtaReturnSplitCards.buildOutboundSummaryHtml(j, meta || {}, fareKey);
    }

    function proceedReturnCheckout(card, fareOptionKey, triggerEl) {
        if (splitCheckoutInFlight || !card) return false;
        var form = card.querySelector('.ota-return-split-card__form');
        if (!form) return false;
        var outboundFareKey = outboundSelection && (outboundSelection.fare_option_key || outboundSelection.fareOptionKey)
            ? (outboundSelection.fare_option_key || outboundSelection.fareOptionKey)
            : '';
        if (window.OtaReturnSplitCards && OtaReturnSplitCards.prepareReturnSplitCheckoutForm) {
            OtaReturnSplitCards.prepareReturnSplitCheckoutForm(form, fareOptionKey || '', outboundFareKey);
        }
        splitCheckoutInFlight = true;
        if (triggerEl && triggerEl.tagName === 'BUTTON') {
            triggerEl.disabled = true;
            var priceEl = triggerEl.querySelector('[data-card-price]');
            if (priceEl) {
                priceEl.textContent = 'Continuing...';
            }
        }
        form.submit();
        return true;
    }

    window.otaProceedBrandedFareCheckout = function (card, fareOptionKey, triggerEl) {
        if (!card || card.getAttribute('data-split-leg') !== 'return') return false;
        return proceedReturnCheckout(card, fareOptionKey, triggerEl);
    };

    function bindReturnListInteractions() {
        if (!list || !brandedState) return;
        OtaBrandedFares.bindAll(list, brandedState, {
            getList: function () { return list; },
            onBrandedFareSelect: function (oid, key, offer, card, trigger) {
                if (!card || card.getAttribute('data-split-leg') !== 'return') return;
                proceedReturnCheckout(card, key, trigger);
            },
            onReturnCheckout: function (card, fareKey, offer, trigger) {
                proceedReturnCheckout(card, fareKey, trigger);
            },
        });
        OtaReturnSplitCards.bindSplitCardInteractions(list, {
            brandedState: brandedState,
            readOutboundFareOptionKey: function () {
                if (!outboundSelection) return '';
                return outboundSelection.fare_option_key || outboundSelection.fareOptionKey || '';
            },
        });
    }

    function fetchPage(reset) {
        if (loading || !hasMore) return;
        loading = true;
        if (loadMore) loadMore.disabled = true;
        var targetPage = reset ? 1 : page;
        var qs = 'search_id=' + encodeURIComponent(searchId) +
            '&outbound_key=' + encodeURIComponent(outboundKey) +
            '&page=' + targetPage + '&per_page=12';
        fetch(dataUrl + '?' + qs, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) {
                if (res.status === 410) {
                    if (expired) expired.hidden = false;
                    hasMore = false;
                    throw new Error('expired');
                }
                if (!res.ok) {
                    if (summary) summary.textContent = @json(__('Unable to load return flight options. Please try another flight or refresh.'));
                    hasMore = false;
                    throw new Error('http_' + res.status);
                }
                return res.json();
            })
            .then(function (json) {
                if (!json || json.success === false) {
                    if (summary) {
                        summary.textContent = (json && json.message)
                            ? json.message
                            : @json(__('Unable to load return flight options. Please try another flight or refresh.'));
                    }
                    hasMore = false;
                    return;
                }
                if (reset && list) list.innerHTML = '';
                if (json.outbound_journey) {
                    var meta = json.outbound_meta || {};
                    if (outboundSelection && outboundSelection.outbound_key === outboundKey) {
                        meta = Object.assign({}, meta, outboundSelection);
                    }
                    renderOutboundSummary(json.outbound_journey, meta);
                }
                var options = json.return_options || [];
                if (!options.length && targetPage === 1) {
                    if (emptyMsg) emptyMsg.hidden = false;
                    if (summary) summary.textContent = '';
                    hasMore = false;
                    return;
                }
                if (emptyMsg) emptyMsg.hidden = true;
                if (list) list.insertAdjacentHTML('beforeend', options.map(returnCardHtml).join(''));
                bindReturnListInteractions();
                if (window.OtaBrandedFares) {
                    OtaBrandedFares.normalizeAllBrandedFaresPanels(list);
                }
                if (isJetPkResults && typeof window.bindBrandedFaresCarousel === 'function') {
                    window.bindBrandedFaresCarousel();
                }
                syncSelectedReturnFromState();
                if (summary) {
                    summary.textContent = @json(__('Showing')) + ' ' + options.length + ' ' + @json(__('of')) + ' ' + (json.total || 0) + ' ' + @json(__('return options'));
                }
                hasMore = !!json.has_more;
                if (hasMore) page = targetPage + 1;
            })
            .catch(function () {})
            .finally(function () {
                loading = false;
                if (loadMore) loadMore.disabled = !hasMore;
            });
    }

    if (loadMore) {
        loadMore.addEventListener('click', function () { fetchPage(false); });
    }
    fetchPage(true);
})();
</script>
