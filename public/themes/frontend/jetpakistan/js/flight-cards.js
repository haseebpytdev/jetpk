/**
 * JetPakistan flight result cards — branded fare tray + fare details modal (JetPK-only).
 * Extends JetPkResultCards from results.js; does not replace OTA booking handlers.
 */
(function () {
  'use strict';

  if (!document.body.classList.contains('jp-flights-results')) {
    return;
  }

  var JP_FARE_ICONS = {
    cabin:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="7" width="12" height="14" rx="2"/><path d="M9 7V4h6v3M10 11v6M14 11v6"/></svg>',
    checked:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="8" width="16" height="12" rx="2"/><path d="M9 8V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v3M12 12v4"/></svg>',
    meal:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 3v7a2 2 0 0 0 2 2h0a2 2 0 0 0 2-2V3M6 12v9M18 3c-1.7 0-3 2.2-3 5s1.3 4 3 4v9"/></svg>',
    refund:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 1 3 6.7M3 21v-5h5"/></svg>',
    plane:
      '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 15.5 14 12V5.5A1.5 1.5 0 0 0 12.5 4 1.5 1.5 0 0 0 11 5.5V12l-7 3.5V17l7-2v3l-2 1.3V21l3.5-1 3.5 1v-1.7L13 18v-3l8 2z"/></svg>',
    close:
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
  };

  function escFallback(s) {
    if (s === null || s === undefined) {
      return '';
    }
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/"/g, '&quot;');
  }

  function cleanDisplayText(val, fallback) {
    if (val === null || val === undefined) {
      return fallback;
    }
    if (typeof val === 'object') {
      return fallback;
    }
    var text = String(val).trim();
    if (!text || text === 'undefined' || text === 'null' || text === '[object Object]' || text === '--' || text === '-') {
      return fallback;
    }
    if ((text.charAt(0) === '{' || text.charAt(0) === '[') && text.length > 40) {
      return fallback;
    }
    return text;
  }

  function jpField(opt, keys) {
    if (!opt) {
      return '';
    }
    for (var i = 0; i < keys.length; i++) {
      var v = opt[keys[i]];
      if (v !== null && v !== undefined && String(v).trim() !== '') {
        return String(v).trim();
      }
    }
    return '';
  }

  function jpBenefitValueClass(value) {
    var text = String(value || '').toLowerCase();
    if (/not\s+included|non[- ]?refund|not\s+specified|check\s+fare|airline\s+policy/.test(text)) {
      return ' jp-fare-feature__value--muted';
    }
    if (/refund/.test(text) && !/non/.test(text)) {
      return ' jp-fare-feature__value--ok';
    }
    if (/included|kg|pc|piece|allow/.test(text)) {
      return ' jp-fare-feature__value--ok';
    }
    return '';
  }

  function jpBuildBenefitRowsHtml(opt, esc) {
    var carry = cleanDisplayText(
      jpField(opt, ['carry_on_summary', 'carry_on', 'hand_carry', 'cabin_baggage', 'hand_baggage']),
      'Airline policy'
    );
    var checked = cleanDisplayText(
      jpField(opt, ['check_in_summary', 'checked_baggage', 'check_in', 'baggage_summary', 'baggage']),
      'Not included'
    );
    var meal = cleanDisplayText(jpField(opt, ['meal_included', 'meal', 'meals', 'meal_display']), 'Not specified');
    var refund = cleanDisplayText(
      jpField(opt, ['refundable_display', 'refund_rule', 'refund', 'refundable']),
      'Check fare rules'
    );

    var rows = [
      ['cabin', 'Carry-on baggage', carry],
      ['checked', 'Checked baggage', checked],
      ['meal', 'Meal', meal],
      ['refund', 'Refund', refund],
    ];

    return rows
      .map(function (row) {
        var iconKey = row[0];
        var label = row[1];
        var value = row[2];
        return (
          '<li class="jp-fare-feature ota-branded-fare-card__row">' +
          '<span class="jp-fare-feature__icon">' +
          (JP_FARE_ICONS[iconKey] || '') +
          '</span>' +
          '<span class="jp-fare-feature__label ota-branded-fare-card__row-label">' +
          esc(label) +
          '</span>' +
          '<span class="jp-fare-feature__value ota-branded-fare-card__row-value' +
          jpBenefitValueClass(value) +
          '">' +
          esc(value) +
          '</span>' +
          '</li>'
        );
      })
      .join('');
  }

  function fareCtaPrice(opt, helpers) {
    var formatCardButtonRs = helpers.formatCardButtonRs;
    var formatBrandedFarePrice = helpers.formatBrandedFarePrice;
    if (typeof formatCardButtonRs === 'function' && opt.displayed_price != null && isFinite(Number(opt.displayed_price))) {
      return formatCardButtonRs(opt.displayed_price);
    }
    var text = typeof formatBrandedFarePrice === 'function' ? formatBrandedFarePrice(opt) : '';
    if (!text) {
      return '';
    }
    return String(text).replace(/^PKR\s+/i, 'Rs. ').replace(/^Approx\.\s*/i, '').trim();
  }

  function buildBrandedFaresPanelHtml(offer, state, helpers) {
    helpers = helpers || {};
    var esc = helpers.esc || escFallback;
    var payloadAttr = helpers.payloadAttr;
    var buildFareSummaryPayload = helpers.buildFareSummaryPayload;
    var searchIdAttr = helpers.searchId ? String(helpers.searchId) : '';

    if (!offer || !(offer.has_fare_choice_options || (offer.branded_fares_display_enabled && offer.has_branded_fares))) {
      return '';
    }

    if (window.OtaBrandedFares && typeof OtaBrandedFares.offerHasFareChoicePanel === 'function') {
      if (!OtaBrandedFares.offerHasFareChoicePanel(offer)) {
        return '';
      }
    }

    var opts = offer.fare_family_options_display || offer.branded_fares_display_options || [];
    var buildRenderedFareOptions = helpers.buildRenderedFareOptions;
    var renderedFareOptions =
      typeof buildRenderedFareOptions === 'function' ? buildRenderedFareOptions(opts) : opts;
    if (!renderedFareOptions.length) {
      return '';
    }

    var selectionActive = !!(offer.branded_fares_selection_active || offer.universal_fare_selection_active);
    var selectedKey = (state && state.selectedFareOptionByOfferId[offer.offer_id]) || '';
    var isExpanded = !!(state && state.expandedBrandedFaresByOfferId[offer.offer_id]);
    var renderedCount = renderedFareOptions.length;
    var useSlider = renderedCount > 3;

    var cards = renderedFareOptions
      .map(function (opt) {
        var key = String(opt.option_key || opt.fare_option_key || opt.fareOptionKey || '');
        var offerRef = String(offer.offer_id || offer.id || '').trim();
        var sharedFareAttrs =
          ' data-offer-id="' +
          esc(offerRef) +
          '" data-selected-offer-reference="' +
          esc(offerRef) +
          '" data-fare-option-key="' +
          esc(key) +
          '"' +
          (searchIdAttr ? ' data-search-id="' + esc(searchIdAttr) + '"' : '') +
          (offer.select_url ? ' data-select-url="' + esc(String(offer.select_url)) + '"' : '');
        var isSelected = selectionActive && selectedKey === key;
        var cardClass =
          'jp-fare-card ota-branded-fare-card' +
          (opt.is_synthetic_default ? ' ota-branded-fare-card--compact-default' : '') +
          (isSelected ? ' is-selected' : '') +
          (opt.is_cheapest ? ' is-cheapest' : '');
        var summaryPayload =
          typeof payloadAttr === 'function' && typeof buildFareSummaryPayload === 'function'
            ? payloadAttr(buildFareSummaryPayload(offer, opt))
            : '';
        var features = jpBuildBenefitRowsHtml(opt, esc);
        var priceText = fareCtaPrice(opt, helpers);
        var cheapestBadge = opt.is_cheapest
          ? '<span class="ota-branded-fare-card__badge ota-branded-fare-card__badge--cheapest">Cheapest</span>'
          : '';
        var flexibleBadge =
          opt.is_flexible || /freedom|flexible/i.test(String(opt.name || opt.brand_code || ''))
            ? '<span class="ota-branded-fare-card__badge ota-branded-fare-card__badge--flex">Flexible</span>'
            : '';
        var selectedBadge = isSelected
          ? '<span class="ota-branded-fare-card__badge ota-branded-fare-card__badge--selected" data-fare-selected-badge>Selected</span>'
          : '<span class="ota-branded-fare-card__badge ota-branded-fare-card__badge--selected" data-fare-selected-badge hidden>Selected</span>';
        var ctaLabel = priceText || (isSelected ? 'Selected' : 'Select');
        var selectControl = '';
        if (selectionActive) {
          selectControl =
            '<button type="button" class="jp-fare-card__cta ota-branded-fare-card__cta btn btn-primary" data-fare-option-card' +
            sharedFareAttrs +
            ' aria-pressed="' +
            (isSelected ? 'true' : 'false') +
            '" aria-label="' +
            esc('Select fare ' + ctaLabel) +
            '">' +
            esc(ctaLabel) +
            '</button>';
        } else if (renderedCount === 1) {
          selectControl =
            '<button type="button" class="jp-fare-card__cta ota-branded-fare-card__cta btn btn-primary" data-fare-option-card' +
            sharedFareAttrs +
            ' aria-label="' +
            esc('Select fare ' + ctaLabel) +
            '">' +
            esc(ctaLabel) +
            '</button>';
        }

        var wrapAttrs =
          ' data-fare-option-card-wrap' +
          sharedFareAttrs +
          ' data-option-key="' +
          esc(key) +
          '"';
        if (selectionActive) {
          wrapAttrs += ' role="button" tabindex="0" aria-pressed="' + (isSelected ? 'true' : 'false') + '"';
        }

        return (
          '<article class="' +
          cardClass +
          '"' +
          wrapAttrs +
          '>' +
          '<div class="ota-branded-fare-card__header">' +
          '<div class="ota-branded-fare-card__title-block">' +
          '<h5 class="ota-branded-fare-card__name">' +
          esc(opt.name || '') +
          '</h5>' +
          '</div>' +
          '<div class="ota-branded-fare-card__badges">' +
          cheapestBadge +
          flexibleBadge +
          selectedBadge +
          '</div>' +
          '</div>' +
          '<ul class="jp-fare-card__list ota-branded-fare-card__matrix">' +
          features +
          '</ul>' +
          '<div class="ota-branded-fare-card__footer">' +
          (priceText ? '<p class="ota-branded-fare-card__price">' + esc(priceText) + '</p>' : '') +
          '<div class="ota-branded-fare-card__actions">' +
          '<button type="button" class="jp-fare-card__details ota-branded-fare-card__details ota-fare-summary-trigger" data-fare-summary-open data-fare-summary-payload="' +
          summaryPayload +
          '"' +
          sharedFareAttrs +
          '>Details</button>' +
          selectControl +
          '</div></div></article>'
        );
      })
      .join('');

    var gridClass =
      'ota-branded-fares-panel__grid' + (useSlider ? ' ota-branded-fares-panel__grid--slider' : ' ota-branded-fares-panel__grid--grid');
    if (!useSlider && renderedCount === 1 && renderedFareOptions[0] && renderedFareOptions[0].is_synthetic_default) {
      gridClass += ' ota-branded-fares-panel__grid--single-default';
    }
    var gridCountAttr = useSlider ? '' : ' data-fare-count="' + renderedCount + '"';
    var gridHtml = '<div class="' + gridClass + '" data-branded-fares-grid' + gridCountAttr + '>' + cards + '</div>';
    var bodyInner = useSlider
      ? '<div class="ota-branded-fares-carousel" data-branded-fares-carousel data-carousel-index="0" data-nav-hidden="false">' +
        '<button type="button" class="ota-branded-fares-carousel__nav ota-branded-fares-carousel__nav--prev" data-branded-fares-prev aria-label="Previous fares"><span aria-hidden="true">‹</span></button>' +
        '<div class="ota-branded-fares-carousel__viewport">' +
        gridHtml +
        '</div>' +
        '<button type="button" class="ota-branded-fares-carousel__nav ota-branded-fares-carousel__nav--next" data-branded-fares-next aria-label="Next fares"><span aria-hidden="true">›</span></button>' +
        '</div>'
      : gridHtml;

    var headingHtml = '<p class="jp-fare-tray__head ota-branded-fares-panel__heading">Select a fare option</p>';
    var hintHtml = selectionActive
      ? '<p class="ota-fare-family-selection-hint" hidden role="status">Select a fare option to continue</p>'
      : '';

    return (
      '<div class="jp-fare-tray ota-branded-fares-panel" data-branded-fares-panel data-rendered-fare-count="' +
      renderedCount +
      '" data-slider-active="' +
      (useSlider ? 'true' : 'false') +
      '" data-offer-id="' +
      esc(offer.offer_id || '') +
      '">' +
      '<button type="button" class="ota-branded-fares-panel__toggle ota-visually-hidden" data-branded-fares-toggle data-offer-id="' +
      esc(offer.offer_id || '') +
      '" aria-expanded="' +
      (isExpanded ? 'true' : 'false') +
      '" aria-label="Toggle fare options"></button>' +
      hintHtml +
      '<div class="ota-branded-fares-panel__body" data-branded-fares-body' +
      (isExpanded ? '' : ' hidden') +
      '>' +
      headingHtml +
      bodyInner +
      '</div></div>'
    );
  }

  /* ================================================= JetPK fare details modal === */
  var jpFareModal = null;
  var jpFareModalBody = null;
  var jpFareModalFoot = null;
  var jpFareModalTitle = null;
  var jpFareModalSub = null;
  var jpFareModalLastFocus = null;
  var jpFareModalActiveTrigger = null;
  var jpFareModalBound = false;

  function jpFormatPkr(amount) {
    if (window.OtaFareBreakdownModal && typeof OtaFareBreakdownModal.formatPkr === 'function') {
      return OtaFareBreakdownModal.formatPkr(amount);
    }
    if (amount === null || amount === undefined || !isFinite(Number(amount))) {
      return '—';
    }
    return 'PKR ' + Number(amount).toLocaleString('en-PK', { maximumFractionDigits: 0 });
  }

  function jpResolveGrandTotal(data) {
    if (window.OtaFareBreakdownModal && typeof OtaFareBreakdownModal.readPassengerPricingBreakdown === 'function') {
      var total = Number(data.displayed_price || data.final_total || data.final_customer_price || 0);
      return total;
    }
    return Number(data.displayed_price || data.final_total || data.final_customer_price || 0);
  }

  function jpResolveRouteLabel(data) {
    var label = cleanDisplayText(data.route_label, '');
    if (label) {
      return label;
    }
    var journeys = Array.isArray(data.journeys_display) ? data.journeys_display : [];
    if (journeys.length && journeys[0] && journeys[0].origin && journeys[0].destination) {
      return String(journeys[0].origin) + ' → ' + String(journeys[0].destination);
    }
    return '';
  }

  function jpKvRow(iconKey, label, value, valueClass) {
    var icon = iconKey ? JP_FARE_ICONS[iconKey] || '' : '';
    var iconHtml = icon ? '<span class="jp-fare-modal__kv-icon">' + icon + '</span>' : '';
    return (
      '<div class="jp-fare-detail-row jp-fare-modal__kv-row">' +
      '<span class="jp-fare-modal__kv-k">' +
      iconHtml +
      escFallback(label) +
      '</span>' +
      '<span class="jp-fare-modal__kv-v' +
      (valueClass ? ' ' + valueClass : '') +
      '">' +
      escFallback(value) +
      '</span>' +
      '</div>'
    );
  }

  function jpPolicyText(data, keys, fallback) {
    for (var i = 0; i < keys.length; i++) {
      var text = cleanDisplayText(data[keys[i]], '');
      if (text) {
        return text;
      }
    }
    return fallback;
  }

  function jpBuildBaggagePanel(data) {
    var route = jpResolveRouteLabel(data);
    var cabin = cleanDisplayText(data.baggage_cabin_display, 'Airline policy');
    var checked = cleanDisplayText(data.baggage_checked_display, 'Not included');
    var summary = cleanDisplayText(data.baggage_summary_display, '');
    if (summary && summary !== cabin && summary !== checked) {
      checked = summary;
    }
    var routeHtml = route
      ? '<p class="jp-fare-modal__route">' + JP_FARE_ICONS.plane + '<span>' + escFallback(route) + '</span></p>'
      : '';
    return (
      routeHtml +
      '<div class="jp-fare-modal__kv">' +
      jpKvRow('cabin', 'Carry-on baggage', cabin, /not|policy/i.test(cabin) ? 'jp-fare-modal__kv-v--muted' : '') +
      jpKvRow(
        'checked',
        'Checked baggage',
        checked,
        /not|0\s*kg/i.test(checked) ? 'jp-fare-modal__kv-v--off' : 'jp-fare-modal__kv-v--ok'
      ) +
      '</div>'
    );
  }

  function jpBuildPolicyPanel(data) {
    var refund = jpPolicyText(
      data,
      ['refundable_display', 'refund_rule'],
      data.refundable === true ? 'Refundable' : data.refundable === false ? 'Non-refundable' : 'Check fare rules'
    );
    var changes = jpPolicyText(
      data,
      ['change_rule', 'modification_rule', 'exchange_rule'],
      'As per airline rules'
    );
    var cancel = jpPolicyText(data, ['cancellation_rule'], '');
    var rows =
      jpKvRow(
        'refund',
        'Refund',
        refund,
        /refundable/i.test(refund) && !/non/i.test(refund) ? 'jp-fare-modal__kv-v--ok' : 'jp-fare-modal__kv-v--off'
      ) + jpKvRow('', 'Changes', changes, '');
    if (cancel) {
      rows += jpKvRow('', 'Cancellation', cancel, '');
    }
    return '<div class="jp-fare-modal__kv">' + rows + '</div>';
  }

  function jpBuildFareDetailsPanel(data) {
    var rows = [];
    if (window.OtaFareBreakdownModal && typeof OtaFareBreakdownModal.buildFarePassengerBreakdownRows === 'function') {
      rows = OtaFareBreakdownModal.buildFarePassengerBreakdownRows(data);
    }
    if (!rows.length) {
      var counts = data.passenger_counts || data.search_passengers || { adults: 1, children: 0, infants: 0 };
      rows = [
        {
          label: 'Adult',
          qty: Number(counts.adults || 1),
          base: Number(data.base_fare || 0),
          tax: Number(data.taxes || 0),
          total: jpResolveGrandTotal(data),
          fallback: false,
        },
      ];
    }

    var body = rows
      .map(function (row) {
        var base = row.fallback ? 'Included in total' : jpFormatPkr(row.base);
        var tax = row.fallback ? 'Included in total' : jpFormatPkr(row.tax);
        var total = row.fallback ? jpFormatPkr(jpResolveGrandTotal(data)) : jpFormatPkr(row.total);
        return (
          '<tr>' +
          '<td>' +
          escFallback(row.label) +
          '</td>' +
          '<td>' +
          escFallback(String(row.qty)) +
          '</td>' +
          '<td>' +
          escFallback(base) +
          '</td>' +
          '<td>' +
          escFallback(tax) +
          '</td>' +
          '<td>' +
          escFallback(total) +
          '</td>' +
          '</tr>'
        );
      })
      .join('');

    var adminMarkup = Number(data.admin_markup || 0);
    var serviceFee = Number(data.service_fee || 0);
    if (adminMarkup > 0) {
      body +=
        '<tr class="jp-fare-modal__table-extra"><td colspan="4">Agency charge</td><td>' +
        escFallback(jpFormatPkr(adminMarkup)) +
        '</td></tr>';
    }
    if (serviceFee > 0) {
      body +=
        '<tr class="jp-fare-modal__table-extra"><td colspan="4">Service fee</td><td>' +
        escFallback(jpFormatPkr(serviceFee)) +
        '</td></tr>';
    }

    return (
      '<table class="jp-fare-modal__table">' +
      '<thead><tr><th>Passenger</th><th>Qty</th><th>Base fare</th><th>Taxes &amp; fees</th><th>Total</th></tr></thead>' +
      '<tbody>' +
      body +
      '</tbody></table>'
    );
  }

  function jpBuildModalTabsHtml(data) {
    var tabs = ['Baggage Policy', 'Fare Policy', 'Fare Details'];
    var panels = [jpBuildBaggagePanel(data), jpBuildPolicyPanel(data), jpBuildFareDetailsPanel(data)];
    var tabsHtml = tabs
      .map(function (label, i) {
        return (
          '<button type="button" class="jp-fare-modal__tab" role="tab" id="jpFareTab' +
          i +
          '" aria-controls="jpFarePanel' +
          i +
          '" aria-selected="' +
          (i === 0 ? 'true' : 'false') +
          '" data-jp-fare-tab="' +
          i +
          '">' +
          escFallback(label) +
          '</button>'
        );
      })
      .join('');
    var panelsHtml = panels
      .map(function (panel, i) {
        return (
          '<div class="jp-fare-modal__panel jp-fare-modal-tabs__panel" role="tabpanel" id="jpFarePanel' +
          i +
          '" aria-labelledby="jpFareTab' +
          i +
          '"' +
          (i === 0 ? '' : ' hidden') +
          '>' +
          panel +
          '</div>'
        );
      })
      .join('');
    return (
      '<div class="jp-fare-modal__tabs" role="tablist" aria-label="Fare summary">' +
      tabsHtml +
      '</div>' +
      panelsHtml
    );
  }

  function jpActivateFareTab(index) {
    if (!jpFareModal) {
      return;
    }
    var tabs = jpFareModal.querySelectorAll('[data-jp-fare-tab]');
    var panels = jpFareModal.querySelectorAll('.jp-fare-modal__panel');
    Array.prototype.forEach.call(tabs, function (tab) {
      var active = tab.getAttribute('data-jp-fare-tab') === String(index);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.classList.toggle('is-active', active);
    });
    Array.prototype.forEach.call(panels, function (panel, i) {
      var show = i === index;
      panel.hidden = !show;
      if (show) {
        panel.removeAttribute('hidden');
      } else {
        panel.setAttribute('hidden', 'hidden');
      }
    });
  }

  function jpEnsureFareModal() {
    if (jpFareModal) {
      return true;
    }
    jpFareModal = document.getElementById('jpFareModal');
    if (!jpFareModal) {
      jpFareModal = document.createElement('div');
      jpFareModal.id = 'jpFareModal';
      jpFareModal.className = 'jp-fare-modal';
      jpFareModal.hidden = true;
      jpFareModal.setAttribute('aria-hidden', 'true');
      jpFareModal.innerHTML =
        '<div class="jp-fare-modal__backdrop" data-jp-fare-modal-close tabindex="-1"></div>' +
        '<div class="jp-fare-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="jpFareModalTitle">' +
        '<div class="jp-fare-modal__header">' +
        '<div class="jp-fare-modal__header-text">' +
        '<h2 class="jp-fare-modal__title" id="jpFareModalTitle">Fare Summary</h2>' +
        '<p class="jp-fare-modal__subtitle" data-jp-fare-modal-sub></p>' +
        '</div>' +
        '<button type="button" class="jp-fare-modal__close" data-jp-fare-modal-close aria-label="Close">' +
        JP_FARE_ICONS.close +
        '</button>' +
        '</div>' +
        '<div class="jp-fare-modal__body" data-jp-fare-modal-body></div>' +
        '<div class="jp-fare-modal__footer" data-jp-fare-modal-foot></div>' +
        '</div>';
      document.body.appendChild(jpFareModal);
    }
    jpFareModalBody = jpFareModal.querySelector('[data-jp-fare-modal-body]');
    jpFareModalFoot = jpFareModal.querySelector('[data-jp-fare-modal-foot]');
    jpFareModalTitle = jpFareModal.querySelector('#jpFareModalTitle');
    jpFareModalSub = jpFareModal.querySelector('[data-jp-fare-modal-sub]');
    return !!(jpFareModalBody && jpFareModalFoot);
  }

  function jpCloseFareModal() {
    if (!jpFareModal) {
      return;
    }
    jpFareModal.classList.remove('is-open');
    jpFareModal.hidden = true;
    jpFareModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('jp-fare-modal-open');
    if (jpFareModalLastFocus && jpFareModalLastFocus.focus) {
      jpFareModalLastFocus.focus();
    }
    jpFareModalActiveTrigger = null;
  }

  function jpEscapeSelector(value) {
    var raw = String(value || '');
    if (typeof CSS !== 'undefined' && CSS.escape) {
      return CSS.escape(raw);
    }
    return raw.replace(/["\\]/g, '\\$&');
  }

  function jpReadDataAttr(el, names) {
    if (!el) {
      return '';
    }
    for (var i = 0; i < names.length; i++) {
      var val = el.getAttribute(names[i]);
      if (val !== null && String(val).trim() !== '') {
        return String(val).trim();
      }
    }
    return '';
  }

  function jpFindFareCardFromTrigger(btn) {
    if (!btn) {
      return null;
    }
    if (typeof window.otaResolveFlightCard === 'function') {
      return window.otaResolveFlightCard(btn);
    }
    return btn.closest('[data-flight-card], .jp-flight-card, .ota-result-pro-card');
  }

  function jpResolveModalSelection(trigger, selectBtn) {
    var source = selectBtn || trigger;
    var fareKey = jpReadDataAttr(source, ['data-fare-option-key', 'data-option-key']);
    if (!fareKey && trigger) {
      fareKey = jpReadDataAttr(trigger, ['data-fare-option-key', 'data-option-key']);
    }
    var flightCard = jpFindFareCardFromTrigger(trigger || selectBtn);
    if (typeof window.otaBuildBrandedFareSelectionPayload === 'function') {
      return window.otaBuildBrandedFareSelectionPayload(flightCard, fareKey, trigger || selectBtn);
    }
    return {
      offer_id: jpReadDataAttr(source, ['data-offer-id', 'data-selected-offer-reference']),
      fare_option_key: fareKey,
      flight_card: flightCard,
      valid: !!(flightCard && fareKey),
    };
  }

  function jpHandleModalSelect() {
    var trigger = jpFareModalActiveTrigger;
    var selectBtn = jpFareModal ? jpFareModal.querySelector('[data-jp-fare-modal-select]') : null;
    var payload = jpResolveModalSelection(trigger, selectBtn);
    jpCloseFareModal();
    if (!payload || !payload.valid || !payload.flight_card || !payload.fare_option_key) {
      if (typeof window.otaWarnIncompleteBrandedFarePayload === 'function') {
        window.otaWarnIncompleteBrandedFarePayload(payload || {}, 'jpHandleModalSelect');
      }
      return;
    }
    if (typeof window.otaProceedBrandedFareCheckout === 'function') {
      if (window.otaProceedBrandedFareCheckout(payload.flight_card, payload.fare_option_key, trigger || selectBtn)) {
        return;
      }
    }
    var fareBtn = payload.flight_card.querySelector(
      '[data-fare-option-card][data-fare-option-key="' + jpEscapeSelector(payload.fare_option_key) + '"]'
    );
    if (fareBtn && typeof fareBtn.click === 'function') {
      fareBtn.click();
    }
  }

  function jpOpenFareModal(data, triggerBtn) {
    if (!jpEnsureFareModal() || !data) {
      return;
    }
    jpFareModalLastFocus = document.activeElement;
    jpFareModalActiveTrigger = triggerBtn || null;

    var brandName = cleanDisplayText(data.fare_family_name || data.brand_name, 'Fare option');
    if (jpFareModalTitle) {
      jpFareModalTitle.textContent = 'Fare Summary';
    }
    if (jpFareModalSub) {
      jpFareModalSub.textContent = brandName + ' — review baggage, policy, and pricing.';
    }
    jpFareModalBody.innerHTML = jpBuildModalTabsHtml(data);

    var grandTotal = jpFormatPkr(jpResolveGrandTotal(data));
    var ctaLabel = 'Select';
    if (triggerBtn) {
      var fareBtn = jpFindFareCardFromTrigger(triggerBtn);
      if (fareBtn) {
        var priceBtn = fareBtn.querySelector(
          '[data-fare-option-card][data-fare-option-key="' +
            jpEscapeSelector(triggerBtn.getAttribute('data-fare-option-key') || '') +
            '"]'
        );
        if (priceBtn) {
          var label = (priceBtn.textContent || '').trim();
          if (label) {
            ctaLabel = label;
          }
        }
      }
    }

    jpFareModalFoot.innerHTML =
      '<div class="jp-fare-modal__total">' +
      '<span class="jp-fare-modal__total-label">Grand total inclusive of all taxes &amp; fees</span>' +
      '<span class="jp-fare-modal__total-amount">' +
      escFallback(grandTotal) +
      '</span>' +
      '</div>' +
      '<div class="jp-fare-modal__foot-actions">' +
      '<button type="button" class="jp-fare-modal__btn jp-fare-modal__btn--ghost" data-jp-fare-modal-close>Close</button>' +
      '<button type="button" class="jp-fare-modal__btn jp-fare-modal__btn--primary" data-jp-fare-modal-select' +
      (triggerBtn
        ? ' data-offer-id="' +
          escFallback(jpReadDataAttr(triggerBtn, ['data-offer-id', 'data-selected-offer-reference'])) +
          '" data-selected-offer-reference="' +
          escFallback(jpReadDataAttr(triggerBtn, ['data-offer-id', 'data-selected-offer-reference'])) +
          '" data-fare-option-key="' +
          escFallback(jpReadDataAttr(triggerBtn, ['data-fare-option-key', 'data-option-key'])) +
          '"' +
          (jpReadDataAttr(triggerBtn, ['data-search-id'])
            ? ' data-search-id="' + escFallback(jpReadDataAttr(triggerBtn, ['data-search-id'])) + '"'
            : '') +
          (jpReadDataAttr(triggerBtn, ['data-select-url'])
            ? ' data-select-url="' + escFallback(jpReadDataAttr(triggerBtn, ['data-select-url'])) + '"'
            : '')
        : '') +
      '>' +
      escFallback(ctaLabel) +
      '</button>' +
      '</div>';

    jpActivateFareTab(0);
    jpFareModal.hidden = false;
    jpFareModal.removeAttribute('aria-hidden');
    document.body.classList.add('jp-fare-modal-open');
    requestAnimationFrame(function () {
      jpFareModal.classList.add('is-open');
      var focusable = jpFareModal.querySelector('[data-jp-fare-modal-close]');
      if (focusable && focusable.focus) {
        focusable.focus();
      }
    });
  }

  function jpBindFareModalEvents() {
    if (jpFareModalBound) {
      return;
    }
    jpFareModalBound = true;

    document.addEventListener(
      'click',
      function (e) {
        var btn = e.target.closest('.jp-flights-results .jp-fare-card [data-fare-summary-open]');
        if (!btn) {
          return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        var raw = btn.getAttribute('data-fare-summary-payload');
        if (!raw) {
          return;
        }
        try {
          jpOpenFareModal(JSON.parse(raw), btn);
        } catch (err) {
          /* ignore malformed payload */
        }
      },
      true
    );

    document.addEventListener('click', function (e) {
      if (!jpFareModal || jpFareModal.hidden) {
        return;
      }
      if (e.target.closest('[data-jp-fare-modal-close]')) {
        e.preventDefault();
        jpCloseFareModal();
        return;
      }
      if (e.target.closest('[data-jp-fare-modal-select]')) {
        e.preventDefault();
        jpHandleModalSelect();
        return;
      }
      var tab = e.target.closest('[data-jp-fare-tab]');
      if (tab && jpFareModal.contains(tab)) {
        e.preventDefault();
        jpActivateFareTab(parseInt(tab.getAttribute('data-jp-fare-tab') || '0', 10) || 0);
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && jpFareModal && !jpFareModal.hidden) {
        jpCloseFareModal();
      }
    });
  }

  jpBindFareModalEvents();

  window.JetPkResultCards = window.JetPkResultCards || {};
  window.JetPkResultCards.buildBrandedFaresPanelHtml = buildBrandedFaresPanelHtml;
})();
