/*
V2 cloned from v1.
Do not edit v1 for v2 redesign work.
*/

/**
 * FLIGHT-SEARCH-MODAL-UI-POLISH-1 — tabbed Fare Summary modal (desktop results + split flow).
 */
(function (global) {
    'use strict';

    var modal = null;
    var rowsEl = null;
    var baggageEl = null;
    var policyEl = null;
    var routeEl = null;
    var subtitleEl = null;
    var titleEl = null;
    var totalEl = null;
    var selectBtn = null;
    var tabsWrap = null;
    var initialized = false;
    var activeCard = null;
    var activeFareOptionKey = null;

    function esc(s) {
        if (s === null || s === undefined) {
            return '';
        }

        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function formatPkr(amount) {
        if (amount === null || amount === undefined || !isFinite(Number(amount))) {
            return '—';
        }

        return 'PKR ' + Math.round(Number(amount)).toLocaleString('en-US');
    }

    var INCLUDED_IN_TOTAL_LABEL = 'Included in total';

    function normalizePassengerTypeCode(code) {
        var raw = String(code || 'adult').trim().toLowerCase();
        if (raw === 'adt' || raw === 'adults' || raw === 'adult') {
            return 'adult';
        }
        if (raw === 'chd' || raw === 'cnn' || raw === 'children' || raw === 'child') {
            return 'child';
        }
        if (raw === 'inf' || raw === 'infants' || raw === 'infant') {
            return 'infant';
        }

        return 'adult';
    }

    function normalizeMoneyNumber(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }
        var n = Number(value);
        return isFinite(n) ? n : null;
    }

    function readRowPassengerType(row) {
        return normalizePassengerTypeCode(
            row.passenger_type || row.passengerType || row.type || row.ptc || row.code || 'adult'
        );
    }

    function readRowMoney(row, keys) {
        for (var i = 0; i < keys.length; i++) {
            var n = normalizeMoneyNumber(row[keys[i]]);
            if (n !== null) {
                return n;
            }
        }

        return null;
    }

    function readRowBase(row) {
        return readRowMoney(row, ['base_amount', 'base', 'base_fare', 'baseFare', 'fare', 'fare_amount']) || 0;
    }

    function readRowTax(row) {
        return readRowMoney(row, ['tax_amount', 'tax', 'taxes', 'taxes_fees', 'taxesAndFees', 'tax_amount']) || 0;
    }

    function readRowTotal(row) {
        var total = readRowMoney(row, ['total_amount', 'total', 'total_fare', 'totalFare', 'amount', 'price']);
        if (total !== null) {
            return total;
        }
        var base = readRowBase(row);
        var tax = readRowTax(row);
        if (base > 0 || tax > 0) {
            return base + tax;
        }

        return 0;
    }

    function isGroupTotalRow(row, qty) {
        if (!row || qty <= 1) {
            return false;
        }
        if (row.per_passenger === true || row.perPassenger === true || row.per_pax === true || row.unit === true) {
            return false;
        }
        if (row.group_total === true || row.groupTotal === true || row.is_group_total === true) {
            return true;
        }
        var rowQty = Number(row.quantity || row.qty || row.passenger_count || 0);
        return rowQty > 1 && rowQty >= qty;
    }

    function shouldMultiplyByQty(row, qty) {
        if (!row || qty <= 1) {
            return false;
        }
        if (isGroupTotalRow(row, qty)) {
            return false;
        }
        if (row.per_passenger === true || row.perPassenger === true || row.per_pax === true) {
            return true;
        }
        var rowQty = Number(row.quantity || row.qty || row.passenger_count || 1);
        return rowQty === 1 && qty > 1;
    }

    function extractPassengerPricingRows(data) {
        var rows = [];
        var sources = [
            data.passenger_pricing,
            data.passengerPricing,
            data.passenger_fares,
            data.passengerFares,
            data.pax_breakdown,
            data.paxBreakdown,
            data.passengerTypeBreakdown,
            data.pricingByPassengerType,
        ];
        sources.forEach(function (src) {
            if (Array.isArray(src)) {
                rows = rows.concat(src);
                return;
            }
            if (!src || typeof src !== 'object') {
                return;
            }
            Object.keys(src).forEach(function (key) {
                var entry = src[key];
                if (Array.isArray(entry)) {
                    rows = rows.concat(entry);
                    return;
                }
                if (entry && typeof entry === 'object') {
                    rows.push(Object.assign({}, entry, {
                        passenger_type: entry.passenger_type || entry.passengerType || entry.type || key,
                    }));
                }
            });
        });
        var nested = data.fare_breakdown || data.fareBreakdown;
        if (nested && typeof nested === 'object') {
            [
                nested.passenger_pricing,
                nested.passengerPricing,
                nested.passenger_fares,
                nested.passengerFares,
            ].forEach(function (src) {
                if (Array.isArray(src)) {
                    rows = rows.concat(src);
                }
            });
        }

        return rows;
    }

    function groupPassengerPricingRows(pricingRows) {
        var groups = {
            adult: { count: 0, base: 0, tax: 0, total: 0, hasRow: false },
            child: { count: 0, base: 0, tax: 0, total: 0, hasRow: false },
            infant: { count: 0, base: 0, tax: 0, total: 0, hasRow: false },
        };
        (pricingRows || []).forEach(function (row) {
            if (!row || typeof row !== 'object') {
                return;
            }
            var type = readRowPassengerType(row);
            var qty = Math.max(1, Number(row.passenger_count || row.quantity || row.qty || 1));
            var base = readRowBase(row);
            var tax = readRowTax(row);
            var total = readRowTotal(row);
            if (shouldMultiplyByQty(row, qty)) {
                base *= qty;
                tax *= qty;
                total *= qty;
            }
            groups[type].count += qty;
            groups[type].base += base;
            groups[type].tax += tax;
            groups[type].total += total;
            groups[type].hasRow = true;
        });

        return groups;
    }

    var RECONCILIATION_TOLERANCE = 2;

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
        return roundPkr(Number(amount || 0) * factor);
    }

    function adminMarkupFromData(data) {
        if (data.admin_markup != null && Number(data.admin_markup) > 0) {
            return Number(data.admin_markup);
        }
        if (data.markup != null && Number(data.markup) > 0 && data.admin_markup_only) {
            return Number(data.markup);
        }

        return 0;
    }

    function serviceFeeFromData(data) {
        return Number(data.service_fee || 0);
    }

    function resolveDisplayedTotal(data) {
        return Number(data.displayed_price || data.final_total || data.final_customer_price || 0);
    }

    function resolveGrandTotal(data) {
        var displayed = resolveDisplayedTotal(data);
        var adminMarkup = adminMarkupFromData(data);
        var serviceFee = serviceFeeFromData(data);
        var fees = adminMarkup + serviceFee;
        if (fees <= 0) {
            return displayed;
        }
        var baseFare = Number(data.base_fare || 0);
        var taxes = Number(data.taxes || 0);
        var supplierSubtotal = baseFare + taxes;
        if (supplierSubtotal > 0 && Math.abs(displayed - supplierSubtotal) <= 2) {
            return displayed + fees;
        }
        if (displayed > 0 && displayed + fees > displayed) {
            var passengerRows = buildFarePassengerBreakdownRows(data);
            var rowTotal = 0;
            passengerRows.forEach(function (row) {
                if (!row.fallback && row.total != null) {
                    rowTotal += Number(row.total || 0);
                }
            });
            if (rowTotal > 0 && Math.abs(displayed - rowTotal) <= 2) {
                return displayed + fees;
            }
        }

        return displayed;
    }

    function reconciliationDelta(componentTotal, displayedTotal) {
        return Math.abs(Number(componentTotal || 0) - Number(displayedTotal || 0));
    }

    function componentsReconcile(baseFare, taxes, adminMarkup, serviceFee, displayedTotal) {
        if (!displayedTotal || displayedTotal <= 0) {
            return false;
        }
        if (baseFare <= 0 && taxes <= 0) {
            return false;
        }

        return reconciliationDelta(baseFare + taxes + adminMarkup + serviceFee, displayedTotal) <= RECONCILIATION_TOLERANCE;
    }

    function canUseExplicitPassengerPricing(data) {
        if (data.passenger_pricing_trusted === false && data.conversion_status === 'converted') {
            return false;
        }

        return extractPassengerPricingRows(data).length > 0;
    }

    function typeGroupHasReliablePricing(group, type) {
        if (!group || !group.hasRow) {
            return false;
        }
        if (type === 'infant') {
            return normalizeMoneyNumber(group.base) !== null
                || normalizeMoneyNumber(group.tax) !== null
                || normalizeMoneyNumber(group.total) !== null;
        }

        return Number(group.base) > 0 || Number(group.tax) > 0 || Number(group.total) > 0;
    }

    function resolveOfferLevelComponents(data, total, adminMarkup, serviceFee) {
        var baseFare = Number(data.base_fare || 0);
        var taxes = Number(data.taxes || 0);
        if (baseFare <= 0 && taxes <= 0) {
            return null;
        }
        if (!componentsReconcile(baseFare, taxes, adminMarkup, serviceFee, total)) {
            return null;
        }

        return { base: baseFare, tax: taxes, total: baseFare + taxes };
    }

    function hasMultiplePassengerTypes(counts) {
        var typesPresent = 0;
        if (counts.adults > 0) {
            typesPresent++;
        }
        if (counts.children > 0) {
            typesPresent++;
        }
        if (counts.infants > 0) {
            typesPresent++;
        }

        return typesPresent > 1;
    }

    function totalPassengerQty(counts) {
        return Math.max(0, counts.adults) + Math.max(0, counts.children) + Math.max(0, counts.infants);
    }

    function passengerTypesWithQty(counts) {
        var types = [];
        if (counts.adults > 0) {
            types.push('adult');
        }
        if (counts.children > 0) {
            types.push('child');
        }
        if (counts.infants > 0) {
            types.push('infant');
        }

        return types;
    }

    function hasCompletePassengerTypeSplit(grouped, counts) {
        return passengerTypesWithQty(counts).every(function (type) {
            return typeGroupHasReliablePricing(grouped[type], type);
        });
    }

    function adultPricingLooksLikeGroupTotal(grouped, offerLevel, displayedTotal) {
        var adultTotal = Number(grouped.adult ? grouped.adult.total : 0);
        var offerTotal = offerLevel ? Number(offerLevel.total || 0) : 0;
        if (displayedTotal > 0) {
            if (Math.abs(adultTotal - displayedTotal) <= RECONCILIATION_TOLERANCE) {
                return true;
            }
            if (offerTotal > 0 && Math.abs(offerTotal - displayedTotal) <= RECONCILIATION_TOLERANCE) {
                return true;
            }
        }

        return false;
    }

    function isOfferLevelAggregateOnly(data, grouped, offerLevel, counts, displayedTotal) {
        if (!hasMultiplePassengerTypes(counts)) {
            return false;
        }
        if (hasCompletePassengerTypeSplit(grouped, counts)) {
            return false;
        }
        var typesWithPricing = passengerTypesWithQty(counts).filter(function (type) {
            return typeGroupHasReliablePricing(grouped[type], type);
        });
        if (typesWithPricing.length === 1 && typesWithPricing[0] === 'adult') {
            return adultPricingLooksLikeGroupTotal(grouped, offerLevel, displayedTotal);
        }

        return !!offerLevel;
    }

    function buildAggregateAllPassengersRow(data, counts, offerLevel) {
        return {
            label: 'All passengers',
            qty: totalPassengerQty(counts),
            base: Number(offerLevel.base || 0),
            tax: Number(offerLevel.tax || 0),
            total: Number(offerLevel.total || 0),
            fallback: false,
            isAggregate: true,
        };
    }

    function resolvePassengerPricingRow(type, qty, typeGroup, offerLevel, context) {
        context = context || {};
        var counts = context.counts || { adults: 0, children: 0, infants: 0 };
        var grouped = context.grouped || {};
        var displayedTotal = Number(context.displayedTotal || 0);
        var multiType = hasMultiplePassengerTypes(counts);

        if (typeGroupHasReliablePricing(typeGroup, type)) {
            if (multiType && type === 'adult') {
                var otherTypesHavePricing = passengerTypesWithQty(counts).some(function (otherType) {
                    return otherType !== 'adult' && typeGroupHasReliablePricing(grouped[otherType], otherType);
                });
                if (!otherTypesHavePricing && adultPricingLooksLikeGroupTotal(grouped, offerLevel, displayedTotal)) {
                    return { hasReliable: false, base: 0, tax: 0, total: 0 };
                }
            }
            return {
                hasReliable: true,
                base: Number(typeGroup.base || 0),
                tax: Number(typeGroup.tax || 0),
                total: Number(typeGroup.total || 0),
            };
        }
        if (type === 'infant' && typeGroup && typeGroup.hasRow) {
            return {
                hasReliable: true,
                base: Number(typeGroup.base || 0),
                tax: Number(typeGroup.tax || 0),
                total: Number(typeGroup.total || 0),
            };
        }
        if (offerLevel && qty > 0 && !multiType) {
            var soleType = counts.adults > 0 ? 'adult' : (counts.children > 0 ? 'child' : 'infant');
            if (type === soleType) {
                return {
                    hasReliable: true,
                    base: Number(offerLevel.base || 0),
                    tax: Number(offerLevel.tax || 0),
                    total: Number(offerLevel.total || 0),
                };
            }
        }

        return { hasReliable: false, base: 0, tax: 0, total: 0 };
    }

    function resolvePricingSource(data, grouped, offerLevel, counts, displayedTotal, breakdown) {
        if (hasCompletePassengerTypeSplit(grouped, counts)) {
            return 'passenger_type_split';
        }
        if (hasMultiplePassengerTypes(counts)) {
            var typesWithReliable = passengerTypesWithQty(counts).filter(function (type) {
                return breakdown[type] && breakdown[type].hasReliable;
            });
            if (typesWithReliable.length === 1 && typesWithReliable[0] === 'adult') {
                return 'adult_only_split';
            }
            if (isOfferLevelAggregateOnly(data, grouped, offerLevel, counts, displayedTotal) && offerLevel) {
                return 'aggregate_offer_total';
            }

            return 'fallback_included_total';
        }
        if (!hasMultiplePassengerTypes(counts)) {
            if (breakdown.adult && breakdown.adult.hasReliable) {
                return 'passenger_type_split';
            }
            if (breakdown.child && breakdown.child.hasReliable) {
                return 'passenger_type_split';
            }
            if (breakdown.infant && breakdown.infant.hasReliable) {
                return 'passenger_type_split';
            }
            if (offerLevel) {
                return 'adult_only_split';
            }

            return 'fallback_included_total';
        }
    }

    function fareDebugLog(label, payload) {
        if (typeof global.OTA_FARE_DEBUG === 'undefined' || global.OTA_FARE_DEBUG !== true) {
            return;
        }
        try {
            console.log('[OTA_FARE_DEBUG] ' + label, payload);
        } catch (err) {
            /* ignore */
        }
    }

    function readPassengerCounts(data) {
        var sources = [
            data && data.search_passengers,
            data && data.passenger_counts,
        ];
        var adults = NaN;
        var children = NaN;
        var infants = NaN;

        sources.forEach(function (counts) {
            if (!counts || typeof counts !== 'object') {
                return;
            }
            if (!isFinite(adults) && counts.adults != null) {
                adults = Number(counts.adults);
            }
            if (!isFinite(adults) && counts.adult != null) {
                adults = Number(counts.adult);
            }
            if (!isFinite(children) && counts.children != null) {
                children = Number(counts.children);
            }
            if (!isFinite(children) && counts.child != null) {
                children = Number(counts.child);
            }
            if (!isFinite(infants) && counts.infants != null) {
                infants = Number(counts.infants);
            }
            if (!isFinite(infants) && counts.infant != null) {
                infants = Number(counts.infant);
            }
        });

        adults = Math.max(0, isFinite(adults) ? adults : 0);
        children = Math.max(0, isFinite(children) ? children : 0);
        infants = Math.max(0, isFinite(infants) ? infants : 0);
        if (adults + children + infants <= 0) {
            adults = 1;
        }

        return { adults: adults, children: children, infants: infants };
    }

    function resolvePassengerCounts(data) {
        return readPassengerCounts(data);
    }

    function moneyDisplay(amount, useFallback) {
        if (useFallback) {
            return INCLUDED_IN_TOTAL_LABEL;
        }
        if (amount === null || amount === undefined || !isFinite(Number(amount))) {
            return INCLUDED_IN_TOTAL_LABEL;
        }

        return formatPkr(amount);
    }

    function readPassengerPricingBreakdown(data, total, adminMarkup, serviceFee) {
        var breakdown = {
            adult: { hasReliable: false, base: 0, tax: 0, total: 0 },
            child: { hasReliable: false, base: 0, tax: 0, total: 0 },
            infant: { hasReliable: false, base: 0, tax: 0, total: 0 },
        };
        var counts = readPassengerCounts(data);
        var offerLevel = resolveOfferLevelComponents(data, total, adminMarkup, serviceFee);
        var grouped = canUseExplicitPassengerPricing(data)
            ? groupPassengerPricingRows(extractPassengerPricingRows(data))
            : { adult: { hasRow: false }, child: { hasRow: false }, infant: { hasRow: false } };
        var rowContext = {
            counts: counts,
            grouped: grouped,
            displayedTotal: total,
        };

        ['adult', 'child', 'infant'].forEach(function (type) {
            var qty = type === 'adult' ? counts.adults : (type === 'child' ? counts.children : counts.infants);
            if (qty <= 0) {
                return;
            }
            breakdown[type] = resolvePassengerPricingRow(type, qty, grouped[type], offerLevel, rowContext);
        });

        var pricingSource = resolvePricingSource(data, grouped, offerLevel, counts, total, breakdown);

        fareDebugLog('readPassengerPricingBreakdown', {
            keys: Object.keys(data || {}),
            passenger_counts: counts,
            pricing_source: pricingSource,
            pricing_row_types: ['adult', 'child', 'infant'].map(function (type) {
                return {
                    type: type,
                    hasRow: !!(grouped[type] && grouped[type].hasRow),
                    base: grouped[type] ? grouped[type].base : 0,
                    tax: grouped[type] ? grouped[type].tax : 0,
                    total: grouped[type] ? grouped[type].total : 0,
                };
            }),
            offer_level: offerLevel,
            final: breakdown,
        });

        breakdown._pricingSource = pricingSource;
        breakdown._offerLevel = offerLevel;
        breakdown._grouped = grouped;
        breakdown._counts = counts;

        return breakdown;
    }

    function buildFarePassengerBreakdownRows(data) {
        var adminMarkup = adminMarkupFromData(data);
        var serviceFee = serviceFeeFromData(data);
        var total = resolveDisplayedTotal(data);
        var pricing = readPassengerPricingBreakdown(data, total, adminMarkup, serviceFee);
        var counts = pricing._counts || readPassengerCounts(data);
        var offerLevel = pricing._offerLevel || null;
        var pricingSource = pricing._pricingSource || 'fallback_included_total';
        var types = [
            { key: 'adult', label: 'Adult', qty: counts.adults },
            { key: 'child', label: 'Child', qty: counts.children },
            { key: 'infant', label: 'Infant', qty: counts.infants },
        ];
        var rows = [];

        if (pricingSource === 'aggregate_offer_total' && offerLevel && hasMultiplePassengerTypes(counts)) {
            rows.push(buildAggregateAllPassengersRow(data, counts, offerLevel));
        }

        types.forEach(function (type) {
            if (type.qty <= 0) {
                return;
            }
            var price = pricing[type.key] || {};
            var hasReliable = false;
            if (pricingSource === 'aggregate_offer_total' || pricingSource === 'fallback_included_total') {
                hasReliable = false;
            } else if (pricingSource === 'adult_only_split') {
                hasReliable = type.key === 'adult' && price.hasReliable === true;
            } else {
                hasReliable = price.hasReliable === true;
            }
            rows.push({
                label: type.label,
                qty: type.qty,
                base: hasReliable ? Number(price.base || 0) : null,
                tax: hasReliable ? Number(price.tax || 0) : null,
                total: hasReliable ? Number(price.total || 0) : null,
                fallback: !hasReliable,
            });
        });

        fareDebugLog('buildFarePassengerBreakdownRows', {
            counts: counts,
            rows: rows,
            displayed_total: total,
            pricing_source: pricingSource,
        });

        return rows;
    }

    function buildFareDetailsTable(data) {
        var adminMarkup = adminMarkupFromData(data);
        var serviceFee = serviceFeeFromData(data);
        var total = resolveDisplayedTotal(data);
        var passengerRows = buildFarePassengerBreakdownRows(data);
        var useDetailedColumns = passengerRows.some(function (row) {
            return !row.fallback;
        }) || passengerRows.length > 1;
        var bodyRows = '';

        function appendDetailedRow(label, qty, base, tax, rowTotal, useFallback) {
            bodyRows += '<tr>' +
                '<td class="ota-fare-summary-table__passenger">' + esc(label) + '</td>' +
                '<td class="ota-fare-summary-table__qty">' + esc(String(qty)) + '</td>' +
                '<td class="ota-fare-summary-table__num">' + esc(moneyDisplay(base, useFallback)) + '</td>' +
                '<td class="ota-fare-summary-table__num">' + esc(moneyDisplay(tax, useFallback)) + '</td>' +
                '<td class="ota-fare-summary-table__num ota-fare-summary-table__num--total">' + esc(moneyDisplay(rowTotal, useFallback)) + '</td>' +
                '</tr>';
        }

        function appendTotalOnlyRow(label, qty, rowTotal) {
            bodyRows += '<tr>' +
                '<td class="ota-fare-summary-table__passenger">' + esc(label) + '</td>' +
                '<td class="ota-fare-summary-table__qty">' + esc(String(qty)) + '</td>' +
                '<td class="ota-fare-summary-table__num ota-fare-summary-table__num--total">' + esc(formatPkr(rowTotal)) + '</td>' +
                '</tr>';
        }

        if (!passengerRows.length) {
            appendTotalOnlyRow('Adult', 1, total);
        } else if (useDetailedColumns) {
            passengerRows.forEach(function (row) {
                appendDetailedRow(row.label, row.qty, row.base, row.tax, row.total, row.fallback);
            });
        } else {
            var single = passengerRows[0];
            if (single.fallback) {
                appendTotalOnlyRow(single.label, single.qty, total);
            } else {
                appendTotalOnlyRow(single.label, single.qty, single.total);
            }
        }

        var extras = '';
        var extraColspan = useDetailedColumns ? 4 : 2;
        if (adminMarkup > 0) {
            extras += '<tr class="ota-fare-summary-table__extra"><td colspan="' + extraColspan + '">Agency charge</td><td class="ota-fare-summary-table__num">' + esc(formatPkr(adminMarkup)) + '</td></tr>';
        }
        if (serviceFee > 0) {
            extras += '<tr class="ota-fare-summary-table__extra"><td colspan="' + extraColspan + '">Service fee</td><td class="ota-fare-summary-table__num">' + esc(formatPkr(serviceFee)) + '</td></tr>';
        }

        var headCols = useDetailedColumns
            ? '<th scope="col">Passenger</th><th scope="col">Qty</th><th scope="col">Base fare</th><th scope="col">Taxes &amp; fees</th><th scope="col">Total</th>'
            : '<th scope="col">Passenger</th><th scope="col">Qty</th><th scope="col">Total</th>';

        return '<div class="ota-fare-summary-table-wrap">' +
            '<table class="ota-fare-summary-table' + (useDetailedColumns ? '' : ' ota-fare-summary-table--total-only') + '">' +
            '<thead><tr>' + headCols + '</tr></thead>' +
            '<tbody>' + bodyRows + extras + '</tbody>' +
            '</table></div>';
    }

    function normalizeBaggageText(value) {
        return String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');
    }

    function baggageLabelCompareKey(label) {
        return String(label || '').trim().toLowerCase().replace(/[\s\-_.,/'"]/g, '');
    }

    var CABIN_BAGGAGE_LABEL_KEYS = {
        cabinbag: true,
        cabinbaggage: true,
        carryon: true,
        handbag: true,
        handbaggage: true,
        handcarry: true,
    };

    var CHECKED_BAGGAGE_LABEL_KEYS = {
        checkedbag: true,
        checkedbaggage: true,
        checkinbag: true,
        checkinbaggage: true,
        baggageallowance: true,
        baggage: true,
        baggageincluded: true,
        allowance: true,
        luggageallowance: true,
    };

    function classifyPublicBaggageLabel(rawLabel) {
        var key = baggageLabelCompareKey(rawLabel);
        if (!key) {
            return null;
        }
        if (CABIN_BAGGAGE_LABEL_KEYS[key]) {
            return 'Cabin baggage';
        }
        if (CHECKED_BAGGAGE_LABEL_KEYS[key]) {
            return 'Checked baggage';
        }
        if (/^cabin|^carry|^hand/.test(key)) {
            return 'Cabin baggage';
        }
        if (/^checked|^checkin|^baggage|^allowance|^luggage/.test(key)) {
            return 'Checked baggage';
        }

        return null;
    }

    function baggageRowKey(label, value) {
        return String(label || '').trim().toLowerCase() + '|' + normalizeBaggageText(value);
    }

    function buildBaggageHtml(data) {
        var entries = [];
        var seen = {};
        var cabin = String(data.baggage_cabin_display || '').trim();
        var checked = String(data.baggage_checked_display || '').trim();
        var summary = String(data.baggage_summary_display || data.baggage || '').trim();
        var lines = Array.isArray(data.baggage_lines) ? data.baggage_lines : [];
        var routeLabel = resolveRouteLabel(data);

        function addEntry(rawLabel, value) {
            var val = String(value || '').trim();
            if (!val) {
                return;
            }
            var displayLabel = classifyPublicBaggageLabel(rawLabel);
            if (!displayLabel) {
                displayLabel = 'Checked baggage';
            }
            var key = baggageRowKey(displayLabel, val);
            if (seen[key]) {
                return;
            }
            seen[key] = true;
            entries.push({ label: displayLabel, value: val });
        }

        addEntry('Cabin baggage', cabin);
        addEntry('Checked baggage', checked);

        if (summary) {
            var summaryKey = normalizeBaggageText(summary);
            var cabinKey = normalizeBaggageText(cabin);
            var checkedKey = normalizeBaggageText(checked);
            if (summaryKey !== cabinKey && summaryKey !== checkedKey) {
                addEntry('baggage', summary);
            }
        }

        lines.forEach(function (line) {
            if (!line) {
                return;
            }
            if (typeof line === 'string') {
                addEntry('baggage', line);
                return;
            }
            if (typeof line === 'object') {
                addEntry(line.label || line.type || 'baggage', line.text || line.value || line.summary || '');
            }
        });

        if (!entries.length) {
            return '<p class="ota-fare-summary-modal__empty">Baggage allowance details are not available for this fare.</p>';
        }

        var cards = entries.map(function (entry) {
            return '<div class="ota-fare-summary-modal__baggage-card">' +
                '<span class="ota-fare-summary-modal__baggage-label">' + esc(entry.label) + '</span>' +
                '<span class="ota-fare-summary-modal__baggage-value">' + esc(entry.value) + '</span></div>';
        }).join('');

        return '<section class="ota-fare-summary-modal__baggage-segment">' +
            (routeLabel ? '<h5 class="ota-fare-summary-modal__baggage-segment-title">' + esc(routeLabel) + '</h5>' : '') +
            '<div class="ota-fare-summary-modal__baggage-grid">' + cards + '</div></section>';
    }

    function formatPolicyTextBlock(text) {
        var raw = String(text || '').trim();
        if (!raw) {
            return '';
        }
        if (!/;/.test(raw) && !/\b(before|after)\s+departure\b/i.test(raw)) {
            return esc(raw);
        }
        var segments = raw.split(/\s*;\s*/).filter(function (part) {
            return !!String(part || '').trim();
        });
        if (segments.length <= 1) {
            return '<span class="ota-fare-summary-modal__policy-text">' + esc(raw) + '</span>';
        }
        return '<div class="ota-fare-summary-modal__policy-block">' + segments.map(function (segment) {
            var piece = String(segment || '').trim();
            var colon = piece.indexOf(':');
            if (colon > 0 && colon < piece.length - 1) {
                return '<div class="ota-fare-summary-modal__policy-segment">' +
                    '<span class="ota-fare-summary-modal__policy-segment-label">' + esc(piece.slice(0, colon).trim()) + '</span>' +
                    '<span class="ota-fare-summary-modal__policy-segment-value">' + esc(piece.slice(colon + 1).trim()) + '</span>' +
                    '</div>';
            }
            return '<div class="ota-fare-summary-modal__policy-segment"><span class="ota-fare-summary-modal__policy-segment-value">' + esc(piece) + '</span></div>';
        }).join('') + '</div>';
    }

    function buildPolicyHtml(data) {
        var parts = [];
        var refundRule = String(data.refund_rule || data.refundable_display || '').trim();
        var changeRule = String(data.change_rule || data.modification_rule || '').trim();
        var cancelRule = String(data.cancellation_rule || '').trim();
        var noShowRule = String(data.no_show_rule || '').trim();
        var exchangeRule = String(data.exchange_rule || '').trim();
        var mealRule = String(data.meal_included || '').trim();
        var seatRule = String(data.seat_selection_rule || '').trim();

        if (refundRule) {
            parts.push('<li><strong>Refund</strong> ' + formatPolicyTextBlock(refundRule) + '</li>');
        }
        if (changeRule) {
            parts.push('<li><strong>Changes</strong> ' + formatPolicyTextBlock(changeRule) + '</li>');
        }
        if (cancelRule) {
            parts.push('<li><strong>Cancellation</strong> ' + formatPolicyTextBlock(cancelRule) + '</li>');
        }
        if (exchangeRule) {
            parts.push('<li><strong>Reissue / exchange</strong> ' + formatPolicyTextBlock(exchangeRule) + '</li>');
        }
        if (noShowRule) {
            parts.push('<li><strong>No-show</strong> ' + formatPolicyTextBlock(noShowRule) + '</li>');
        }
        if (mealRule) {
            parts.push('<li><strong>Meal</strong> ' + esc(mealRule) + '</li>');
        }
        if (seatRule) {
            parts.push('<li><strong>Seat selection</strong> ' + esc(seatRule) + '</li>');
        }
        if (!refundRule && !changeRule && !cancelRule && data.refundable !== undefined && data.refundable !== null) {
            parts.push('<li>' + esc(data.refundable ? 'Refundable fare' : 'Non-refundable fare') + '</li>');
        }

        if (!parts.length) {
            return '<p class="ota-fare-summary-modal__empty">Fare policy information is not available for this fare.</p>';
        }

        return '<ul class="ota-fare-summary-modal__policy-list">' + parts.join('') + '</ul>' + buildSupplierFreshnessHtml(data);
    }

    function buildSupplierFreshnessHtml(data) {
        var supplier = data.fallback_details && data.fallback_details.supplier
            ? data.fallback_details.supplier
            : null;
        if (!supplier || typeof supplier !== 'object') {
            supplier = {
                provider_label: data.supplier_source_label || data.supplier_provider || '',
                freshness_status: data.offer_freshness && data.offer_freshness.offer_freshness_status
                    ? data.offer_freshness.offer_freshness_status
                    : '',
                last_checked_display: data.offer_freshness && (data.offer_freshness.last_checked_display || data.offer_freshness.search_age_display)
                    ? (data.offer_freshness.last_checked_display || data.offer_freshness.search_age_display)
                    : '',
                revalidation_note: data.offer_freshness && data.offer_freshness.revalidation_note
                    ? data.offer_freshness.revalidation_note
                    : '',
            };
        }
        var rows = [];
        if (supplier.provider_label) {
            rows.push('<li><strong>Supplier</strong> ' + esc(supplier.provider_label) + '</li>');
        }
        if (supplier.freshness_status) {
            rows.push('<li><strong>Freshness</strong> ' + esc(supplier.freshness_status) + '</li>');
        }
        if (supplier.last_checked_display) {
            rows.push('<li><strong>Last checked</strong> ' + esc(supplier.last_checked_display) + '</li>');
        }
        if (supplier.revalidation_note) {
            rows.push('<li>' + esc(supplier.revalidation_note) + '</li>');
        }
        if (!rows.length) {
            return '';
        }

        return '<section class="ota-fare-summary-modal__supplier"><h4 class="ota-fare-summary-modal__supplier-title">Supplier / Freshness</h4><ul class="ota-fare-summary-modal__policy-list">' + rows.join('') + '</ul></section>';
    }

    function resolveRouteLabel(data) {
        var label = String(data.route_label || '').trim();
        if (label) {
            return label;
        }
        var journeys = Array.isArray(data.journeys_display) ? data.journeys_display : [];
        if (journeys.length === 1 && journeys[0]) {
            var j = journeys[0];
            if (j.origin && j.destination) {
                return String(j.origin) + ' → ' + String(j.destination);
            }
        }
        if (data.journey_display && data.journey_display.origin && data.journey_display.destination) {
            return String(data.journey_display.origin) + ' → ' + String(data.journey_display.destination);
        }

        return '';
    }

    function activateTab(tabId) {
        if (!tabsWrap || !tabId) {
            return;
        }
        var tabs = tabsWrap.querySelectorAll('[data-fare-summary-tab]');
        var panels = modal ? modal.querySelectorAll('[data-fare-summary-panel]') : [];
        Array.prototype.forEach.call(tabs, function (tab) {
            var active = tab.getAttribute('data-fare-summary-tab') === tabId;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });
        Array.prototype.forEach.call(panels, function (panel) {
            var show = panel.getAttribute('data-fare-summary-panel') === tabId;
            if (show) {
                panel.removeAttribute('hidden');
                panel.hidden = false;
            } else {
                panel.setAttribute('hidden', 'hidden');
                panel.hidden = true;
            }
        });
    }

    function configurePresentation(fareFamilyName) {
        if (subtitleEl) {
            subtitleEl.textContent = fareFamilyName
                ? fareFamilyName + ' — review baggage, policy, and pricing.'
                : 'Review fare, baggage, and policy before booking.';
        }
        if (routeEl) {
            routeEl.textContent = '';
            routeEl.hidden = true;
        }
    }

    function bindTabs() {
        if (!tabsWrap || tabsWrap.getAttribute('data-fare-summary-tabs-bound') === '1') {
            return;
        }
        tabsWrap.setAttribute('data-fare-summary-tabs-bound', '1');
        tabsWrap.addEventListener('click', function (e) {
            var tab = e.target.closest('[data-fare-summary-tab]');
            if (!tab || !tabsWrap.contains(tab)) {
                return;
            }
            e.preventDefault();
            activateTab(tab.getAttribute('data-fare-summary-tab'));
        });
    }

    function findCardFromTrigger(btn) {
        if (!btn) {
            return null;
        }

        return btn.closest('.ota-result-pro-card, .ota-return-split-pro-card, [data-offer-id]');
    }

    function findSelectTarget(card) {
        if (!card) {
            return null;
        }
        var link = card.querySelector('a.ota-return-split-card__cta[href], a.ota-btn-book[href]');
        if (link) {
            return link;
        }
        var btn = card.querySelector('button.ota-return-split-card__cta:not([disabled]), button.ota-btn-book:not([disabled]), .ota-flight-book-button:not([disabled])');
        if (btn) {
            return btn;
        }

        return null;
    }

    function configureSelectBtn(card) {
        activeCard = card || null;
        if (!selectBtn) {
            return;
        }
        var target = findSelectTarget(card);
        if (!target || target.disabled) {
            selectBtn.hidden = true;
            return;
        }
        selectBtn.hidden = false;
        var label = (target.textContent || '').trim();
        selectBtn.textContent = label || 'Select';
    }

    function handleSelectClick() {
        if (selectBtn && selectBtn.getAttribute('data-checkout-loading') === '1') {
            return;
        }
        var triedOneWayHook = false;
        if (activeCard && activeFareOptionKey) {
            var fareBtn = activeCard.querySelector('[data-fare-option-card][data-fare-option-key="' + activeFareOptionKey + '"]');
            if (typeof global.otaProceedBrandedFareCheckout === 'function') {
                triedOneWayHook = true;
                if (selectBtn) {
                    selectBtn.setAttribute('data-fare-summary-select', '1');
                }
                if (global.otaProceedBrandedFareCheckout(activeCard, activeFareOptionKey, selectBtn || fareBtn)) {
                    closeModal();
                    return;
                }
                if (selectBtn) {
                    selectBtn.removeAttribute('data-fare-summary-select');
                }
            } else if (fareBtn && typeof fareBtn.click === 'function') {
                fareBtn.click();
            }
        }
        if (triedOneWayHook) {
            closeModal();
            return;
        }
        var target = findSelectTarget(activeCard);
        closeModal();
        if (!target || target.getAttribute('data-checkout-loading') === '1') {
            return;
        }
        if (target.tagName === 'A') {
            target.click();
            return;
        }
        target.click();
    }

    function init() {
        if (initialized) {
            return;
        }
        modal = document.getElementById('ota-fare-breakdown-modal');
        if (!modal) {
            return;
        }
        rowsEl = modal.querySelector('[data-fare-breakdown-rows]');
        baggageEl = modal.querySelector('[data-fare-summary-baggage]');
        policyEl = modal.querySelector('[data-fare-summary-policy]');
        routeEl = modal.querySelector('[data-fare-summary-route]');
        subtitleEl = modal.querySelector('[data-fare-summary-subtitle]');
        titleEl = modal.querySelector('#ota-fare-breakdown-title');
        totalEl = modal.querySelector('[data-fare-summary-total]');
        selectBtn = modal.querySelector('[data-fare-summary-select]');
        tabsWrap = modal.querySelector('[data-fare-summary-tabs]');

        Array.prototype.forEach.call(modal.querySelectorAll('[data-close-fare-breakdown]'), function (el) {
            el.addEventListener('click', closeModal);
        });
        if (selectBtn) {
            selectBtn.addEventListener('click', handleSelectClick);
        }
        modal.addEventListener('click', function (e) {
            var backdrop = modal.querySelector('.ota-fare-summary-modal__backdrop, .ota-fare-breakdown-modal__backdrop');
            if (e.target === backdrop) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && !modal.hidden) {
                closeModal();
            }
        });
        bindTabs();
        initialized = true;
    }

    function closeModal() {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ota-fare-breakdown-modal-open');
        activeCard = null;
        activeFareOptionKey = null;
    }

    function openModal(data, triggerBtn) {
        init();
        if (!modal || !rowsEl || !data) {
            return;
        }

        if (baggageEl) {
            baggageEl.innerHTML = buildBaggageHtml(data);
        }
        if (policyEl) {
            policyEl.innerHTML = buildPolicyHtml(data);
        }
        rowsEl.innerHTML = buildFareDetailsTable(data);

        var fareFamilyName = String(data.fare_family_name || data.brand_name || '').trim();
        configurePresentation(fareFamilyName);
        if (totalEl) {
            totalEl.textContent = formatPkr(resolveGrandTotal(data));
        }

        activeCard = findCardFromTrigger(triggerBtn);
        activeFareOptionKey = triggerBtn ? (triggerBtn.getAttribute('data-fare-option-key') || null) : null;
        configureSelectBtn(activeCard);
        activateTab('baggage');

        modal.hidden = false;
        modal.removeAttribute('aria-hidden');
        document.body.classList.add('ota-fare-breakdown-modal-open');
    }

    function bindFareBreakdownLinks(containerEl) {
        init();
        if (!containerEl) {
            return;
        }
        Array.prototype.forEach.call(containerEl.querySelectorAll('[data-fare-summary-open], [data-fare-breakdown-open]'), function (btn) {
            if (btn.getAttribute('data-bound-fare') === '1') {
                return;
            }
            btn.setAttribute('data-bound-fare', '1');
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var raw = btn.getAttribute('data-fare-summary-payload') || btn.getAttribute('data-fare-breakdown');
                if (!raw) {
                    return;
                }
                try {
                    openModal(JSON.parse(raw), btn);
                } catch (err) {
                    /* ignore malformed payload */
                }
            });
        });
    }

    global.OtaFareBreakdownModal = {
        init: init,
        esc: esc,
        formatRs: formatPkr,
        formatPkr: formatPkr,
        open: openModal,
        close: closeModal,
        bindLinks: bindFareBreakdownLinks,
        normalizePassengerTypeCode: normalizePassengerTypeCode,
        normalizeMoneyNumber: normalizeMoneyNumber,
        readPassengerCounts: readPassengerCounts,
        extractPassengerPricingRows: extractPassengerPricingRows,
        readPassengerPricingBreakdown: readPassengerPricingBreakdown,
        resolvePassengerPricingRow: resolvePassengerPricingRow,
        resolveOfferLevelComponents: resolveOfferLevelComponents,
        hasMultiplePassengerTypes: hasMultiplePassengerTypes,
        isOfferLevelAggregateOnly: isOfferLevelAggregateOnly,
        buildAggregateAllPassengersRow: buildAggregateAllPassengersRow,
        resolvePricingSource: resolvePricingSource,
        buildFarePassengerBreakdownRows: buildFarePassengerBreakdownRows,
        moneyDisplay: moneyDisplay,
    };
}(typeof window !== 'undefined' ? window : this));
