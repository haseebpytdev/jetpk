(function () {
    'use strict';

    var shell = document.querySelector('[data-testid="ota-mobile-app-shell"]');
    if (!shell) {
        return;
    }

    shell.setAttribute('data-mobile-app-ready', 'true');

    initMobileHome();
    initMobileResults();
    initMobileBooking();
    initMobileFlightDetails();

    function esc(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
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

    /**
     * RETURN-SPLIT-SELECT-R2 — shared mobile outbound/return split-leg card builder.
     * Exposed for mobile return-options inline script (loads after defer).
     */
    window.OtaMobileSplitCards = {
        buildSplitLegCard: buildMobileSplitLegCard,
        buildOutboundSummaryHtml: buildMobileOutboundSummaryHtml,
        bindSplitCardDetails: bindMobileSplitCardDetails,
    };

    function mobileSplitBaggageLine(option) {
        var checked = String(option.baggage_checked_display || '').trim();
        var cabin = String(option.baggage_cabin_display || '').trim();
        var summary = String(option.baggage_summary_display || option.baggage || '').trim();
        if (checked && cabin) {
            return 'Checked: ' + checked + ' · Cabin: ' + cabin;
        }
        return summary;
    }

    function mobileSplitChipHtml(option) {
        var chips = [];
        var cabin = String(option.cabin || option.fare_family || '').trim();
        if (cabin) {
            chips.push('<span class="ota-mobile-result-card__chip">' + esc(cabin) + '</span>');
        }
        if (option.refundable === true) {
            chips.push('<span class="ota-mobile-result-card__chip ota-mobile-result-card__chip--ok">Refundable</span>');
        } else if (option.refundable === false) {
            chips.push('<span class="ota-mobile-result-card__chip ota-mobile-result-card__chip--warn">Non-refundable</span>');
        }
        if (!chips.length) {
            return '';
        }
        return '<div class="ota-mobile-result-card__chips">' + chips.join('') + '</div>';
    }

    function mobileSplitDetailsHtml(option, detailsId) {
        var j = option && option.journey_display;
        if (!j) {
            return '';
        }
        var segs = j.segments_display || [];
        var segHtml = segs.map(function (seg, idx) {
            return '<li class="ota-mobile-split-detail__seg">' +
                '<span class="ota-mobile-split-detail__seg-label">Segment ' + (idx + 1) + '</span>' +
                '<span>' + esc(seg.origin || '') + ' → ' + esc(seg.destination || '') + '</span>' +
                (seg.flight_number ? '<span class="ota-mobile-split-detail__fn">' + esc(seg.flight_number) + '</span>' : '') +
                '</li>';
        }).join('');
        var bag = mobileSplitBaggageLine(option);
        var bagBlock = bag
            ? '<p class="ota-mobile-split-detail__baggage"><i class="fa fa-suitcase" aria-hidden="true"></i> ' + esc(bag) + '</p>'
            : '';

        return '<div id="' + esc(detailsId) + '" class="ota-mobile-split-detail" hidden>' +
            (segHtml ? '<ul class="ota-mobile-split-detail__list">' + segHtml + '</ul>' : '') +
            bagBlock +
            '</div>';
    }

    function bindMobileSplitCardDetails(listEl) {
        if (!listEl) {
            return;
        }
        Array.prototype.forEach.call(listEl.querySelectorAll('[data-mobile-split-details-toggle]'), function (btn) {
            if (btn.getAttribute('data-bound') === '1') {
                return;
            }
            btn.setAttribute('data-bound', '1');
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var targetId = btn.getAttribute('aria-controls') || '';
                var panel = targetId ? document.getElementById(targetId) : null;
                if (!panel) {
                    return;
                }
                var open = panel.hasAttribute('hidden');
                if (open) {
                    panel.removeAttribute('hidden');
                    btn.setAttribute('aria-expanded', 'true');
                    btn.textContent = 'Hide details';
                } else {
                    panel.setAttribute('hidden', 'hidden');
                    btn.setAttribute('aria-expanded', 'false');
                    btn.textContent = 'View details';
                }
            });
        });
    }

    function mobileSplitFlightNumbers(journey) {
        var segs = (journey && journey.segments_display) || [];
        var nums = [];
        segs.forEach(function (seg) {
            var fn = String(seg.flight_number || '').trim();
            if (fn && nums.indexOf(fn) === -1) {
                nums.push(fn);
            }
        });

        return nums.join(', ');
    }

    function buildMobileOutboundSummaryHtml(journey, option) {
        option = option || {};
        if (!journey) {
            return '';
        }
        var logoUrl = option.airline_logo_url || '';
        var airlineCode = option.airline_code || '';
        var airlineName = option.airline_name || airlineCode || '';
        var logoInner = logoUrl
            ? '<img src="' + esc(logoUrl) + '" alt="" width="36" height="36" loading="lazy">'
            : esc(airlineCode || '—');
        var flightLine = mobileSplitFlightNumbers(journey);
        var flightHtml = flightLine
            ? '<p class="ota-mobile-split-summary__flights">' + esc(flightLine) + '</p>'
            : '';

        return '<div class="ota-mobile-split-summary">' +
            '<div class="ota-mobile-result-card__head">' +
            '<span class="ota-mobile-result-card__logo">' + logoInner + '</span>' +
            '<span class="ota-mobile-result-card__airline">' + esc(airlineName) + '</span>' +
            '</div>' +
            flightHtml +
            '<div class="ota-mobile-result-card__route ota-mobile-result-card__route--split">' +
            '<div class="ota-mobile-result-card__point">' +
            '<span class="ota-mobile-result-card__time">' + esc(journey.departure_time_display || '') + '</span>' +
            '<span class="ota-mobile-result-card__code">' + esc(journey.origin || '') + '</span>' +
            '</div>' +
            '<div class="ota-mobile-result-card__mid">' +
            '<span class="ota-mobile-result-card__dur">' + esc(journey.duration_display || '') + '</span>' +
            '<span class="ota-mobile-result-card__stops">' + esc(journey.stops_display || '') + '</span>' +
            '</div>' +
            '<div class="ota-mobile-result-card__point ota-mobile-result-card__point--arr">' +
            '<span class="ota-mobile-result-card__time">' + esc(journey.arrival_time_display || '') + '</span>' +
            '<span class="ota-mobile-result-card__code">' + esc(journey.destination || '') + '</span>' +
            '</div>' +
            '</div></div>';
    }

    function buildMobileSplitLegCard(option, config) {
        config = config || {};
        if (!option || !option.journey_display) {
            return '';
        }
        var j = option.journey_display;
        var modifier = config.modifier || 'ota-mobile-result-card--split-leg';
        var legLabel = config.legLabel || '';
        var cardKey = option.combo_id || option.outbound_key || 'split';
        var detailsId = 'mobile-split-details-' + String(cardKey).replace(/[^a-zA-Z0-9_-]/g, '-');
        var logoUrl = option.airline_logo_url || '';
        var airlineCode = option.airline_code || '';
        var airlineName = option.airline_name || airlineCode || '';
        var logoInner = logoUrl
            ? '<img src="' + esc(logoUrl) + '" alt="" width="36" height="36" loading="lazy">'
            : esc(airlineCode || '—');
        var flightLine = mobileSplitFlightNumbers(j);
        var flightHtml = flightLine
            ? '<p class="ota-mobile-result-card__flights">' + esc(flightLine) + '</p>'
            : '';
        var legLabelHtml = legLabel
            ? '<p class="ota-mobile-result-card__leg-label">' + esc(legLabel) + '</p>'
            : '';
        var deltaHtml = option.fare_delta_display
            ? '<p class="ota-mobile-result-card__delta">' + esc(option.fare_delta_display) + '</p>'
            : '';
        var priceLabel = config.priceLabel || '';
        if (!priceLabel) {
            var amount = config.priceAmount != null
                ? config.priceAmount
                : (option.total_amount || option.from_total_amount);
            if (amount != null && isFinite(Number(amount)) && Number(amount) > 0) {
                var pkrPrefix = config.priceFromPrefix ? 'From ' : '';
                priceLabel = pkrPrefix + 'PKR ' + Math.round(Number(amount)).toLocaleString('en-US');
            } else {
                priceLabel = option.from_total_display || option.total_display || 'Fare unavailable';
            }
        }
        var priceNote = config.priceNote || '';
        var priceNoteHtml = priceNote
            ? '<span class="ota-mobile-result-card__price-note">' + esc(priceNote) + '</span>'
            : '';
        var bagLine = mobileSplitBaggageLine(option);
        var bagHtml = bagLine
            ? '<div class="ota-mobile-result-card__baggage">' + esc(bagLine) + '</div>'
            : '';
        var chipsHtml = mobileSplitChipHtml(option);
        var detailsToggleHtml = '<button type="button" class="ota-mobile-result-card__details ota-mobile-result-card__details--toggle" data-mobile-split-details-toggle aria-expanded="false" aria-controls="' + esc(detailsId) + '">View details</button>';
        var priceHtml = '<div class="ota-mobile-result-card__footer">' +
            '<div class="ota-mobile-result-card__price-wrap">' +
            '<span class="ota-mobile-result-card__price">' + esc(priceLabel) + '</span>' +
            priceNoteHtml +
            deltaHtml +
            detailsToggleHtml +
            '</div>';
        var ctaHtml = '';
        if (config.ctaMode === 'form') {
            ctaHtml = '<form method="post" action="' + esc(config.formAction || '') + '" class="ota-mobile-result-card__form">' +
                '<input type="hidden" name="_token" value="' + esc(config.csrf || '') + '">' +
                '<input type="hidden" name="search_id" value="' + esc(config.searchId || '') + '">' +
                '<input type="hidden" name="combo_id" value="' + esc(option.combo_id || '') + '">' +
                '<input type="hidden" name="outbound_key" value="' + esc(config.outboundKey || '') + '">' +
                '<button type="submit" class="ota-mobile-result-card__cta btn btn-primary btn-block"' +
                (option.can_book === false ? ' disabled' : '') + '>' + esc(config.ctaLabel || 'Select') + '</button>' +
                '</form>';
        } else if (config.ctaMode === 'link') {
            ctaHtml = '<a class="ota-mobile-result-card__cta btn btn-primary btn-block" href="' + esc(config.linkHref || '#') + '">' +
                esc(config.ctaLabel || 'Select') + '</a>';
        }
        priceHtml += ctaHtml + '</div>';
        var dataAttrs = config.dataAttrs || '';

        return '<article class="ota-mobile-result-card ' + esc(modifier) + '"' + dataAttrs + '>' +
            legLabelHtml +
            '<div class="ota-mobile-result-card__head">' +
            '<span class="ota-mobile-result-card__logo">' + logoInner + '</span>' +
            '<div class="ota-mobile-result-card__carrier">' +
            '<span class="ota-mobile-result-card__airline">' + esc(airlineName) + '</span>' +
            flightHtml +
            '</div></div>' +
            '<div class="ota-mobile-result-card__route ota-mobile-result-card__route--split">' +
            '<div class="ota-mobile-result-card__point">' +
            '<span class="ota-mobile-result-card__time">' + esc(j.departure_time_display || '') + '</span>' +
            '<span class="ota-mobile-result-card__code">' + esc(j.origin || '') + '</span>' +
            '<span class="ota-mobile-result-card__date">' + esc(j.departure_date_display || '') + '</span>' +
            '</div>' +
            '<div class="ota-mobile-result-card__mid">' +
            '<span class="ota-mobile-result-card__dur">' + esc(j.duration_display || '') + '</span>' +
            '<span class="ota-mobile-result-card__arrow" aria-hidden="true">→</span>' +
            '<span class="ota-mobile-result-card__stops">' + esc(j.stops_display || '') + '</span>' +
            '</div>' +
            '<div class="ota-mobile-result-card__point ota-mobile-result-card__point--arr">' +
            '<span class="ota-mobile-result-card__time">' + esc(j.arrival_time_display || '') + '</span>' +
            '<span class="ota-mobile-result-card__code">' + esc(j.destination || '') + '</span>' +
            '<span class="ota-mobile-result-card__date">' + esc(j.arrival_date_display || '') + '</span>' +
            '</div></div>' +
            bagHtml +
            chipsHtml +
            mobileSplitDetailsHtml(option, detailsId) +
            priceHtml +
            '<div class="ota-mobile-result-card__source">' + buildFlightCardSourceBadgeHtml(option) + '</div>' +
            '</article>';
    }

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function isoFromParts(y, m, d) {
        return y + '-' + pad2(m + 1) + '-' + pad2(d);
    }

    function parseIso(iso) {
        if (!iso || typeof iso !== 'string') {
            return null;
        }
        var parts = iso.split('-');
        if (parts.length !== 3) {
            return null;
        }
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) - 1;
        var d = parseInt(parts[2], 10);
        var dt = new Date(y, m, d);
        if (dt.getFullYear() !== y || dt.getMonth() !== m || dt.getDate() !== d) {
            return null;
        }
        return dt;
    }

    function formatDateLabel(iso) {
        var dt = parseIso(iso);
        if (!dt) {
            return iso || '';
        }
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return dt.getDate() + ' ' + months[dt.getMonth()] + ', ' + dt.getFullYear();
    }

    function initMobileHome() {
        var home = document.querySelector('[data-testid="ota-mobile-home"]');
        if (!home) {
            return;
        }

        var form = home.querySelector('[data-mobile-search-form]');
        if (!form) {
            return;
        }

        var airportsSearchUrl = home.getAttribute('data-airports-search-url') || '/airports/search';
        var minDateIso = home.getAttribute('data-min-date') || isoFromParts(new Date().getFullYear(), new Date().getMonth(), new Date().getDate());

        var tripInput = form.querySelector('[data-trip-type-input]');
        var tripButtons = home.querySelectorAll('[data-trip-toggle]');
        var returnField = home.querySelector('[data-return-date-field]');
        var returnInput = form.querySelector('#ota-mobile-return');
        var returnLabel = home.querySelector('[data-date-label="return"]');
        var departInput = form.querySelector('#ota-mobile-depart');
        var departLabel = home.querySelector('[data-date-label="depart"]');
        var fromDisplay = form.querySelector('[data-airport-display="from"]');
        var toDisplay = form.querySelector('[data-airport-display="to"]');
        var fromCode = form.querySelector('[data-airport-code="from"]');
        var toCode = form.querySelector('[data-airport-code="to"]');
        var fromLabel = home.querySelector('[data-airport-label="from"]');
        var toLabel = home.querySelector('[data-airport-label="to"]');
        var swapBtn = home.querySelector('[data-swap-routes]');

        var airportSheet = home.querySelector('[data-mobile-airport-sheet]');
        var airportBackdrop = home.querySelector('[data-mobile-airport-backdrop]');
        var airportSearchInput = home.querySelector('[data-mobile-airport-search]');
        var airportResults = home.querySelector('[data-mobile-airport-results]');
        var airportHint = home.querySelector('[data-mobile-airport-hint]');
        var airportError = home.querySelector('[data-mobile-airport-error]');
        var airportFallback = home.querySelector('[data-mobile-airport-fallback]');
        var airportUseCode = home.querySelector('[data-mobile-airport-use-code]');
        var airportSheetTitle = home.querySelector('[data-mobile-airport-sheet-title]');
        var activeAirportField = null;
        var airportFetchCtrl = null;
        var airportDebounce = null;

        var calendarSheet = home.querySelector('[data-mobile-calendar-sheet]');
        var calendarBackdrop = home.querySelector('[data-mobile-calendar-backdrop]');
        var calendarMonths = home.querySelector('[data-mobile-calendar-months]');
        var calendarTitle = home.querySelector('[data-mobile-calendar-title]');
        var calendarSubtitle = home.querySelector('[data-mobile-calendar-subtitle]');
        var calendarRangeStart = null;
        var calendarRangeEnd = null;
        var calendarPickPhase = 0;

        var travellersTrigger = home.querySelector('[data-travellers-trigger]');
        var travellersSummary = home.querySelector('[data-travellers-summary]');
        var travellersSheet = home.querySelector('[data-mobile-travellers-sheet]');
        var travellersBackdrop = home.querySelector('[data-mobile-travellers-backdrop]');
        var adultsInput = form.querySelector('[data-travellers-adults-input]');
        var childrenInput = form.querySelector('[data-travellers-children-input]');
        var infantsInput = form.querySelector('[data-travellers-infants-input]');
        var cabinInput = form.querySelector('[data-travellers-cabin-input]');
        var travellersCounts = {
            adults: parseInt((adultsInput && adultsInput.value) || '1', 10),
            children: parseInt((childrenInput && childrenInput.value) || '0', 10),
            infants: parseInt((infantsInput && infantsInput.value) || '0', 10),
        };
        var travellersCabin = (cabinInput && cabinInput.value) || 'economy';
        var MAX_TRAVELLERS_TOTAL = 9;

        function travellersCabinLabel(val) {
            var map = {
                economy: 'Economy',
                premium_economy: 'Premium Economy',
                business: 'Business',
                first: 'First',
            };
            return map[val] || 'Economy';
        }

        function formatTravellersSummary(adults, children, infants, cabin) {
            var parts = [];
            parts.push(adults + ' Adult' + (adults === 1 ? '' : 's'));
            if (children > 0) {
                parts.push(children + ' Child' + (children === 1 ? '' : 'ren'));
            }
            if (infants > 0) {
                parts.push(infants + ' Infant' + (infants === 1 ? '' : 's'));
            }
            parts.push(travellersCabinLabel(cabin));
            return parts.join(', ');
        }

        function syncTravellersUi() {
            if (adultsInput) {
                adultsInput.value = String(travellersCounts.adults);
            }
            if (childrenInput) {
                childrenInput.value = String(travellersCounts.children);
            }
            if (infantsInput) {
                infantsInput.value = String(travellersCounts.infants);
            }
            if (cabinInput) {
                cabinInput.value = travellersCabin;
            }
            if (travellersSummary) {
                travellersSummary.textContent = formatTravellersSummary(
                    travellersCounts.adults,
                    travellersCounts.children,
                    travellersCounts.infants,
                    travellersCabin
                );
            }
            home.querySelectorAll('[data-travellers-count]').forEach(function (el) {
                var key = el.getAttribute('data-travellers-count');
                if (key && Object.prototype.hasOwnProperty.call(travellersCounts, key)) {
                    el.textContent = String(travellersCounts[key]);
                }
            });
            home.querySelectorAll('[data-travellers-cabin]').forEach(function (btn) {
                var active = btn.getAttribute('data-travellers-cabin') === travellersCabin;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            updateTravellersSteppers();
        }

        function travellersTotal() {
            return travellersCounts.adults + travellersCounts.children + travellersCounts.infants;
        }

        function canChangeTravellers(type, delta) {
            var next = travellersCounts[type] + delta;
            if (type === 'adults') {
                if (next < 1 || next > 9) {
                    return false;
                }
                var projected = next + travellersCounts.children + Math.min(travellersCounts.infants, next);
                if (projected > MAX_TRAVELLERS_TOTAL) {
                    return false;
                }
                return true;
            }
            if (type === 'children') {
                if (next < 0 || next > 8) {
                    return false;
                }
                return travellersCounts.adults + next + travellersCounts.infants <= MAX_TRAVELLERS_TOTAL;
            }
            if (type === 'infants') {
                if (next < 0 || next > travellersCounts.adults) {
                    return false;
                }
                return travellersCounts.adults + travellersCounts.children + next <= MAX_TRAVELLERS_TOTAL;
            }
            return false;
        }

        function changeTravellers(type, delta) {
            if (!canChangeTravellers(type, delta)) {
                return;
            }
            travellersCounts[type] += delta;
            if (type === 'adults' && travellersCounts.infants > travellersCounts.adults) {
                travellersCounts.infants = travellersCounts.adults;
            }
            syncTravellersUi();
        }

        function setTravellersCabin(val) {
            if (!val) {
                return;
            }
            travellersCabin = val;
            syncTravellersUi();
        }

        function updateTravellersSteppers() {
            home.querySelectorAll('[data-travellers-step]').forEach(function (btn) {
                var type = btn.getAttribute('data-travellers-step');
                var delta = parseInt(btn.getAttribute('data-travellers-delta') || '0', 10);
                var allowed = canChangeTravellers(type, delta);
                btn.disabled = !allowed;
                btn.classList.toggle('is-disabled', !allowed);
            });
        }

        function openTravellersSheet() {
            closeAirportSheet();
            closeCalendarSheet();
            if (travellersSheet) {
                travellersSheet.classList.add('is-open');
                travellersSheet.setAttribute('aria-hidden', 'false');
            }
            if (travellersBackdrop) {
                travellersBackdrop.classList.add('is-open');
                travellersBackdrop.setAttribute('aria-hidden', 'false');
            }
            if (travellersTrigger) {
                travellersTrigger.setAttribute('aria-expanded', 'true');
            }
            document.body.classList.add('ota-mobile-sheet-open');
            syncTravellersUi();
        }

        function closeTravellersSheet() {
            if (travellersSheet) {
                travellersSheet.classList.remove('is-open');
                travellersSheet.setAttribute('aria-hidden', 'true');
            }
            if (travellersBackdrop) {
                travellersBackdrop.classList.remove('is-open');
                travellersBackdrop.setAttribute('aria-hidden', 'true');
            }
            if (travellersTrigger) {
                travellersTrigger.setAttribute('aria-expanded', 'false');
            }
            if (!(
                (airportSheet && airportSheet.classList.contains('is-open')) ||
                (calendarSheet && calendarSheet.classList.contains('is-open'))
            )) {
                document.body.classList.remove('ota-mobile-sheet-open');
            }
        }

        function setAirportSelection(field, iata, displayText) {
            var codeEl = field === 'from' ? fromCode : toCode;
            var displayEl = field === 'from' ? fromDisplay : toDisplay;
            var labelEl = field === 'from' ? fromLabel : toLabel;
            if (codeEl) {
                codeEl.value = iata;
            }
            if (displayEl) {
                displayEl.value = displayText;
            }
            if (labelEl) {
                labelEl.textContent = displayText;
            }
        }

        function setDateSelection(field, iso) {
            var input = field === 'depart' ? departInput : returnInput;
            var label = field === 'depart' ? departLabel : returnLabel;
            if (input) {
                input.value = iso;
            }
            if (label) {
                label.textContent = formatDateLabel(iso);
            }
        }

        function setTripType(type) {
            if (!tripInput) {
                return;
            }

            tripInput.value = type;
            tripButtons.forEach(function (btn) {
                var active = btn.getAttribute('data-trip-toggle') === type;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            if (returnField) {
                var isRound = type === 'round_trip';
                returnField.classList.toggle('is-hidden', !isRound);
            }

            if (type !== 'round_trip' && returnInput) {
                returnInput.value = '';
                if (returnLabel) {
                    returnLabel.textContent = '—';
                }
            }
        }

        function syncReturnMin() {
            if (!departInput || !returnInput) {
                return;
            }
            if (returnInput.value && departInput.value && returnInput.value < departInput.value) {
                setDateSelection('return', departInput.value);
            }
        }

        function openAirportSheet(field) {
            activeAirportField = field;
            if (airportSheetTitle) {
                airportSheetTitle.textContent = field === 'from' ? 'Select departure airport' : 'Select arrival airport';
            }
            if (airportSearchInput) {
                airportSearchInput.value = '';
                airportSearchInput.focus();
            }
            if (airportResults) {
                airportResults.innerHTML = '';
            }
            if (airportHint) {
                airportHint.hidden = false;
            }
            if (airportError) {
                airportError.hidden = true;
            }
            if (airportFallback) {
                airportFallback.hidden = true;
            }
            if (airportSheet) {
                airportSheet.classList.add('is-open');
                airportSheet.setAttribute('aria-hidden', 'false');
            }
            if (airportBackdrop) {
                airportBackdrop.classList.add('is-open');
                airportBackdrop.setAttribute('aria-hidden', 'false');
            }
            document.body.classList.add('ota-mobile-sheet-open');
        }

        function closeAirportSheet() {
            activeAirportField = null;
            if (airportFetchCtrl) {
                airportFetchCtrl.abort();
                airportFetchCtrl = null;
            }
            if (airportSheet) {
                airportSheet.classList.remove('is-open');
                airportSheet.setAttribute('aria-hidden', 'true');
            }
            if (airportBackdrop) {
                airportBackdrop.classList.remove('is-open');
                airportBackdrop.setAttribute('aria-hidden', 'true');
            }
            document.body.classList.remove('ota-mobile-sheet-open');
        }

        function renderAirportResults(rows) {
            if (!airportResults) {
                return;
            }
            if (!rows || !rows.length) {
                airportResults.innerHTML = '<li class="ota-mobile-airport-sheet__empty">No airports found.</li>';
                return;
            }
            airportResults.innerHTML = rows.map(function (row) {
                var iata = esc(row.iata || row.iata_code || '');
                var city = esc(row.city || '');
                var name = esc(row.name || '');
                var country = esc(row.country || '');
                var main = city || name || iata;
                var sub = name + (country ? ' · ' + country : '');
                return '' +
                    '<li><button type="button" class="ota-mobile-airport-sheet__item" data-airport-iata="' + iata + '" data-airport-label="' + esc(main) + '">' +
                    '<span class="ota-mobile-airport-sheet__code">' + iata + '</span>' +
                    '<span class="ota-mobile-airport-sheet__main">' +
                    '<span class="ota-mobile-airport-sheet__city">' + main + '</span>' +
                    (name && name !== main ? '<span class="ota-mobile-airport-sheet__name">' + name + '</span>' : '') +
                    '</span>' +
                    (country ? '<span class="ota-mobile-airport-sheet__country">' + country + '</span>' : '') +
                    '</button></li>';
            }).join('');
        }

        function searchAirports(q) {
            if (airportFetchCtrl) {
                airportFetchCtrl.abort();
            }
            if (q.length < 2) {
                if (airportResults) {
                    airportResults.innerHTML = '';
                }
                if (airportHint) {
                    airportHint.hidden = false;
                }
                if (airportFallback) {
                    airportFallback.hidden = true;
                }
                return;
            }
            if (airportHint) {
                airportHint.hidden = true;
            }
            airportFetchCtrl = new AbortController();
            var url = airportsSearchUrl + (airportsSearchUrl.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q) + '&limit=12';
            fetch(url, {
                signal: airportFetchCtrl.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function (res) {
                if (!res.ok) {
                    throw new Error('search failed');
                }
                return res.json();
            }).then(function (rows) {
                renderAirportResults(Array.isArray(rows) ? rows : []);
                if (airportError) {
                    airportError.hidden = true;
                }
                if (airportFallback) {
                    airportFallback.hidden = true;
                }
            }).catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (airportResults) {
                    airportResults.innerHTML = '';
                }
                if (airportError) {
                    airportError.hidden = false;
                }
                if (airportFallback) {
                    airportFallback.hidden = false;
                }
            });
        }

        function selectAirport(iata, label) {
            if (!activeAirportField || !iata) {
                return;
            }
            var displayText = iata + ' ' + label;
            setAirportSelection(activeAirportField, iata, displayText);
            closeAirportSheet();
        }

        function buildCalendarMonths() {
            if (!calendarMonths) {
                return;
            }
            var minDt = parseIso(minDateIso) || new Date();
            var startYear = minDt.getFullYear();
            var startMonth = minDt.getMonth();
            var html = '';
            var dayNames = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
            var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

            for (var i = 0; i < 12; i++) {
                var monthIndex = startMonth + i;
                var y = startYear + Math.floor(monthIndex / 12);
                var m = monthIndex % 12;
                var firstDay = new Date(y, m, 1);
                var daysInMonth = new Date(y, m + 1, 0).getDate();
                var startPad = firstDay.getDay();

                html += '<section class="ota-mobile-calendar-month" aria-label="' + esc(monthNames[m] + ' ' + y) + '">';
                html += '<h3 class="ota-mobile-calendar-month__title">' + esc(monthNames[m] + ' ' + y) + '</h3>';
                html += '<div class="ota-mobile-calendar-month__grid">';
                dayNames.forEach(function (dn) {
                    html += '<span class="ota-mobile-calendar-month__dow">' + dn + '</span>';
                });
                for (var pad = 0; pad < startPad; pad++) {
                    html += '<span class="ota-mobile-calendar-month__pad" aria-hidden="true"></span>';
                }
                for (var day = 1; day <= daysInMonth; day++) {
                    var iso = isoFromParts(y, m, day);
                    var disabled = iso < minDateIso;
                    html += '<button type="button" class="ota-mobile-calendar-month__day' + (disabled ? ' is-disabled' : '') + '" data-calendar-day="' + iso + '"' + (disabled ? ' disabled' : '') + '>' + day + '</button>';
                }
                html += '</div></section>';
            }
            calendarMonths.innerHTML = html;
        }

        function highlightCalendarRange() {
            if (!calendarMonths) {
                return;
            }
            var isRound = tripInput && tripInput.value === 'round_trip';
            calendarMonths.querySelectorAll('[data-calendar-day]').forEach(function (btn) {
                var iso = btn.getAttribute('data-calendar-day');
                btn.classList.remove('is-selected', 'is-range', 'is-range-start', 'is-range-end');
                if (!iso) {
                    return;
                }
                if (!isRound) {
                    if (departInput && iso === departInput.value) {
                        btn.classList.add('is-selected');
                    }
                    return;
                }
                var start = calendarRangeStart || (departInput ? departInput.value : '');
                var end = calendarRangeEnd || (returnInput ? returnInput.value : '');
                if (start && iso === start) {
                    btn.classList.add('is-range-start', 'is-selected');
                }
                if (end && iso === end) {
                    btn.classList.add('is-range-end', 'is-selected');
                }
                if (start && end && iso > start && iso < end) {
                    btn.classList.add('is-range');
                }
            });
        }

        function openCalendarSheet(triggerField) {
            var isRound = tripInput && tripInput.value === 'round_trip';
            calendarPickPhase = 0;
            calendarRangeStart = departInput ? departInput.value : null;
            calendarRangeEnd = isRound && returnInput ? returnInput.value : null;

            if (calendarTitle) {
                calendarTitle.textContent = isRound ? 'Select travel dates' : 'Select departure date';
            }
            if (calendarSubtitle) {
                calendarSubtitle.textContent = isRound
                    ? 'Tap departure date, then return date.'
                    : 'Tap your departure date.';
            }

            buildCalendarMonths();
            highlightCalendarRange();

            if (calendarSheet) {
                calendarSheet.classList.add('is-open');
                calendarSheet.setAttribute('aria-hidden', 'false');
            }
            if (calendarBackdrop) {
                calendarBackdrop.classList.add('is-open');
                calendarBackdrop.setAttribute('aria-hidden', 'false');
            }
            document.body.classList.add('ota-mobile-sheet-open');

            if (triggerField === 'return' && isRound && calendarRangeStart) {
                calendarPickPhase = 1;
            }
        }

        function closeCalendarSheet() {
            if (calendarSheet) {
                calendarSheet.classList.remove('is-open');
                calendarSheet.setAttribute('aria-hidden', 'true');
            }
            if (calendarBackdrop) {
                calendarBackdrop.classList.remove('is-open');
                calendarBackdrop.setAttribute('aria-hidden', 'true');
            }
            document.body.classList.remove('ota-mobile-sheet-open');
        }

        function handleCalendarDayClick(iso) {
            if (!iso || iso < minDateIso) {
                return;
            }
            var isRound = tripInput && tripInput.value === 'round_trip';

            if (!isRound) {
                setDateSelection('depart', iso);
                closeCalendarSheet();
                return;
            }

            if (calendarPickPhase === 0 || !calendarRangeStart) {
                calendarRangeStart = iso;
                calendarRangeEnd = null;
                calendarPickPhase = 1;
                setDateSelection('depart', iso);
                if (returnInput) {
                    returnInput.value = '';
                }
                if (returnLabel) {
                    returnLabel.textContent = 'Select return';
                }
                highlightCalendarRange();
                return;
            }

            calendarRangeEnd = iso;
            if (calendarRangeEnd < calendarRangeStart) {
                var tmp = calendarRangeStart;
                calendarRangeStart = calendarRangeEnd;
                calendarRangeEnd = tmp;
                setDateSelection('depart', calendarRangeStart);
            }
            setDateSelection('return', calendarRangeEnd);
            closeCalendarSheet();
        }

        tripButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setTripType(btn.getAttribute('data-trip-toggle') || 'one_way');
            });
        });

        if (swapBtn) {
            swapBtn.addEventListener('click', function () {
                if (!fromDisplay || !toDisplay || !fromCode || !toCode) {
                    return;
                }
                var fromText = fromDisplay.value;
                var toText = toDisplay.value;
                var fromVal = fromCode.value;
                var toVal = toCode.value;
                setAirportSelection('from', toVal, toText);
                setAirportSelection('to', fromVal, fromText);
            });
        }

        home.querySelectorAll('[data-airport-trigger]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openAirportSheet(btn.getAttribute('data-airport-trigger'));
            });
        });

        home.querySelectorAll('[data-date-trigger]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openCalendarSheet(btn.getAttribute('data-date-trigger'));
            });
        });

        if (airportSearchInput) {
            airportSearchInput.addEventListener('input', function () {
                clearTimeout(airportDebounce);
                var q = airportSearchInput.value.trim();
                airportDebounce = setTimeout(function () {
                    searchAirports(q);
                }, 280);
            });
        }

        if (airportResults) {
            airportResults.addEventListener('click', function (e) {
                var item = e.target.closest('[data-airport-iata]');
                if (!item) {
                    return;
                }
                selectAirport(item.getAttribute('data-airport-iata'), item.getAttribute('data-airport-label') || '');
            });
        }

        if (airportUseCode) {
            airportUseCode.addEventListener('click', function () {
                if (!airportSearchInput) {
                    return;
                }
                var code = airportSearchInput.value.trim().toUpperCase();
                if (/^[A-Z]{3}$/.test(code)) {
                    selectAirport(code, code);
                }
            });
        }

        home.querySelectorAll('[data-mobile-airport-close]').forEach(function (btn) {
            btn.addEventListener('click', closeAirportSheet);
        });

        if (airportBackdrop) {
            airportBackdrop.addEventListener('click', closeAirportSheet);
        }

        if (calendarMonths) {
            calendarMonths.addEventListener('click', function (e) {
                var dayBtn = e.target.closest('[data-calendar-day]');
                if (!dayBtn || dayBtn.disabled) {
                    return;
                }
                handleCalendarDayClick(dayBtn.getAttribute('data-calendar-day'));
            });
        }

        home.querySelectorAll('[data-mobile-calendar-close]').forEach(function (btn) {
            btn.addEventListener('click', closeCalendarSheet);
        });

        if (calendarBackdrop) {
            calendarBackdrop.addEventListener('click', closeCalendarSheet);
        }

        if (travellersTrigger) {
            travellersTrigger.addEventListener('click', openTravellersSheet);
        }

        home.querySelectorAll('[data-travellers-step]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var type = btn.getAttribute('data-travellers-step');
                var delta = parseInt(btn.getAttribute('data-travellers-delta') || '0', 10);
                if (type) {
                    changeTravellers(type, delta);
                }
            });
        });

        home.querySelectorAll('[data-travellers-cabin]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setTravellersCabin(btn.getAttribute('data-travellers-cabin'));
            });
        });

        home.querySelectorAll('[data-mobile-travellers-close], [data-mobile-travellers-done]').forEach(function (btn) {
            btn.addEventListener('click', closeTravellersSheet);
        });

        if (travellersBackdrop) {
            travellersBackdrop.addEventListener('click', closeTravellersSheet);
        }

        form.addEventListener('submit', function (e) {
            var from = fromCode ? fromCode.value.trim().toUpperCase() : '';
            var to = toCode ? toCode.value.trim().toUpperCase() : '';
            if (!/^[A-Z]{3}$/.test(from) || !/^[A-Z]{3}$/.test(to)) {
                e.preventDefault();
                return;
            }
            if (from === to) {
                e.preventDefault();
                return;
            }
            if (tripInput && tripInput.value !== 'round_trip' && returnInput) {
                returnInput.removeAttribute('name');
            } else if (returnInput) {
                returnInput.setAttribute('name', 'return_date');
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAirportSheet();
                closeCalendarSheet();
                closeTravellersSheet();
            }
        });

        syncTravellersUi();
        setTripType(tripInput ? tripInput.value : 'round_trip');
        syncReturnMin();
    }

    function initMobileResults() {
        var root = document.querySelector('[data-testid="ota-mobile-results"]');
        if (!root) {
            return;
        }

        function mobileResultsDebugEnabled() {
            try {
                return new URLSearchParams(window.location.search).get('mobile_debug') === '1';
            } catch (err) {
                return false;
            }
        }

        function mlog() {
            if (!mobileResultsDebugEnabled() || typeof console === 'undefined' || !console.log) {
                return;
            }
            console.log.apply(console, arguments);
        }

        function mwarn() {
            if (!mobileResultsDebugEnabled() || typeof console === 'undefined' || !console.warn) {
                return;
            }
            console.warn.apply(console, arguments);
        }

        var FILTER_CONTAINER_MAP = {
            departure_window: '[data-mobile-filter-departure]',
            arrival_window: '[data-mobile-filter-arrival]',
            fare_family: '[data-mobile-filter-fare-family]',
            refundable: '[data-mobile-filter-refundable]',
            stops: '[data-mobile-filter-stops]',
            baggage: '[data-mobile-filter-baggage]',
            airline: '[data-mobile-filter-airlines]',
        };

        var FILTER_DRAWER_KEYS = ['airline', 'stops', 'refundable', 'cabin', 'baggage', 'departure_window', 'arrival_window', 'fare_family'];

        var searchId = root.getAttribute('data-search-id') || '';
        var returnSplitFlow = root.getAttribute('data-return-split-flow') === '1';
        var resultsUrl = root.getAttribute('data-results-url') || '/flights/results/data';
        var list = root.querySelector('[data-mobile-results-list]');
        var summary = root.querySelector('[data-mobile-results-summary]');
        var inlineError = root.querySelector('[data-mobile-results-inline-error]');
        var loadMoreBtn = root.querySelector('[data-mobile-load-more]');
        var expiredMsg = root.querySelector('[data-mobile-expired-message]');
        var emptyMsg = root.querySelector('[data-mobile-empty-message]');
        var noResultsMsg = root.querySelector('[data-mobile-no-results-message]');
        var filterDrawer = root.querySelector('[data-mobile-filter-drawer]');
        var filterBackdrop = root.querySelector('[data-mobile-filter-backdrop]');
        var filterCount = root.querySelector('[data-mobile-filter-result-count]');
        var sortSheet = root.querySelector('[data-mobile-sort-sheet]');
        var quickFilters = root.querySelector('[data-mobile-quick-filters]');
        var defaultOrigin = root.getAttribute('data-origin') || '';
        var defaultDestination = root.getAttribute('data-destination') || '';
        var searchCriteria = {};
        try {
            searchCriteria = JSON.parse(root.getAttribute('data-criteria') || '{}');
        } catch (criteriaErr) {
            searchCriteria = {};
        }

        var defaultFilterState = function () {
            return {
                airline: '',
                stops: '',
                refundable: '',
                cabin: '',
                baggage: '',
                departure_window: '',
                arrival_window: '',
                fare_family: '',
                sort: 'recommended',
            };
        };

        var appliedFilters = defaultFilterState();
        var draftFilters = defaultFilterState();
        var offersById = {};
        var selectedFareOptionByOfferId = {};

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

        function offerRequiresFareFamilySelection(offer) {
            if (window.OtaBrandedFares && typeof OtaBrandedFares.offerNeedsFareChoiceBeforeCheckout === 'function') {
                return OtaBrandedFares.offerNeedsFareChoiceBeforeCheckout(offer);
            }
            if (!offer || !offer.branded_fares_selection_active || !offer.has_branded_fares) {
                return false;
            }
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
            if (!offer.branded_fares_selection_active || !offer.has_branded_fares) {
                return false;
            }
            return (offer.fare_family_options_display || []).length > 0;
        }

        function isSyntheticDefaultFareOption(offer, fareOptionKey) {
            if (window.OtaBrandedFares && typeof OtaBrandedFares.isSyntheticDefaultFareOption === 'function') {
                return OtaBrandedFares.isSyntheticDefaultFareOption(offer, fareOptionKey);
            }
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

        function proceedMobileFareOptionSelect(offer, fareKey, triggerEl) {
            if (!offer || !offer.select_url || !fareKey) {
                return;
            }
            clearOtherOfferFareSelections(offer.offer_id);
            selectedFareOptionByOfferId[offer.offer_id] = fareKey;
            refreshMobileFareSelectionUi(offer.offer_id);
            if (isIatiProviderOffer(offer)) {
                var revalKey = fareKey;
                if (isSyntheticDefaultFareOption(offer, revalKey)) {
                    revalKey = '';
                }
                beginIatiSelectRevalidation(offer.offer_id, revalKey, triggerEl, offer.select_url);
                return;
            }
            if (isSyntheticDefaultFareOption(offer, fareKey)) {
                window.location.href = offer.select_url;
                return;
            }
            navigateToCheckoutWithFareKey(offer.select_url, offer.offer_id, fareKey);
        }

        function offerRequiresBrandedFareChoice(offer) {
            if (!offer || !offer.has_branded_fares || !offer.branded_fares_display_enabled) {
                return false;
            }
            var opts = offer.fare_family_options_display || [];
            return opts.length >= 2;
        }

        function offerNeedsBrandedFarePickBeforeCheckout(offer) {
            return offerNeedsFareChoiceBeforeCheckout(offer);
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

        function isIatiProviderOffer(offer) {
            if (!offer) {
                return false;
            }
            var provider = String(offer.supplier_provider || offer.provider || '').toLowerCase();
            return provider === 'iati';
        }

        function formatRevalidationPrice(amount) {
            if (amount === null || amount === undefined || !isFinite(Number(amount))) {
                return '—';
            }
            return 'PKR ' + Math.round(Number(amount)).toLocaleString('en-US');
        }

        function setMobileSelectLoading(triggerEl, isLoading) {
            if (!triggerEl) {
                return;
            }
            if (isLoading) {
                if (!triggerEl.getAttribute('data-iati-prev-label')) {
                    triggerEl.setAttribute('data-iati-prev-label', (triggerEl.textContent || '').trim());
                }
                triggerEl.setAttribute('data-iati-revalidation-loading', '1');
                triggerEl.setAttribute('aria-busy', 'true');
                if (triggerEl.tagName === 'BUTTON' || triggerEl.tagName === 'A') {
                    triggerEl.disabled = true;
                }
                triggerEl.textContent = 'Checking...';
                return;
            }
            triggerEl.removeAttribute('data-iati-revalidation-loading');
            triggerEl.removeAttribute('aria-busy');
            if (triggerEl.tagName === 'BUTTON' || triggerEl.tagName === 'A') {
                triggerEl.disabled = false;
            }
            var prevLabel = triggerEl.getAttribute('data-iati-prev-label');
            if (prevLabel) {
                triggerEl.textContent = prevLabel;
                triggerEl.removeAttribute('data-iati-prev-label');
            }
        }

        var iatiSelectRevalidationInFlight = false;
        var iatiPriceChangePrompt = null;

        function ensureIatiPriceChangePrompt() {
            if (iatiPriceChangePrompt) {
                return iatiPriceChangePrompt;
            }
            iatiPriceChangePrompt = document.createElement('div');
            iatiPriceChangePrompt.className = 'ota-mobile-results__freshness ota-mobile-results__freshness--checkout';
            iatiPriceChangePrompt.setAttribute('data-mobile-iati-price-change-prompt', '');
            iatiPriceChangePrompt.hidden = true;
            iatiPriceChangePrompt.innerHTML =
                '<p class="ota-mobile-results__freshness-text" data-mobile-iati-price-change-message>Fare price has changed. Please review before continuing.</p>' +
                '<p class="ota-mobile-results__freshness-text"><span data-mobile-iati-price-change-old></span> &rarr; <strong data-mobile-iati-price-change-new></strong></p>' +
                '<div class="ota-mobile-results__freshness-actions">' +
                '<button type="button" class="ota-mobile-results__freshness-btn" data-mobile-iati-price-change-continue>Continue</button>' +
                '<button type="button" class="ota-mobile-results__freshness-btn" data-mobile-iati-price-change-cancel>Cancel</button>' +
                '</div>';
            var anchor = root.querySelector('[data-mobile-results-inline-error]');
            if (anchor && anchor.parentElement) {
                anchor.parentElement.insertBefore(iatiPriceChangePrompt, anchor.nextSibling);
            } else {
                root.appendChild(iatiPriceChangePrompt);
            }
            iatiPriceChangePrompt.querySelector('[data-mobile-iati-price-change-continue]').addEventListener('click', function () {
                var onContinue = iatiPriceChangePrompt._onContinue;
                hideIatiPriceChangePrompt();
                if (typeof onContinue === 'function') {
                    onContinue();
                }
            });
            iatiPriceChangePrompt.querySelector('[data-mobile-iati-price-change-cancel]').addEventListener('click', function () {
                hideIatiPriceChangePrompt();
            });
            return iatiPriceChangePrompt;
        }

        function hideIatiPriceChangePrompt() {
            if (!iatiPriceChangePrompt) {
                return;
            }
            iatiPriceChangePrompt.hidden = true;
            iatiPriceChangePrompt._onContinue = null;
        }

        function showIatiPriceChangePrompt(oldTotal, newTotal, onContinue) {
            var prompt = ensureIatiPriceChangePrompt();
            var oldEl = prompt.querySelector('[data-mobile-iati-price-change-old]');
            var newEl = prompt.querySelector('[data-mobile-iati-price-change-new]');
            if (oldEl) {
                oldEl.textContent = formatRevalidationPrice(oldTotal);
            }
            if (newEl) {
                newEl.textContent = formatRevalidationPrice(newTotal);
            }
            prompt._onContinue = onContinue;
            hideInlineError();
            prompt.hidden = false;
        }

        function navigateToPassengersAfterRevalidation(offerId, json, fareOptionKey, fallbackSelectUrl) {
            var passengersUrl = (json && json.passengers_url) ? json.passengers_url : fallbackSelectUrl;
            if (!passengersUrl) {
                showInlineError('Fare could not be confirmed. Please search again.');
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
            setMobileSelectLoading(triggerEl, false);
        }

        function beginIatiSelectRevalidation(offerId, fareOptionKey, triggerEl, fallbackSelectUrl) {
            if (!revalidateOfferUrl || !offerId || iatiSelectRevalidationInFlight) {
                return;
            }
            iatiSelectRevalidationInFlight = true;
            hideInlineError();
            hideIatiPriceChangePrompt();
            setMobileSelectLoading(triggerEl, true);

            var body = new URLSearchParams();
            body.set('search_id', searchId);
            body.set('offer_id', offerId);
            body.set('provider', 'iati');
            if (fareOptionKey && offersById[offerId] && window.OtaBrandedFares && typeof OtaBrandedFares.isSyntheticDefaultFareOption === 'function' && OtaBrandedFares.isSyntheticDefaultFareOption(offersById[offerId], fareOptionKey)) {
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
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: body.toString(),
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
                        });
                        return;
                    }
                }
                showInlineError(iatiRevalidationCustomerMessage(result));
                finishIatiSelectRevalidation(triggerEl);
            }).catch(function () {
                showInlineError('Fare could not be confirmed. Please search again.');
                finishIatiSelectRevalidation(triggerEl);
            });
        }

        function refreshMobileFareSelectionUi(offerId) {
            if (!list) {
                return;
            }
            var card = list.querySelector('[data-flight-card][data-offer-id="' + offerId + '"]');
            if (!card) {
                return;
            }
            var selectedKey = selectedFareOptionByOfferId[offerId] || '';
            var hasSelection = selectedKey !== '';
            card.querySelectorAll('[data-fare-option-card]').forEach(function (el) {
                var elKey = el.getAttribute('data-fare-option-key') || '';
                var isSel = elKey === selectedKey && hasSelection;
                el.classList.toggle('is-selected', isSel);
                el.setAttribute('aria-pressed', isSel ? 'true' : 'false');
            });
            var selectBtn = card.querySelector('[data-mobile-select]');
            if (selectBtn && selectBtn.hasAttribute('data-requires-fare-family')) {
                selectBtn.disabled = !hasSelection;
                if (hasSelection) {
                    selectBtn.removeAttribute('aria-disabled');
                    selectBtn.textContent = 'Select';
                } else {
                    selectBtn.setAttribute('aria-disabled', 'true');
                    selectBtn.textContent = 'Select fare';
                }
            }
        }

        function buildMobileFareFamilyPickerHtml(offer) {
            if (!offerNeedsFareChoiceBeforeCheckout(offer)) {
                return '';
            }
            var opts = offer.fare_family_options_display || [];
            var selectedKey = selectedFareOptionByOfferId[offer.offer_id] || '';
            var rows = opts.map(function (opt) {
                if (!opt || !opt.name) {
                    return '';
                }
                var isSel = selectedKey === (opt.option_key || '');
                var priceText = opt.price_display || '';
                if (!priceText && opt.price_total != null && Number(opt.price_total) > 0) {
                    priceText = (opt.currency ? String(opt.currency) + ' ' : '') + String(Math.round(Number(opt.price_total)));
                }
                var approx = opt.price_is_approximate ? '<span class="ota-mobile-fare-option__approx">Approx.</span>' : '';
                var brandCode = opt.brand_code ? '<span class="ota-mobile-fare-option__code">' + esc(opt.brand_code) + '</span>' : '';
                var summaryBtn = '';
                var selectBtn = '<button type="button" class="ota-mobile-fare-option__select" data-fare-option-select data-offer-id="' + esc(offer.offer_id || '') + '" data-fare-option-key="' + esc(opt.option_key || '') + '">Select</button>';
                if (window.OtaBrandedFares && typeof OtaBrandedFares.buildFareSummaryPayload === 'function' && typeof OtaBrandedFares.payloadAttr === 'function') {
                    summaryBtn = '<button type="button" class="ota-mobile-fare-option__details" data-fare-summary-open data-fare-summary-payload="' +
                        OtaBrandedFares.payloadAttr(OtaBrandedFares.buildFareSummaryPayload(offer, opt)) +
                        '" data-fare-option-key="' + esc(opt.option_key || '') + '">View details</button>';
                }
                return '<div class="ota-mobile-fare-option-wrap">' +
                    '<button type="button" class="ota-mobile-fare-option' + (isSel ? ' is-selected' : '') + '"' +
                    ' data-fare-option-card data-offer-id="' + esc(offer.offer_id || '') + '"' +
                    ' data-fare-option-key="' + esc(opt.option_key || '') + '"' +
                    ' aria-pressed="' + (isSel ? 'true' : 'false') + '">' +
                    '<span class="ota-mobile-fare-option__name">' + esc(opt.name) + '</span>' +
                    brandCode +
                    (priceText ? '<span class="ota-mobile-fare-option__price">' + approx + esc(String(priceText).replace(/^Approx\.\s*/i, '')) + '</span>' : '') +
                    '</button>' +
                    '<div class="ota-mobile-fare-option__actions">' + summaryBtn + selectBtn + '</div></div>';
            }).join('');
            return '<div class="ota-mobile-fare-family-options" data-mobile-fare-family-options>' +
                '<p class="ota-mobile-fare-family-options__label">Select fare option</p>' +
                '<div class="ota-mobile-fare-family-options__list">' + rows + '</div>' +
                '<p class="ota-mobile-fare-family-options__hint" hidden role="status">Please select a fare family option to continue.</p>' +
                '</div>';
        }

        function promptMobileFareFamilySelection(card) {
            if (!card) {
                return;
            }
            var section = card.querySelector('[data-mobile-fare-family-options]');
            if (section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            var hint = card.querySelector('.ota-mobile-fare-family-options__hint');
            if (hint) {
                hint.hidden = false;
            }
        }

        var page = 1;
        var loading = false;
        var hasMore = true;
        var hasLoadedResults = false;
        var fetchCtrl = null;
        var fetchGeneration = 0;
        var inlineErrorTimer = null;
        var freshnessBanner = root.querySelector('[data-mobile-offer-freshness-banner]');
        var freshnessMessage = root.querySelector('[data-mobile-offer-freshness-message]');
        var freshnessRefreshBtn = root.querySelector('[data-mobile-offer-freshness-refresh]');
        var selectedOfferRefreshBtn = root.querySelector('[data-mobile-selected-offer-refresh]');
        var selectedOfferRefreshBanner = root.querySelector('[data-mobile-selected-offer-refresh-banner]');
        var revalidateOfferUrl = root.getAttribute('data-revalidate-offer-url') || '';
        var freshnessRefreshDueSec = parseInt(root.getAttribute('data-freshness-refresh-due') || '300', 10);
        var freshnessStaleAfterSec = parseInt(root.getAttribute('data-freshness-stale-after') || '600', 10);
        var searchFreshnessMeta = null;
        var freshnessTimer = null;
        var csrfToken = document.querySelector('meta[name="csrf-token"]');

        if (!searchId || !list) {
            if (summary) {
                summary.textContent = 'Search unavailable. Please try again.';
            }
            return;
        }

        function mountOverlayNode(node) {
            if (node && node.parentElement && node.parentElement !== document.body) {
                document.body.appendChild(node);
            }
        }

        mountOverlayNode(filterBackdrop);
        mountOverlayNode(filterDrawer);
        mountOverlayNode(sortSheet);

        function filterEl(selector) {
            if (!selector || typeof selector !== 'string' || selector.trim() === '') {
                mwarn('[mobile-results] filterEl skipped empty selector');
                return null;
            }
            mlog('[mobile-results] filterEl query', selector);
            if (filterDrawer) {
                var inDrawer = filterDrawer.querySelector(selector);
                if (inDrawer) {
                    return inDrawer;
                }
            }
            return root.querySelector(selector);
        }

        function hideInlineError() {
            if (inlineErrorTimer) {
                clearTimeout(inlineErrorTimer);
                inlineErrorTimer = null;
            }
            if (inlineError) {
                inlineError.hidden = true;
                inlineError.classList.remove('is-toast-visible');
            }
        }

        function showInlineError(message) {
            if (!inlineError) {
                return;
            }
            inlineError.textContent = message || 'Could not refresh results. Try again.';
            inlineError.hidden = false;
            inlineError.classList.add('is-toast-visible');
            if (inlineErrorTimer) {
                clearTimeout(inlineErrorTimer);
            }
            inlineErrorTimer = setTimeout(function () {
                hideInlineError();
            }, 4000);
        }

        function freshnessAgeSeconds(meta) {
            if (!meta) {
                return null;
            }
            if (typeof meta.offer_age_seconds === 'number') {
                return meta.offer_age_seconds;
            }
            var created = meta.search_created_at || meta.selected_offer_created_at;
            if (!created) {
                return null;
            }
            var ts = Date.parse(created);
            if (isNaN(ts)) {
                return null;
            }
            return Math.max(0, Math.floor((Date.now() - ts) / 1000));
        }

        function updateSearchFreshnessBanner(meta) {
            if (!freshnessBanner || !freshnessMessage) {
                return;
            }
            searchFreshnessMeta = meta || searchFreshnessMeta;
            var age = freshnessAgeSeconds(searchFreshnessMeta);
            if (age === null) {
                freshnessBanner.hidden = true;
                return;
            }
            var status = (searchFreshnessMeta && searchFreshnessMeta.offer_freshness_status) || '';
            if (!status) {
                if (age >= freshnessStaleAfterSec) {
                    status = 'stale';
                } else if (age >= freshnessRefreshDueSec) {
                    status = 'refresh_due';
                } else {
                    status = 'fresh';
                }
            }
            if (status === 'fresh') {
                freshnessBanner.hidden = true;
                return;
            }
            freshnessBanner.hidden = false;
            freshnessBanner.classList.remove('ota-mobile-results__freshness--stale', 'ota-mobile-results__freshness--due');
            if (status === 'stale') {
                freshnessBanner.classList.add('ota-mobile-results__freshness--stale');
                freshnessMessage.textContent = 'This fare needs to be refreshed because airline prices and availability can change quickly.';
                if (freshnessRefreshBtn) {
                    freshnessRefreshBtn.textContent = 'Check availability again';
                }
            } else {
                freshnessBanner.classList.add('ota-mobile-results__freshness--due');
                freshnessMessage.textContent = 'Fares and availability may have changed.';
                if (freshnessRefreshBtn) {
                    freshnessRefreshBtn.textContent = 'Refresh fares';
                }
            }
            if (freshnessRefreshBtn) {
                freshnessRefreshBtn.hidden = false;
            }
        }

        function scheduleFreshnessTicker() {
            if (freshnessTimer) {
                clearInterval(freshnessTimer);
            }
            freshnessTimer = setInterval(function () {
                if (!searchFreshnessMeta) {
                    return;
                }
                var age = freshnessAgeSeconds(searchFreshnessMeta);
                if (age === null) {
                    return;
                }
                searchFreshnessMeta.offer_age_seconds = age;
                if (age >= freshnessStaleAfterSec) {
                    searchFreshnessMeta.offer_freshness_status = 'stale';
                } else if (age >= freshnessRefreshDueSec) {
                    searchFreshnessMeta.offer_freshness_status = 'refresh_due';
                }
                updateSearchFreshnessBanner(searchFreshnessMeta);
            }, 15000);
        }

        function postSelectedOfferRefresh(offerId, onSuccess) {
            if (!revalidateOfferUrl || !offerId) {
                return;
            }
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
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: body.toString(),
            }).then(function (res) {
                return res.json().then(function (json) {
                    return { ok: res.ok, json: json };
                }).catch(function () {
                    return {
                        ok: false,
                        json: { message: 'We could not confirm this fare with the airline. Please refresh your search or choose another option.' },
                    };
                });
            }).then(function (result) {
                if (result.ok && result.json && result.json.success) {
                    if (result.json.search_freshness) {
                        updateSearchFreshnessBanner(result.json.search_freshness);
                    }
                    if (selectedOfferRefreshBanner) {
                        selectedOfferRefreshBanner.hidden = true;
                    }
                    if (typeof onSuccess === 'function') {
                        onSuccess(result.json);
                    }
                    return;
                }
                var msg = (result.json && result.json.message) || 'We could not confirm this fare with the airline. Please refresh your search or choose another option.';
                showInlineError(msg);
            }).catch(function () {
                showInlineError('We could not confirm this fare with the airline. Please refresh your search or choose another option.');
            });
        }

        function hasVisibleResultCards() {
            if (!list) {
                return false;
            }
            return list.querySelectorAll('[data-flight-card]:not(.ota-mobile-result-card--skeleton)').length > 0;
        }

        function formatPrice(amount) {
            if (amount === null || amount === undefined || !isFinite(Number(amount))) {
                return 'Fare unavailable';
            }
            return 'PKR ' + Math.round(Number(amount)).toLocaleString('en-US');
        }

        function stopsLabel(stops) {
            var count = Number(stops || 0);
            if (count === 0) {
                return 'Direct';
            }
            return count + ' stop' + (count === 1 ? '' : 's');
        }

        function baggageLine(offer) {
            var text = String(offer.baggage_summary_display || offer.baggage || '').trim();
            if (text) {
                return text;
            }
            var parts = [];
            if (offer.baggage_checked_display) {
                parts.push(String(offer.baggage_checked_display));
            }
            if (offer.baggage_cabin_display) {
                parts.push('Cabin: ' + String(offer.baggage_cabin_display));
            }
            return parts.join(' · ');
        }

        function isRoundTripSearch() {
            return (searchCriteria.trip_type || 'one_way') === 'round_trip';
        }

        function hasRoundTripCardLayout(offer) {
            if (!isRoundTripSearch()) {
                return false;
            }
            var journeys = offer.journeys_display || [];
            return journeys.length >= 2 && !offer.journey_grouping_unavailable;
        }

        function offerDetailsUrl(offer) {
            var detailsUrl = offer.details_url || '';
            if (!detailsUrl && root) {
                var detailsBase = root.getAttribute('data-offer-details-url') || '';
                var sid = root.getAttribute('data-search-id') || searchId;
                if (detailsBase && sid && offer.offer_id) {
                    detailsUrl = detailsBase + '?search_id=' + encodeURIComponent(sid) + '&offer_id=' + encodeURIComponent(offer.offer_id);
                }
            }
            return detailsUrl;
        }

        function cardSelectHtml(offer) {
            if (!offer.can_book || !offer.select_url) {
                return '<button type="button" class="ota-mobile-result-card__select is-disabled" disabled title="' + esc(offer.disabled_reason || 'Not available') + '">Select</button>';
            }
            if (offerNeedsFareChoiceBeforeCheckout(offer)) {
                return '<button type="button" class="ota-mobile-result-card__select" data-mobile-select data-offer-id="' + esc(offer.offer_id || '') + '">Select</button>';
            }
            return '<a class="ota-mobile-result-card__select" data-mobile-select href="' + esc(offer.select_url) + '">Select</a>';
        }

        function cardFooterHtml(offer, price) {
            var detailsUrl = offerDetailsUrl(offer);
            var detailsHtml = detailsUrl
                ? '<a class="ota-mobile-result-card__details" href="' + esc(detailsUrl) + '">View details</a>'
                : '';
            return '<div class="ota-mobile-result-card__footer"><div class="ota-mobile-result-card__price-wrap">' +
                '<span class="ota-mobile-result-card__price">' + esc(price) + '</span>' + detailsHtml + '</div>' +
                cardSelectHtml(offer) + '</div>' +
                '<div class="ota-mobile-result-card__source">' + buildFlightCardSourceBadgeHtml(offer) + '</div>';
        }

        function cardHeadHtml(offer, chipHtml) {
            var airlineName = esc(offer.airline_name || offer.airline_code || 'Airline');
            var logoHtml = offer.airline_logo_url
                ? '<span class="ota-mobile-result-card__logo"><img src="' + esc(offer.airline_logo_url) + '" alt="" loading="lazy"></span>'
                : '<span class="ota-mobile-result-card__logo">' + esc(offer.airline_code || '—') + '</span>';
            return '<div class="ota-mobile-result-card__head">' + logoHtml +
                '<div class="ota-mobile-result-card__head-text">' +
                '<span class="ota-mobile-result-card__airline">' + airlineName + '</span>' +
                (chipHtml || '') +
                '</div></div>';
        }

        function fareFamilyChipHtml(offer) {
            var chip = String(offer.fare_family || '').trim();
            if (!chip) {
                var fareSummary = offer.fare_summary_display || {};
                chip = String(fareSummary.fare_family || '').trim();
            }
            if (!chip) {
                chip = String(offer.cabin || '').trim();
            }
            if (!chip) {
                return '';
            }
            return '<span class="ota-mobile-result-card__chip">' + esc(chip) + '</span>';
        }

        function journeyStopsText(journey) {
            var label = String(journey.stops_display || '').trim();
            if (label) {
                return label;
            }
            if (journey.stops_count != null) {
                return stopsLabel(journey.stops_count);
            }
            return 'Direct';
        }

        function buildLegMetaHtml(journey) {
            var duration = esc(journey.duration_display || '');
            var stopsText = esc(journeyStopsText(journey));
            var stopClass = Number(journey.stops_count || 0) === 0
                ? ''
                : ' ota-mobile-result-leg-meta__stops--connecting';
            return '<div class="ota-mobile-result-leg-meta">' +
                (duration ? '<span class="ota-mobile-result-leg-meta__duration">' + duration + '</span>' : '') +
                '<span class="ota-mobile-result-leg-meta__stops' + stopClass + '">' + stopsText + '</span>' +
                '</div>';
        }

        function buildLegHtml(journey) {
            var label = esc((journey.label || '').trim() || (journey.type === 'return' ? 'Return' : 'Outbound'));
            var depCode = esc(journey.origin || '');
            var arrCode = esc(journey.destination || '');
            var depTime = esc(journey.departure_time_display || '');
            var arrTime = esc(journey.arrival_time_display || '');
            var arrOffset = journey.arrival_day_offset
                ? '<span class="ota-mobile-result-leg__offset">' + esc(journey.arrival_day_offset) + '</span>'
                : '';
            return '<div class="ota-mobile-result-leg" data-journey-type="' + esc(journey.type || '') + '">' +
                '<span class="ota-mobile-result-leg__label">' + label + '</span>' +
                '<div class="ota-mobile-result-leg__route">' +
                '<div class="ota-mobile-result-leg__point">' +
                '<span class="ota-mobile-result-leg__code">' + depCode + '</span>' +
                '<span class="ota-mobile-result-leg__time">' + depTime + '</span>' +
                '</div>' +
                buildLegMetaHtml(journey) +
                '<div class="ota-mobile-result-leg__point ota-mobile-result-leg__point--arr">' +
                '<span class="ota-mobile-result-leg__code">' + arrCode + '</span>' +
                '<span class="ota-mobile-result-leg__time">' + arrTime + arrOffset + '</span>' +
                '</div></div></div>';
        }

        function roundTripBaggageHtml(offer) {
            var checked = String(offer.baggage_checked_display || '').trim();
            var cabin = String(offer.baggage_cabin_display || '').trim();
            var summary = String(offer.baggage_summary_display || offer.baggage || '').trim();
            if (checked && cabin) {
                return '<div class="ota-mobile-result-card__baggage">' +
                    '<span class="ota-mobile-result-card__baggage-item">Checked: ' + esc(checked) + '</span>' +
                    '<span class="ota-mobile-result-card__baggage-item">Cabin: ' + esc(cabin) + '</span>' +
                    '</div>';
            }
            var line = baggageLine(offer);
            if (!line) {
                return '';
            }
            return '<div class="ota-mobile-result-card__baggage">' + esc(line) + '</div>';
        }

        function roundTripCardHtml(offer) {
            var pkrOk = offer.has_confirmed_pkr_quote && offer.displayed_price != null && Number(offer.displayed_price) > 0;
            var price = pkrOk ? formatPrice(offer.displayed_price) : 'Fare unavailable';
            var journeys = offer.journeys_display || [];
            var legsHtml = journeys.slice(0, 2).map(buildLegHtml).join('');
            return '' +
                '<article class="ota-mobile-result-card ota-mobile-result-card--roundtrip" data-flight-card data-offer-id="' + esc(offer.offer_id) + '">' +
                cardHeadHtml(offer, fareFamilyChipHtml(offer)) +
                '<div class="ota-mobile-result-card__legs">' + legsHtml + '</div>' +
                roundTripBaggageHtml(offer) +
                buildMobileFareFamilyPickerHtml(offer) +
                cardFooterHtml(offer, price) +
                '</article>';
        }

        function oneWayCardHtml(offer) {
            var pkrOk = offer.has_confirmed_pkr_quote && offer.displayed_price != null && Number(offer.displayed_price) > 0;
            var price = pkrOk ? formatPrice(offer.displayed_price) : 'Fare unavailable';
            var depTime = esc(offer.departure_time_display || offer.departure_time || '');
            var arrTime = esc(offer.arrival_time_display || offer.arrival_time || '');
            var depCode = esc(offer.departure_airport_code || defaultOrigin);
            var arrCode = esc(offer.arrival_airport_code || defaultDestination);
            var duration = esc(offer.itinerary_duration_display || offer.duration || '');
            var stopText = stopsLabel(offer.stops);
            var stopClass = Number(offer.stops || 0) === 0 ? '' : ' ota-mobile-result-card__stops--connecting';
            var bag = baggageLine(offer);
            var bagHtml = bag
                ? '<div class="ota-mobile-result-card__baggage">' + esc(bag) + '</div>'
                : '';

            return '' +
                '<article class="ota-mobile-result-card" data-flight-card data-offer-id="' + esc(offer.offer_id) + '">' +
                '<div class="ota-mobile-result-card__head">' +
                (offer.airline_logo_url
                    ? '<span class="ota-mobile-result-card__logo"><img src="' + esc(offer.airline_logo_url) + '" alt="" loading="lazy"></span>'
                    : '<span class="ota-mobile-result-card__logo">' + esc(offer.airline_code || '—') + '</span>') +
                '<span class="ota-mobile-result-card__airline">' + esc(offer.airline_name || offer.airline_code || 'Airline') + '</span></div>' +
                '<div class="ota-mobile-result-card__route">' +
                '<div class="ota-mobile-result-card__point"><span class="ota-mobile-result-card__time">' + depTime + '</span>' +
                '<span class="ota-mobile-result-card__code">' + depCode + '</span></div>' +
                '<div class="ota-mobile-result-card__mid"><span class="ota-mobile-result-card__duration">' + duration + '</span>' +
                '<span class="ota-mobile-result-card__mid-line" aria-hidden="true"></span>' +
                '<span class="ota-mobile-result-card__stops' + stopClass + '">' + esc(stopText) + '</span></div>' +
                '<div class="ota-mobile-result-card__point ota-mobile-result-card__point--arr"><span class="ota-mobile-result-card__time">' + arrTime + '</span>' +
                '<span class="ota-mobile-result-card__code">' + arrCode + '</span></div></div>' +
                bagHtml +
                buildMobileFareFamilyPickerHtml(offer) +
                cardFooterHtml(offer, price) +
                '</article>';
        }

        function cardHtml(offer) {
            if (hasRoundTripCardLayout(offer)) {
                return roundTripCardHtml(offer);
            }
            return oneWayCardHtml(offer);
        }

        function queryString(pageNo, filters) {
            var params = [
                'search_id=' + encodeURIComponent(searchId),
                'page=' + pageNo,
                'per_page=12',
                'sort=' + encodeURIComponent(filters.sort || 'recommended'),
            ];
            ['airline', 'stops', 'refundable', 'cabin', 'baggage', 'departure_window', 'arrival_window', 'fare_family'].forEach(function (key) {
                if (filters[key]) {
                    params.push(key + '=' + encodeURIComponent(filters[key]));
                }
            });
            return params.join('&');
        }

        function updateSummary(countShown, total) {
            if (summary) {
                summary.textContent = total > 0
                    ? ('Showing ' + countShown + ' of ' + total + ' flights')
                    : 'No flights found';
            }
            if (filterCount) {
                filterCount.textContent = total > 0
                    ? (total + ' flight' + (total === 1 ? '' : 's') + ' found')
                    : 'No flights found';
            }
        }

        function hideEmptyStates() {
            if (expiredMsg) {
                expiredMsg.hidden = true;
            }
            if (emptyMsg) {
                emptyMsg.hidden = true;
            }
            if (noResultsMsg) {
                noResultsMsg.hidden = true;
            }
        }

        function filterContainerForKey(key) {
            var selector = FILTER_CONTAINER_MAP[key];
            mlog('[mobile-results] filterContainerForKey', key, selector || '(none)');
            if (!selector) {
                mwarn('[mobile-results] filter key has no container selector', key);
                return null;
            }
            return filterEl(selector);
        }

        function setChipGroupActive(container, key, value) {
            if (!container) {
                return;
            }
            container.querySelectorAll('[data-filter-key="' + key + '"]').forEach(function (chip) {
                chip.classList.toggle('is-active', (chip.getAttribute('data-filter-value') || '') === (value || ''));
            });
        }

        function syncDrawerFromFilters(filters) {
            FILTER_DRAWER_KEYS.forEach(function (key) {
                var container = filterContainerForKey(key);
                if (!container) {
                    return;
                }
                setChipGroupActive(container, key, filters[key] || '');
            });

            if (sortSheet) {
                sortSheet.querySelectorAll('[data-sort-value]').forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.getAttribute('data-sort-value') === filters.sort);
                });
            }
        }

        function readFiltersFromDrawer() {
            var next = defaultFilterState();
            next.sort = draftFilters.sort;
            FILTER_DRAWER_KEYS.forEach(function (key) {
                var container = filterContainerForKey(key);
                if (!container) {
                    next[key] = draftFilters[key] || '';
                    return;
                }
                var active = container.querySelector('[data-filter-key="' + key + '"].is-active');
                next[key] = active ? (active.getAttribute('data-filter-value') || '') : '';
            });
            return next;
        }

        function fillDynamicChips(container, key, rows, valueKey, labelBuilder, allLabel, filters) {
            if (!container) {
                return;
            }
            var active = filters[key] || '';
            var html = '<button type="button" class="ota-mobile-filter-chip' + (active === '' ? ' is-active' : '') + '" data-filter-key="' + key + '" data-filter-value="">' + esc(allLabel || 'All') + '</button>';
            (rows || []).forEach(function (row) {
                var value = String(row[valueKey] || '');
                if (!value) {
                    return;
                }
                var label = labelBuilder(row);
                html += '<button type="button" class="ota-mobile-filter-chip' + (active === value ? ' is-active' : '') + '" data-filter-key="' + key + '" data-filter-value="' + esc(value) + '">' + esc(label) + '</button>';
            });
            container.innerHTML = html;
        }

        function syncFilterControls(meta) {
            if (!meta) {
                return;
            }

            fillDynamicChips(
                filterEl('[data-mobile-filter-airlines]'),
                'airline',
                meta.airlines || [],
                'code',
                function (row) { return (row.name || row.code) + ' (' + row.count + ')'; },
                'All',
                draftFilters
            );

            fillDynamicChips(
                filterEl('[data-mobile-filter-baggage]'),
                'baggage',
                meta.baggage_options || [],
                'value',
                function (row) { return row.label + ' (' + row.count + ')'; },
                'All',
                draftFilters
            );

            fillDynamicChips(
                filterEl('[data-mobile-filter-departure]'),
                'departure_window',
                meta.departure_time_windows || [],
                'value',
                function (row) { return row.label.replace(/\s*\([^)]*\)/, '') + ' (' + row.count + ')'; },
                'All',
                draftFilters
            );

            fillDynamicChips(
                filterEl('[data-mobile-filter-arrival]'),
                'arrival_window',
                meta.arrival_time_windows || [],
                'value',
                function (row) { return row.label.replace(/\s*\([^)]*\)/, '') + ' (' + row.count + ')'; },
                'All',
                draftFilters
            );

            fillDynamicChips(
                filterEl('[data-mobile-filter-fare-family]'),
                'fare_family',
                meta.fare_families || [],
                'value',
                function (row) { return row.label + ' (' + row.count + ')'; },
                'All',
                draftFilters
            );
        }

        function syncQuickChips() {
            if (!quickFilters) {
                return;
            }
            quickFilters.querySelectorAll('[data-mobile-results-chip]').forEach(function (chip) {
                var kind = chip.getAttribute('data-mobile-results-chip') || chip.getAttribute('data-quick-filter');
                var active = false;
                if (kind === 'cheapest') {
                    active = appliedFilters.sort === 'recommended' && !appliedFilters.stops;
                } else if (kind === 'fastest') {
                    active = appliedFilters.sort === 'fastest';
                } else if (kind === 'direct') {
                    active = appliedFilters.stops === 'direct';
                } else if (kind === 'airline') {
                    active = !!appliedFilters.airline;
                } else if (kind === 'stops') {
                    active = !!appliedFilters.stops;
                }
                chip.classList.toggle('is-active', active);
            });
        }

        function outboundSplitCardHtml(option) {
            return buildMobileSplitLegCard(option, {
                modifier: 'ota-mobile-result-card--outbound-split',
                legLabel: 'Outbound',
                ctaMode: 'link',
                linkHref: option.return_options_url || '#',
                ctaLabel: 'Select outbound',
                priceAmount: option.from_total_amount,
                priceFromPrefix: true,
                priceNote: 'total return fare',
                dataAttrs: ' data-outbound-split-card data-outbound-key="' + esc(option.outbound_key || '') + '"',
            });
        }

        function hasActiveFilters(filters) {
            return Object.keys(filters).some(function (key) {
                return key !== 'sort' && !!filters[key];
            });
        }

        function fetchPage(reset) {
            if (!reset && (loading || !hasMore)) {
                return;
            }

            if (fetchCtrl) {
                fetchCtrl.abort();
            }
            fetchCtrl = new AbortController();
            var thisGeneration = ++fetchGeneration;
            var thisCtrl = fetchCtrl;

            loading = true;
            if (loadMoreBtn) {
                loadMoreBtn.disabled = true;
            }

            var targetPage = reset ? 1 : page;
            var fetchParams = queryString(targetPage, appliedFilters);
            mlog('[mobile-results] fetch params', fetchParams);

            fetch(resultsUrl + '?' + fetchParams, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: fetchCtrl.signal,
            }).then(function (res) {
                mlog('[mobile-results] fetch status', res.status);
                if (thisGeneration !== fetchGeneration) {
                    return null;
                }
                if (res.status === 410) {
                    if (expiredMsg) {
                        expiredMsg.hidden = false;
                    }
                    hasMore = false;
                    throw new Error('expired');
                }
                if (!res.ok) {
                    throw new Error('http_' + res.status);
                }
                return res.json();
            }).then(function (json) {
                if (json === null || thisGeneration !== fetchGeneration) {
                    return;
                }
                hideEmptyStates();
                hideInlineError();

                if (reset) {
                    list.innerHTML = '';
                    offersById = {};
                    selectedFareOptionByOfferId = {};
                }

                if (json.flow === 'return_split_outbound') {
                    var outboundOptions = json.return_options || json.outbound_options || [];
                    if (!outboundOptions.length) {
                        if (targetPage === 1) {
                            if (noResultsMsg) {
                                noResultsMsg.hidden = false;
                            }
                            updateSummary(0, json.total || 0);
                        }
                        hasMore = false;
                        hasLoadedResults = true;
                        return;
                    }
                    list.insertAdjacentHTML('beforeend', outboundOptions.map(outboundSplitCardHtml).join(''));
                    if (window.OtaMobileSplitCards && window.OtaMobileSplitCards.bindSplitCardDetails) {
                        OtaMobileSplitCards.bindSplitCardDetails(list);
                    }
                    if (targetPage === 1) {
                        syncFilterControls(json.filters || null);
                        syncDrawerFromFilters(draftFilters);
                    }
                    updateSummary(
                        reset ? outboundOptions.length : list.querySelectorAll('[data-outbound-split-card]').length,
                        json.total || 0
                    );
                    if (json.search_freshness) {
                        updateSearchFreshnessBanner(json.search_freshness);
                    }
                    hasMore = !!json.has_more;
                    if (hasMore) {
                        page = targetPage + 1;
                    }
                    hasLoadedResults = true;
                    syncQuickChips();
                    return;
                }

                var offers = json.offers || [];
                offers.forEach(function (row) {
                    if (row && row.offer_id) {
                        offersById[row.offer_id] = row;
                    }
                });
                pruneSelectedFareOptions();
                if (!offers.length) {
                    if (targetPage === 1) {
                        if (hasActiveFilters(appliedFilters)) {
                            if (emptyMsg) {
                                emptyMsg.hidden = false;
                            }
                        } else if (noResultsMsg) {
                            if (json.empty_message) {
                                noResultsMsg.textContent = json.empty_message;
                            }
                            noResultsMsg.hidden = false;
                        }
                        updateSummary(0, json.total || 0);
                    }
                    hasMore = false;
                    hasLoadedResults = true;
                    syncQuickChips();
                    return;
                }

                list.insertAdjacentHTML('beforeend', offers.map(cardHtml).join(''));

                if (targetPage === 1) {
                    syncFilterControls(json.filters || null);
                    syncDrawerFromFilters(draftFilters);
                }

                updateSummary(
                    reset ? offers.length : list.querySelectorAll('[data-flight-card]').length,
                    json.total || 0
                );

                if (json.search_freshness) {
                    updateSearchFreshnessBanner(json.search_freshness);
                    scheduleFreshnessTicker();
                }

                hasMore = !!json.has_more;
                if (hasMore) {
                    page = targetPage + 1;
                }
                hasLoadedResults = true;
                syncQuickChips();
            }).catch(function (err) {
                if (thisGeneration !== fetchGeneration || thisCtrl !== fetchCtrl) {
                    return;
                }
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (err && err.message === 'expired') {
                    return;
                }
                if (typeof console !== 'undefined' && console.error) {
                    console.error('[ota-mobile-results] Failed to load results:', err);
                }
                if (targetPage === 1) {
                    if (hasVisibleResultCards()) {
                        showInlineError('Could not refresh results. Showing previous results.');
                    } else if (summary) {
                        summary.textContent = 'Unable to load flights. Try again.';
                    }
                }
            }).finally(function () {
                if (thisGeneration !== fetchGeneration) {
                    return;
                }
                loading = false;
                if (loadMoreBtn) {
                    loadMoreBtn.disabled = !hasMore;
                }
            });
        }

        function applyFilters() {
            draftFilters = readFiltersFromDrawer();
            appliedFilters = Object.assign({}, draftFilters);
            page = 1;
            hasMore = true;
            fetchPage(true);
        }

        function resetFilters() {
            appliedFilters = defaultFilterState();
            draftFilters = defaultFilterState();
            syncDrawerFromFilters(draftFilters);
            syncQuickChips();
            page = 1;
            hasMore = true;
            fetchPage(true);
        }

        function openFilterDrawer(scrollSection) {
            mlog('[mobile-results] opening filter drawer', scrollSection || '');
            draftFilters = Object.assign({}, appliedFilters);
            syncDrawerFromFilters(draftFilters);
            closeSortSheet();
            if (!filterDrawer) {
                return;
            }
            filterDrawer.classList.add('is-open');
            filterDrawer.setAttribute('aria-hidden', 'false');
            if (filterBackdrop) {
                filterBackdrop.classList.add('is-open');
                filterBackdrop.setAttribute('aria-hidden', 'false');
            }
            document.body.classList.add('ota-mobile-filter-open');

            if (scrollSection) {
                var section = filterDrawer.querySelector('[data-filter-section="' + scrollSection + '"]');
                if (section) {
                    window.requestAnimationFrame(function () {
                        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                }
            }
            mlog('[mobile-results] filter drawer open success');
        }

        function closeFilterDrawer(revertDraft) {
            if (revertDraft !== false) {
                draftFilters = Object.assign({}, appliedFilters);
                syncDrawerFromFilters(draftFilters);
            }
            if (!filterDrawer) {
                return;
            }
            filterDrawer.classList.remove('is-open');
            filterDrawer.setAttribute('aria-hidden', 'true');
            if (filterBackdrop) {
                filterBackdrop.classList.remove('is-open');
                filterBackdrop.setAttribute('aria-hidden', 'true');
            }
            document.body.classList.remove('ota-mobile-filter-open');
        }

        function openSortSheet() {
            if (!sortSheet) {
                return;
            }
            sortSheet.classList.add('is-open');
            sortSheet.setAttribute('aria-hidden', 'false');
            document.body.classList.add('ota-mobile-sort-open');
        }

        function closeSortSheet() {
            if (!sortSheet) {
                return;
            }
            sortSheet.classList.remove('is-open');
            sortSheet.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('ota-mobile-sort-open');
        }

        function applySort(value) {
            var nextSort = value || 'recommended';
            appliedFilters.sort = nextSort;
            draftFilters.sort = nextSort;
            if (sortSheet) {
                sortSheet.querySelectorAll('[data-sort-value]').forEach(function (option) {
                    option.classList.toggle('is-active', option.getAttribute('data-sort-value') === nextSort);
                });
            }
            closeSortSheet();
            syncQuickChips();
            page = 1;
            hasMore = true;
            fetchPage(true);
        }

        function handleQuickChip(kind) {
            if (!kind) {
                return;
            }
            mlog('[mobile-results] chip clicked', kind);
            if (kind === 'cheapest') {
                appliedFilters.sort = 'recommended';
                appliedFilters.stops = '';
            } else if (kind === 'fastest') {
                appliedFilters.sort = 'fastest';
                appliedFilters.stops = '';
            } else if (kind === 'direct') {
                appliedFilters.stops = 'direct';
            } else if (kind === 'airline') {
                openFilterDrawer('airline');
                return;
            } else if (kind === 'stops') {
                openFilterDrawer('stops');
                return;
            } else {
                return;
            }
            draftFilters = Object.assign({}, appliedFilters);
            syncDrawerFromFilters(draftFilters);
            syncQuickChips();
            page = 1;
            hasMore = true;
            fetchPage(true);
        }

        document.addEventListener('click', function (e) {
            var resultsRoot = document.querySelector('[data-mobile-results-root]');
            if (!resultsRoot) {
                return;
            }

            var quickChip = e.target.closest('[data-mobile-results-chip]');
            if (quickChip && resultsRoot.contains(quickChip)) {
                e.preventDefault();
                handleQuickChip(quickChip.getAttribute('data-mobile-results-chip') || quickChip.getAttribute('data-quick-filter'));
                return;
            }

            if (e.target.closest('[data-mobile-open-filter-bar]')) {
                e.preventDefault();
                openFilterDrawer();
                return;
            }

            if (e.target.closest('[data-mobile-filter-close]')) {
                closeFilterDrawer(true);
                return;
            }

            if (filterBackdrop && e.target.closest('[data-mobile-filter-backdrop]') && filterBackdrop.classList.contains('is-open')) {
                closeFilterDrawer(true);
                return;
            }

            if (e.target.closest('[data-mobile-filter-apply]')) {
                applyFilters();
                closeFilterDrawer(false);
                return;
            }

            if (e.target.closest('[data-mobile-filter-reset]')) {
                resetFilters();
                return;
            }

            if (e.target.closest('[data-mobile-filter-select-all="airline"]')) {
                draftFilters.airline = '';
                setChipGroupActive(filterEl('[data-mobile-filter-airlines]'), 'airline', '');
                return;
            }

            var filterChip = e.target.closest('.ota-mobile-filter-chip[data-filter-key]');
            if (filterChip && filterDrawer && filterDrawer.contains(filterChip)) {
                var key = filterChip.getAttribute('data-filter-key');
                var value = filterChip.getAttribute('data-filter-value') || '';
                if (!key || draftFilters[key] === undefined) {
                    return;
                }
                draftFilters[key] = value;
                var group = filterChip.parentElement;
                if (group) {
                    setChipGroupActive(group, key, value);
                }
                return;
            }

            if (e.target.closest('[data-mobile-open-sort]')) {
                openSortSheet();
                return;
            }

            if (sortSheet && sortSheet.contains(e.target)) {
                if (e.target.closest('[data-mobile-sort-close]')) {
                    closeSortSheet();
                    return;
                }
                var sortBtn = e.target.closest('[data-sort-value]');
                if (sortBtn) {
                    applySort(sortBtn.getAttribute('data-sort-value') || 'recommended');
                }
                return;
            }

            if (e.target.closest('[data-mobile-load-more]')) {
                fetchPage(false);
                return;
            }

            var fareOptionSelectBtn = e.target.closest('[data-fare-option-select]');
            if (fareOptionSelectBtn && resultsRoot.contains(fareOptionSelectBtn)) {
                e.preventDefault();
                var selectOid = fareOptionSelectBtn.getAttribute('data-offer-id') || '';
                var selectKey = fareOptionSelectBtn.getAttribute('data-fare-option-key') || '';
                var selectOffer = selectOid ? offersById[selectOid] : null;
                if (!selectOffer || !selectKey) {
                    return;
                }
                proceedMobileFareOptionSelect(selectOffer, selectKey, fareOptionSelectBtn);
                return;
            }

            var fareOptionBtn = e.target.closest('[data-fare-option-card]');
            if (fareOptionBtn && resultsRoot.contains(fareOptionBtn)) {
                e.preventDefault();
                var fareOid = fareOptionBtn.getAttribute('data-offer-id') || '';
                var fareKey = fareOptionBtn.getAttribute('data-fare-option-key') || '';
                if (!fareOid || !fareKey || !offersById[fareOid]) {
                    return;
                }
                clearOtherOfferFareSelections(fareOid);
                selectedFareOptionByOfferId[fareOid] = selectedFareOptionByOfferId[fareOid] === fareKey ? '' : fareKey;
                refreshMobileFareSelectionUi(fareOid);
                return;
            }

            var selectBtn = e.target.closest('[data-mobile-select]');
            if (selectBtn && resultsRoot.contains(selectBtn)) {
                e.preventDefault();
                var selectOid = selectBtn.getAttribute('data-offer-id') || '';
                var selectCard = selectBtn.closest('[data-flight-card]');
                if (!selectCard && selectOid) {
                    selectCard = list ? list.querySelector('[data-flight-card][data-offer-id="' + selectOid + '"]') : null;
                }
                if (!selectCard) {
                    selectCard = selectBtn.closest('[data-flight-card]');
                }
                var selectOffer = selectOid ? offersById[selectOid] : null;
                if (!selectOffer && selectCard) {
                    selectOid = selectCard.getAttribute('data-offer-id') || '';
                    selectOffer = selectOid ? offersById[selectOid] : null;
                }
                if (!selectOffer || !selectOffer.select_url) {
                    return;
                }
                if (selectOffer && offerNeedsFareChoiceBeforeCheckout(selectOffer)) {
                    promptMobileFareFamilySelection(selectCard);
                    return;
                }
                if (isIatiProviderOffer(selectOffer)) {
                    beginIatiSelectRevalidation(selectOffer.offer_id, '', selectBtn, selectOffer.select_url);
                    return;
                }
                if (selectBtn.tagName === 'A' && selectBtn.href) {
                    window.location.href = selectBtn.href;
                }
            }
        });

        document.addEventListener('keydown', function (e) {
            if (!document.querySelector('[data-mobile-results-root]')) {
                return;
            }
            if (e.key === 'Escape') {
                closeFilterDrawer(true);
                closeSortSheet();
            }
        });

        if (freshnessRefreshBtn) {
            freshnessRefreshBtn.addEventListener('click', function () {
                freshnessRefreshBtn.disabled = true;
                var refreshParams = new URLSearchParams();
                refreshParams.set('from', searchCriteria.origin || defaultOrigin);
                refreshParams.set('to', searchCriteria.destination || defaultDestination);
                refreshParams.set('depart', searchCriteria.depart_date || '');
                refreshParams.set('trip_type', searchCriteria.trip_type || 'one_way');
                refreshParams.set('cabin', searchCriteria.cabin || 'economy');
                refreshParams.set('adults', String(searchCriteria.adults || 1));
                refreshParams.set('children', String(searchCriteria.children || 0));
                refreshParams.set('infants', String(searchCriteria.infants || 0));
                if (searchCriteria.return_date) {
                    refreshParams.set('return_date', searchCriteria.return_date);
                }
                window.location.href = '/flights/results?' + refreshParams.toString();
            });
        }

        if (selectedOfferRefreshBtn) {
            selectedOfferRefreshBtn.addEventListener('click', function () {
                var offerId = selectedOfferRefreshBtn.getAttribute('data-offer-id') || '';
                selectedOfferRefreshBtn.disabled = true;
                postSelectedOfferRefresh(offerId, function (json) {
                    if (json.passengers_url) {
                        var refreshKey = offerId ? (selectedFareOptionByOfferId[offerId] || '') : '';
                        if (refreshKey) {
                            navigateToCheckoutWithFareKey(json.passengers_url, offerId, refreshKey);
                        } else {
                            window.location.href = json.passengers_url;
                        }
                        return;
                    }
                    fetchPage(true);
                });
                window.setTimeout(function () {
                    selectedOfferRefreshBtn.disabled = false;
                }, 3000);
            });
        }

        fetchPage(true);
    }

    function initMobileFlightDetails() {
        var root = document.querySelector('[data-mobile-fare-summary]');
        if (!root || root.getAttribute('data-mobile-fare-tabs-bound') === '1') {
            return;
        }
        root.setAttribute('data-mobile-fare-tabs-bound', '1');
        root.addEventListener('click', function (e) {
            var tab = e.target.closest('[data-mobile-fare-tab]');
            if (!tab || !root.contains(tab)) {
                return;
            }
            var tabId = tab.getAttribute('data-mobile-fare-tab');
            if (!tabId) {
                return;
            }
            root.querySelectorAll('[data-mobile-fare-tab]').forEach(function (el) {
                var active = el === tab;
                el.classList.toggle('is-active', active);
                el.setAttribute('aria-selected', active ? 'true' : 'false');
                el.tabIndex = active ? 0 : -1;
            });
            root.querySelectorAll('[data-mobile-fare-panel]').forEach(function (panel) {
                var show = panel.getAttribute('data-mobile-fare-panel') === tabId;
                if (show) {
                    panel.removeAttribute('hidden');
                    panel.hidden = false;
                } else {
                    panel.setAttribute('hidden', 'hidden');
                    panel.hidden = true;
                }
            });
        });
    }

    function initMobileBooking() {
        var passengersRoot = document.querySelector('[data-mobile-booking-passengers]');
        var confirmationRoot = document.querySelector('[data-mobile-booking-confirmation]');

        if (passengersRoot) {
            initMobilePassengerForm(passengersRoot);
        }

        if (confirmationRoot) {
            confirmationRoot.querySelectorAll('[data-mobile-copy-ref]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var value = btn.getAttribute('data-mobile-copy-ref') || '';
                    if (!value || !navigator.clipboard) {
                        return;
                    }
                    navigator.clipboard.writeText(value).then(function () {
                        var original = btn.textContent;
                        btn.textContent = 'Copied';
                        window.setTimeout(function () {
                            btn.textContent = original;
                        }, 1500);
                    }).catch(function () {});
                });
            });
        }
    }

    function initMobilePassengerForm(root) {
        var form = root.querySelector('[data-mobile-checkout-passenger-form]');
        if (!form) {
            return;
        }

        root.querySelectorAll('.ota-mobile-booking__doc-block[data-pk-domestic="1"]').forEach(function (block) {
            var sel = block.querySelector('.ota-mobile-pax-document-type');
            if (!sel) {
                return;
            }
            function syncDocBlock() {
                var nationalId = sel.value === 'national_id';
                block.querySelectorAll('.js-mobile-pax-passport').forEach(function (row) {
                    row.classList.toggle('is-hidden', nationalId);
                });
                block.querySelectorAll('.js-mobile-pax-national-id').forEach(function (row) {
                    row.classList.toggle('is-hidden', !nationalId);
                });
                var card = block.closest('[data-mobile-pax-card]') || block;
                card.querySelectorAll('[data-pax-passport-required]').forEach(function (el) {
                    el.required = !nationalId && !el.closest('.is-hidden');
                });
            }
            sel.addEventListener('change', syncDocBlock);
            syncDocBlock();
        });

        var cb = form.querySelector('#mobile-checkout-create-account');
        var box = form.querySelector('#mobile-checkout-inline-account-fields');
        var pwd = form.querySelector('#mobile-checkout-password');
        var pwdConfirm = form.querySelector('#mobile-checkout-password-confirm');
        var mismatch = form.querySelector('#mobile-checkout-password-mismatch');

        function clearPasswordMismatchUi() {
            if (mismatch) {
                mismatch.hidden = true;
            }
            if (pwdConfirm) {
                pwdConfirm.classList.remove('is-invalid');
            }
            if (pwd) {
                pwd.classList.remove('is-invalid');
            }
        }

        function syncPasswordMismatch() {
            if (!pwd || !pwdConfirm || !mismatch || !cb || !cb.checked) {
                clearPasswordMismatchUi();
                return;
            }
            var show = pwd.value !== '' && pwdConfirm.value !== '' && pwd.value !== pwdConfirm.value;
            mismatch.hidden = !show;
            pwdConfirm.classList.toggle('is-invalid', show);
            pwd.classList.toggle('is-invalid', show);
        }

        function syncAccountPanel() {
            if (!cb || !box) {
                return;
            }
            var open = cb.checked;
            box.classList.toggle('is-open', open);
            if (!open) {
                if (pwd) {
                    pwd.value = '';
                }
                if (pwdConfirm) {
                    pwdConfirm.value = '';
                }
                clearPasswordMismatchUi();
            } else {
                syncPasswordMismatch();
            }
        }

        if (cb && box) {
            cb.addEventListener('change', syncAccountPanel);
            syncAccountPanel();
        }
        if (pwd && pwdConfirm) {
            pwd.addEventListener('input', syncPasswordMismatch);
            pwdConfirm.addEventListener('input', syncPasswordMismatch);
            syncPasswordMismatch();
        }

        var contactName = form.querySelector('[data-mobile-checkout-contact-name]');
        var contactCountry = form.querySelector('[data-mobile-checkout-contact-country]');
        var contactNameEdited = contactName && contactName.value.trim() !== '';
        var contactCountryEdited = contactCountry && contactCountry.value.trim() !== '';

        if (contactName) {
            contactName.addEventListener('input', function () {
                contactNameEdited = true;
            });
        }
        if (contactCountry) {
            contactCountry.addEventListener('change', function () {
                contactCountryEdited = true;
            });
        }

        function leadPassengerIndex() {
            var selected = form.querySelector('input[name="lead_passenger_index"]:checked');
            if (selected) {
                return selected.value;
            }
            var hidden = form.querySelector('input[name="lead_passenger_index"][type="hidden"]');
            return hidden ? hidden.value : '0';
        }

        function leadField(name) {
            var idx = leadPassengerIndex();
            return form.querySelector('[name="passengers[' + idx + '][' + name + ']"]');
        }

        function syncContactFromLead() {
            var first = leadField('first_name');
            var last = leadField('last_name');
            if (!contactNameEdited && contactName && first && last) {
                var full = (first.value.trim() + ' ' + last.value.trim()).trim();
                if (full !== '') {
                    contactName.value = full;
                }
            }
        }

        form.querySelectorAll('input[name$="[first_name]"], input[name$="[last_name]"], input[name="lead_passenger_index"]').forEach(function (el) {
            el.addEventListener('input', syncContactFromLead);
            el.addEventListener('change', syncContactFromLead);
        });
        syncContactFromLead();

        var titleGenderMap = { Mr: 'M', Mrs: 'F', Ms: 'F', Miss: 'F', Master: 'M' };
        form.querySelectorAll('[data-mobile-pax-card]').forEach(function (card) {
            var titleSel = card.querySelector('.js-mobile-pax-title');
            var genderSel = card.querySelector('.js-mobile-pax-gender');
            if (!titleSel || !genderSel) {
                return;
            }

            var fromTitle = false;
            genderSel.addEventListener('change', function () {
                if (!fromTitle) {
                    genderSel.dataset.manualGender = '1';
                }
            });

            titleSel.addEventListener('change', function () {
                var mapped = titleGenderMap[titleSel.value];
                if (!mapped) {
                    return;
                }
                fromTitle = true;
                genderSel.value = mapped;
                delete genderSel.dataset.manualGender;
                fromTitle = false;
            });

            if (!genderSel.value && genderSel.dataset.manualGender !== '1') {
                var initial = titleGenderMap[titleSel.value];
                if (initial) {
                    genderSel.value = initial;
                }
            }
        });
    }
})();
