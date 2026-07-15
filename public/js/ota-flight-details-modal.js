/**
 * OTA-FLIGHT-RESULTS-UI-POLISH-4 — itinerary-only Flight Details modal (desktop results + split flow).
 */
(function (global) {
    'use strict';

    var modal = null;
    var bodyEl = null;
    var subtitleEl = null;
    var routeEl = null;
    var initialized = false;

    function esc(s) {
        if (s === null || s === undefined) {
            return '';
        }

        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function offerMetaFromData(data) {
        return {
            airline_code: data.airline_code || '',
            airline_logo_url: data.airline_logo_url || '',
            airline_name: data.airline_name || '',
        };
    }

    function buildItineraryHtml(data) {
        var builders = global.OtaFlightDetailBuilders;
        if (!builders) {
            return '<p class="ota-flight-details-modal__empty">Flight details are not available for this fare.</p>';
        }

        var journeys = Array.isArray(data.journeys_display) ? data.journeys_display : [];
        var offerMeta = offerMetaFromData(data);
        var tripType = String(data.trip_type || 'one_way');
        var hasGrouping = !!data.has_journey_grouping || (journeys.length >= 2 && !data.journey_grouping_unavailable);
        var useRoundTripTabs = tripType === 'round_trip' && journeys.length >= 2 && !data.journey_grouping_unavailable;
        var useMultiCityTabs = tripType === 'multi_city' && journeys.length >= 2;

        if (journeys.length && builders.buildFlightDetailJourneysHtml) {
            return builders.buildFlightDetailJourneysHtml(offerMeta, {
                journeys: journeys,
                tripType: tripType,
                detailsId: 'ota-flight-details-modal-tabs',
                hasJourneyGrouping: hasGrouping,
                useRoundTripTabs: useRoundTripTabs,
                useMultiCityTabs: useMultiCityTabs,
                fallbackSegments: Array.isArray(data.segments) ? data.segments : [],
                fallbackLayovers: Array.isArray(data.layovers_display) ? data.layovers_display : [],
                fallbackConnectionUnavailable: !!data.connection_details_unavailable,
                summaryOrigin: data.summary_origin || '',
                summaryDestination: data.summary_destination || '',
                summaryOriginCity: data.summary_origin_city || '',
                summaryDestinationCity: data.summary_destination_city || '',
                summaryDepTime: data.summary_dep_time || '',
                summaryDepDate: data.summary_dep_date || '',
                summaryArrTime: data.summary_arr_time || '',
                summaryArrDate: data.summary_arr_date || '',
                summaryArrOffset: data.summary_arr_offset || null,
                summaryDuration: data.summary_duration || '',
                summaryStops: data.summary_stops || '',
            });
        }

        if (builders.buildFlightDetailJourneysHtml && Array.isArray(data.segments) && data.segments.length) {
            return builders.buildFlightDetailJourneysHtml(offerMeta, {
                journeys: [],
                tripType: tripType,
                detailsId: 'ota-flight-details-modal-tabs',
                hasJourneyGrouping: false,
                fallbackSegments: data.segments,
                fallbackLayovers: Array.isArray(data.layovers_display) ? data.layovers_display : [],
                fallbackConnectionUnavailable: !!data.connection_details_unavailable,
                summaryOrigin: data.summary_origin || '',
                summaryDestination: data.summary_destination || '',
                summaryOriginCity: data.summary_origin_city || '',
                summaryDestinationCity: data.summary_destination_city || '',
                summaryDepTime: data.summary_dep_time || '',
                summaryDepDate: data.summary_dep_date || '',
                summaryArrTime: data.summary_arr_time || '',
                summaryArrDate: data.summary_arr_date || '',
                summaryArrOffset: data.summary_arr_offset || null,
                summaryDuration: data.summary_duration || '',
                summaryStops: data.summary_stops || '',
            });
        }

        if (builders.buildJourneyDetailCardHtml && data.journey_display && typeof data.journey_display === 'object') {
            return '<div class="ota-flight-detail-shell"><div class="ota-flight-detail-card">' +
                builders.buildJourneyDetailCardHtml(data.journey_display, offerMeta) +
                '</div></div>';
        }

        return '<p class="ota-flight-details-modal__empty">Flight details are not available for this fare.</p>';
    }

    function init() {
        if (initialized) {
            return;
        }
        modal = document.getElementById('ota-flight-details-modal');
        if (!modal) {
            return;
        }
        bodyEl = modal.querySelector('[data-flight-details-body]');
        subtitleEl = modal.querySelector('[data-flight-details-subtitle]');
        routeEl = modal.querySelector('[data-flight-details-route]');

        Array.prototype.forEach.call(modal.querySelectorAll('[data-close-flight-details]'), function (el) {
            el.addEventListener('click', closeModal);
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal.querySelector('.ota-flight-details-modal__backdrop')) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && !modal.hidden) {
                closeModal();
            }
        });
        initialized = true;
    }

    function closeModal() {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ota-flight-details-modal-open');
    }

    function openModal(data) {
        init();
        if (!modal || !bodyEl || !data) {
            return;
        }

        if (bodyEl) {
            bodyEl.innerHTML = buildItineraryHtml(data);
        }
        if (subtitleEl) {
            subtitleEl.textContent = 'Review connections, segments, and layovers for this itinerary.';
        }
        if (routeEl) {
            routeEl.textContent = '';
            routeEl.hidden = true;
        }

        if (global.OtaFlightDetailBuilders && bodyEl) {
            global.OtaFlightDetailBuilders.bindFlightDetailTabs(bodyEl);
        }

        modal.hidden = false;
        modal.removeAttribute('aria-hidden');
        document.body.classList.add('ota-flight-details-modal-open');
    }

    function bindFlightDetailsLinks(containerEl) {
        init();
        if (!containerEl) {
            return;
        }
        Array.prototype.forEach.call(containerEl.querySelectorAll('[data-flight-details-open]'), function (btn) {
            if (btn.getAttribute('data-bound-flight-details') === '1') {
                return;
            }
            btn.setAttribute('data-bound-flight-details', '1');
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var raw = btn.getAttribute('data-flight-details-payload');
                if (!raw) {
                    return;
                }
                try {
                    openModal(JSON.parse(raw));
                } catch (err) {
                    /* ignore malformed payload */
                }
            });
        });
    }

    global.OtaFlightDetailsModal = {
        init: init,
        open: openModal,
        close: closeModal,
        bindLinks: bindFlightDetailsLinks,
    };
}(typeof window !== 'undefined' ? window : this));
