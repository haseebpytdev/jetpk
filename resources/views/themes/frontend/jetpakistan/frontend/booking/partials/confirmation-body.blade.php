@php
    use App\Enums\BookingStatus;
    use App\Enums\SupplierProvider;
    use App\Support\Bookings\BookingSupplierProviderResolver;
    use App\Support\Bookings\PnrItinerarySyncSafetyPresenter;
    use App\Support\Bookings\SabrePreCheckoutSellabilityPresentation;
    use App\Support\Branding\PublicAgencyContact;
    use App\Support\Branding\PublicAgencyContactResolver;
    use App\Support\FlightSearch\FlightOfferDisplayPresenter;

    $jpIsPlaceholderContact = static function (?string $value): bool {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

        return $normalized === ''
            || (bool) preg_match('/^(123|\+92\s*300\s*0{6}|\+92\s*21\s*111\s*000\s*000)$/i', $normalized);
    };

    $d = $draft;
    $o = is_array($offer ?? null) ? $offer : null;
    $cr = $criteria;
    $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
    $ref = $d['booking_reference'] ?? ($booking->booking_reference ?? null);
    $bm = $d['booking_method'] ?? 'pay_later';
    $pnr = trim((string) ($booking->pnr ?? ''));
    $hasPnr = $pnr !== '';
    $supplierProvider = (string) ($supplierProvider ?? BookingSupplierProviderResolver::provider($booking));
    $isSabreBooking = $supplierProvider === SupplierProvider::Sabre->value;
    $isPiaNdcBooking = $supplierProvider === SupplierProvider::PiaNdc->value;
    $supplierCheckoutNotice = is_array($supplierConfirmationNotice ?? null)
        ? trim((string) ($supplierConfirmationNotice['notice'] ?? ''))
        : '';
    $piaNdcAutoFailed = $isPiaNdcBooking && ($meta['pia_ndc_auto_option_pnr']['status'] ?? '') === 'failed';
    $pnrPresentation = $isSabreBooking ? PnrItinerarySyncSafetyPresenter::forBooking($booking) : null;
    $sabrePnrLabel = $isSabreBooking
        ? (string) ($pnrPresentation['sabre_pnr_label'] ?? ($hasPnr ? strtoupper($pnr) : ''))
        : ($hasPnr ? strtoupper($pnr) : '');
    $airlineLocatorLabel = is_array($pnrPresentation) ? ($pnrPresentation['airline_locator_label'] ?? null) : null;
    $pnrVerificationNote = is_array($pnrPresentation) ? ($pnrPresentation['verification_note'] ?? null) : null;
    $pnrFieldLabel = $isSabreBooking ? 'Sabre PNR' : 'PNR';
    $isTicketed = $booking->status === BookingStatus::Ticketed
        || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true)
        || $booking->ticketed_at !== null;
    $isFullyConfirmed = $isTicketed;
    $preCheckoutPresentation = $isSabreBooking
        ? SabrePreCheckoutSellabilityPresentation::resolveForBooking($booking)
        : null;
    $preCheckoutConfirmNote = is_array($preCheckoutPresentation)
        ? SabrePreCheckoutSellabilityPresentation::confirmationNote($preCheckoutPresentation)
        : null;
    $allPassengers = $booking->passengers->sortBy('passenger_index')->values();
    $paxCount = $allPassengers->count();
    $leadPassenger = $allPassengers->firstWhere('is_lead_passenger', true) ?? $allPassengers->first();
    $leadName = trim(($leadPassenger?->title ?? '').' '.($leadPassenger?->first_name ?? '').' '.($leadPassenger?->last_name ?? ''));
    $fare = $booking->fareBreakdown ?? null;
    $bmLabel = match ($bm) {
        'bank_transfer', 'offline_bank_transfer' => 'Bank transfer',
        'office', 'office_confirmation' => 'Office confirmation',
        'online_card' => 'Pay online by card',
        default => 'Booking request — pay after confirmation',
    };
    $statusBadge = match (true) {
        $isTicketed => ['label' => 'Confirmed', 'class' => 'jp-status-pill--confirmed'],
        $booking->status === BookingStatus::FareReview => ['label' => 'Under review', 'class' => 'jp-status-pill--review'],
        $hasPnr => ['label' => $isPiaNdcBooking ? 'PNR created' : 'Pending ticketing', 'class' => 'jp-status-pill--pending'],
        default => ['label' => 'Pending', 'class' => 'jp-status-pill--pending'],
    };
    $heroTitle = $isFullyConfirmed ? 'Booking confirmed' : 'Booking request received';
    $heroSub = match (true) {
        $isFullyConfirmed && $hasPnr => 'Your booking is confirmed.',
        $isPiaNdcBooking && $hasPnr => 'Option PNR created. Payment is still pending — ticketing will happen after payment verification.',
        $isPiaNdcBooking && ($supplierCheckoutNotice !== '' || $piaNdcAutoFailed) => \App\Services\Suppliers\PiaNdc\PiaNdcOptionPnrService::AUTO_FAILURE_CUSTOMER_NOTICE,
        $hasPnr => ($isSabreBooking
            ? 'Sabre reservation created. Ticketing is still pending — manual verification may be required before tickets are issued.'
            : 'Reservation/PNR created. Ticketing is still pending.'),
        default => 'Your booking request has been received. Our team will review availability, fare, and payment instructions.',
    };
    $selectedFareFamilyOption = is_array($meta['selected_fare_family_option'] ?? null)
        ? $meta['selected_fare_family_option']
        : null;
    $selectedFareFamilyCheckout = FlightOfferDisplayPresenter::buildSelectedFareFamilyCheckoutView($selectedFareFamilyOption);
    $selectedFareEstimate = FlightOfferDisplayPresenter::buildCheckoutSelectedFareEstimatePresentation($selectedFareFamilyOption);
    $checkoutFareRules = FlightOfferDisplayPresenter::buildCheckoutFareRulesSidebar($o, $selectedFareFamilyOption);
    $useSelectedFareEstimate = is_array($selectedFareEstimate) && ! empty($selectedFareEstimate['has_checkout_estimate']);
    $tripTypeLabel = FlightOfferDisplayPresenter::formatCriteriaTripTypeLabel((string) ($cr['trip_type'] ?? 'one_way'));
    $routeLabel = FlightOfferDisplayPresenter::formatCriteriaRouteLabel($cr);
    if ($routeLabel === '') {
        $routeLabel = ($cr['origin'] ?? '').' → '.($cr['destination'] ?? '');
    }
    $confirmPresentation = [];
    $confirmJourneys = [];
    if (is_array($o)) {
        $displayOffer = FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($o, $cr);
        $confirmCityMap = FlightOfferDisplayPresenter::airportCityMap(
            FlightOfferDisplayPresenter::collectIataCodes($displayOffer)
        );
        $confirmPresentation = FlightOfferDisplayPresenter::buildPresentation($displayOffer, $cr, $confirmCityMap);
        $confirmJourneys = is_array($confirmPresentation['journeys_display'] ?? null)
            ? $confirmPresentation['journeys_display']
            : [];
    }
    $cabinLabel = ucfirst(str_replace('_', ' ', (string) (is_array($o) ? ($checkoutFareRules['cabin_display'] ?? $o['cabin'] ?? $cr['cabin'] ?? 'economy') : ($cr['cabin'] ?? 'economy'))));
    $passengerCounts = [
        'adults' => $allPassengers->where('passenger_type', 'adult')->count(),
        'children' => $allPassengers->where('passenger_type', 'child')->count(),
        'infants' => $allPassengers->where('passenger_type', 'infant')->count(),
        'total' => $paxCount,
    ];
    $jpBrand = trim(client_branding()->companyName());
    if ($jpBrand === '') {
        $jpBrand = client_branding()->companyName();
    }
    $masterContact = PublicAgencyContactResolver::resolve($agencySettings ?? null);
    $jpPhone = trim(client_branding()->phone());
    $jpEmail = trim(client_branding()->email());
    if ($jpIsPlaceholderContact($jpPhone)) {
        $jpPhone = '';
    }
    $resolvedPhone = $jpPhone !== '' ? $jpPhone : $masterContact->phone;
    if ($jpIsPlaceholderContact($resolvedPhone)) {
        $resolvedPhone = '';
    }
    $resolvedWhatsapp = $masterContact->whatsapp;
    if ($jpIsPlaceholderContact($resolvedWhatsapp)) {
        $resolvedWhatsapp = '';
    }
    $resolvedEmail = $jpEmail !== '' ? $jpEmail : $masterContact->email;
    if ($jpIsPlaceholderContact($resolvedEmail)) {
        $resolvedEmail = '';
    }
    $jpContact = new PublicAgencyContact(
        agencyName: $jpBrand,
        phone: $resolvedPhone,
        email: $resolvedEmail,
        whatsapp: $resolvedWhatsapp,
        city: $masterContact->city,
        address: trim(client_branding()->address()) !== '' ? trim(client_branding()->address()) : $masterContact->address,
    );
    $waUrl = $jpContact->whatsappUrl();
    $staleBooking = ! $o && empty($ref);
@endphp

<div class="jp-checkout-body jp-checkout-body--confirm" data-jp-checkout-body data-jp-checkout-confirm>
    <div class="jp-checkout-shell jp-checkout-shell--confirm">
        @if ($staleBooking)
            <div class="jp-alert jp-alert--warning" role="alert">
                This booking summary is no longer available. Please start a new search to continue.
                <div class="jp-alert__actions">
                    <a class="jp-btn jp-btn--primary jp-btn--sm" href="{{ client_route('home') }}">Back to home</a>
                    <a class="jp-btn jp-btn--ghost jp-btn--sm" href="{{ client_url('/flights/results') }}">Search flights</a>
                </div>
            </div>
        @endif

        <article class="jp-confirm-hero" data-jp-confirm-hero>
            <div class="jp-confirm-hero__icon" aria-hidden="true">
                <i class="fa fa-check"></i>
            </div>
            <h1 class="jp-confirm-hero__title">{{ $heroTitle }}</h1>
            <p class="jp-confirm-hero__lead">{{ $heroSub }}</p>
            <div class="jp-confirm-hero__meta">
                <div class="jp-confirm-ref">
                    <span class="jp-confirm-ref__label">Booking reference</span>
                    <span class="jp-confirm-ref__value">{{ $ref ?? '—' }}</span>
                    @unless ($ref)
                        <span class="jp-confirm-ref__hint">Your reference will appear once processing completes.</span>
                    @endunless
                </div>
                <span class="jp-status-pill {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>
            </div>
            <dl class="jp-kv-grid jp-kv-grid--hero">
                <div class="jp-kv-grid__row">
                    <dt>{{ $pnrFieldLabel }}</dt>
                    <dd>{{ $hasPnr ? $sabrePnrLabel : 'Under review' }}</dd>
                </div>
                @if ($isSabreBooking && $airlineLocatorLabel)
                    <div class="jp-kv-grid__row">
                        <dt>Airline locator</dt>
                        <dd>{{ $airlineLocatorLabel }}</dd>
                    </div>
                @endif
                @if ($hasPnr && ! $isTicketed)
                    <div class="jp-kv-grid__row">
                        <dt>Ticketing</dt>
                        <dd>Pending — not ticketed yet</dd>
                    </div>
                @endif
                @if ($paxCount > 0)
                    <div class="jp-kv-grid__row">
                        <dt>Passengers</dt>
                        <dd>{{ $paxCount }} {{ $paxCount === 1 ? 'passenger' : 'passengers' }}</dd>
                    </div>
                @endif
            </dl>
            @if ($pnrVerificationNote)
                <p class="jp-confirm-hero__note">{{ $pnrVerificationNote }}</p>
            @endif
            @if ($supplierCheckoutNotice !== '')
                <p class="jp-confirm-hero__note">{{ $supplierCheckoutNotice }}</p>
            @endif
            @if ($preCheckoutConfirmNote && $isSabreBooking)
                <p class="jp-confirm-hero__note">{{ $preCheckoutConfirmNote }}</p>
            @endif
        </article>

        <div class="jp-checkout-grid jp-checkout-grid--confirm">
            <div class="jp-checkout-grid__main">
                @if ($o)
                    @include('themes.frontend.jetpakistan.frontend.booking.partials.jp-trip-summary-card', [
                        'jpOffer' => $o,
                        'jpCriteria' => $cr,
                        'jpJourneys' => $confirmJourneys,
                        'jpPresentation' => $confirmPresentation,
                        'jpTripTypeLabel' => $tripTypeLabel,
                        'jpRouteLabel' => $routeLabel,
                        'jpAirlineLogo' => $airlineLogo ?? null,
                        'jpFareRules' => $checkoutFareRules,
                        'jpCabinLabel' => $cabinLabel,
                        'jpSelectedFareFamily' => $selectedFareFamilyCheckout,
                        'jpCardTitle' => 'Trip summary',
                    ])
                @else
                    <article class="jp-checkout-card">
                        <h2 class="jp-checkout-card__title">Trip summary</h2>
                        <p class="jp-trip-route">{{ $routeLabel }}</p>
                        <p class="jp-checkout-card__muted">{{ $pnrFieldLabel }}: {{ $hasPnr ? $sabrePnrLabel : 'Under review' }}</p>
                    </article>
                @endif

                @include('themes.frontend.jetpakistan.frontend.booking.partials.jp-passenger-contact-card', [
                    'jpPassengers' => $allPassengers,
                    'jpDraft' => $d,
                    'jpPassengerCounts' => $passengerCounts,
                ])
            </div>

            <aside class="jp-checkout-grid__aside">
                <article class="jp-checkout-card jp-checkout-card--payment">
                    <h2 class="jp-checkout-card__title">Payment</h2>
                    <dl class="jp-kv-grid jp-kv-grid--payment">
                        <div class="jp-kv-grid__row">
                            <dt>Booking method</dt>
                            <dd>{{ $bmLabel }}</dd>
                        </div>
                        @php $abhiPay = is_array($abhiPayCheckout ?? null) ? $abhiPayCheckout : []; @endphp
                        <div class="jp-kv-grid__row">
                            <dt>Payment status</dt>
                            <dd data-testid="abhipay-payment-status">{{ $abhiPay['payment_status_label'] ?? 'Unpaid' }}</dd>
                        </div>
                    </dl>
                    @if ($useSelectedFareEstimate)
                        <p class="jp-checkout-total__value jp-checkout-total__value--inline">
                            @if (!empty($selectedFareEstimate['price_is_approximate']))
                                <span class="jp-checkout-total__approx">Approx.</span>
                            @endif
                            {{ preg_replace('/^Approx\.\s*/i', '', (string) ($selectedFareEstimate['price_display'] ?? '')) }}
                        </p>
                        <p class="jp-checkout-card__muted">{{ $selectedFareEstimate['label'] ?? 'Estimated selected fare' }}</p>
                    @elseif ($fare)
                        <p class="jp-checkout-total__value jp-checkout-total__value--inline">Rs {{ number_format((float) $fare->total, 0) }}</p>
                        <p class="jp-checkout-card__muted">Final total snapshot</p>
                    @endif

                    @if (!empty($abhiPay['show_pay_button']) && (float) ($abhiPay['payable_amount'] ?? 0) > 0)
                        <div class="jp-payment-cta" data-testid="abhipay-confirmation-option">
                            <p class="jp-payment-cta__title">Pay online by card</p>
                            <p class="jp-checkout-card__muted">Pay Rs {{ number_format((float) ($abhiPay['payable_amount'] ?? 0), 0) }} securely with your card.</p>
                            <p class="jp-checkout-card__muted">{{ $abhiPay['ticketing_note'] ?? 'Ticketing will happen after payment verification.' }}</p>
                            @php
                                $abhiPayStartUrl = auth()->check()
                                    ? route('payments.abhipay.start', $booking)
                                    : (filled($guestAbhiPayToken ?? '')
                                        ? route('guest.bookings.abhipay.start', ['booking' => $booking, 'token' => $guestAbhiPayToken])
                                        : null);
                            @endphp
                            @if ($abhiPayStartUrl)
                                <form method="post" action="{{ $abhiPayStartUrl }}">
                                    @csrf
                                    <button type="submit" class="jp-btn jp-btn--primary jp-btn--block">Pay with card</button>
                                </form>
                            @else
                                <p class="jp-checkout-card__muted">Online payment could not be started. Please use booking lookup or contact support.</p>
                            @endif
                        </div>
                    @elseif (!empty($abhiPay['blocked_message']))
                        <div class="jp-alert jp-alert--warning" data-testid="abhipay-pia-ndc-blocked">{{ $abhiPay['blocked_message'] }}</div>
                    @elseif (($abhiPay['payment_status_label'] ?? '') === 'Paid')
                        <p class="jp-checkout-card__note">{{ $abhiPay['ticketing_note'] ?? 'Ticketing will happen after payment verification.' }}</p>
                    @else
                        <p class="jp-checkout-card__note">No payment has been taken at this stage.</p>
                    @endif
                    @error('payment')
                        <div class="jp-alert jp-alert--danger">{{ $message }}</div>
                    @enderror
                </article>

                <article class="jp-checkout-card jp-checkout-card--steps" data-confirmation-next-steps>
                    <h2 class="jp-checkout-card__title">Next steps</h2>
                    <ol class="jp-steps-list">
                        <li class="jp-steps-list__item"><span class="jp-steps-list__num">1</span><span class="jp-steps-list__text">Our team reviews your booking request.</span></li>
                        <li class="jp-steps-list__item"><span class="jp-steps-list__num">2</span><span class="jp-steps-list__text">We contact you to confirm availability, fare, and payment instructions.</span></li>
                        <li class="jp-steps-list__item"><span class="jp-steps-list__num">3</span><span class="jp-steps-list__text">Ticketing is completed after verification and payment where applicable.</span></li>
                    </ol>
                </article>

                <article class="jp-checkout-card jp-checkout-card--support">
                    <h2 class="jp-checkout-card__title">Need help?</h2>
                    <p class="jp-checkout-card__lead">Our support team can assist with your booking reference.</p>
                    <div class="jp-confirm-actions jp-confirm-actions--stack">
                        <a href="{{ client_route('home') }}" class="jp-btn jp-btn--ghost jp-btn--block">Back to home</a>
                        <a href="{{ client_route('booking.lookup') }}" class="jp-btn jp-btn--primary jp-btn--block">Lookup booking</a>
                        <a href="{{ client_route('support') }}" class="jp-btn jp-btn--ghost jp-btn--block">Contact support</a>
                        @if ($waUrl)
                            <a href="{{ $waUrl }}" class="jp-btn jp-btn--wa jp-btn--block" target="_blank" rel="noopener noreferrer">
                                <i class="fa fa-whatsapp" aria-hidden="true"></i> WhatsApp
                            </a>
                        @endif
                    </div>
                </article>
            </aside>
        </div>
    </div>
</div>
