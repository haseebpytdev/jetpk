/**
 * JetPakistan flight results — card renderer + presentation helpers (no booking contract changes).
 * Runtime URLs are client-prefixed via data-results-url (/jetpk/flights/results).
 */
(function () {
  'use strict';

  function clientResultsPrefixFromDom() {
    var root = document.querySelector('[data-results-url]');
    if (!root) {
      return '';
    }
    var url = String(root.getAttribute('data-results-url') || '');
    var match = url.match(/^(\/[^/]+)\//);
    return match ? match[1] : '';
  }

  var JP_CLIENT_PREFIX = clientResultsPrefixFromDom();

  function normalizeDisplayText(value) {
    return String(value || '')
      .replace(/\u00c2\u00b7/g, '\u00b7')
      .replace(/\u00c2\u2192/g, '\u2192')
      .replace(/·/g, '\u00b7')
      .replace(/→/g, '\u2192');
  }

  function esc(s) {
    if (s === null || s === undefined) {
      return '';
    }
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/"/g, '&quot;');
  }

  function tripPersonLabel(tripType) {
    var t = tripType || 'one_way';
    if (t === 'round_trip') {
      return 'Round trip / person';
    }
    if (t === 'multi_city') {
      return 'Multi-city / person';
    }
    return 'One way / person';
  }

  function stopsBadgeText(stopCount) {
    var n = Number(stopCount) || 0;
    if (n <= 0) {
      return 'DIRECT';
    }
    if (n === 1) {
      return '1 STOP';
    }
    return n + ' STOPS';
  }

  function stopsBadgeClass(stopCount) {
    var n = Number(stopCount) || 0;
    return n <= 0 ? 'jp-stop-badge--direct' : 'jp-stop-badge--stops';
  }

  function extractViaCodes(offer) {
    var segs = Array.isArray(offer.segments) ? offer.segments : [];
    if (segs.length < 2) {
      return [];
    }
    var codes = [];
    var seen = {};
    for (var i = 0; i < segs.length - 1; i++) {
      var c = String(segs[i].destination || segs[i].arrival_airport_code || segs[i].arrival || '').trim().toUpperCase();
      if (c && !seen[c]) {
        seen[c] = true;
        codes.push(c);
      }
    }
    return codes;
  }

  function parseLayoverTooltipLines(layoverSummary) {
    var lines = Array.isArray(layoverSummary) ? layoverSummary.filter(Boolean) : [];
    if (!lines.length) {
      return null;
    }
    var text = String(lines[0] || '').trim();
    var match = text.match(/^(.+?)\s+layover\s*·\s*(.+)$/i);
    if (match) {
      return { duration: match[1].trim(), airport: match[2].trim() };
    }
    var fallback = text.match(/^layover\s*·\s*(.+)$/i);
    if (fallback) {
      return { duration: '', airport: fallback[1].trim() };
    }
    return { duration: text, airport: '' };
  }

  function buildLayoverPopoverHtml(stopsLabel, layoverSummary) {
    var parsed = parseLayoverTooltipLines(layoverSummary);
    var label = String(stopsLabel || 'Direct').trim();
    var aria = parsed
      ? [parsed.duration, 'layover', parsed.airport].filter(Boolean).join('; ')
      : label;
    var popoverBody = '';
    if (parsed) {
      popoverBody =
        (parsed.duration
          ? '<span class="jp-layover-popover__line jp-layover-popover__line--dur">' + esc(parsed.duration) + '</span>'
          : '') +
        '<span class="jp-layover-popover__line jp-layover-popover__line--kind">layover</span>' +
        (parsed.airport
          ? '<span class="jp-layover-popover__line jp-layover-popover__line--airport">' + esc(parsed.airport) + '</span>'
          : '');
    } else {
      popoverBody = '<span class="jp-layover-popover__line">' + esc(label) + '</span>';
    }
    return (
      '<button type="button" class="jp-stop-tag jp-layover-trigger" data-jp-layover-trigger aria-expanded="false" aria-label="' +
      esc(aria) +
      '">' +
      esc(label) +
      '<span class="jp-layover-popover" role="tooltip" hidden>' +
      popoverBody +
      '</span></button>'
    );
  }

  function buildRouteArcHtml(durationLabel, stopsLabel, layoverSummary, viaCodes, isDirect) {
    var viaHtml = '';
    if (!isDirect && viaCodes.length) {
      viaHtml =
        '<span class="jp-route-line__via">via ' +
        viaCodes
          .map(function (code) {
            return esc(code);
          })
          .join(' · ') +
        '</span>';
    }
    var stopPip = !isDirect ? '<span class="jp-route-line__pip" aria-hidden="true"></span>' : '';
    var planePos = isDirect ? '50%' : '72%';
    var stopHtml = isDirect
      ? '<span class="jp-route-line__label jp-route-line__label--direct">Direct</span>'
      : buildLayoverPopoverHtml(stopsLabel, layoverSummary).replace(
          'jp-stop-tag jp-layover-trigger',
          'jp-route-line__label jp-route-line__label--stops jp-stop-tag jp-layover-trigger',
        );

    return (
      '<div class="jp-route-line" aria-hidden="false">' +
      '<span class="jp-route-line__dur">' +
      durationLabel +
      '</span>' +
      '<span class="jp-route-line__track" aria-hidden="true">' +
      '<span class="jp-route-line__dot jp-route-line__dot--start"></span>' +
      '<span class="jp-route-line__dash">' +
      stopPip +
      '<span class="jp-route-line__plane" style="left:' +
      planePos +
      '"></span></span>' +
      '<span class="jp-route-line__dot jp-route-line__dot--end"></span>' +
      '</span>' +
      '<span class="jp-route-line__meta">' +
      stopHtml +
      viaHtml +
      '</span>' +
      '</div>'
    );
  }

  function buildDayOffsetBadge(offset, escFn) {
    var text = String(offset || '').trim();
    if (!text) {
      return '';
    }
    return (
      '<span class="jp-next-day-badge ota-arr-offset" aria-label="Arrives ' +
      escFn(text) +
      '">' +
      escFn(text) +
      '</span>'
    );
  }

  function buildLegHtml(time, date, code, city, align, dayOffsetHtml) {
    var cityLine = city
      ? '<span class="jp-flight-card__place">' + esc(city) + '</span>'
      : '';
    return (
      '<div class="jp-flight-card__leg jp-flight-card__leg--' +
      align +
      '">' +
      '<div class="jp-flight-card__time-row">' +
      '<span class="jp-flight-card__time">' +
      esc(time) +
      '</span>' +
      (dayOffsetHtml || '') +
      '</div>' +
      '<span class="jp-flight-card__code">' +
      esc(code) +
      '</span>' +
      cityLine +
      '</div>'
    );
  }

  function buildAirlineSubline(offer, escFn) {
    var cabinRaw = String(offer.cabin || 'Economy').replace(/_/g, ' ').trim();
    var cabin = cabinRaw ? cabinRaw.charAt(0).toUpperCase() + cabinRaw.slice(1) : 'Economy';
    var flightNo = '';
    var segs = Array.isArray(offer.segments) ? offer.segments : [];
    if (segs.length && segs[0]) {
      flightNo = String(
        segs[0].flight_number || segs[0].flight_no || segs[0].marketing_flight_number || '',
      ).trim();
    }
    if (!flightNo && offer.flight_numbers_display) {
      flightNo = String(offer.flight_numbers_display).trim();
    }
    if (!flightNo && offer.primary_flight_number) {
      flightNo = String(offer.primary_flight_number).trim();
    }
    var via = '';
    if ((offer.stops || 0) > 0) {
      var viaCodes = extractViaCodes(offer);
      if (viaCodes.length) {
        via = 'via ' + viaCodes.join(' ');
      }
    }
    var parts = [cabin];
    if (flightNo) {
      parts.push(flightNo);
    } else if (via) {
      parts.push(via);
    }
    return '<span class="jp-airline__sub">' + escFn(parts.join(' · ')) + '</span>';
  }

  function buildAirlineBlock(offer, helpers) {
    var h = helpers || {};
    var escFn = h.esc || esc;
    var logoHtml = h.buildAirlineLogoHtml ? h.buildAirlineLogoHtml(offer) : '';
    var nameHtml = h.buildStandardCardFaceCarrierHtml ? h.buildStandardCardFaceCarrierHtml(offer) : '';
    var logoInner = logoHtml
      .replace('ota-result-brand-logo', 'jp-airline__logo ota-result-brand-logo')
      .replace('ota-airline-logo', 'jp-airline__mark ota-airline-logo');
    return (
      '<div class="jp-airline">' +
      logoInner +
      '<div class="jp-airline__meta">' +
      '<div class="jp-airline__name">' +
      nameHtml +
      '</div>' +
      buildAirlineSubline(offer, escFn) +
      '</div></div>'
    );
  }

  function buildFareActionButtonHtml(ctx) {
    var offer = ctx.offer;
    var escFn = ctx.esc || esc;
    var cardPrice = ctx.cardPrice;
    var priceBtnAria = ctx.priceBtnAria;
    var providerCode = ctx.providerCode;
    var directFareOption = ctx.directFareOption;
    var hasBrandedFares = !!ctx.hasBrandedFares;
    var isMulticityInquiry = ctx.isMulticityInquiry;
    var inquiryUrl = ctx.inquiryUrl;
    var inquiryNotice = ctx.inquiryNotice;
    var csrfToken = ctx.csrfToken;
    var searchId = ctx.searchId;

    var bookInner = '<span class="jp-fare-action__price" data-card-price>' + cardPrice + '</span>';

    var bookNowHtml;
    if (hasBrandedFares) {
      bookNowHtml =
        '<button type="button" class="jp-fare-action__btn btn btn-primary ota-select-primary ota-btn-book ota-flight-book-button" data-branded-fares-toggle data-offer-id="' +
        escFn(offer.offer_id || '') +
        '" aria-label="' +
        priceBtnAria +
        '">' +
        bookInner +
        '</button>';
    } else if (offer.can_book) {
      if (directFareOption && directFareOption.option_key) {
        bookNowHtml =
          '<button type="button" class="jp-fare-action__btn btn btn-primary ota-select-primary ota-btn-book ota-flight-book-button" data-direct-fare-continue data-fare-option-key="' +
          escFn(directFareOption.option_key) +
          '" aria-label="' +
          priceBtnAria +
          '">' +
          bookInner +
          '</button>';
      } else {
        bookNowHtml =
          '<a class="jp-fare-action__btn btn btn-primary ota-select-primary ota-btn-book ota-flight-book-button" data-book-now data-provider="' +
          escFn(providerCode) +
          '" href="' +
          String(offer.select_url || '').replace(/"/g, '&quot;') +
          '" aria-label="' +
          priceBtnAria +
          '">' +
          bookInner +
          '</a>';
      }
    } else {
      bookNowHtml =
        '<button type="button" class="jp-fare-action__btn btn btn-default ota-btn-book ota-flight-book-button" disabled aria-label="Fare unavailable">' +
        '<span class="jp-fare-action__label">Unavailable</span></button>';
    }

    if (isMulticityInquiry && inquiryUrl) {
      bookNowHtml =
        '<form method="post" action="' +
        escFn(inquiryUrl) +
        '" class="ota-multicity-inquiry-form jp-fare-action__form">' +
        '<input type="hidden" name="_token" value="' +
        escFn(csrfToken ? csrfToken.getAttribute('content') : '') +
        '">' +
        '<input type="hidden" name="search_id" value="' +
        escFn(searchId) +
        '">' +
        '<input type="hidden" name="offer_id" value="' +
        escFn(offer.offer_id || '') +
        '">' +
        '<button type="submit" class="jp-fare-action__btn btn btn-primary ota-select-primary ota-btn-book ota-flight-book-button ota-multicity-inquiry-btn" aria-label="Request booking for multi-city fare">' +
        '<span class="jp-fare-action__label">Request Booking</span></button>' +
        '<p class="ota-multicity-inquiry-note small text-muted">' +
        escFn(inquiryNotice) +
        '</p></form>';
    }

    return bookNowHtml;
  }

  function buildPriceColumnHtml(ctx) {
    return (
      '<div class="jp-flight-card__price-col">' +
      '<div class="jp-fare-action">' +
      buildFareActionButtonHtml(ctx) +
      '</div></div>'
    );
  }

  function buildMiniRouteBlock(journey, offer, legLabel) {
    var depTime = journey.departure_time_display || '';
    var depDate = journey.departure_date_display || '';
    var depCode = journey.origin || '';
    var depCity = journey.origin_city || '';
    var arrTime = journey.arrival_time_display || '';
    var arrDate = journey.arrival_date_display || '';
    var arrCode = journey.destination || '';
    var arrCity = journey.destination_city || '';
    var arrOff = journey.arrival_day_offset
      ? buildDayOffsetBadge(journey.arrival_day_offset, esc)
      : '';
    var stopCount = Number(journey.stops || journey.stops_count || 0);
    if (!stopCount && journey.stops_display) {
      var lower = String(journey.stops_display).toLowerCase();
      if (lower.indexOf('direct') === -1) {
        var m = lower.match(/(\d+)/);
        stopCount = m ? Number(m[1]) : 1;
      }
    }
    var stopsLabel = journey.stops_display || (stopCount === 0 ? 'Direct' : stopCount + ' stop' + (stopCount === 1 ? '' : 's'));
    var dur = esc(journey.duration_display || '');
    var viaCodes = [];
    var segs = journey.segments_display || [];
    if (segs.length > 1) {
      for (var i = 0; i < segs.length - 1; i++) {
        var c = String(segs[i].destination || '').trim().toUpperCase();
        if (c) {
          viaCodes.push(c);
        }
      }
    }

    return (
      '<div class="jp-flight-card__mini" data-journey-type="' +
      esc(journey.type || '') +
      '">' +
      (legLabel ? '<p class="jp-flight-card__mini-label">' + esc(legLabel) + '</p>' : '') +
      '<div class="jp-flight-card__route jp-flight-card__route--mini">' +
      buildLegHtml(depTime, depDate, depCode, depCity, 'dep', '') +
      buildRouteArcHtml(dur, stopsLabel, journey.layover_summary, viaCodes, stopCount === 0) +
      buildLegHtml(arrTime, arrDate, arrCode, arrCity, 'arr', arrOff) +
      '</div></div>'
    );
  }

  function buildCard(ctx) {
    var offer = ctx.offer;
    var escFn = ctx.esc || esc;
    var currentCriteria = ctx.currentCriteria || {};
    var tripType = currentCriteria.trip_type || 'one_way';
    var isMultiCityTrip = tripType === 'multi_city';
    var journeysForDisplay = offer.journeys_display || [];
    var hasJourneyGrouping = journeysForDisplay.length >= 2 && !offer.journey_grouping_unavailable;
    var useRoundTripCompact = tripType === 'round_trip' && hasJourneyGrouping && !isMultiCityTrip;

    var stopCount = offer.stops || 0;
    var stopsLabel = stopCount === 0 ? 'Direct' : stopCount + ' stop' + (stopCount === 1 ? '' : 's');
    var depTime = offer.departure_time_display || offer.departure_time || '';
    var depDate = offer.departure_date_display || '';
    var depCode = offer.departure_airport_code || ctx.originFallback || '';
    var depCity = offer.departure_city || '';
    var arrTime = offer.arrival_time_display || offer.arrival_time || '';
    var arrDate = offer.arrival_date_display || '';
    var arrCode = offer.arrival_airport_code || ctx.destinationFallback || '';
    var arrCity = offer.arrival_city || '';
    var arrOff = offer.arrival_day_offset
      ? buildDayOffsetBadge(offer.arrival_day_offset, escFn)
      : '';
    var cardDurLabel = escFn(offer.itinerary_duration_display || offer.duration || '');
    var viaCodes = extractViaCodes(offer);
    var isDirect = stopCount === 0;

    var brandedFaresRowHtml = ctx.brandedFaresRowHtml || '';
    var hasBrandedFares = brandedFaresRowHtml !== '';
    var brandedFaresExpanded = !!ctx.brandedFaresExpanded;
    var brandedFaresAttrs = hasBrandedFares ? ' data-has-fare-choice="1" data-has-branded-fares="1"' : '';
    var brandedFaresOpenClass = brandedFaresExpanded ? ' is-fare-options-open' : '';
    var summaryA11yAttrs = hasBrandedFares
      ? ' data-flight-card-summary role="button" tabindex="0" aria-expanded="' +
        (brandedFaresExpanded ? 'true' : 'false') +
        '" aria-label="Toggle fare options"'
      : ' data-flight-card-summary';
    var providerCode = String(offer.provider || '').toLowerCase();

    var flightDetailsBtn = ctx.flightDetailsBtn || '';
    var sourceBadgeHtml = ctx.sourceBadgeHtml || '';
    var fareDebugLine = ctx.fareDebugLine || '';
    var multicityMetaHtml = ctx.multicityMetaHtml || '';
    var baggageTag = (offer.baggage_summary_display || offer.baggage || '').trim();
    var tripLabel = tripPersonLabel(tripType);

    var priceColumnHtml = ctx.priceColumnHtml || buildPriceColumnHtml(
      Object.assign({}, ctx, { hasBrandedFares: hasBrandedFares })
    );

    var airlineHtml = buildAirlineBlock(offer, ctx);
    var routeHtml = '';

    if (useRoundTripCompact) {
      var outboundJ = journeysForDisplay[0];
      var returnJ = journeysForDisplay[1];
      routeHtml =
        '<div class="jp-flight-card__routes jp-flight-card__routes--return">' +
        buildMiniRouteBlock(outboundJ, offer, 'Outbound') +
        buildMiniRouteBlock(returnJ, offer, 'Return') +
        '</div>';
    } else if (hasJourneyGrouping) {
      var cardJourneys = journeysForDisplay;
      var moreLegsNote = '';
      if (isMultiCityTrip && cardJourneys.length > 3) {
        moreLegsNote =
          '<div class="jp-flight-card__more-legs">+ ' + (cardJourneys.length - 3) + ' more legs — View details</div>';
        cardJourneys = cardJourneys.slice(0, 3);
      }
      routeHtml =
        '<div class="jp-flight-card__routes jp-flight-card__routes--stacked">' +
        cardJourneys
          .map(function (j, idx) {
            return buildMiniRouteBlock(j, offer, j.label || 'Leg ' + (idx + 1));
          })
          .join('') +
        moreLegsNote +
        '</div>';
    } else {
      routeHtml =
        '<div class="jp-flight-card__route jp-flight-card__route--single">' +
        buildLegHtml(depTime, depDate, depCode, depCity, 'dep', '') +
        buildRouteArcHtml(cardDurLabel, stopsLabel, offer.layover_summary, viaCodes, isDirect) +
        buildLegHtml(arrTime, arrDate, arrCode, arrCity, 'arr', arrOff) +
        '</div>';
    }

    var baggageHtml = baggageTag
      ? '<span class="jp-chip">' + escFn(baggageTag) + '</span>'
      : '';
    var refundableHint = String(offer.refundable_display || offer.refund_summary || '').trim();
    var refundableHtml =
      refundableHint && !/non[- ]?refund/i.test(refundableHint)
        ? '<span class="jp-chip jp-chip--ok">' + escFn(refundableHint) + '</span>'
        : '';
    var detailsLink = flightDetailsBtn
      ? flightDetailsBtn
          .replace('View details', 'Flight details')
          .replace('jp-flight-card__details-btn', 'jp-flight-card__details-btn jp-link')
      : '';
    var mainModifier =
      useRoundTripCompact || hasJourneyGrouping ? ' jp-flight-card__main--stacked' : '';

    return (
      '<article class="jp-flight-card jp-result-card ota-result-pro-card ota-result-card-v3' +
      brandedFaresOpenClass +
      (isDirect ? ' jp-flight-card--direct' : ' jp-flight-card--stops') +
      '"' +
      brandedFaresAttrs +
      (ctx.extraArticleAttrs || '') +
      ' data-flight-card data-offer-id="' +
      escFn(offer.offer_id) +
      '" data-provider="' +
      escFn(providerCode) +
      '">' +
      '<div class="jp-flight-card__shell"' +
      summaryA11yAttrs +
      '>' +
      '<div class="jp-flight-card__main' +
      mainModifier +
      '">' +
      airlineHtml +
      '<div class="jp-flight-card__route-wrap">' +
      routeHtml +
      multicityMetaHtml +
      '</div>' +
      priceColumnHtml +
      '</div>' +
      '<div class="jp-flight-card__foot">' +
      '<div class="jp-flight-card__tags">' +
      baggageHtml +
      refundableHtml +
      '<span class="jp-flight-card__trip-label">' +
      escFn(tripLabel) +
      '</span>' +
      sourceBadgeHtml +
      '</div>' +
      '<div class="jp-flight-card__links">' +
      detailsLink +
      '</div></div>' +
      fareDebugLine +
      '</div>' +
      brandedFaresRowHtml +
      '</article>'
    );
  }

  function buildSkeletonCard() {
    return (
      '<article class="jp-flight-card jp-result-card ota-result-pro-card ota-result-card-v3 ota-result-skeleton-card" aria-hidden="true">' +
      '<div class="jp-flight-card__shell">' +
      '<div class="jp-flight-card__main">' +
      '<div class="jp-airline"><div class="ota-skeleton ota-skeleton--logo"></div><div class="ota-skeleton ota-skeleton--line ota-skeleton--line-sm"></div></div>' +
      '<div class="jp-flight-card__route jp-flight-card__route--single">' +
      '<div class="ota-skeleton-block"><div class="ota-skeleton ota-skeleton--line ota-skeleton--line-lg"></div></div>' +
      '<div class="ota-skeleton-block ota-skeleton-block--mid"><div class="ota-skeleton ota-skeleton--line ota-skeleton--line-sm"></div></div>' +
      '<div class="ota-skeleton-block ota-skeleton-block--end"><div class="ota-skeleton ota-skeleton--line ota-skeleton--line-lg"></div></div>' +
      '</div>' +
      '<div class="jp-flight-card__price-col"><div class="ota-skeleton ota-skeleton--btn"></div></div>' +
      '</div>' +
      '<div class="jp-flight-card__foot"><div class="ota-skeleton ota-skeleton--line ota-skeleton--line-sm"></div></div>' +
      '</div></article>'
    );
  }

  function buildReturnSplitCard(option, formConfig, labels, brandedFaresHtml, selectedFareKey, brandedState, helpers) {
    if (!option || !option.journey_display || !window.OtaReturnSplitCards) {
      return '';
    }
    helpers = helpers || {};
    var escFn = helpers.esc || esc;
    var journey = option.journey_display;
    var offer = OtaReturnSplitCards.normalizeOptionForBrandedFares(option, 'return');
    var offerId = String(option.combo_id || offer.offer_id || '');
    var displayAmount = null;
    if (window.OtaBrandedFares && brandedState) {
      var priced = OtaBrandedFares.cardDisplayPrice(offer, brandedState);
      if (priced != null && isFinite(Number(priced))) {
        displayAmount = Number(priced);
      }
    }
    if (displayAmount == null && option.total_amount != null) {
      displayAmount = Number(option.total_amount);
    }
    var cardPricePlain =
      displayAmount != null && isFinite(displayAmount) && helpers.formatCardButtonRs
        ? helpers.formatCardButtonRs(displayAmount)
        : 'Fare unavailable';
    var cardPrice = escFn(cardPricePlain);
    var returnFormHtml =
      option.can_book === false
        ? '<button type="button" class="jp-fare-action__btn btn btn-default ota-btn-book ota-flight-book-button" disabled><span class="jp-fare-action__label">Unavailable</span></button>'
        : '<form method="post" action="' +
          escFn(formConfig.selectUrl || '') +
          '" class="ota-return-split-card__form jp-fare-action__form">' +
          '<input type="hidden" name="_token" value="' +
          escFn(formConfig.csrf || '') +
          '">' +
          '<input type="hidden" name="search_id" value="' +
          escFn(formConfig.searchId || '') +
          '">' +
          '<input type="hidden" name="combo_id" value="' +
          escFn(option.combo_id || '') +
          '">' +
          '<input type="hidden" name="outbound_key" value="' +
          escFn(formConfig.outboundKey || '') +
          '">' +
          '<input type="hidden" name="outbound_fare_option_key" value="" data-split-outbound-fare-option-key>' +
          '<input type="hidden" name="fare_option_key" value="" data-split-fare-option-key>' +
          '<button type="submit" class="jp-fare-action__btn btn btn-primary ota-select-primary ota-btn-book ota-flight-book-button ota-return-split-card__cta" aria-label="' +
          escFn('Select return fare ' + cardPricePlain) +
          '"><span class="jp-fare-action__price" data-card-price>' +
          cardPrice +
          '</span></button></form>';
    var stopCount = Number(journey.stops || journey.stops_count || 0);
    if (!stopCount && journey.stops_display) {
      var lower = String(journey.stops_display).toLowerCase();
      if (lower.indexOf('direct') === -1) {
        var m = lower.match(/(\d+)/);
        stopCount = m ? Number(m[1]) : 1;
      }
    }
    var mappedOffer = {
      offer_id: offerId,
      provider: option.provider || '',
      can_book: option.can_book !== false,
      stops: stopCount,
      departure_time_display: journey.departure_time_display || '',
      departure_date_display: journey.departure_date_display || '',
      departure_airport_code: journey.origin || '',
      departure_city: journey.origin_city || '',
      arrival_time_display: journey.arrival_time_display || '',
      arrival_date_display: journey.arrival_date_display || '',
      arrival_airport_code: journey.destination || '',
      arrival_city: journey.destination_city || '',
      arrival_day_offset: journey.arrival_day_offset || null,
      itinerary_duration_display: journey.duration_display || '',
      layover_summary: journey.layover_summary || option.layover_summary || null,
      segments: Array.isArray(journey.segments_display) ? journey.segments_display : [],
      journeys_display: [Object.assign({}, journey, { type: 'return', label: labels.legLabel || 'Return' })],
      airline_name: option.airline_name || '',
      airline_code: option.airline_code || '',
      airline_logo_url: option.airline_logo_url || '',
      cabin: option.cabin || option.fare_family || 'economy',
      baggage_summary_display: option.baggage_summary_display || option.baggage || '',
      refundable_display: option.refundable ? 'Refundable' : '',
    };
    var brandedExpanded =
      brandedFaresHtml !== '' &&
      window.OtaBrandedFares &&
      brandedState &&
      OtaBrandedFares.isExpanded(offerId, brandedState);
    return buildCard({
      offer: mappedOffer,
      esc: escFn,
      currentCriteria: helpers.currentCriteria || { trip_type: 'round_trip' },
      originFallback: journey.origin || '',
      destinationFallback: journey.destination || '',
      cardPrice: cardPrice,
      priceBtnAria: escFn('Select return fare ' + cardPricePlain),
      providerCode: String(option.provider || '').toLowerCase(),
      brandedFaresRowHtml: brandedFaresHtml || '',
      brandedFaresExpanded: !!brandedExpanded,
      buildAirlineLogoHtml: helpers.buildAirlineLogoHtml,
      buildStandardCardFaceCarrierHtml: helpers.buildStandardCardFaceCarrierHtml,
      priceColumnHtml:
        '<div class="jp-flight-card__price-col"><div class="jp-fare-action">' + returnFormHtml + '</div></div>',
      extraArticleAttrs:
        ' data-split-flow-card="return" data-split-leg="return" data-combo-id="' +
        escFn(option.combo_id || '') +
        '"',
    });
  }

  function buildOutboundSplitCard(option, labels, brandedFaresHtml, selectedFareKey, brandedState, helpers) {
    if (!option || !option.journey_display || !window.OtaReturnSplitCards) {
      return '';
    }
    helpers = helpers || {};
    var escFn = helpers.esc || esc;
    var journey = option.journey_display;
    var offer = OtaReturnSplitCards.normalizeOptionForBrandedFares(option, 'outbound');
    var offerId = String(option.outbound_key || offer.offer_id || '');
    var displayAmount = option.from_total_amount != null ? Number(option.from_total_amount) : null;
    if (window.OtaBrandedFares && brandedState) {
      var priced = OtaBrandedFares.cardDisplayPrice(offer, brandedState, selectedFareKey);
      if (priced != null && isFinite(Number(priced))) {
        displayAmount = Number(priced);
      }
    }
    var cardPricePlain =
      displayAmount != null && isFinite(displayAmount) && helpers.formatCardButtonRs
        ? helpers.formatCardButtonRs(displayAmount)
        : 'Fare unavailable';
    var cardPrice = escFn(cardPricePlain);
    var returnOptionsUrl = escFn(option.return_options_url || '');
    var outboundKey = escFn(option.outbound_key || '');
    var outboundCtaHtml = returnOptionsUrl
      ? '<a class="jp-fare-action__btn btn btn-primary ota-select-primary ota-btn-book ota-flight-book-button ota-return-split-card__cta" data-split-select-outbound data-return-options-url="' +
        returnOptionsUrl +
        '" data-outbound-key="' +
        outboundKey +
        '" href="' +
        returnOptionsUrl +
        '" aria-label="' +
        escFn('Select outbound fare ' + cardPricePlain) +
        '"><span class="jp-fare-action__price" data-card-price>' +
        cardPrice +
        '</span></a>'
      : '<button type="button" class="jp-fare-action__btn btn btn-default ota-btn-book ota-flight-book-button" disabled><span class="jp-fare-action__label">Unavailable</span></button>';
    var stopCount = Number(journey.stops || journey.stops_count || 0);
    if (!stopCount && journey.stops_display) {
      var lower = String(journey.stops_display).toLowerCase();
      if (lower.indexOf('direct') === -1) {
        var m = lower.match(/(\d+)/);
        stopCount = m ? Number(m[1]) : 1;
      }
    }
    var mappedOffer = {
      offer_id: offerId,
      provider: option.provider || '',
      can_book: option.can_book !== false,
      stops: stopCount,
      departure_time_display: journey.departure_time_display || '',
      departure_date_display: journey.departure_date_display || '',
      departure_airport_code: journey.origin || '',
      departure_city: journey.origin_city || '',
      arrival_time_display: journey.arrival_time_display || '',
      arrival_date_display: journey.arrival_date_display || '',
      arrival_airport_code: journey.destination || '',
      arrival_city: journey.destination_city || '',
      arrival_day_offset: journey.arrival_day_offset || null,
      itinerary_duration_display: journey.duration_display || '',
      layover_summary: journey.layover_summary || option.layover_summary || null,
      segments: Array.isArray(journey.segments_display) ? journey.segments_display : [],
      journeys_display: [Object.assign({}, journey, { type: 'outbound', label: labels.legLabel || 'Outbound' })],
      airline_name: option.airline_name || '',
      airline_code: option.airline_code || '',
      airline_logo_url: option.airline_logo_url || '',
      cabin: option.cabin || option.fare_family || 'economy',
      baggage_summary_display: option.baggage_summary_display || option.baggage || '',
      refundable_display: option.refundable ? 'Refundable' : '',
      outbound_key: option.outbound_key || '',
    };
    var brandedExpanded =
      brandedFaresHtml !== '' &&
      window.OtaBrandedFares &&
      brandedState &&
      OtaBrandedFares.isExpanded(offerId, brandedState);
    return buildCard({
      offer: mappedOffer,
      esc: escFn,
      currentCriteria: helpers.currentCriteria || { trip_type: 'round_trip' },
      originFallback: journey.origin || '',
      destinationFallback: journey.destination || '',
      cardPrice: cardPrice,
      priceBtnAria: escFn('Select outbound fare ' + cardPricePlain),
      providerCode: String(option.provider || '').toLowerCase(),
      brandedFaresRowHtml: brandedFaresHtml || '',
      brandedFaresExpanded: !!brandedExpanded,
      buildAirlineLogoHtml: helpers.buildAirlineLogoHtml,
      buildStandardCardFaceCarrierHtml: helpers.buildStandardCardFaceCarrierHtml,
      priceColumnHtml:
        '<div class="jp-flight-card__price-col"><div class="jp-fare-action">' + outboundCtaHtml + '</div></div>',
      extraArticleAttrs:
        ' data-split-flow-card="outbound" data-split-leg="outbound" data-outbound-key="' + outboundKey + '"',
    });
  }

  window.JetPkResultCards = {
    buildCard: buildCard,
    buildSkeletonCard: buildSkeletonCard,
    buildReturnSplitCard: buildReturnSplitCard,
    buildOutboundSplitCard: buildOutboundSplitCard,
    normalizeDisplayText: normalizeDisplayText,
  };

  if (!document.body.classList.contains('jp-flights-results')) {
    return;
  }

  function scrubMojibake(root) {
    var scope = root || document;
    scope
      .querySelectorAll(
        '.jp-flight-card__place, .jp-flight-card__date, .ota-result-leg__city, .ota-return-split-steps__sep, [data-results-root]'
      )
      .forEach(function (node) {
        if (node.childNodes.length === 1 && node.childNodes[0].nodeType === 3) {
          var fixed = normalizeDisplayText(node.textContent);
          if (fixed !== node.textContent) {
            node.textContent = fixed;
          }
        }
      });
  }

  function polishModalScope(root) {
    var scope = root || document;
    scope
      .querySelectorAll(
        '.ota-flight-detail-segment-card__time, .ota-flight-detail-segment-card__code, .ota-flight-detail-segment-card__city, .ota-flight-detail-segment-card__date, .ota-fare-summary-modal__route, .ota-fare-breakdown-modal__rows dd'
      )
      .forEach(function (node) {
        if (node.childNodes.length === 1 && node.childNodes[0].nodeType === 3) {
          var fixed = normalizeDisplayText(node.textContent);
          if (fixed !== node.textContent) {
            node.textContent = fixed;
          }
        }
      });
  }

  function watchModals() {
    ['ota-flight-details-modal', 'ota-fare-breakdown-modal', 'ota-fare-summary-modal'].forEach(function (id) {
      var modal = document.getElementById(id);
      if (!modal || typeof MutationObserver === 'undefined') {
        return;
      }
      var observer = new MutationObserver(function () {
        polishModalScope(modal);
      });
      observer.observe(modal, { childList: true, subtree: true, characterData: true });
    });
  }

  function closeLayoverPopover(trigger) {
    if (!trigger) {
      return;
    }
    var pop = trigger.querySelector('.jp-layover-popover');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.classList.remove('is-open');
    if (pop) {
      pop.hidden = true;
    }
  }

  function openLayoverPopover(trigger) {
    if (!trigger) {
      return;
    }
    document.querySelectorAll('[data-jp-layover-trigger].is-open').forEach(function (node) {
      if (node !== trigger) {
        closeLayoverPopover(node);
      }
    });
    var pop = trigger.querySelector('.jp-layover-popover');
    trigger.setAttribute('aria-expanded', 'true');
    trigger.classList.add('is-open');
    if (pop) {
      pop.hidden = false;
    }
  }

  function toggleLayoverPopover(trigger) {
    if (!trigger) {
      return;
    }
    if (trigger.classList.contains('is-open')) {
      closeLayoverPopover(trigger);
    } else {
      openLayoverPopover(trigger);
    }
  }

  function bindLayoverInteractions(root) {
    var scope = root || document;
    scope.querySelectorAll('[data-jp-layover-trigger]').forEach(function (trigger) {
      if (trigger.getAttribute('data-jp-layover-bound') === '1') {
        return;
      }
      trigger.setAttribute('data-jp-layover-bound', '1');
      trigger.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        toggleLayoverPopover(trigger);
      });
      trigger.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          closeLayoverPopover(trigger);
          return;
        }
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          toggleLayoverPopover(trigger);
        }
      });
      trigger.addEventListener('blur', function () {
        window.setTimeout(function () {
          if (!trigger.classList.contains('is-open')) {
            return;
          }
          var active = document.activeElement;
          if (active && trigger.contains(active)) {
            return;
          }
          closeLayoverPopover(trigger);
        }, 120);
      });
      trigger.addEventListener('mouseenter', function () {
        if (window.matchMedia('(hover: hover)').matches) {
          openLayoverPopover(trigger);
        }
      });
      trigger.addEventListener('mouseleave', function () {
        if (window.matchMedia('(hover: hover)').matches) {
          closeLayoverPopover(trigger);
        }
      });
    });
  }

  function enhanceCards(root) {
    bindLayoverInteractions(root);
  }

  function splitDatePriceChipLabel(dateEl) {
    if (!dateEl || dateEl.querySelector('.ota-date-price-chip__day')) {
      return;
    }
    var raw = String(dateEl.textContent || '').trim();
    var comma = raw.indexOf(',');
    if (comma < 1) {
      return;
    }
    var day = document.createElement('span');
    day.className = 'ota-date-price-chip__day';
    day.textContent = raw.slice(0, comma).trim();
    var md = document.createElement('span');
    md.className = 'ota-date-price-chip__md';
    md.textContent = raw.slice(comma + 1).trim();
    dateEl.textContent = '';
    dateEl.appendChild(day);
    dateEl.appendChild(md);
  }

  function parseChipPrice(text) {
    var digits = String(text || '').replace(/[^\d]/g, '');
    if (!digits) {
      return null;
    }
    var num = Number(digits);
    return isNaN(num) || num <= 0 ? null : num;
  }

  function enhanceDatePriceChips(root) {
    var scope = root || document;
    var inner = scope.querySelector('[data-date-price-strip-inner]');
    if (!inner) {
      return;
    }
    var chips = inner.querySelectorAll('.ota-date-price-chip');
    var minPrice = null;
    var priced = [];

    chips.forEach(function (chip) {
      splitDatePriceChipLabel(chip.querySelector('.ota-date-price-chip__date'));
      var priceEl = chip.querySelector('.ota-date-price-chip__price');
      if (!priceEl || priceEl.classList.contains('is-loading') || priceEl.classList.contains('is-unavailable')) {
        return;
      }
      var amount = parseChipPrice(priceEl.textContent);
      if (amount == null) {
        return;
      }
      priced.push({ chip: chip, amount: amount });
      if (minPrice == null || amount < minPrice) {
        minPrice = amount;
      }
    });

    chips.forEach(function (chip) {
      chip.classList.remove('is-lowest');
    });
    if (minPrice == null) {
      return;
    }
    priced.forEach(function (row) {
      if (row.amount === minPrice) {
        row.chip.classList.add('is-lowest');
      }
    });
  }

  function watchDatePriceStrip() {
    var inner = document.querySelector('[data-date-price-strip-inner]');
    if (!inner || typeof MutationObserver === 'undefined') {
      return;
    }
    var observer = new MutationObserver(function () {
      enhanceDatePriceChips(document);
    });
    observer.observe(inner, { childList: true, subtree: true, characterData: true });
    enhanceDatePriceChips(document);
  }

  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-jp-layover-trigger]')) {
      document.querySelectorAll('[data-jp-layover-trigger].is-open').forEach(closeLayoverPopover);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('[data-jp-layover-trigger].is-open').forEach(closeLayoverPopover);
    }
  });

  var list = document.querySelector('[data-results-list]');
  if (list && typeof MutationObserver !== 'undefined') {
    var listObserver = new MutationObserver(function () {
      enhanceCards(list);
      scrubMojibake(list);
    });
    listObserver.observe(list, { childList: true, subtree: true });
  }

  function boot() {
    enhanceCards(document);
    scrubMojibake(document);
    watchModals();
    watchDatePriceStrip();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
