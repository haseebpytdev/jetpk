/**
 * RETURN-SPLIT-SELECT-R3 — shared flight detail HTML builders (desktop results + split flow).
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

    function journeyCarrierInfo(journey, offer) {
        if (global.OtaReturnSplitCards && global.OtaReturnSplitCards.journeyCarrierInfo) {
            return global.OtaReturnSplitCards.journeyCarrierInfo(journey, offer);
        }

        return { code: '', name: '', flightNumbers: '', mixedWithin: false, carrierChain: '', logoUrl: '' };
    }

    function buildStopsLabelHtml(stopsLabel, layoverSummary) {
        if (global.OtaReturnSplitCards && global.OtaReturnSplitCards.buildStopsLabelHtml) {
            return global.OtaReturnSplitCards.buildStopsLabelHtml(stopsLabel, layoverSummary);
        }

        return '<span class="label label-default ota-result-stops">' + esc(stopsLabel) + '</span>';
    }

    function buildLegBlockHtml(time, date, code, city, align, dayOffsetHtml) {
        var cityLine = city
            ? '<span class="ota-result-leg__city">' + esc(code) + ' • ' + esc(city) + '</span>'
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

    function buildStandardCardFaceCarrierHtml(offer) {
        var airlineDisplayName = String(offer.airline_name || offer.primary_display_carrier_name || '').trim();
        var airlineCodeLabel = String(offer.airline_code || offer.primary_display_carrier || '').trim();
        var carrierChain = String(offer.marketing_carrier_chain_display || '').trim();
        if (offer.mixed_carrier && carrierChain.indexOf('+') !== -1) {
            return '<div class="ota-result-carrier-face">' +
                '<div class="ota-result-carrier-face__names">' + esc(getShortAirlineName(airlineDisplayName, airlineCodeLabel) || airlineDisplayName || airlineCodeLabel) + '</div>' +
                '<div class="ota-result-carrier-face__chain">' + esc(carrierChain) + '</div>' +
                '</div>';
        }
        var singleName = getShortAirlineName(airlineDisplayName, airlineCodeLabel) || airlineDisplayName || airlineCodeLabel || '';

        return '<div class="ota-airline-name">' + esc(singleName) + '</div>';
    }

    function buildJourneyScheduleRow(j, options) {
        options = options || {};
        var jDepTime = esc(j.departure_time_display || '');
        var jDepDate = esc(j.departure_date_display || '');
        var jDepCode = esc(j.origin || '');
        var jDepCity = esc(j.origin_city || '');
        var jArrTime = esc(j.arrival_time_display || '');
        var jArrDate = esc(j.arrival_date_display || '');
        var jArrCode = esc(j.destination || '');
        var jArrCity = esc(j.destination_city || '');
        var jArrOff = j.arrival_day_offset
            ? '<span class="label label-default ota-arr-offset">' + esc(j.arrival_day_offset) + '</span>'
            : '';
        var jDur = esc(j.duration_display || '');
        var jStops = buildStopsLabelHtml(esc(j.stops_display || ''), j.layover_summary);
        var legLabel = (j.label || '').trim();
        if (!legLabel && options.isMultiCity && j.origin && j.destination) {
            legLabel = 'Leg: ' + String(j.origin).toUpperCase() + ' \u2192 ' + String(j.destination).toUpperCase();
        }
        var legLabelHtml = legLabel
            ? '<p class="ota-flight-detail-section__title ota-result-round-leg__label">' + esc(legLabel) + '</p>'
            : '';

        return '<div class="ota-result-round-leg" style="margin-bottom:0.65rem">' +
            legLabelHtml +
            '<div class="row ota-result-schedule ota-result-schedule--compact">' +
            '<div class="col-xs-4"><div class="ota-result-leg">' +
            '<div class="ota-time-lg">' + jDepTime + '</div>' +
            '<div class="ota-result-leg__date">' + jDepDate + '</div>' +
            '<div class="ota-result-leg__code">' + jDepCode + '</div>' +
            (jDepCity ? '<div class="ota-result-leg__city small text-muted">' + jDepCity + '</div>' : '') +
            '</div></div>' +
            '<div class="col-xs-4 text-center ota-result-mid"><div class="ota-dur-line">' + jDur + '</div><div class="ota-dur-bar"></div>' + jStops + '</div>' +
            '<div class="col-xs-4 text-right"><div class="ota-result-leg ota-result-leg--right">' +
            '<div class="ota-time-lg">' + jArrTime + '</div>' +
            '<div class="ota-result-leg__date">' + jArrDate + ' ' + jArrOff + '</div>' +
            '<div class="ota-result-leg__code">' + jArrCode + '</div>' +
            (jArrCity ? '<div class="ota-result-leg__city small text-muted">' + jArrCity + '</div>' : '') +
            '</div></div>' +
            '</div></div>';
    }

    function flightDetailTabKey(journey, index) {
        var type = String(journey.type || '').toLowerCase();
        if (type === 'outbound' || type === 'return') {
            return type;
        }

        return 'leg-' + (index + 1);
    }

    function flightDetailTabLabel(journey, index, tripType) {
        var label = String(journey.label || '').trim();
        if (label) {
            return label;
        }
        if (tripType === 'round_trip') {
            return index === 0 ? 'Outbound' : 'Return';
        }

        return 'Leg ' + (index + 1);
    }

    function buildJourneyDetailHeaderHtml(journey, offer) {
        var info = journeyCarrierInfo(journey, offer);
        var title = esc((journey.label || '').trim() || (journey.type === 'return' ? 'Return' : 'Outbound'));
        var routeLine = esc(journey.origin || '') + ' \u2192 ' + esc(journey.destination || '');
        var logoHtml = info.logoUrl
            ? '<div class="ota-flight-detail-journey-header__logo ota-airline-logo ota-airline-logo--img"><img src="' + esc(info.logoUrl) + '" alt="' + esc(info.name || info.code || 'Airline') + ' logo" loading="lazy"></div>'
            : '<div class="ota-flight-detail-journey-header__logo ota-airline-logo">' + esc(info.code || '\u2014') + '</div>';
        var summaryBits = [];
        if (journey.duration_display) {
            summaryBits.push('<span class="ota-flight-detail-journey-header__chip">' + esc(journey.duration_display) + '</span>');
        }
        if (journey.stops_display) {
            summaryBits.push('<span class="ota-flight-detail-journey-header__chip">' + esc(journey.stops_display) + '</span>');
        }
        if (info.mixedWithin) {
            summaryBits.push('<span class="ota-flight-detail-journey-header__chip ota-flight-detail-journey-header__chip--mixed">Mixed</span>');
            if (info.carrierChain) {
                var shortChain = uniqueNonEmptyOrdered((journey.segments_display || []).map(function (seg) {
                    return getShortAirlineName(seg.airline_name, seg.airline_code);
                })).join(' + ');
                summaryBits.push('<span class="ota-flight-detail-journey-header__chip ota-flight-detail-journey-header__chip--muted">' + esc(shortChain || info.carrierChain) + '</span>');
            }
        } else if (info.name || info.code) {
            summaryBits.push('<span class="ota-flight-detail-journey-header__chip ota-flight-detail-journey-header__chip--airline">' + esc(getShortAirlineName(info.name, info.code) || info.name || info.code) + '</span>');
        }

        return '<header class="ota-flight-detail-journey-header">' +
            logoHtml +
            '<div class="ota-flight-detail-journey-header__main">' +
            '<h4 class="ota-flight-detail-journey-header__title">' + title + '</h4>' +
            '<p class="ota-flight-detail-journey-header__route">' + routeLine + '</p>' +
            (summaryBits.length ? '<div class="ota-flight-detail-journey-header__summary">' + summaryBits.join('') + '</div>' : '') +
            '</div></header>';
    }

    function buildJourneyRouteSummaryHtml(journey) {
        var jDepCode = esc(journey.origin || '');
        var jDepCity = esc(journey.origin_city || '');
        var jArrCode = esc(journey.destination || '');
        var jArrCity = esc(journey.destination_city || '');
        var jDepTime = esc(journey.departure_time_display || '');
        var jDepDate = esc(journey.departure_date_display || '');
        var jArrTime = esc(journey.arrival_time_display || '');
        var jArrDate = esc(journey.arrival_date_display || '');
        var jDur = esc(journey.duration_display || '');
        var jStops = esc(journey.stops_display || '');
        var jArrOff = journey.arrival_day_offset
            ? '<span class="ota-flight-detail-day-offset">' + esc(journey.arrival_day_offset) + '</span>'
            : '';

        return '<div class="ota-flight-detail-journey-route">' +
            '<div class="ota-flight-detail-journey-route__point ota-flight-detail-journey-route__point--dep">' +
            '<span class="ota-flight-detail-journey-route__time">' + jDepTime + '</span>' +
            '<span class="ota-flight-detail-journey-route__code">' + jDepCode + '</span>' +
            (jDepCity ? '<span class="ota-flight-detail-journey-route__city">' + jDepCity + '</span>' : '') +
            '<span class="ota-flight-detail-journey-route__date">' + jDepDate + '</span>' +
            '</div>' +
            '<div class="ota-flight-detail-journey-route__mid">' +
            (jDur ? '<span class="ota-flight-detail-journey-route__dur">' + jDur + '</span>' : '') +
            (jStops ? '<span class="ota-flight-detail-journey-route__stops">' + jStops + '</span>' : '') +
            '<span class="ota-flight-detail-journey-route__line" aria-hidden="true"></span>' +
            '</div>' +
            '<div class="ota-flight-detail-journey-route__point ota-flight-detail-journey-route__point--arr">' +
            '<span class="ota-flight-detail-journey-route__time">' + jArrTime + '</span>' +
            '<span class="ota-flight-detail-journey-route__code">' + jArrCode + '</span>' +
            (jArrCity ? '<span class="ota-flight-detail-journey-route__city">' + jArrCity + '</span>' : '') +
            '<span class="ota-flight-detail-journey-route__date-row">' +
            '<span class="ota-flight-detail-journey-route__date">' + jArrDate + '</span>' + jArrOff +
            '</span></div></div>';
    }

    function buildSegmentDetailCardsHtml(segs, layoversDisplay, connectionUnavailable) {
        var layoversByIndex = {};
        (layoversDisplay || []).forEach(function (lay) {
            if (lay && lay.after_segment_index != null) {
                layoversByIndex[lay.after_segment_index] = lay;
            }
        });
        if (!segs || segs.length === 0) {
            return '';
        }
        var html = '<div class="ota-flight-detail-segments" role="list">';
        if (connectionUnavailable) {
            html += '<p class="ota-flight-detail-connection-msg" role="status">Connection details unavailable</p>';
        }
        segs.forEach(function (seg, idx) {
            var segNum = seg.segment_number || (idx + 1);
            var airlineLine = '';
            if (seg.airline_name) {
                airlineLine = esc(seg.airline_name);
                if (seg.airline_code && String(seg.airline_name).toUpperCase().indexOf(String(seg.airline_code).toUpperCase()) === -1) {
                    airlineLine += ' <span class="ota-flight-detail-segment-card__airline-code">(' + esc(seg.airline_code) + ')</span>';
                }
            } else if (seg.airline_code) {
                airlineLine = esc(seg.airline_code);
            }
            var fnLabel = seg.flight_number ? esc(seg.flight_number) : '';
            var depCity = seg.origin_city ? '<span class="ota-flight-detail-segment-card__city">' + esc(seg.origin_city) + '</span>' : '';
            var arrCity = seg.destination_city ? '<span class="ota-flight-detail-segment-card__city">' + esc(seg.destination_city) + '</span>' : '';
            var durLabel = seg.duration_display ? esc(seg.duration_display) : '';
            var metaItems = [];
            if (seg.cabin_display) {
                metaItems.push('<span class="ota-flight-detail-segment-card__meta-item">Cabin ' + esc(seg.cabin_display) + '</span>');
            }
            if (seg.aircraft_display) {
                metaItems.push('<span class="ota-flight-detail-segment-card__meta-item">Aircraft ' + esc(seg.aircraft_display) + '</span>');
            }
            if (seg.operating_airline_name || seg.operating_airline_code) {
                metaItems.push('<span class="ota-flight-detail-segment-card__meta-item">Operated by ' + esc([seg.operating_airline_name, seg.operating_airline_code].filter(Boolean).join(' ')) + '</span>');
            }
            html += '<article class="ota-flight-detail-segment-card" role="listitem">' +
                '<div class="ota-flight-detail-segment-card__head">' +
                '<span class="ota-flight-detail-segment-card__badge">Segment ' + segNum + '</span>' +
                '<div class="ota-flight-detail-segment-card__flight">' +
                (airlineLine ? '<span class="ota-flight-detail-segment-card__airline">' + airlineLine + '</span>' : '') +
                (fnLabel ? '<span class="ota-flight-detail-segment-card__fn">' + fnLabel + '</span>' : '') +
                '</div></div>' +
                '<div class="ota-flight-detail-segment-card__body">' +
                '<div class="ota-flight-detail-segment-card__point ota-flight-detail-segment-card__point--dep">' +
                '<span class="ota-flight-detail-segment-card__time">' + esc(seg.departure_time_display || '') + '</span>' +
                '<span class="ota-flight-detail-segment-card__code">' + esc(seg.origin || '') + '</span>' +
                depCity +
                (seg.departure_date_display ? '<span class="ota-flight-detail-segment-card__date">' + esc(seg.departure_date_display) + '</span>' : '') +
                '</div>' +
                '<div class="ota-flight-detail-segment-card__track">' +
                (durLabel ? '<span class="ota-flight-detail-segment-card__duration">' + durLabel + '</span>' : '') +
                '<span class="ota-flight-detail-segment-card__line" aria-hidden="true"></span>' +
                '</div>' +
                '<div class="ota-flight-detail-segment-card__point ota-flight-detail-segment-card__point--arr">' +
                '<span class="ota-flight-detail-segment-card__time">' + esc(seg.arrival_time_display || '') + '</span>' +
                '<span class="ota-flight-detail-segment-card__code">' + esc(seg.destination || '') + '</span>' +
                arrCity +
                (seg.arrival_date_display ? '<span class="ota-flight-detail-segment-card__date">' + esc(seg.arrival_date_display) + '</span>' : '') +
                '</div></div>' +
                (metaItems.length ? '<div class="ota-flight-detail-segment-card__meta">' + metaItems.join('') + '</div>' : '') +
                '</article>';
            var layRow = layoversByIndex[idx];
            if (layRow && layRow.label) {
                html += '<div class="ota-flight-detail-layover-divider" role="separator">' +
                    '<span class="ota-flight-detail-layover-divider__rule" aria-hidden="true"></span>' +
                    '<div class="ota-flight-detail-layover-divider__content">' +
                    '<span class="ota-flight-detail-layover-divider__label">Connection</span>' +
                    '<span class="ota-flight-detail-layover-divider__text">' + esc(layRow.label) + '</span>' +
                    '</div>' +
                    '<span class="ota-flight-detail-layover-divider__rule" aria-hidden="true"></span>' +
                    '</div>';
            }
        });
        html += '</div>';

        return html;
    }

    function buildJourneyDetailCardHtml(journey, offer) {
        return '<div class="ota-flight-detail-journey-card">' +
            buildJourneyDetailHeaderHtml(journey, offer) +
            buildJourneyRouteSummaryHtml(journey) +
            buildSegmentDetailCardsHtml(
                journey.segments_display || [],
                journey.layovers_display || [],
                journey.connection_details_unavailable
            ) +
            '</div>';
    }

    function buildFlightDetailJourneysHtml(offer, options) {
        options = options || {};
        var journeys = options.journeys || [];
        var tripType = options.tripType || 'one_way';
        var detailsId = options.detailsId || '';
        var hasGrouping = !!options.hasJourneyGrouping;
        var useRoundTripTabs = !!options.useRoundTripTabs;
        var useMultiCityTabs = !!options.useMultiCityTabs;
        var segs = options.fallbackSegments || [];
        var fallbackLayovers = options.fallbackLayovers || [];
        var fallbackConnectionUnavailable = !!options.fallbackConnectionUnavailable;
        var oneWayJourney = {
            segments_display: segs,
            layovers_display: fallbackLayovers,
            connection_details_unavailable: fallbackConnectionUnavailable,
            origin: options.summaryOrigin || '',
            destination: options.summaryDestination || '',
            origin_city: options.summaryOriginCity || '',
            destination_city: options.summaryDestinationCity || '',
            departure_time_display: options.summaryDepTime || '',
            departure_date_display: options.summaryDepDate || '',
            arrival_time_display: options.summaryArrTime || '',
            arrival_date_display: options.summaryArrDate || '',
            arrival_day_offset: options.summaryArrOffset || null,
            duration_display: options.summaryDuration || '',
            stops_display: options.summaryStops || '',
        };

        if (useRoundTripTabs || useMultiCityTabs) {
            var tabsHtml = '';
            var panelsHtml = '';
            journeys.forEach(function (j, idx) {
                var tabKey = flightDetailTabKey(j, idx);
                var tabLabel = flightDetailTabLabel(j, idx, tripType);
                var isFirst = idx === 0;
                var panelId = detailsId + '-panel-' + tabKey;
                tabsHtml += '<button type="button" class="ota-flight-detail-tab' + (isFirst ? ' is-active' : '') + '" role="tab" data-detail-tab="' + esc(tabKey) + '" id="' + esc(detailsId + '-tab-' + tabKey) + '" aria-controls="' + esc(panelId) + '" aria-selected="' + (isFirst ? 'true' : 'false') + '" tabindex="' + (isFirst ? '0' : '-1') + '">' + esc(tabLabel) + '</button>';
                panelsHtml += '<section class="ota-flight-detail-tab-panel" role="tabpanel" data-detail-tab-panel="' + esc(tabKey) + '" id="' + esc(panelId) + '" aria-labelledby="' + esc(detailsId + '-tab-' + tabKey) + '"' + (isFirst ? '' : ' hidden') + '>' +
                    buildJourneyDetailCardHtml(j, offer) +
                    '</section>';
            });

            return '<div class="ota-flight-detail-tabs-wrap" data-flight-detail-tabs>' +
                '<div class="ota-flight-detail-tabs" role="tablist" aria-label="Flight direction">' + tabsHtml + '</div>' +
                '<div class="ota-flight-detail-tab-panels">' + panelsHtml + '</div>' +
                '</div>';
        }

        if (hasGrouping) {
            return '<div class="ota-flight-detail-journeys-stack">' +
                journeys.map(function (j) {
                    return buildJourneyDetailCardHtml(j, offer);
                }).join('') +
                '</div>';
        }

        return buildJourneyDetailCardHtml(oneWayJourney, offer);
    }

    function detailsToggleLabel(isOpen) {
        return isOpen
            ? 'Hide details <span class="ota-btn-details-caret" aria-hidden="true">▲</span>'
            : 'Flight details <span class="ota-btn-details-caret" aria-hidden="true">▼</span>';
    }

    function toggleOfferDetails(btn, block) {
        if (!btn || !block) {
            return;
        }
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

    function bindDetailsToggles(listEl) {
        if (!listEl) {
            return;
        }
        Array.prototype.forEach.call(listEl.querySelectorAll('.ota-btn-details[data-toggle-details]'), function (btn) {
            if (btn.getAttribute('data-bound') === '1') {
                return;
            }
            btn.setAttribute('data-bound', '1');
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var id = btn.getAttribute('data-toggle-details');
                var block = document.getElementById(id);
                toggleOfferDetails(btn, block);
            });
        });
    }

    function activateFlightDetailTab(tabsWrap, tabId) {
        if (!tabsWrap || !tabId) {
            return;
        }
        var tabs = tabsWrap.querySelectorAll('.ota-flight-detail-tab');
        var panels = tabsWrap.querySelectorAll('[data-detail-tab-panel]');
        Array.prototype.forEach.call(tabs, function (tab) {
            var active = tab.getAttribute('data-detail-tab') === tabId;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });
        Array.prototype.forEach.call(panels, function (panel) {
            var show = panel.getAttribute('data-detail-tab-panel') === tabId;
            if (show) {
                panel.removeAttribute('hidden');
                panel.hidden = false;
            } else {
                panel.setAttribute('hidden', 'hidden');
                panel.hidden = true;
            }
        });
    }

    function bindFlightDetailTabs(listEl) {
        if (!listEl || listEl.getAttribute('data-detail-tabs-bound') === '1') {
            return;
        }
        listEl.setAttribute('data-detail-tabs-bound', '1');
        listEl.addEventListener('click', function (e) {
            var tab = e.target.closest('.ota-flight-detail-tab');
            if (!tab || !listEl.contains(tab)) {
                return;
            }
            e.stopPropagation();
            var tabsWrap = tab.closest('[data-flight-detail-tabs]');
            if (!tabsWrap) {
                return;
            }
            activateFlightDetailTab(tabsWrap, tab.getAttribute('data-detail-tab'));
        });
        listEl.addEventListener('keydown', function (e) {
            var tab = e.target.closest('.ota-flight-detail-tab');
            if (!tab || !listEl.contains(tab) || (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' && e.key !== 'Home' && e.key !== 'End')) {
                return;
            }
            var tabsWrap = tab.closest('[data-flight-detail-tabs]');
            if (!tabsWrap) {
                return;
            }
            var tabs = Array.prototype.slice.call(tabsWrap.querySelectorAll('.ota-flight-detail-tab'));
            var idx = tabs.indexOf(tab);
            if (idx === -1) {
                return;
            }
            var nextIdx = idx;
            if (e.key === 'ArrowRight') {
                nextIdx = (idx + 1) % tabs.length;
            }
            if (e.key === 'ArrowLeft') {
                nextIdx = (idx - 1 + tabs.length) % tabs.length;
            }
            if (e.key === 'Home') {
                nextIdx = 0;
            }
            if (e.key === 'End') {
                nextIdx = tabs.length - 1;
            }
            e.preventDefault();
            var nextTab = tabs[nextIdx];
            if (!nextTab) {
                return;
            }
            activateFlightDetailTab(tabsWrap, nextTab.getAttribute('data-detail-tab'));
            nextTab.focus();
        });
    }

    function supplierSourceLabel(provider) {
        var p = String(provider || '').toLowerCase();
        if (p === 'sabre') return 'Sabre';
        if (p === 'iati') return 'IATI';
        if (p === 'pia_ndc') return 'PIA NDC';
        if (p === 'duffel') return 'Duffel';
        if (p === 'airline_direct') return 'Airline Direct';
        return 'Supplier';
    }

    function buildFlightCardSourceBadgeHtml(offer) {
        offer = offer || {};
        var label = offer.supplier_source_label
            ? String(offer.supplier_source_label)
            : supplierSourceLabel(offer.supplier_provider || offer.provider);
        return '<span class="flight-card-source-badge">Source: ' + esc(label) + '</span>';
    }

    global.OtaFlightDetailBuilders = {
        init: init,
        esc: esc,
        uniqueNonEmptyOrdered: uniqueNonEmptyOrdered,
        getShortAirlineName: getShortAirlineName,
        journeyCarrierInfo: journeyCarrierInfo,
        buildStopsLabelHtml: buildStopsLabelHtml,
        buildLegBlockHtml: buildLegBlockHtml,
        buildCardRouteMidHtml: buildCardRouteMidHtml,
        buildStandardCardFaceCarrierHtml: buildStandardCardFaceCarrierHtml,
        buildJourneyScheduleRow: buildJourneyScheduleRow,
        flightDetailTabKey: flightDetailTabKey,
        flightDetailTabLabel: flightDetailTabLabel,
        buildJourneyDetailHeaderHtml: buildJourneyDetailHeaderHtml,
        buildJourneyRouteSummaryHtml: buildJourneyRouteSummaryHtml,
        buildSegmentDetailCardsHtml: buildSegmentDetailCardsHtml,
        buildJourneyDetailCardHtml: buildJourneyDetailCardHtml,
        buildFlightDetailJourneysHtml: buildFlightDetailJourneysHtml,
        detailsToggleLabel: detailsToggleLabel,
        toggleOfferDetails: toggleOfferDetails,
        bindDetailsToggles: bindDetailsToggles,
        bindFlightDetailTabs: bindFlightDetailTabs,
        supplierSourceLabel: supplierSourceLabel,
        buildFlightCardSourceBadgeHtml: buildFlightCardSourceBadgeHtml,
    };
}(typeof window !== 'undefined' ? window : this));
