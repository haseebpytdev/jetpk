/*
V2 cloned from v1.
Do not edit v1 for v2 redesign work.
*/

/**
 * OTA-RETURN-FLIGHT-UI-PARITY-SEPARATE-SELECTION-1 — shared branded fare panel builder.
 */
(function (global) {
    'use strict';

    var config = {
        criteria: {
            adults: 1,
            children: 0,
            infants: 0,
        },
    };

    function init(options) {
        options = options || {};
        config.criteria = options.criteria || config.criteria;
    }

    function createState() {
        return {
            offersById: {},
            selectedFareOptionByOfferId: {},
            expandedBrandedFaresByOfferId: {},
        };
    }

    function registerOffer(state, offer) {
        if (!state || !offer || !offer.offer_id) {
            return;
        }
        state.offersById[offer.offer_id] = offer;
    }

    function esc(s) {
        if (s === null || s === undefined) {
            return '';
        }
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function formatRs(amount) {
        if (amount === null || amount === undefined || !isFinite(Number(amount))) {
            return '—';
        }
        return 'PKR ' + Math.round(Number(amount)).toLocaleString('en-US');
    }

    function formatCardButtonRs(amount) {
        if (amount === null || amount === undefined || !isFinite(Number(amount))) {
            return '—';
        }
        return 'Rs. ' + Math.round(Number(amount)).toLocaleString('en-US');
    }

    function formatBrandedFarePrice(opt) {
        if (!opt) {
            return '';
        }
        if (opt.displayed_price != null && Number(opt.displayed_price) > 0) {
            return formatRs(opt.displayed_price);
        }
        if (opt.price_display) {
            return String(opt.price_display).replace(/^Approx\.\s*/i, '').trim();
        }
        return '';
    }

    function normalizeFareLabel(value) {
        return String(value || '').trim().toLowerCase().replace(/[\s\-_.,/'"]/g, '');
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

    function scaleAmountForFareOption(amount, mainDisplayed, optionDisplayed) {
        var base = Number(amount || 0);
        var main = Number(mainDisplayed || 0);
        var option = Number(optionDisplayed || 0);
        if (!isFinite(base) || main <= 0 || option <= 0 || Math.abs(main - option) < 1) {
            return Math.round(base);
        }
        return Math.round(base * (option / main));
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
                adults: Number(config.criteria.adults || 1),
                children: Number(config.criteria.children || 0),
                infants: Number(config.criteria.infants || 0),
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
                adults: Number(config.criteria.adults || 1),
                children: Number(config.criteria.children || 0),
                infants: Number(config.criteria.infants || 0),
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
            meal_included: option ? (option.meal_included || option.meal || '') : '',
            seat_selection_rule: option ? (option.seat_selection_rule || option.seat_selection || '') : '',
            no_show_rule: option ? (option.no_show_rule || '') : '',
            exchange_rule: option ? (option.exchange_rule || option.reissue_rule || '') : '',
            refundable_display: option ? (option.refundable_display || '') : '',
            fare_family_name: option ? (option.name || '') : '',
            brand_name: option ? (option.name || '') : '',
            cabin: option ? (option.cabin || offer.cabin || '') : (offer.cabin || ''),
            has_fallback_details: !!offer.has_fallback_details,
            fallback_details: offer.fallback_details || null,
            supplier_provider: offer.supplier_provider || offer.provider || '',
            supplier_source_label: offer.supplier_source_label || '',
            offer_freshness: offer.offer_freshness || null,
        };
    }

    function payloadAttr(obj) {
        return esc(JSON.stringify(obj));
    }

    function cardDisplayPrice(offer, state, selectedKeyOverride) {
        var selectedKey = selectedKeyOverride != null && selectedKeyOverride !== ''
            ? selectedKeyOverride
            : (state && offer ? (state.selectedFareOptionByOfferId[offer.offer_id] || '') : '');
        if (selectedKey && offer.fare_family_options_display) {
            for (var i = 0; i < offer.fare_family_options_display.length; i++) {
                var opt = offer.fare_family_options_display[i];
                if (opt && opt.option_key === selectedKey && opt.displayed_price != null && Number(opt.displayed_price) > 0) {
                    return Number(opt.displayed_price);
                }
            }
        }
        if (offer.displayed_price != null && Number(offer.displayed_price) > 0) {
            return Number(offer.displayed_price);
        }
        if (offer.from_total_amount != null && Number(offer.from_total_amount) > 0) {
            return Number(offer.from_total_amount);
        }
        if (offer.total_amount != null && Number(offer.total_amount) > 0) {
            return Number(offer.total_amount);
        }
        if (offer.has_confirmed_pkr_quote && offer.final_customer_price != null && Number(offer.final_customer_price) > 0) {
            return Number(offer.final_customer_price);
        }

        return null;
    }

    function isExpanded(offerId, state) {
        return !!(state && state.expandedBrandedFaresByOfferId[offerId]);
    }

    function getSelectedFareKey(offerId, state) {
        if (!state) {
            return '';
        }

        return state.selectedFareOptionByOfferId[offerId] || '';
    }

    function handleOutboundSelectClick(e, link, state, callbacks) {
        callbacks = callbacks || {};
        if (!link || !state) {
            return true;
        }
        var card = link.closest('[data-flight-card]');
        var oid = link.getAttribute('data-outbound-key') || (card ? card.getAttribute('data-offer-id') : '');
        var offer = oid ? state.offersById[oid] : null;
        if (!offer || !offerRequiresFareFamilySelection(offer)) {
            return true;
        }
        var selectedKey = state.selectedFareOptionByOfferId[oid] || '';
        if (!selectedKey) {
            e.preventDefault();
            if (card) {
                promptFareFamilySelection(card, offer, state);
            }
            return false;
        }
        if (typeof callbacks.onOutboundNavigate === 'function') {
            e.preventDefault();
            callbacks.onOutboundNavigate(link, offer, selectedKey);
            return false;
        }

        return true;
    }

    var NOT_PROVIDED_LABEL = 'Not provided by supplier';

    function brandedFareFieldValue(opt, keys) {
        if (!opt) {
            return '';
        }
        for (var i = 0; i < keys.length; i++) {
            var raw = opt[keys[i]];
            if (raw === null || raw === undefined) {
                continue;
            }
            var text = String(raw).trim();
            if (text !== '') {
                return text;
            }
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
        if (!text) {
            return false;
        }
        if (/\bnot\s+included\b/.test(text)) {
            return true;
        }
        if (/\bno\s+baggage\b/.test(text) || /\bwithout\s+baggage\b/.test(text) || /\bno\s+checked\b/.test(text)) {
            return true;
        }
        if (/\b0\s*kg\b/.test(text) || /^0\s*pc/.test(text) || text === '0') {
            return true;
        }
        if (/\bno\s+meal/.test(text) || /\bmeals?\s+not\s+included\b/.test(text)) {
            return true;
        }
        if (text === 'unavailable' || text === 'not available' || text === 'no service') {
            return true;
        }
        return false;
    }

    function normalizeRefundSummaryForCard(policyText, refundableFlag) {
        var text = String(policyText || '').trim().replace(/\s+/g, ' ');
        if (typeof refundableFlag === 'boolean') {
            if (!text) {
                return refundableFlag ? 'Refundable' : 'Non-refundable';
            }
        }
        if (refundableFlag === true || refundableFlag === 'true' || refundableFlag === 1 || refundableFlag === '1') {
            if (!text || /^yes$/i.test(text)) {
                return 'Refundable';
            }
        }
        if (refundableFlag === false || refundableFlag === 'false' || refundableFlag === 0 || refundableFlag === '0') {
            if (!text || /^no$/i.test(text)) {
                return 'Non-refundable';
            }
        }
        if (!text) {
            return 'Not specified';
        }
        var lower = text.toLowerCase();
        if (lower === 'refundable' || lower === 'yes' || lower === 'y' || lower === 'true') {
            return 'Refundable';
        }
        if (lower === 'non-refundable' || lower === 'nonrefundable' || lower === 'not refundable' || lower === 'no' || lower === 'false') {
            return 'Non-refundable';
        }
        var hasPermitted = /\bpermitted\b/i.test(text) || /\ballowed\b/i.test(text) || /\brefundable\b/i.test(text);
        var hasDenied = /\bnon[\s-]?refundable\b/i.test(text) || /\bnot\s+permitted\b/i.test(text) ||
            /\bno\s+refund\b/i.test(text) || /\bnot\s+allowed\b/i.test(text) || /\bforbidden\b/i.test(text);
        if (text.length > 32 || /;/.test(text) || /\b(before|after)\s+departure\b/i.test(text)) {
            if (hasPermitted && hasDenied) {
                return 'Check fare rules';
            }
            if (hasPermitted && !hasDenied) {
                return 'Refundable';
            }
            if (hasDenied && !hasPermitted) {
                return 'Non-refundable';
            }
            return 'Check fare rules';
        }
        if (hasDenied && !hasPermitted) {
            return 'Non-refundable';
        }
        if (hasPermitted && !hasDenied) {
            return 'Refundable';
        }
        if (hasDenied && hasPermitted) {
            return 'Check fare rules';
        }
        return 'Check fare rules';
    }

    function brandedFareCleanBenefitValue(raw) {
        var text = String(raw || '').trim().replace(/\s+/g, ' ');
        if (!text || brandedFareBenefitIsFareBasis('', text)) {
            return '';
        }
        var lower = text.toLowerCase();
        if (brandedFareValueMeansNotIncluded(text)) {
            return 'Not included';
        }
        if (lower === 'n/a' || lower === 'na' || lower === 'nil' || lower === 'none' || lower === '—' || lower === '-' || lower === 'no') {
            return '';
        }
        if (lower === 'yes' || lower === 'y') {
            return 'Included';
        }
        if (lower === 'non refundable' || lower === 'nonrefundable' || lower === 'not refundable') {
            return 'Non-refundable';
        }
        text = text.replace(/(\d+(?:\.\d+)?)\s*kg\b/gi, function (match, amount) {
            return amount + ' kg';
        });
        if (/^0\s*kg$/i.test(text)) {
            return 'Not included';
        }
        text = text.replace(/(\d+)\s*(?:pc|pcs|piece|pieces)\b/gi, function (match, amount) {
            var pieceLabel = Number(amount) === 1 ? 'piece' : 'pieces';
            return amount + ' ' + pieceLabel;
        });
        if (/^0\s*(?:pc|piece)/i.test(text)) {
            return 'Not included';
        }
        if (text.length > 56) {
            text = text.slice(0, 53).trim() + '…';
        }
        return text;
    }

    function brandedFareNormalizeBenefitKey(label) {
        var norm = normalizeFareLabel(label);
        if (!norm) {
            return null;
        }
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
        if (!raw) {
            return '';
        }
        if (brandedFareValueMeansNotIncluded(raw)) {
            return 'Not included';
        }
        if (/\badditional\s+cost\b/i.test(raw) || /\bat\s+(?:extra\s+)?(?:cost|charge)\b/i.test(raw) || /\bpay(?:able)?\b/i.test(raw)) {
            return 'Additional cost';
        }
        if (/\bnon[\s-]?refundable\b/i.test(raw)) {
            return 'Non-refundable';
        }
        if (/\brefundable\b/i.test(raw)) {
            return 'Refundable';
        }
        if (/\b(?:meal|food|snack|sandwich)[\s\w-]*included\b/i.test(raw) || /\bincluded\s+(?:meal|food|snack)\b/i.test(raw)) {
            return 'Included';
        }
        var kgMatch = raw.match(/(\d+(?:\.\d+)?)\s*kg/i);
        if (kgMatch) {
            return kgMatch[1] + ' kg';
        }
        var pcMatch = raw.match(/(\d+)\s*(?:pc|pcs|piece|pieces)/i);
        if (pcMatch) {
            var pcLabel = Number(pcMatch[1]) === 1 ? 'piece' : 'pieces';
            return pcMatch[1] + ' ' + pcLabel;
        }
        return raw;
    }

    function brandedFareParseBenefitLine(raw) {
        var text = String(raw || '').trim();
        if (!text || brandedFareBenefitIsFareBasis('', text)) {
            return null;
        }
        var colon = text.indexOf(':');
        if (colon > 0 && colon < text.length - 1) {
            return {
                label: text.slice(0, colon).trim(),
                value: text.slice(colon + 1).trim(),
            };
        }
        var key = brandedFareNormalizeBenefitKey(text);
        if (!key) {
            return null;
        }
        return {
            label: text,
            value: brandedFareExtractValueFromBenefitLine(text),
        };
    }

    function brandedFareBenefitFallback(rowKey, hintText) {
        var hint = String(hintText || '').toLowerCase();
        if (rowKey === 'meal') {
            if (/\bcharge\b/.test(hint) || /\bpaid\b/.test(hint) || /\bbuy\b/.test(hint) || /\bat\s+cost\b/.test(hint)) {
                return 'Additional cost';
            }
        }
        if (rowKey === 'check_in' && brandedFareValueMeansNotIncluded(hint)) {
            return 'Not included';
        }
        if (rowKey === 'refund' && !hint) {
            return 'Check fare rules';
        }
        return NOT_PROVIDED_LABEL;
    }

    function brandedFareBenefitRows(opt) {
        var resolved = {
            carry_on: '',
            check_in: '',
            meal: '',
            refund: '',
        };
        var hints = {
            carry_on: '',
            check_in: '',
            meal: '',
            refund: '',
        };

        function setRow(rowKey, value, hint) {
            if (resolved[rowKey]) {
                return;
            }
            var clean = rowKey === 'refund'
                ? normalizeRefundSummaryForCard(value, null)
                : brandedFareCleanBenefitValue(value);
            if (!clean || (rowKey === 'refund' && clean === 'Not specified')) {
                if (rowKey === 'refund' && value) {
                    hints.refund = hints.refund ? (hints.refund + ' ' + String(value).trim()) : String(value).trim();
                }
                if (hint) {
                    hints[rowKey] = hints[rowKey] ? (hints[rowKey] + ' ' + String(hint)) : String(hint);
                }
                return;
            }
            resolved[rowKey] = clean;
            if (hint) {
                hints[rowKey] = String(hint);
            }
        }

        function absorbLabelValue(label, value) {
            var rowKey = brandedFareNormalizeBenefitKey(label);
            if (!rowKey) {
                return;
            }
            setRow(rowKey, value, label + ' ' + value);
        }

        function scanLines(lines) {
            (lines || []).forEach(function (line) {
                var parsed = brandedFareParseBenefitLine(line);
                if (!parsed) {
                    return;
                }
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

        var refundRaw = brandedFareFieldValue(opt, ['refundable_display', 'refund_rule', 'refund']) ||
            resolved.refund ||
            hints.refund ||
            '';
        var refundSummary = normalizeRefundSummaryForCard(refundRaw, opt.refundable);
        if (refundSummary === 'Not specified') {
            refundSummary = brandedFareBenefitFallback('refund', hints.refund);
            if (refundSummary === NOT_PROVIDED_LABEL) {
                refundSummary = 'Not specified';
            }
        }

        var compactRows = [
            ['Carry-on baggage', resolved.carry_on || brandedFareBenefitFallback('carry_on', hints.carry_on), 'carry'],
            ['Check-in baggage', resolved.check_in || brandedFareBenefitFallback('check_in', hints.check_in), 'checked'],
            ['Meal', resolved.meal || brandedFareBenefitFallback('meal', hints.meal), 'meal'],
            ['Refund', refundSummary, 'refund'],
        ];

        return compactRows.map(function (item) {
            return brandedFareBenefitRow(item[0], item[1], item[2]);
        }).join('');
    }

    function brandedFareHasDisplayPrice(opt) {
        if (!opt) {
            return false;
        }
        if (opt.displayed_price != null && isFinite(Number(opt.displayed_price)) && Number(opt.displayed_price) > 0) {
            return true;
        }
        var priceDisplay = opt.price_display ? String(opt.price_display).replace(/^Approx\.\s*/i, '').trim() : '';
        return priceDisplay !== '' && priceDisplay !== '—' && priceDisplay !== '-' && priceDisplay !== '0';
    }

    function brandedFareHasDisplayName(opt) {
        if (!opt) {
            return false;
        }
        var name = String(opt.name || '').trim();
        return name !== '' && name !== '—' && name !== '-' && !/^placeholder$/i.test(name);
    }

    function brandedFareIsPlaceholder(opt) {
        if (!opt) {
            return true;
        }
        if (opt.is_synthetic_default) {
            return !brandedFareHasDisplayName(opt);
        }
        if (!brandedFareHasDisplayName(opt)) {
            return true;
        }
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
            if (brandedFareIsPlaceholder(opt)) {
                return;
            }
            var dedupeKey = brandedFareDedupeKey(opt);
            if (seen[dedupeKey]) {
                return;
            }
            seen[dedupeKey] = true;
            rendered.push(opt);
        });
        return rendered;
    }

    function brandedFaresHideCarouselNav(panel) {
        if (!panel) {
            return;
        }
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
        if (!panel || !grid) {
            return;
        }
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
        if (!panel) {
            return;
        }
        var carousel = panel.querySelector('[data-branded-fares-carousel]');
        if (!carousel) {
            return;
        }
        var viewport = carousel.querySelector('.ota-branded-fares-carousel__viewport');
        if (!viewport) {
            return;
        }
        var renderedCount = parseInt(panel.getAttribute('data-rendered-fare-count') || '0', 10);
        var hideNav = renderedCount <= 3 || viewport.scrollWidth <= viewport.clientWidth + 2;
        carousel.setAttribute('data-nav-hidden', hideNav ? 'true' : 'false');
        carousel.querySelectorAll('[data-branded-fares-prev], [data-branded-fares-next]').forEach(function (btn) {
            btn.hidden = hideNav;
            btn.style.display = hideNav ? 'none' : '';
        });
    }

    function normalizeBrandedFaresPanel(panel) {
        if (!panel) {
            return;
        }
        var grid = panel.querySelector('[data-branded-fares-grid]');
        if (!grid) {
            return;
        }
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
        if (!viewport) {
            return;
        }
        var grid = viewport.querySelector('[data-branded-fares-grid]');
        var cards = grid ? grid.querySelectorAll('.ota-branded-fare-card') : viewport.querySelectorAll('.ota-branded-fare-card');
        if (!cards.length) {
            return;
        }
        var gap = parseFloat(window.getComputedStyle(grid || viewport).gap || '10') || 10;
        var step = cards[0].offsetWidth + gap;
        if (!step) {
            return;
        }
        var currentIndex = parseInt(carousel.getAttribute('data-carousel-index') || '0', 10);
        if (!isFinite(currentIndex) || currentIndex < 0) {
            currentIndex = 0;
        }
        var nextIndex = currentIndex + direction;
        if (nextIndex < 0) {
            nextIndex = cards.length - 1;
        } else if (nextIndex >= cards.length) {
            nextIndex = 0;
        }
        carousel.setAttribute('data-carousel-index', String(nextIndex));
        viewport.scrollTo({ left: nextIndex * step, behavior: 'smooth' });
    }

    function offerHasFareChoicePanel(offer) {
        if (!offer) {
            return false;
        }
        if (offer.has_fare_choice_options || offer.has_synthetic_default_fare || offer.universal_fare_selection_active) {
            return (offer.fare_family_options_display || []).length > 0;
        }

        return !!(offer.branded_fares_display_enabled && offer.has_branded_fares);
    }

    function selectableFareOptions(offer) {
        return (offer.fare_family_options_display || []).filter(function (opt) {
            return opt && opt.selectable !== false && opt.display_only !== true;
        });
    }

    function offerSingleDirectFareOption(offer) {
        if (!offer) {
            return null;
        }
        var opts = selectableFareOptions(offer);

        return opts.length === 1 ? opts[0] : null;
    }

    function offerAllowsDirectCardContinue(offer) {
        return offerSingleDirectFareOption(offer) !== null;
    }

    function offerNeedsFareChoiceBeforeCheckout(offer) {
        if (offerAllowsDirectCardContinue(offer)) {
            return false;
        }
        if (!offer) {
            return false;
        }
        if (offer.has_fare_choice_options || offer.has_synthetic_default_fare || offer.universal_fare_selection_active) {
            return (offer.fare_family_options_display || []).length > 0;
        }
        if (!offer.branded_fares_selection_active || !offer.has_branded_fares) {
            return false;
        }

        return (offer.fare_family_options_display || []).length > 0;
    }

    function isGroupedFareOption(offer, fareOptionKey) {
        if (!offer || !fareOptionKey) {
            return false;
        }
        var opts = offer.fare_family_options_display || [];
        for (var i = 0; i < opts.length; i++) {
            var opt = opts[i];
            if (opt && opt.option_key === fareOptionKey) {
                return !!opt.is_grouped_offer_option;
            }
        }

        return false;
    }

    function isSyntheticDefaultFareOption(offer, fareOptionKey) {
        if (!offer || !fareOptionKey) {
            return false;
        }
        var opts = offer.fare_family_options_display || [];
        for (var i = 0; i < opts.length; i++) {
            var opt = opts[i];
            if (opt && opt.option_key === fareOptionKey) {
                return !!opt.is_synthetic_default;
            }
        }

        return false;
    }

    function buildPanelHtml(offer, state) {
        if (!offer || !offerHasFareChoicePanel(offer)) {
            return '';
        }
        var opts = offer.fare_family_options_display || offer.branded_fares_display_options || [];
        var renderedFareOptions = buildRenderedFareOptions(opts);
        if (!renderedFareOptions.length) {
            return '';
        }
        var selectionActive = !!(offer.branded_fares_selection_active || offer.universal_fare_selection_active);
        var selectedKey = state.selectedFareOptionByOfferId[offer.offer_id] || '';
        var isExpanded = !!state.expandedBrandedFaresByOfferId[offer.offer_id];
        var renderedCount = renderedFareOptions.length;
        var useSlider = renderedCount > 3;
        var cards = renderedFareOptions.map(function (opt) {
            var key = String(opt.option_key || '');
            var isSelected = selectionActive && selectedKey === key;
            var cardClass = 'ota-branded-fare-card' +
                (opt.is_synthetic_default ? ' ota-branded-fare-card--compact-default' : '') +
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
                : (renderedCount === 1
                    ? '<button type="button" class="ota-branded-fare-card__cta btn btn-primary" data-fare-option-card data-offer-id="' + esc(offer.offer_id || '') + '" data-fare-option-key="' + esc(key) + '">Continue</button>'
                    : '');
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
        if (!useSlider && renderedCount === 1 && renderedFareOptions[0] && renderedFareOptions[0].is_synthetic_default) {
            gridClass += ' ota-branded-fares-panel__grid--single-default';
        }
        var gridCountAttr = useSlider ? '' : (' data-fare-count="' + renderedCount + '"');
        var gridHtml = '<div class="' + gridClass + '" data-branded-fares-grid' + gridCountAttr + '>' + cards + '</div>';
        var bodyInner = useSlider
            ? ('<div class="ota-branded-fares-carousel" data-branded-fares-carousel data-carousel-index="0" data-nav-hidden="false">' +
                '<button type="button" class="ota-branded-fares-carousel__nav ota-branded-fares-carousel__nav--prev" data-branded-fares-prev aria-label="Previous fare options"><span aria-hidden="true">‹</span></button>' +
                '<div class="ota-branded-fares-carousel__viewport">' + gridHtml + '</div>' +
                '<button type="button" class="ota-branded-fares-carousel__nav ota-branded-fares-carousel__nav--next" data-branded-fares-next aria-label="Next fare options"><span aria-hidden="true">›</span></button>' +
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

    function setBrandedFaresExpanded(card, oid, expanded, state) {
        if (!card || !oid || !state) {
            return;
        }
        state.expandedBrandedFaresByOfferId[oid] = !!expanded;
        card.classList.toggle('is-fare-options-open', !!expanded);
        var summary = card.querySelector('[data-flight-card-summary]');
        if (summary) {
            summary.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
        var panel = card.querySelector('[data-branded-fares-panel]');
        if (!panel) {
            return;
        }
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
        if (expanded) {
            normalizeBrandedFaresPanel(panel);
        }
    }

    function toggleBrandedFaresExpand(card, oid, state) {
        if (!card || !oid || !state) {
            return;
        }
        var isOpen = !!state.expandedBrandedFaresByOfferId[oid];
        setBrandedFaresExpanded(card, oid, !isOpen, state);
    }

    function clearOtherOfferFareSelections(state, activeOfferId) {
        Object.keys(state.selectedFareOptionByOfferId).forEach(function (oid) {
            if (oid !== activeOfferId) {
                delete state.selectedFareOptionByOfferId[oid];
            }
        });
    }

    function offerRequiresFareFamilySelection(offer) {
        return offerNeedsFareChoiceBeforeCheckout(offer);
    }

    function selectBrandedFareOption(flightCard, oid, key, state) {
        if (!flightCard || !oid || !key || !state) {
            return;
        }
        var offer = state.offersById[oid];
        if (!offer || !offerRequiresFareFamilySelection(offer)) {
            return;
        }
        clearOtherOfferFareSelections(state, oid);
        state.selectedFareOptionByOfferId[oid] = state.selectedFareOptionByOfferId[oid] === key ? '' : key;
    }

    function refreshBrandedFareSelectionUi(offerId, state, listEl) {
        if (!listEl || !state) {
            return;
        }
        var card = listEl.querySelector('[data-flight-card][data-offer-id="' + offerId + '"]');
        if (!card) {
            return;
        }
        var offer = state.offersById[offerId];
        var selectedKey = state.selectedFareOptionByOfferId[offerId] || '';
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
            var dp = cardDisplayPrice(offer, state);
            priceEl.textContent = dp != null ? formatCardButtonRs(dp) : 'Fare unavailable';
        }
        if (hasSelection) {
            var hint = card.querySelector('.ota-fare-family-selection-hint');
            if (hint) {
                hint.hidden = true;
            }
        }
    }

    function navigateToCheckoutWithFareKey(selectUrl, offerId, fareOptionKey) {
        if (!selectUrl || !offerId || !fareOptionKey) {
            return;
        }
        try {
            var url = new URL(selectUrl, window.location.origin);
            url.searchParams.set('offer_id', offerId);
            url.searchParams.set('flight_id', offerId);
            url.searchParams.set('fare_option_key', fareOptionKey);
            window.location.href = url.toString();
        } catch (err) {
            var sep = selectUrl.indexOf('?') >= 0 ? '&' : '?';
            window.location.href = selectUrl + sep +
                'offer_id=' + encodeURIComponent(offerId) +
                '&flight_id=' + encodeURIComponent(offerId) +
                '&fare_option_key=' + encodeURIComponent(fareOptionKey);
        }
    }

    function promptFareFamilySelection(card, offer, state) {
        if (!card || !offer || !state) {
            return;
        }
        var oid = offer.offer_id || '';
        setBrandedFaresExpanded(card, oid, true, state);
        var panel = card.querySelector('[data-branded-fares-panel]');
        if (panel) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        var hint = card.querySelector('.ota-fare-family-selection-hint');
        if (hint) {
            hint.hidden = false;
            hint.setAttribute('aria-live', 'polite');
        }
    }

    function resolveListEl(listEl, callbacks) {
        if (listEl) {
            return listEl;
        }
        if (callbacks && typeof callbacks.getList === 'function') {
            return callbacks.getList();
        }
        return null;
    }

    function bindAll(listEl, state, callbacks) {
        callbacks = callbacks || {};
        var list = resolveListEl(listEl, callbacks);
        if (!list || !state) {
            return;
        }

        if (list.getAttribute('data-bound-branded-fare') !== '1') {
            list.setAttribute('data-bound-branded-fare', '1');
            list.addEventListener('click', function (e) {
                if (e.target.closest('[data-fare-summary-open], [data-branded-fares-toggle], [data-flight-details-open], [data-branded-fares-prev], [data-branded-fares-next]')) {
                    return;
                }
                var fareWrap = e.target.closest('[data-fare-option-card-wrap]');
                var fareBtn = e.target.closest('[data-fare-option-card]');
                if (fareBtn && fareBtn.closest('[data-fare-summary-open]')) {
                    return;
                }
                var fareTarget = fareWrap || fareBtn;
                if (!fareTarget || !list.contains(fareTarget)) {
                    return;
                }
                var flightCard = fareTarget.closest('[data-flight-card]');
                if (!flightCard) {
                    return;
                }
                var oid = fareTarget.getAttribute('data-offer-id') || flightCard.getAttribute('data-offer-id') || '';
                var key = fareTarget.getAttribute('data-fare-option-key') || fareTarget.getAttribute('data-option-key') || '';
                if (!oid || !key) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                if (typeof callbacks.onBrandedFareSelect === 'function') {
                    selectBrandedFareOption(flightCard, oid, key, state);
                    refreshBrandedFareSelectionUi(oid, state, list);
                    callbacks.onBrandedFareSelect(oid, key, state.offersById[oid], flightCard, fareBtn || fareWrap);
                    return;
                }
                selectBrandedFareOption(flightCard, oid, key, state);
                refreshBrandedFareSelectionUi(oid, state, list);
            });
            list.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                var fareWrap = e.target.closest('[data-fare-option-card-wrap][role="button"]');
                if (!fareWrap || !list.contains(fareWrap)) {
                    return;
                }
                e.preventDefault();
                var flightCard = fareWrap.closest('[data-flight-card]');
                if (!flightCard) {
                    return;
                }
                var oid = fareWrap.getAttribute('data-offer-id') || flightCard.getAttribute('data-offer-id') || '';
                var key = fareWrap.getAttribute('data-fare-option-key') || fareWrap.getAttribute('data-option-key') || '';
                e.stopPropagation();
                if (typeof callbacks.onBrandedFareSelect === 'function') {
                    selectBrandedFareOption(flightCard, oid, key, state);
                    refreshBrandedFareSelectionUi(oid, state, list);
                    callbacks.onBrandedFareSelect(oid, key, state.offersById[oid], flightCard, fareWrap);
                    return;
                }
                selectBrandedFareOption(flightCard, oid, key, state);
                refreshBrandedFareSelectionUi(oid, state, list);
            });
        }

        if (list.getAttribute('data-bound-branded-toggle') !== '1') {
            list.setAttribute('data-bound-branded-toggle', '1');
            list.addEventListener('click', function (e) {
                var toggle = e.target.closest('[data-branded-fares-toggle]');
                if (!toggle || !list.contains(toggle)) {
                    return;
                }
                e.preventDefault();
                var card = toggle.closest('[data-flight-card]');
                var oid = toggle.getAttribute('data-offer-id') || (card ? card.getAttribute('data-offer-id') : '');
                if (!card || !oid) {
                    return;
                }
                toggleBrandedFaresExpand(card, oid, state);
            });
        }

        if (list.getAttribute('data-bound-card-fare-expand') !== '1') {
            list.setAttribute('data-bound-card-fare-expand', '1');
            list.addEventListener('click', function (e) {
                if (e.target.closest('a, button, [data-fare-summary-open], [data-flight-details-open], [data-fare-option-card], [data-book-now], [data-branded-fares-panel], .ota-branded-fares-panel, .ota-result-action-meta, .ota-result-card-v3__flight-details-row')) {
                    return;
                }
                var card = e.target.closest('[data-flight-card][data-has-branded-fares]');
                if (!card || !list.contains(card)) {
                    return;
                }
                var summary = e.target.closest('[data-flight-card-summary]');
                if (!summary || !card.contains(summary)) {
                    return;
                }
                var oid = card.getAttribute('data-offer-id') || '';
                if (!oid) {
                    return;
                }
                toggleBrandedFaresExpand(card, oid, state);
            });
            list.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                var summary = e.target.closest('[data-flight-card-summary]');
                if (!summary || !list.contains(summary)) {
                    return;
                }
                var card = summary.closest('[data-flight-card][data-has-branded-fares]');
                if (!card) {
                    return;
                }
                e.preventDefault();
                var oid = card.getAttribute('data-offer-id') || '';
                if (!oid) {
                    return;
                }
                toggleBrandedFaresExpand(card, oid, state);
            });
        }

        if (list.getAttribute('data-bound-book-selected-fare') !== '1') {
            list.setAttribute('data-bound-book-selected-fare', '1');
            list.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-book-selected-fare]');
                if (!btn || !list.contains(btn) || btn.disabled) {
                    return;
                }
                e.preventDefault();
                var flightCard = btn.closest('[data-flight-card]');
                var oid = flightCard ? flightCard.getAttribute('data-offer-id') : '';
                var offer = oid ? state.offersById[oid] : null;
                var selectedKey = oid ? (state.selectedFareOptionByOfferId[oid] || '') : '';
                if (!selectedKey) {
                    var selectedCard = flightCard ? flightCard.querySelector('[data-fare-option-card].is-selected') : null;
                    selectedKey = selectedCard
                        ? (selectedCard.getAttribute('data-fare-option-key') || selectedCard.getAttribute('data-option-key') || '')
                        : '';
                }
                if (!offer || !selectedKey) {
                    return;
                }
                if (typeof callbacks.onBookSelectedFare === 'function') {
                    callbacks.onBookSelectedFare(oid, selectedKey, offer);
                    return;
                }
                if (offer.select_url) {
                    navigateToCheckoutWithFareKey(offer.select_url, oid, selectedKey);
                }
            });
        }

        if (list.getAttribute('data-bound-book-now') !== '1') {
            list.setAttribute('data-bound-book-now', '1');
            list.addEventListener('click', function (e) {
                var link = e.target.closest('a[data-book-now]');
                if (!link || !list.contains(link)) {
                    return;
                }
                var card = link.closest('[data-flight-card]');
                var oid = card ? card.getAttribute('data-offer-id') : '';
                var offer = oid ? state.offersById[oid] : null;
                if (!offer || !offerRequiresFareFamilySelection(offer)) {
                    return;
                }
                e.preventDefault();
                var selectedKey = oid ? (state.selectedFareOptionByOfferId[oid] || '') : '';
                if (!state.expandedBrandedFaresByOfferId[oid]) {
                    setBrandedFaresExpanded(card, oid, true, state);
                    if (!selectedKey) {
                        var hint = card.querySelector('.ota-fare-family-selection-hint');
                        if (hint) {
                            hint.hidden = false;
                            hint.setAttribute('aria-live', 'polite');
                        }
                    }
                    return;
                }
                if (!selectedKey) {
                    promptFareFamilySelection(card, offer, state);
                    return;
                }
                if (typeof callbacks.onBookSelectedFare === 'function') {
                    callbacks.onBookSelectedFare(oid, selectedKey, offer);
                    return;
                }
                navigateToCheckoutWithFareKey(link.href, oid, selectedKey);
            });
        }

        if (list.getAttribute('data-bound-split-outbound-book') !== '1') {
            list.setAttribute('data-bound-split-outbound-book', '1');
            list.addEventListener('click', function (e) {
                var link = e.target.closest('[data-split-select-outbound]');
                if (!link || !list.contains(link)) {
                    return;
                }
                var card = link.closest('[data-flight-card]');
                var oid = link.getAttribute('data-outbound-key') || (card ? card.getAttribute('data-offer-id') : '');
                var offer = oid ? state.offersById[oid] : null;
                if (!offer || !offerRequiresFareFamilySelection(offer)) {
                    if (typeof callbacks.onOutboundNavigate === 'function') {
                        e.preventDefault();
                        callbacks.onOutboundNavigate(link, offer || { outbound_key: oid, offer_id: oid }, '');
                    }
                    return;
                }
                var selectedKey = state.selectedFareOptionByOfferId[oid] || '';
                if (!state.expandedBrandedFaresByOfferId[oid]) {
                    e.preventDefault();
                    e.stopPropagation();
                    setBrandedFaresExpanded(card, oid, true, state);
                    if (!selectedKey) {
                        var hint = card ? card.querySelector('.ota-fare-family-selection-hint') : null;
                        if (hint) {
                            hint.hidden = false;
                            hint.setAttribute('aria-live', 'polite');
                        }
                    }
                    return;
                }
                if (!selectedKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    promptFareFamilySelection(card, offer, state);
                    return;
                }
                if (typeof callbacks.onOutboundNavigate === 'function') {
                    e.preventDefault();
                    e.stopPropagation();
                    callbacks.onOutboundNavigate(link, offer, selectedKey);
                }
            });
        }

        if (list.getAttribute('data-bound-split-return-cta') !== '1') {
            list.setAttribute('data-bound-split-return-cta', '1');
            list.addEventListener('click', function (e) {
                var btn = e.target.closest('.ota-return-split-card__form button[type="submit"]');
                if (!btn || !list.contains(btn) || btn.disabled) {
                    return;
                }
                var form = btn.closest('.ota-return-split-card__form');
                var card = form ? form.closest('[data-flight-card]') : null;
                if (!card || card.getAttribute('data-split-leg') !== 'return') {
                    return;
                }
                var oid = card.getAttribute('data-offer-id') || '';
                var offer = oid ? state.offersById[oid] : null;
                var fareInput = form ? form.querySelector('[data-split-fare-option-key]') : null;
                if (fareInput) {
                    fareInput.value = getSelectedFareKey(oid, state) || '';
                }
                if (!offer || !offerRequiresFareFamilySelection(offer)) {
                    if (typeof callbacks.onReturnCheckout === 'function') {
                        e.preventDefault();
                        callbacks.onReturnCheckout(card, '', offer, btn);
                    }
                    return;
                }
                var selectedKey = getSelectedFareKey(oid, state) || '';
                if (!selectedKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    promptFareFamilySelection(card, offer, state);
                    return;
                }
                if (fareInput) {
                    fareInput.value = selectedKey;
                }
                if (typeof callbacks.onReturnCheckout === 'function') {
                    e.preventDefault();
                    e.stopPropagation();
                    callbacks.onReturnCheckout(card, selectedKey, offer, btn);
                }
            });
        }

        if (list.getAttribute('data-bound-branded-carousel') !== '1') {
            list.setAttribute('data-bound-branded-carousel', '1');
            list.addEventListener('click', function (e) {
                var prev = e.target.closest('[data-branded-fares-prev]');
                var next = e.target.closest('[data-branded-fares-next]');
                if (!prev && !next) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                var carousel = (prev || next).closest('[data-branded-fares-carousel]');
                if (!carousel || !list.contains(carousel)) {
                    return;
                }
                brandedFaresCarouselStep(carousel, prev ? -1 : 1);
            });
            if (!global.__otaBrandedFaresResizeBound) {
                global.__otaBrandedFaresResizeBound = true;
                var resizeTimer = null;
                window.addEventListener('resize', function () {
                    if (resizeTimer) {
                        clearTimeout(resizeTimer);
                    }
                    resizeTimer = setTimeout(function () {
                        normalizeAllBrandedFaresPanels(document);
                    }, 120);
                });
            }
        }
    }

    global.OtaBrandedFares = {
        init: init,
        createState: createState,
        registerOffer: registerOffer,
        esc: esc,
        formatRs: formatRs,
        formatCardButtonRs: formatCardButtonRs,
        formatBrandedFarePrice: formatBrandedFarePrice,
        payloadAttr: payloadAttr,
        buildFareSummaryPayload: buildFareSummaryPayload,
        brandedFareBenefitRows: brandedFareBenefitRows,
        brandedFareBenefitRow: brandedFareBenefitRow,
        brandedFareFieldValue: brandedFareFieldValue,
        brandedFareCleanBenefitValue: brandedFareCleanBenefitValue,
        normalizeRefundSummaryForCard: normalizeRefundSummaryForCard,
        brandedFareNormalizeBenefitKey: brandedFareNormalizeBenefitKey,
        brandedFareBenefitFallback: brandedFareBenefitFallback,
        brandedFareParseBenefitLine: brandedFareParseBenefitLine,
        brandedFareIsPlaceholder: brandedFareIsPlaceholder,
        brandedFareDedupeKey: brandedFareDedupeKey,
        buildRenderedFareOptions: buildRenderedFareOptions,
        buildPanelHtml: buildPanelHtml,
        normalizeBrandedFaresPanel: normalizeBrandedFaresPanel,
        normalizeAllBrandedFaresPanels: normalizeAllBrandedFaresPanels,
        brandedFaresCarouselStep: brandedFaresCarouselStep,
        brandedFaresForceGridMode: brandedFaresForceGridMode,
        brandedFaresSyncCarouselNav: brandedFaresSyncCarouselNav,
        brandedFaresHideCarouselNav: brandedFaresHideCarouselNav,
        cardDisplayPrice: cardDisplayPrice,
        isExpanded: isExpanded,
        getSelectedFareKey: getSelectedFareKey,
        handleOutboundSelectClick: handleOutboundSelectClick,
        setBrandedFaresExpanded: setBrandedFaresExpanded,
        toggleBrandedFaresExpand: toggleBrandedFaresExpand,
        refreshBrandedFareSelectionUi: refreshBrandedFareSelectionUi,
        selectBrandedFareOption: selectBrandedFareOption,
        offerRequiresFareFamilySelection: offerRequiresFareFamilySelection,
        offerNeedsFareChoiceBeforeCheckout: offerNeedsFareChoiceBeforeCheckout,
        offerAllowsDirectCardContinue: offerAllowsDirectCardContinue,
        offerSingleDirectFareOption: offerSingleDirectFareOption,
        selectableFareOptions: selectableFareOptions,
        offerHasFareChoicePanel: offerHasFareChoicePanel,
        isSyntheticDefaultFareOption: isSyntheticDefaultFareOption,
        isGroupedFareOption: isGroupedFareOption,
        promptFareFamilySelection: promptFareFamilySelection,
        navigateToCheckoutWithFareKey: navigateToCheckoutWithFareKey,
        bindAll: bindAll,
    };
}(typeof window !== 'undefined' ? window : this));
