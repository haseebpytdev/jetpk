@php
    $d = $draft;
    $o = $offer;
    $cr = $criteria;
    $meta = $booking->meta ?? [];
    $selectedFareFamilyOption = is_array($meta['selected_fare_family_option'] ?? null)
        ? $meta['selected_fare_family_option']
        : null;
    $selectedFareFamilyCheckout = \App\Support\FlightSearch\FlightOfferDisplayPresenter::buildSelectedFareFamilyCheckoutView($selectedFareFamilyOption);
    $selectedFareEstimate = \App\Support\FlightSearch\FlightOfferDisplayPresenter::buildCheckoutSelectedFareEstimatePresentation($selectedFareFamilyOption);
    $checkoutFareRules = \App\Support\FlightSearch\FlightOfferDisplayPresenter::buildCheckoutFareRulesSidebar($o, $selectedFareFamilyOption);
    $useSelectedFareEstimate = is_array($selectedFareEstimate) && ! empty($selectedFareEstimate['has_checkout_estimate']);
    $cabinLabel = ucfirst(str_replace('_', ' ', (string) ($checkoutFareRules['cabin_display'] ?? $o['cabin'] ?? $cr['cabin'] ?? 'economy')));
    $allPassengers = $booking->passengers->sortBy('passenger_index')->values();
    $passengerCounts = [
        'adults' => $allPassengers->where('passenger_type', 'adult')->count(),
        'children' => $allPassengers->where('passenger_type', 'child')->count(),
        'infants' => $allPassengers->where('passenger_type', 'infant')->count(),
        'total' => $allPassengers->count(),
    ];
    $fare = $booking->fareBreakdown;
    $lockExpiresAt = (string) ($meta['checkout_lock_expires_at'] ?? '');
    $totalFromDb = (float) ($fare?->total ?? 0);
    $reviewPresentation = is_array($reviewPresentation ?? null) ? $reviewPresentation : [];
    $checkoutJourneys = is_array($reviewPresentation['journeys_display'] ?? null) ? $reviewPresentation['journeys_display'] : [];
    $tripTypeLabel = \App\Support\FlightSearch\FlightOfferDisplayPresenter::formatCriteriaTripTypeLabel((string) ($cr['trip_type'] ?? 'one_way'));
    $routeLabel = \App\Support\FlightSearch\FlightOfferDisplayPresenter::formatCriteriaRouteLabel($cr);
    if ($routeLabel === '') {
        $routeLabel = ($cr['origin'] ?? '').' → '.($cr['destination'] ?? '');
    }
    $complexItineraryNotice = (bool) ($complexItineraryNotice ?? false);
    $timelineSnapshotInvalid = (bool) ($timelineSnapshotInvalid ?? false);
    $sabreCheckoutSubmitDisabled = (bool) ($sabreCheckoutSubmitDisabled ?? false);
    $offerRefreshPending = (bool) ($offerRefreshPending ?? false);
    $offerRefreshDisplay = is_array($offerRefreshDisplay ?? null) ? $offerRefreshDisplay : null;
    $showOfferRefreshModal = $offerRefreshPending && $offerRefreshDisplay !== null;
    $sabreCheckoutDryRunInfo = (bool) ($sabreCheckoutDryRunInfo ?? false);
    $sabreTripOrdersDryRunReview = (bool) ($sabreTripOrdersDryRunReview ?? false);
    $sabreTripOrdersFareBasisWarning = (bool) ($sabreTripOrdersFareBasisWarning ?? false);
@endphp

<div class="jp-checkout-body jp-checkout-body--review" data-jp-checkout-body data-jp-checkout-review>
    <div class="jp-checkout-shell">
        <header class="jp-checkout-head">
            <h1 class="jp-checkout-head__title">{{ $checkoutPageHeading ?? 'Review your booking' }}</h1>
            <p class="jp-checkout-head__lead">Please confirm your flight and traveller details before submitting your booking request.</p>
        </header>

        <x-bookings.fare-session-countdown
            :session-key="'review:'.($booking->booking_reference ?? $booking->id ?? '')"
            :expires-at-iso="($fareSessionExpiresAt ?? $lockExpiresAt) !== '' ? ($fareSessionExpiresAt ?? $lockExpiresAt) : null"
            :refresh-search-url="$refreshSearchUrl ?? url('/')"
            variant="desktop"
        />

        @if (session('offer_refresh_accepted'))
            <div class="jp-alert jp-alert--success" role="status">
                {{ session('status') ?: __('Updated fare accepted. Continue booking.') }}
            </div>
        @endif
        @if ($complexItineraryNotice)
            <div class="jp-alert jp-alert--info" role="status">
                {{ __('Your booking request will require staff confirmation before airline hold/PNR.') }}
            </div>
        @endif
        <x-bookings.iati-reservation-status :booking="$booking" variant="customer" />
        @if ($timelineSnapshotInvalid)
            <div class="jp-alert jp-alert--danger">
                {{ __('Selected itinerary timing could not be verified. Please choose another fare.') }}
            </div>
        @endif
        @if ($sabreTripOrdersFareBasisWarning)
            <div class="jp-alert jp-alert--warning" role="status">
                {{ __('Some fare details could not be verified from your selected flight. Our team will confirm the correct fare before proceeding — you can still submit your booking request.') }}
            </div>
        @endif
        @if (!empty($meta['validation_warnings']) && is_array($meta['validation_warnings']))
            <div class="jp-alert jp-alert--warning">
                @foreach ($meta['validation_warnings'] as $warning)
                    <div>{{ $warning }}</div>
                @endforeach
            </div>
        @endif
        @if (($passengerCounts['total'] ?? 0) >= 9 && isset($meta['supplier_hold_success']) && ! $meta['supplier_hold_success'])
            <div class="jp-alert jp-alert--warning">
                We could not temporarily hold this fare with the airline. You can still submit a booking request, and our team will confirm availability manually.
            </div>
        @endif
        @if ($recheckRequired ?? false)
            <div class="jp-alert jp-alert--warning" role="alert">
                {{ __('Your fare needs to be checked again before you can continue.') }}
                <div class="jp-alert__actions">
                    <a class="jp-btn jp-btn--ghost jp-btn--sm" href="{{ client_route('booking.passengers', [
                        'flight_id' => (string) ($meta['original_offer_id'] ?? ''),
                        'offer_id' => (string) ($meta['original_offer_id'] ?? ''),
                        'search_id' => (string) data_get($meta, 'search_criteria.search_id', ''),
                        'from' => (string) data_get($meta, 'search_criteria.origin', ''),
                        'to' => (string) data_get($meta, 'search_criteria.destination', ''),
                        'depart' => (string) data_get($meta, 'search_criteria.depart_date', ''),
                    ]) }}">{{ __('Recheck fare') }}</a>
                </div>
            </div>
        @endif

        <div class="jp-checkout-grid">
            <div class="jp-checkout-grid__main">
                @include('themes.frontend.jetpakistan.frontend.booking.partials.jp-trip-summary-card', [
                    'jpOffer' => $o,
                    'jpCriteria' => $cr,
                    'jpJourneys' => $checkoutJourneys,
                    'jpPresentation' => $reviewPresentation,
                    'jpTripTypeLabel' => $tripTypeLabel,
                    'jpRouteLabel' => $routeLabel,
                    'jpAirlineLogo' => $airlineLogo ?? null,
                    'jpFareRules' => $checkoutFareRules,
                    'jpCabinLabel' => $cabinLabel,
                    'jpSelectedFareFamily' => $selectedFareFamilyCheckout,
                    'jpCardTitle' => 'Flight summary',
                ])

                @include('themes.frontend.jetpakistan.frontend.booking.partials.jp-passenger-contact-card', [
                    'jpPassengers' => $allPassengers,
                    'jpDraft' => $d,
                    'jpPassengerCounts' => $passengerCounts,
                ])
            </div>

            <aside class="jp-checkout-grid__aside" aria-label="Fare and confirmation">
                <article class="jp-checkout-card jp-checkout-card--fare">
                    <h2 class="jp-checkout-card__title">Fare breakdown</h2>
                    <x-bookings.checkout-fare-breakdown
                        :breakdown="$checkoutFareBreakdown ?? []"
                        :use-selected-fare-estimate="$useSelectedFareEstimate"
                        :selected-fare-estimate="$selectedFareEstimate"
                        :passenger-count-summary="$passengerCounts"
                    />
                    @if ($useSelectedFareEstimate)
                        <p class="jp-checkout-card__note">Final payable will be confirmed before ticketing or payment.</p>
                    @else
                        <p class="jp-checkout-card__note">Final fare is shown in PKR and may be rechecked before confirmation.</p>
                    @endif
                </article>

                <form method="post" action="{{ client_url('/booking/review') }}" class="jp-checkout-form" id="ota-review-submit-form" data-jp-review-form
                    onsubmit='(function(f){if(f.hasAttribute("data-ota-submitting"))return false;var b=f.querySelector("button[type=submit]");if(!b||b.disabled)return false;f.setAttribute("data-ota-submitting","1");b.setAttribute("data-ota-submitting","1");b.disabled=true;b.setAttribute("aria-busy","true");b.textContent=@json(__('Please wait…'));})(this);'>
                    @csrf
                    @error('booking')
                        <div class="jp-alert jp-alert--danger" role="alert">{{ $message }}</div>
                        @if ($selectedFareFamilyCheckout)
                            <p class="jp-checkout-card__muted">This selected fare will be reconfirmed by airline validation before booking is finalized.</p>
                        @endif
                    @enderror

                    <article class="jp-checkout-card jp-checkout-card--payment" data-jp-payment-options>
                        <h2 class="jp-checkout-card__title">Payment option</h2>
                        <p class="jp-checkout-card__lead">Choose how you would like to complete payment for this booking.</p>
                        @include('frontend.booking.partials.checkout-payment-methods')
                    </article>

                    <div class="jp-checkout-total" aria-live="polite">
                        @if ($useSelectedFareEstimate)
                            <span class="jp-checkout-total__label">{{ $selectedFareEstimate['label'] ?? 'Estimated selected fare' }}</span>
                            <span class="jp-checkout-total__value">
                                @if (!empty($selectedFareEstimate['price_is_approximate']))
                                    <span class="jp-checkout-total__approx">Approx.</span>
                                @endif
                                {{ preg_replace('/^Approx\.\s*/i', '', (string) ($selectedFareEstimate['price_display'] ?? '')) }}
                            </span>
                        @else
                            <span class="jp-checkout-total__label">Amount due (PKR)</span>
                            <span class="jp-checkout-total__value">Rs {{ number_format($totalFromDb > 0 ? $totalFromDb : (float) ($o['total'] ?? 0), 0) }}</span>
                        @endif
                    </div>

                    @if ($sabreCheckoutSubmitDisabled)
                        <div class="jp-alert jp-alert--warning">
                            {{ __('Online airline confirmation is not available yet.') }} {{ __('You can review your details and submit a booking request for staff follow-up.') }}
                        </div>
                    @elseif ($sabreTripOrdersDryRunReview)
                        <div class="jp-alert jp-alert--info">
                            {{ __('Your booking request is being prepared. Airline confirmation will follow staff review.') }}
                        </div>
                    @elseif ($sabreCheckoutDryRunInfo)
                        <div class="jp-alert jp-alert--info">
                            {{ __('Submitting sends your booking request to our team for review. No airline confirmation is created until staff verify availability and fare.') }}
                        </div>
                    @endif
                    @if ($offerRefreshPending)
                        <div class="jp-alert jp-alert--warning" role="alert">
                            {{ __('An airline fare update must be accepted before you can continue.') }}
                        </div>
                    @endif

                    <button type="submit" class="jp-btn jp-btn--primary jp-btn--block" data-jp-confirm-booking @if($sabreCheckoutSubmitDisabled || $offerRefreshPending) disabled aria-disabled="true" @endif>
                        @if ($offerRefreshPending)
                            {{ __('Accept updated fare to continue') }}
                        @elseif ($sabreCheckoutSubmitDisabled)
                            {{ __('Confirm Booking (unavailable)') }}
                        @else
                            {{ __('Confirm Booking') }}
                        @endif
                    </button>
                    @if (! $sabreCheckoutSubmitDisabled && ! $offerRefreshPending)
                        <p class="jp-checkout-disclaimer">No payment is taken on this step.</p>
                    @endif
                </form>
            </aside>
        </div>
    </div>
</div>

@if ($showOfferRefreshModal && $offerRefreshDisplay)
    <div id="ota-offer-refresh-modal" class="jp-modal" role="dialog" aria-modal="true" aria-labelledby="ota-offer-refresh-title">
        <div class="jp-modal__backdrop" tabindex="-1"></div>
        <div class="jp-modal__panel" role="document">
            <h4 id="ota-offer-refresh-title" class="jp-modal__title">{{ \App\Support\Bookings\SabreOfferRefreshAcceptance::CUSTOMER_MODAL_TITLE }}</h4>
            <p class="jp-modal__text">{{ \App\Support\Bookings\SabreOfferRefreshAcceptance::CUSTOMER_MODAL_MESSAGE }}</p>
            <p class="jp-modal__meta">{{ $routeLabel }} · {{ $reviewPresentation['departure_date_display'] ?? '' }}</p>
            @if (!empty($offerRefreshDisplay['brand_label']))
                <p class="jp-modal__meta">{{ __('Affected fare family') }}: <strong>{{ $offerRefreshDisplay['brand_label'] }}</strong></p>
            @endif
            <dl class="jp-kv-grid jp-kv-grid--modal">
                <div class="jp-kv-grid__row"><dt>{{ __('Old fare') }}</dt><dd>Rs {{ number_format($offerRefreshDisplay['old_total'], 0) }}</dd></div>
                <div class="jp-kv-grid__row"><dt>{{ __('New fare') }}</dt><dd>Rs {{ number_format($offerRefreshDisplay['new_total'], 0) }}</dd></div>
                <div class="jp-kv-grid__row jp-kv-grid__row--emphasis"><dt>{{ __('Difference') }}</dt><dd>Rs {{ number_format(abs($offerRefreshDisplay['delta']), 0) }}{{ $offerRefreshDisplay['delta'] >= 0 ? ' +' : ' −' }}</dd></div>
            </dl>
            <div class="jp-modal__actions">
                <form method="post" action="{{ client_route('booking.accept-updated-fare', ['booking' => $booking]) }}">
                    @csrf
                    <button type="submit" class="jp-btn jp-btn--primary jp-btn--block">{{ __('Accept new fare') }}</button>
                </form>
                <form method="post" action="{{ client_route('booking.decline-updated-fare', ['booking' => $booking]) }}">
                    @csrf
                    <button type="submit" class="jp-btn jp-btn--ghost jp-btn--block">{{ __('Back to flight search') }}</button>
                </form>
            </div>
        </div>
    </div>
    <script>document.body.classList.add('jp-offer-refresh-modal-open');</script>
@endif
