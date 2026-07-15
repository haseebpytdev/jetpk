/**
 * Tabbed fallback flight details for offers without branded fare options.
 */
(function (global) {
    'use strict';

    function esc(s) {
        if (s === null || s === undefined) {
            return '';
        }

        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function kvRow(label, value) {
        if (!value) {
            return '';
        }

        return '<div class="ota-flight-fallback-kv"><dt>' + esc(label) + '</dt><dd>' + esc(value) + '</dd></div>';
    }

    function sectionPanel(id, title, bodyHtml, active) {
        return '<div id="' + esc(id) + '" class="ota-flight-fallback-panel' + (active ? ' is-active' : '') + '" data-flight-fallback-panel="' + esc(id) + '" role="tabpanel"' + (active ? '' : ' hidden') + '>' +
            '<h5 class="ota-flight-fallback-panel__title">' + esc(title) + '</h5>' +
            bodyHtml +
            '</div>';
    }

    function buildOverviewHtml(overview, data) {
        var rows = [
            kvRow('Airline', overview.airline_name ? overview.airline_name + (overview.airline_code ? ' (' + overview.airline_code + ')' : '') : overview.airline_code),
            kvRow('Flight', overview.flight_number),
            kvRow('Operating carrier', overview.operating_airline_name || overview.operating_airline_code),
            kvRow('Route', overview.route),
            kvRow('From', overview.origin_city ? overview.origin + ' · ' + overview.origin_city : overview.origin),
            kvRow('To', overview.destination_city ? overview.destination + ' · ' + overview.destination_city : overview.destination),
            kvRow('Departure', [overview.departure_date, overview.departure_time].filter(Boolean).join(' ')),
            kvRow('Arrival', [overview.arrival_date, overview.arrival_time].filter(Boolean).join(' ')),
            kvRow('Duration', overview.duration),
            kvRow('Stops', overview.stops),
        ].join('');

        var itineraryHtml = '';
        if (global.OtaFlightDetailBuilders && global.OtaFlightDetailBuilders.buildFlightDetailJourneysHtml) {
            itineraryHtml = global.OtaFlightDetailBuilders.buildFlightDetailJourneysHtml({
                airline_code: data.airline_code || overview.airline_code || '',
                airline_logo_url: data.airline_logo_url || '',
                airline_name: data.airline_name || overview.airline_name || '',
            }, {
                journeys: Array.isArray(overview.journeys_display) ? overview.journeys_display : [],
                tripType: String(data.trip_type || 'one_way'),
                detailsId: 'ota-flight-fallback-itinerary',
                hasJourneyGrouping: Array.isArray(overview.journeys_display) && overview.journeys_display.length >= 2,
                fallbackSegments: Array.isArray(overview.segments) ? overview.segments : (Array.isArray(data.segments) ? data.segments : []),
                fallbackLayovers: Array.isArray(data.layovers_display) ? data.layovers_display : [],
                fallbackConnectionUnavailable: !!data.connection_details_unavailable,
                summaryOrigin: overview.origin || data.summary_origin || '',
                summaryDestination: overview.destination || data.summary_destination || '',
                summaryOriginCity: overview.origin_city || data.summary_origin_city || '',
                summaryDestinationCity: overview.destination_city || data.summary_destination_city || '',
                summaryDepTime: overview.departure_time || data.summary_dep_time || '',
                summaryDepDate: overview.departure_date || data.summary_dep_date || '',
                summaryArrTime: overview.arrival_time || data.summary_arr_time || '',
                summaryArrDate: overview.arrival_date || data.summary_arr_date || '',
                summaryArrOffset: data.summary_arr_offset || null,
                summaryDuration: overview.duration || data.summary_duration || '',
                summaryStops: overview.stops || data.summary_stops || '',
            });
        }

        return '<dl class="ota-flight-fallback-kv-list">' + rows + '</dl>' + (itineraryHtml || '');
    }

    function buildBaggageHtml(baggage) {
        if (!baggage || typeof baggage !== 'object') {
            return '<p class="ota-flight-details-modal__empty">Baggage allowance details are not available for this fare.</p>';
        }
        if (baggage.unavailable_message) {
            return '<p class="ota-flight-details-modal__empty">' + esc(baggage.unavailable_message) + '</p>';
        }
        var rows = [
            kvRow('Checked baggage', baggage.checked),
            kvRow('Cabin baggage', baggage.cabin),
            kvRow('Summary', baggage.summary),
        ].join('');
        if (Array.isArray(baggage.lines)) {
            baggage.lines.forEach(function (line) {
                rows += kvRow('Baggage', line);
            });
        }
        if (Array.isArray(baggage.passenger_baggage)) {
            baggage.passenger_baggage.forEach(function (row) {
                if (!row || typeof row !== 'object') {
                    return;
                }
                var label = String(row.passenger_type || 'passenger');
                var bits = [row.checked, row.cabin].filter(Boolean).join(' / ');
                rows += kvRow(label.charAt(0).toUpperCase() + label.slice(1), bits);
            });
        }
        if (Array.isArray(baggage.segment_baggage)) {
            baggage.segment_baggage.forEach(function (row, idx) {
                if (!row || typeof row !== 'object') {
                    return;
                }
                var bits = [row.checked, row.cabin].filter(Boolean).join(' / ');
                var route = String(row.route || '').trim();
                var label = route !== '' ? route : 'Segment ' + (Number(row.segment_index ?? idx) + 1);
                rows += kvRow(label, bits);
            });
        }

        return rows ? '<dl class="ota-flight-fallback-kv-list">' + rows + '</dl>' : '<p class="ota-flight-details-modal__empty">Baggage allowance details are not available for this fare.</p>';
    }

    function formatMoney(amount, currency) {
        if (amount === null || amount === undefined || !isFinite(Number(amount))) {
            return '';
        }
        var cur = String(currency || '').trim();
        var value = Number(amount);
        if (cur === 'PKR') {
            return 'PKR ' + Math.round(value).toLocaleString('en-US');
        }

        return (cur ? cur + ' ' : '') + value.toLocaleString('en-US', { maximumFractionDigits: 2 });
    }

    function buildFareBreakdownHtml(fare) {
        if (!fare || typeof fare !== 'object') {
            return '<p class="ota-flight-details-modal__empty">Fare breakdown is not available for this option.</p>';
        }
        var rows = [
            kvRow('Base fare', formatMoney(fare.base_fare, fare.currency)),
            kvRow('Taxes', formatMoney(fare.taxes, fare.currency)),
            kvRow('Supplier total', formatMoney(fare.supplier_total, fare.currency)),
            kvRow('Agency charge', formatMoney(fare.markup, fare.displayed_currency || 'PKR')),
            kvRow('Service fee', formatMoney(fare.service_fee, fare.displayed_currency || 'PKR')),
            kvRow('Grand total', fare.displayed_price != null ? formatMoney(fare.displayed_price, 'PKR') : formatMoney(fare.grand_total, fare.displayed_currency || fare.currency)),
            kvRow('Currency', fare.currency),
        ].join('');
        if (Array.isArray(fare.passenger_pricing)) {
            fare.passenger_pricing.forEach(function (row) {
                if (!row || typeof row !== 'object') {
                    return;
                }
                var type = String(row.type || row.passenger_type || 'adult');
                rows += kvRow(type.charAt(0).toUpperCase() + type.slice(1), formatMoney(row.total, row.currency || fare.currency));
            });
        }
        var note = fare.price_note ? '<p class="ota-flight-fallback-note">' + esc(fare.price_note) + '</p>' : '';

        return '<dl class="ota-flight-fallback-kv-list">' + rows + '</dl>' + note;
    }

    function buildFareRulesHtml(rules) {
        if (!rules || typeof rules !== 'object') {
            return '<p class="ota-flight-details-modal__empty">Fare rules are not available for this fare.</p>';
        }
        var rows = [
            kvRow('Refund', rules.refund_status),
            kvRow('Changes', rules.change_rule || (rules.change_allowed === true ? 'Changes permitted' : (rules.change_allowed === false ? 'Changes not permitted' : ''))),
            kvRow('Penalty', rules.penalty),
            kvRow('Fare basis', rules.fare_basis),
            kvRow('Booking class', rules.booking_class),
            kvRow('Cabin', rules.cabin),
            kvRow('Fare family', rules.fare_family),
        ].join('');
        if (Array.isArray(rules.rule_lines)) {
            rules.rule_lines.forEach(function (line) {
                rows += kvRow('Rule', line);
            });
        }

        return rows ? '<dl class="ota-flight-fallback-kv-list">' + rows + '</dl>' : '<p class="ota-flight-details-modal__empty">Fare rules are not available for this fare.</p>';
    }

    function buildSupplierHtml(supplier) {
        if (!supplier || typeof supplier !== 'object') {
            return '';
        }

        return '<dl class="ota-flight-fallback-kv-list">' +
            kvRow('Provider', supplier.provider_label || supplier.provider) +
            kvRow('Freshness', supplier.freshness_status) +
            kvRow('Last checked', supplier.last_checked_display) +
            kvRow('Revalidation', supplier.revalidation_required ? 'Required before booking' : 'Not required') +
            kvRow('Note', supplier.revalidation_note) +
            '</dl>';
    }

    function bindTabs(rootEl) {
        if (!rootEl) {
            return;
        }
        var tabs = rootEl.querySelectorAll('[data-flight-fallback-tab]');
        Array.prototype.forEach.call(tabs, function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-flight-fallback-tab');
                Array.prototype.forEach.call(tabs, function (btn) {
                    var active = btn === tab;
                    btn.classList.toggle('is-active', active);
                    btn.setAttribute('aria-selected', active ? 'true' : 'false');
                    btn.tabIndex = active ? 0 : -1;
                });
                Array.prototype.forEach.call(rootEl.querySelectorAll('[data-flight-fallback-panel]'), function (panel) {
                    var show = panel.getAttribute('data-flight-fallback-panel') === target;
                    panel.classList.toggle('is-active', show);
                    panel.hidden = !show;
                });
            });
        });
    }

    function buildFallbackDetailsHtml(data) {
        var details = data.fallback_details;
        if (!details || typeof details !== 'object') {
            return '';
        }

        var tabs = [];
        var panels = [];
        var tabDefs = [
            ['overview', 'Overview', buildOverviewHtml(details.overview || {}, data)],
            ['baggage', 'Baggage', buildBaggageHtml(details.baggage)],
            ['fare_breakdown', 'Fare Breakdown', buildFareBreakdownHtml(details.fare_breakdown)],
            ['fare_rules', 'Fare Rules', buildFareRulesHtml(details.fare_rules)],
            ['supplier', 'Supplier', buildSupplierHtml(details.supplier)],
        ];
        var first = true;
        tabDefs.forEach(function (def) {
            var key = def[0];
            var label = def[1];
            var body = def[2];
            if (!body || body.indexOf('ota-flight-details-modal__empty') !== -1 && body.indexOf('ota-flight-fallback-kv-list') === -1 && key !== 'overview') {
                if (key === 'supplier' && !body) {
                    return;
                }
                if (key !== 'overview' && key !== 'baggage' && key !== 'fare_breakdown' && key !== 'fare_rules') {
                    return;
                }
            }
            tabs.push('<button type="button" class="ota-flight-fallback-tab' + (first ? ' is-active' : '') + '" data-flight-fallback-tab="' + esc(key) + '" role="tab" aria-selected="' + (first ? 'true' : 'false') + '"' + (first ? '' : ' tabindex="-1"') + '>' + esc(label) + '</button>');
            panels.push(sectionPanel('ota-flight-fallback-panel-' + key, label, body, first));
            first = false;
        });

        if (!tabs.length) {
            return '';
        }

        return '<div class="ota-flight-fallback-shell" data-flight-fallback-shell>' +
            '<div class="ota-flight-fallback-tabs" role="tablist">' + tabs.join('') + '</div>' +
            '<div class="ota-flight-fallback-panels">' + panels.join('') + '</div>' +
            '</div>';
    }

    global.OtaFlightFallbackDetails = {
        buildHtml: buildFallbackDetailsHtml,
        bindTabs: bindTabs,
    };
}(typeof window !== 'undefined' ? window : this));
