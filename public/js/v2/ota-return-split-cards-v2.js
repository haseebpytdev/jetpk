/*
V2 cloned from v1.
Do not edit v1 for v2 redesign work.
*/

/**
 * OTA-RETURN-FLIGHT-UI-PARITY-SEPARATE-SELECTION-1 — split-flow cards matching one-way results layout.
 */
(function (global) {
    'use strict';

    var config = { airlineLogoCdnTemplate: '' };

    function esc(s) {
        if (s === null || s === undefined) {
            return '';
        }

        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function init(options) {
        options = options || {};
        config.airlineLogoCdnTemplate = String(options.airlineLogoCdnTemplate || '');
    }

    function builders() {
        return global.OtaFlightDetailBuilders || null;
    }

    function airlineLogoUrlForCode(code, offer) {
        var normalized = (code || '').trim().toUpperCase();
        if (!normalized) {
            return offer && offer.airline_logo_url ? offer.airline_logo_url : '';
        }
        if (offer && offer.airline_logo_url && String(offer.airline_code || '').toUpperCase() === normalized) {
            return offer.airline_logo_url;
        }
        if (config.airlineLogoCdnTemplate && /^[A-Z0-9]{2}$/.test(normalized)) {
            return String(config.airlineLogoCdnTemplate).replace('{CODE}', normalized);
        }

        return '';
    }

    function buildStopsLabelHtml(stopsLabel, layoverSummary) {
        var lines = Array.isArray(layoverSummary) ? layoverSummary.filter(Boolean) : [];
        if (!lines.length) {
            return '<span class="label label-default ota-result-stops">' + esc(stopsLabel) + '</span>';
        }
        var tooltipLines = lines.map(function (line) {
            return '<span class="flight-stop-tooltip__line">' + esc(String(line)) + '</span>';
        }).join('');

        return '<span class="label label-default ota-result-stops flight-stop-tooltip-wrap" tabindex="0" aria-label="' + esc(lines.join('; ')) + '">' +
            esc(stopsLabel) +
            '<span class="flight-stop-tooltip" role="tooltip">' + tooltipLines + '</span>' +
            '</span>';
    }

    function journeyCarrierInfo(journey, offer) {
        var segs = journey.segments_display || [];
        var first = segs[0] || {};
        var code = String(first.airline_code || offer && offer.airline_code || '').trim().toUpperCase();
        var name = String(first.airline_name || offer && offer.airline_name || '').trim();
        var codes = {};
        segs.forEach(function (seg) {
            var c = String(seg.airline_code || '').trim().toUpperCase();
            if (c) {
                codes[c] = true;
            }
        });
        var codeList = Object.keys(codes);
        var flightNums = [];
        segs.forEach(function (seg) {
            var fn = String(seg.flight_number || '').trim();
            if (fn && flightNums.indexOf(fn) === -1) {
                flightNums.push(fn);
            }
        });

        return {
            code: code,
            name: name,
            flightNumbers: flightNums.join(', '),
            mixedWithin: codeList.length > 1,
            carrierChain: codeList.join(' + '),
            logoUrl: airlineLogoUrlForCode(code, offer),
        };
    }

    function formatCardButtonRs(amount) {
        if (amount === null || amount === undefined || !isFinite(Number(amount)) || Number(amount) <= 0) {
            return 'Fare unavailable';
        }

        return 'Rs. ' + Math.round(Number(amount)).toLocaleString('en-US');
    }

    function formatPkr(amount, opts) {
        opts = opts || {};
        if (amount === null || amount === undefined || !isFinite(Number(amount)) || Number(amount) <= 0) {
            return 'Fare unavailable';
        }
        var prefix = opts.fromPrefix ? 'From ' : '';

        return prefix + 'PKR ' + Math.round(Number(amount)).toLocaleString('en-US');
    }

    function cardDisplayAmount(option, mode, selectedFareKey, brandedState) {
        if (global.OtaBrandedFares && option) {
            var normalized = normalizeOptionForBrandedFares(option, mode);
            var price = global.OtaBrandedFares.cardDisplayPrice(normalized, brandedState || null, selectedFareKey);
            if (price != null && price > 0) {
                return price;
            }
        }
        if (mode === 'outbound') {
            return option.from_total_amount;
        }

        return option.total_amount;
    }

    function buildOneWayRouteHtml(journey, offer) {
        var b = builders();
        var depTime = journey.departure_time_display || '';
        var depDate = journey.departure_date_display || '';
        var depCode = journey.origin || '';
        var depCity = journey.origin_city || '';
        var arrTime = journey.arrival_time_display || '';
        var arrDate = journey.arrival_date_display || '';
        var arrCode = journey.destination || '';
        var arrCity = journey.destination_city || '';
        var arrOff = journey.arrival_day_offset
            ? '<span class="ota-arr-offset">' + esc(journey.arrival_day_offset) + '</span>'
            : '';
        var stopsLabel = journey.stops_display || 'Direct';
        var stopsLabelHtml = buildStopsLabelHtml(stopsLabel, journey.layover_summary);
        var cardDurLabel = esc(journey.duration_display || '');

        if (b && b.buildLegBlockHtml && b.buildCardRouteMidHtml) {
            return '<div class="ota-result-col-route ota-result-col-route--oneway">' +
                b.buildLegBlockHtml(depTime, depDate, depCode, depCity, 'dep', '') +
                b.buildCardRouteMidHtml(cardDurLabel, stopsLabelHtml) +
                b.buildLegBlockHtml(arrTime, arrDate, arrCode, arrCity, 'arr', arrOff) +
                '</div>';
        }

        return buildCompactRoundTripSegmentHtml(journey, offer, '', '');
    }

    function buildCompactRoundTripSegmentHtml(journey, offer, cabinLabel, baggageLabel) {
        var info = journeyCarrierInfo(journey, offer);
        var legLabel = esc((journey.label || '').trim() || (journey.type === 'return' ? 'Return' : 'Outbound'));
        var logoHtml = info.logoUrl
            ? '<div class="ota-result-round-segment__logo ota-airline-logo ota-airline-logo--img"><img src="' + esc(info.logoUrl) + '" alt="' + esc(info.name || info.code || 'Airline') + ' logo" loading="lazy"></div>'
            : '<div class="ota-result-round-segment__logo ota-airline-logo">' + esc(info.code || '—') + '</div>';
        var jDepTime = esc(journey.departure_time_display || '');
        var jDepDate = esc(journey.departure_date_display || '');
        var jDepCode = esc(journey.origin || '');
        var jArrTime = esc(journey.arrival_time_display || '');
        var jArrDate = esc(journey.arrival_date_display || '');
        var jArrCode = esc(journey.destination || '');
        var jArrOff = journey.arrival_day_offset
            ? '<span class="ota-result-round-segment__day-offset">' + esc(journey.arrival_day_offset) + '</span>'
            : '';
        var jDur = esc(journey.duration_display || '');
        var jStops = buildStopsLabelHtml(esc(journey.stops_display || ''), journey.layover_summary);
        if (jStops) {
            jStops = '<span class="ota-result-round-segment__stops">' + jStops + '</span>';
        }

        return '<div class="ota-result-round-segment" data-journey-type="' + esc(journey.type || '') + '">' +
            '<p class="ota-result-round-segment__label">' + legLabel + '</p>' +
            '<div class="ota-result-round-segment__brand">' + logoHtml +
            '<div class="ota-result-round-segment__carrier">' +
            '<span class="ota-result-round-segment__airline">' + esc(info.name || info.code || '') + '</span>' +
            '</div></div>' +
            '<div class="ota-result-round-segment__route">' +
            '<div class="ota-result-round-segment__point ota-result-round-segment__point--dep">' +
            '<span class="ota-result-round-segment__time">' + jDepTime + '</span>' +
            '<span class="ota-result-round-segment__code">' + jDepCode + '</span>' +
            '<span class="ota-result-round-segment__date">' + jDepDate + '</span>' +
            '</div>' +
            '<div class="ota-result-round-segment__mid">' +
            (jDur ? '<span class="ota-result-round-segment__dur">' + jDur + '</span>' : '') +
            '<span class="ota-result-round-segment__arrow" aria-hidden="true">→</span>' +
            jStops +
            '</div>' +
            '<div class="ota-result-round-segment__point ota-result-round-segment__point--arr">' +
            '<span class="ota-result-round-segment__time">' + jArrTime + '</span>' +
            '<span class="ota-result-round-segment__code">' + jArrCode + '</span>' +
            '<span class="ota-result-round-segment__date">' + jArrDate + jArrOff + '</span>' +
            '</div>' +
            '</div></div>';
    }

    function buildFlightDetailsPayload(option, journey, mode) {
        var routeLabel = '';
        if (journey.origin && journey.destination) {
            routeLabel = String(journey.origin) + ' → ' + String(journey.destination);
        }

        return {
            route_label: routeLabel,
            trip_type: 'round_trip',
            split_leg: mode,
            has_journey_grouping: false,
            journey_display: journey,
            journeys_display: journey && journey.origin ? [journey] : [],
            segments: Array.isArray(journey.segments_display) ? journey.segments_display : [],
            layovers_display: Array.isArray(journey.layovers_display) ? journey.layovers_display : [],
            connection_details_unavailable: !!journey.connection_details_unavailable,
            summary_origin: journey.origin || '',
            summary_destination: journey.destination || '',
            summary_origin_city: journey.origin_city || '',
            summary_destination_city: journey.destination_city || '',
            summary_dep_time: journey.departure_time_display || '',
            summary_dep_date: journey.departure_date_display || '',
            summary_arr_time: journey.arrival_time_display || '',
            summary_arr_date: journey.arrival_date_display || '',
            summary_arr_offset: journey.arrival_day_offset || null,
            summary_duration: journey.duration_display || '',
            summary_stops: journey.stops_display || '',
            airline_logo_url: option.airline_logo_url || '',
            airline_name: option.airline_name || '',
            airline_code: option.airline_code || '',
            cabin: option.cabin || option.fare_family || '',
            baggage_summary_display: option.baggage_summary_display || option.baggage || '',
            baggage_cabin_display: option.baggage_cabin_display || '',
            baggage_checked_display: option.baggage_checked_display || '',
            refundable: !!option.refundable,
        };
    }

    function normalizeOptionForBrandedFares(option, mode) {
        var offerId = mode === 'outbound'
            ? String(option.outbound_key || option.offer_id || '')
            : String(option.combo_id || option.offer_id || '');
        var route = '';
        var j = option.journey_display || {};
        if (j.origin && j.destination) {
            route = String(j.origin) + ' → ' + String(j.destination);
        }

        return Object.assign({}, option, {
            offer_id: offerId,
            route: route,
            displayed_price: mode === 'outbound' ? option.from_total_amount : option.total_amount,
            final_customer_price: mode === 'outbound' ? option.from_total_amount : option.total_amount,
            journeys_display: j && j.origin ? [Object.assign({}, j, { type: mode, label: mode === 'outbound' ? 'Outbound' : 'Return' })] : [],
        });
    }

    function buildSplitFlowProCard(option, mode, cardConfig, labels, brandedFaresHtml, selectedFareKey, brandedState) {
        cardConfig = cardConfig || {};
        labels = labels || {};
        mode = mode === 'return' ? 'return' : 'outbound';
        brandedFaresHtml = brandedFaresHtml || '';
        if (!option || !option.journey_display) {
            return '';
        }

        var b = builders();
        var journey = Object.assign({}, option.journey_display, {
            label: labels.legLabel || (mode === 'return' ? 'Return' : 'Outbound'),
            type: mode,
        });
        var offerId = mode === 'outbound'
            ? String(option.outbound_key || option.offer_id || '')
            : String(option.combo_id || option.offer_id || '');
        var providerCode = String(option.provider || '').toLowerCase();
        var passengerMix = option.passenger_mix_display || '';
        var displayAmount = cardDisplayAmount(option, mode, selectedFareKey, brandedState);
        var cardPrice = esc(formatCardButtonRs(displayAmount));
        var hasBrandedFares = brandedFaresHtml !== '';
        var brandedFaresOpenClass = hasBrandedFares && global.OtaBrandedFares && brandedState
            ? (global.OtaBrandedFares.isExpanded(offerId, brandedState) ? ' is-fare-options-open' : '')
            : '';
        var summaryA11yAttrs = hasBrandedFares
            ? ' data-flight-card-summary role="button" tabindex="0" aria-expanded="false" aria-label="Toggle fare options"'
            : ' data-flight-card-summary';
        var brandedFaresAttrs = hasBrandedFares ? ' data-has-branded-fares="1"' : '';

        var airlineDisplayName = (option.airline_name || '').trim();
        var airlineCodeLabel = (option.airline_code || '').trim();
        var logoHtml = option.airline_logo_url
            ? '<div class="ota-result-brand-logo ota-airline-logo ota-airline-logo--img"><img src="' + esc(option.airline_logo_url) + '" alt="' + esc(airlineDisplayName || 'Airline') + ' logo" loading="lazy"></div>'
            : '<div class="ota-result-brand-logo ota-airline-logo">' + esc(airlineCodeLabel || 'XX') + '</div>';
        var carrierHtml = b && b.buildStandardCardFaceCarrierHtml
            ? b.buildStandardCardFaceCarrierHtml(option)
            : ('<div class="ota-airline-name">' + esc(airlineDisplayName || airlineCodeLabel) + '</div>');

        var routeHtml = buildOneWayRouteHtml(journey, option);
        var flightDetailsPayload = esc(JSON.stringify(buildFlightDetailsPayload(option, journey, mode)));
        var flightDetailsBtn = '<button class="ota-flight-details-trigger ota-flight-detail-link" type="button" data-flight-details-open data-flight-details-payload="' + flightDetailsPayload + '">Flight details</button>';

        var priceNote = mode === 'outbound'
            ? esc(labels.fromNote || 'total return fare')
            : esc(labels.totalReturnFare || 'total return fare');
        var deltaHtml = option.fare_delta_display && mode === 'return'
            ? '<p class="small text-muted ota-return-split-card__delta">' + esc(option.fare_delta_display) + '</p>'
            : '';

        var ctaHtml = '';
        if (mode === 'outbound') {
            var ctaUrl = esc(option.return_options_url || '');
            ctaHtml = ctaUrl
                ? '<a class="btn btn-primary ota-select-primary ota-btn-book ota-flight-book-button ota-result-price-btn ota-return-split-card__cta" data-split-select-outbound data-return-options-url="' + ctaUrl + '" data-outbound-key="' + esc(option.outbound_key || '') + '" href="' + ctaUrl + '"><span class="ota-result-price-btn__amount" data-card-price>' + cardPrice + '</span></a>'
                : '';
        } else if (option.can_book !== false) {
            ctaHtml = '<form method="post" action="' + esc(cardConfig.selectUrl || '') + '" class="ota-return-split-card__form">' +
                '<input type="hidden" name="_token" value="' + esc(cardConfig.csrf || '') + '">' +
                '<input type="hidden" name="search_id" value="' + esc(cardConfig.searchId || '') + '">' +
                '<input type="hidden" name="combo_id" value="' + esc(option.combo_id || '') + '">' +
                '<input type="hidden" name="outbound_key" value="' + esc(cardConfig.outboundKey || '') + '">' +
                '<input type="hidden" name="outbound_fare_option_key" value="" data-split-outbound-fare-option-key>' +
                '<input type="hidden" name="fare_option_key" value="" data-split-fare-option-key>' +
                '<button type="submit" class="btn btn-primary ota-select-primary ota-btn-book ota-flight-book-button ota-result-price-btn ota-return-split-card__cta"' +
                (option.can_book === false ? ' disabled' : '') + '><span class="ota-result-price-btn__amount" data-card-price>' + cardPrice + '</span></button>' +
                '</form>';
        } else {
            ctaHtml = '<button type="button" class="btn btn-default ota-btn-book ota-flight-book-button ota-result-price-btn" disabled><span class="ota-result-price-btn__amount">Fare unavailable</span></button>';
        }

        var sourceBadgeHtml = b.buildFlightCardSourceBadgeHtml
            ? b.buildFlightCardSourceBadgeHtml(option)
            : '';

        var actionColumnHtml = '<div class="ota-result-col-price">' +
            '<div class="ota-result-actions-book">' + ctaHtml + '</div>' +
            deltaHtml +
            '<div class="ota-price-sub ota-result-passenger-mix">' + esc(passengerMix) + '</div>' +
            (mode === 'outbound' && priceNote ? '<div class="ota-price-sub">' + priceNote + '</div>' : '') +
            '<div class="ota-result-action-meta">' + flightDetailsBtn + '</div>' +
            sourceBadgeHtml +
            '</div>';

        var dataAttrs = ' data-split-flow-card="' + mode + '" data-split-leg="' + mode + '"' +
            (mode === 'outbound'
                ? ' data-outbound-key="' + esc(option.outbound_key || '') + '"'
                : ' data-combo-id="' + esc(option.combo_id || '') + '"');

        return '' +
            '<article class="ota-result-pro-card ota-result-card-v3 ota-return-split-pro-card ota-return-split-pro-card--' + mode + brandedFaresOpenClass + '"' +
            brandedFaresAttrs + ' data-flight-card' + dataAttrs +
            ' data-offer-id="' + esc(offerId) + '" data-provider="' + esc(providerCode) + '">' +
            '<div class="ota-result-card-main"' + summaryA11yAttrs + '>' +
            '<div class="ota-result-col-brand">' + logoHtml + carrierHtml + '</div>' +
            routeHtml +
            actionColumnHtml +
            '</div>' +
            brandedFaresHtml +
            '</article>';
    }

    function buildOutboundSplitCardHtml(option, labels, brandedFaresHtml, selectedFareKey, brandedState) {
        return buildSplitFlowProCard(option, 'outbound', {}, labels || {}, brandedFaresHtml || '', selectedFareKey, brandedState);
    }

    function buildReturnSplitCardHtml(option, formConfig, labels, brandedFaresHtml, selectedFareKey, brandedState) {
        return buildSplitFlowProCard(option, 'return', formConfig || {}, labels || {}, brandedFaresHtml || '', selectedFareKey, brandedState);
    }

    function buildOutboundSummaryHtml(journey, optionMeta, selectedFareKey) {
        if (!journey) {
            return '';
        }
        var meta = Object.assign({}, optionMeta || {}, { journey_display: journey });
        var priceHtml = '';
        if (optionMeta) {
            var amount = cardDisplayAmount(optionMeta, 'outbound', selectedFareKey);
            if (amount != null && amount > 0) {
                priceHtml = '<p class="ota-return-split-selected-outbound__price">' + esc(formatPkr(amount)) + '</p>';
            } else if (optionMeta.from_total_display) {
                priceHtml = '<p class="ota-return-split-selected-outbound__price">' + esc(optionMeta.from_total_display) + '</p>';
            }
        }

        return '<div class="ota-return-split-selected-outbound__card">' +
            buildCompactRoundTripSegmentHtml(
                Object.assign({}, journey, { label: 'Outbound', type: 'outbound' }),
                meta,
                (optionMeta && (optionMeta.cabin || optionMeta.fare_family)) || '',
                (optionMeta && optionMeta.baggage_summary_display) || ''
            ) +
            priceHtml +
            '</div>';
    }

    function prepareReturnSplitCheckoutForm(form, returnFareOptionKey, outboundFareOptionKey) {
        if (!form) {
            return;
        }
        var fareInput = form.querySelector('[data-split-fare-option-key]');
        if (fareInput) {
            fareInput.value = returnFareOptionKey || '';
        }
        var outboundFareInput = form.querySelector('[data-split-outbound-fare-option-key]');
        if (outboundFareInput) {
            outboundFareInput.value = outboundFareOptionKey || '';
        }
    }

    function bindSplitCardInteractions(listEl, callbacks) {
        callbacks = callbacks || {};
        var brandedState = callbacks.brandedState || null;
        if (!listEl) {
            return;
        }
        if (global.OtaFlightDetailBuilders) {
            global.OtaFlightDetailBuilders.bindDetailsToggles(listEl);
            global.OtaFlightDetailBuilders.bindFlightDetailTabs(listEl);
        }
        if (global.OtaFareBreakdownModal) {
            global.OtaFareBreakdownModal.bindLinks(listEl);
        }
        if (global.OtaFlightDetailsModal) {
            global.OtaFlightDetailsModal.bindLinks(listEl);
        }
        if (callbacks.onOutboundSelect && listEl.getAttribute('data-bound-split-outbound-select') !== '1') {
            listEl.setAttribute('data-bound-split-outbound-select', '1');
            listEl.addEventListener('click', function (e) {
                var link = e.target.closest('[data-split-select-outbound]');
                if (!link || !listEl.contains(link)) {
                    return;
                }
                if (global.OtaBrandedFares && global.OtaBrandedFares.handleOutboundSelectClick) {
                    var handled = global.OtaBrandedFares.handleOutboundSelectClick(e, link, brandedState, callbacks);
                    if (handled === false) {
                        return;
                    }
                }
                if (typeof callbacks.onOutboundSelect === 'function') {
                    e.preventDefault();
                    e.stopPropagation();
                    callbacks.onOutboundSelect(link, e);
                }
            });
        }
        if (listEl.getAttribute('data-bound-split-return-submit') !== '1') {
            listEl.setAttribute('data-bound-split-return-submit', '1');
            listEl.addEventListener('submit', function (e) {
                var form = e.target;
                if (!form || !form.matches('.ota-return-split-card__form') || !listEl.contains(form)) {
                    return;
                }
                var card = form.closest('[data-flight-card]');
                var oid = card ? card.getAttribute('data-offer-id') : '';
                var fareInput = form.querySelector('[data-split-fare-option-key]');
                var outboundFareKey = '';
                if (typeof callbacks.readOutboundFareOptionKey === 'function') {
                    outboundFareKey = callbacks.readOutboundFareOptionKey() || '';
                }
                prepareReturnSplitCheckoutForm(
                    form,
                    global.OtaBrandedFares && global.OtaBrandedFares.getSelectedFareKey
                        ? (global.OtaBrandedFares.getSelectedFareKey(oid, brandedState) || '')
                        : (fareInput ? fareInput.value : ''),
                    outboundFareKey,
                );
                if (typeof callbacks.onReturnFormSubmit === 'function') {
                    callbacks.onReturnFormSubmit(form, card, e);
                }
            });
        }
    }

    function storageKey(searchId) {
        return 'ota_return_split_outbound_' + String(searchId || '');
    }

    function saveOutboundSelection(searchId, payload) {
        try {
            sessionStorage.setItem(storageKey(searchId), JSON.stringify(payload || {}));
        } catch (err) {
            /* ignore storage errors */
        }
    }

    function readOutboundSelection(searchId) {
        try {
            var raw = sessionStorage.getItem(storageKey(searchId));
            return raw ? JSON.parse(raw) : null;
        } catch (err) {
            return null;
        }
    }

    global.OtaReturnSplitCards = {
        init: init,
        esc: esc,
        buildStopsLabelHtml: buildStopsLabelHtml,
        journeyCarrierInfo: journeyCarrierInfo,
        buildCompactRoundTripSegmentHtml: buildCompactRoundTripSegmentHtml,
        buildOneWayRouteHtml: buildOneWayRouteHtml,
        buildSplitFlowProCard: buildSplitFlowProCard,
        buildOutboundSplitCardHtml: buildOutboundSplitCardHtml,
        buildReturnSplitCardHtml: buildReturnSplitCardHtml,
        buildOutboundSummaryHtml: buildOutboundSummaryHtml,
        normalizeOptionForBrandedFares: normalizeOptionForBrandedFares,
        bindSplitCardInteractions: bindSplitCardInteractions,
        prepareReturnSplitCheckoutForm: prepareReturnSplitCheckoutForm,
        saveOutboundSelection: saveOutboundSelection,
        readOutboundSelection: readOutboundSelection,
        formatPkr: formatPkr,
        formatCardButtonRs: formatCardButtonRs,
        cardDisplayAmount: cardDisplayAmount,
    };
}(typeof window !== 'undefined' ? window : this));
