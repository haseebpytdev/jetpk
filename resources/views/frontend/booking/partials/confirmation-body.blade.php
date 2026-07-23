    @php
        use App\Enums\BookingStatus;
        use App\Enums\SupplierProvider;
        use App\Support\Bookings\BookingSupplierProviderResolver;
        use App\Support\Bookings\PnrItinerarySyncSafetyPresenter;
        use App\Support\Bookings\SabrePreCheckoutSellabilityPresentation;
        use App\Support\FlightSearch\FlightOfferDisplayPresenter;

        $d = $draft;
        $o = is_array($offer ?? null) ? $offer : null;
        $cr = $criteria;
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $ref = $d['booking_reference'] ?? ($booking->booking_reference ?? null);
        $bm = $d['booking_method'] ?? 'pay_later';
        $pnr = trim((string) ($booking->pnr ?? ''));
        $hasPnr = $pnr !== '';
        $supplierProvider = (string) ($supplierProvider ?? BookingSupplierProviderResolver::provider($booking));
        $supplierScope = (string) ($supplierScope ?? BookingSupplierProviderResolver::scope($booking));
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
            'online_card' => 'Pay online by card / AbhiPay',
            default => 'Booking request — pay after confirmation',
        };
        $statusBadge = match (true) {
            $isTicketed => [
                'label' => 'Confirmed',
                'class' => 'ota-confirm-status--confirmed',
            ],
            $booking->status === BookingStatus::FareReview => [
                'label' => 'Under review',
                'class' => 'ota-confirm-status--review',
            ],
            $hasPnr => [
                'label' => $isPiaNdcBooking ? 'PNR created' : 'Pending ticketing',
                'class' => 'ota-confirm-status--pending',
            ],
            default => [
                'label' => 'Pending',
                'class' => 'ota-confirm-status--pending',
            ],
        };
        $heroTitle = $isFullyConfirmed ? 'Booking confirmed' : 'Booking request received';
        $heroSub = match (true) {
            $isFullyConfirmed && $hasPnr => 'Your booking is confirmed.',
            $isPiaNdcBooking && $hasPnr => 'Option PNR created. Payment is still pending — ticketing will happen after payment verification.',
            $isPiaNdcBooking && ($supplierCheckoutNotice !== '' || $piaNdcAutoFailed) => \App\Services\Suppliers\PiaNdc\PiaNdcOptionPnrService::AUTO_FAILURE_CUSTOMER_NOTICE,
            $hasPnr => ($isSabreBooking
                ? 'Sabre reservation created. Ticketing is still pending — manual verification may be required before tickets are issued.'
                : 'Reservation/PNR created. Ticketing is still pending.'),
            default => 'Your booking request has been received, but no airline reservation/PNR has been issued yet. Our team will review and update you.',
        };
        $selectedFareFamilyOption = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : null;
        $selectedFareFamilyCheckout = FlightOfferDisplayPresenter::buildSelectedFareFamilyCheckoutView($selectedFareFamilyOption);
        $selectedFareEstimate = FlightOfferDisplayPresenter::buildCheckoutSelectedFareEstimatePresentation($selectedFareFamilyOption);
        $checkoutFareRules = FlightOfferDisplayPresenter::buildCheckoutFareRulesSidebar($o, $selectedFareFamilyOption);
        $useSelectedFareEstimate = is_array($selectedFareEstimate) && ! empty($selectedFareEstimate['has_checkout_estimate']);
        $selectedFareFamilyLabel = trim((string) ($selectedFareFamilyCheckout['name'] ?? ''));
        if ($selectedFareFamilyLabel === '' && is_array($o)) {
            $selectedFareFamilyLabel = trim((string) ($o['fare_family'] ?? ''));
        }
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
        $wa = config('ota-client.support_whatsapp', '');
        $waUrl = $wa !== '' ? 'https://wa.me/'.preg_replace('/\D+/', '', (string) $wa) : null;
    @endphp
    <section class="ota-confirmation-wrap ota-confirmation-page">
        <div class="ota-container ota-container-narrow">
            <div class="ota-confirm-hero-card">
                <div class="ota-confirm-success-ring" aria-hidden="true">
                    <span class="ota-confirm-success-icon"><i class="fa fa-check"></i></span>
                </div>
                <h1 class="ota-confirm-title">{{ $heroTitle }}</h1>
                <p class="ota-confirm-sub">{{ $heroSub }}</p>

                <div class="ota-confirm-hero-meta">
                    <div class="ota-confirm-ref">
                        <span class="ota-confirm-ref__label">Booking reference</span>
                        <span class="ota-confirm-ref__value ota-confirm-ref__value--hero">{{ $ref ?? '—' }}</span>
                        @unless ($ref)
                            <span class="ota-confirm-ref__hint">Your reference will appear once processing completes.</span>
                        @endunless
                    </div>
                    <span class="ota-confirm-status {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>
                </div>

                <dl class="ota-confirm-hero-facts">
                    <div class="ota-confirm-hero-facts__row">
                        <dt>{{ $pnrFieldLabel }}</dt>
                        <dd>{{ $hasPnr ? $sabrePnrLabel : 'Under review' }}</dd>
                    </div>
                    @if ($isSabreBooking && $airlineLocatorLabel)
                        <div class="ota-confirm-hero-facts__row">
                            <dt>Airline locator</dt>
                            <dd>{{ $airlineLocatorLabel }}</dd>
                        </div>
                    @endif
                    @if ($hasPnr && ! $isTicketed)
                        <div class="ota-confirm-hero-facts__row">
                            <dt>Ticketing</dt>
                            <dd>Pending — not ticketed yet</dd>
                        </div>
                    @endif
                    @if ($paxCount > 0)
                        <div class="ota-confirm-hero-facts__row">
                            <dt>Passengers</dt>
                            <dd>{{ $paxCount }} {{ $paxCount === 1 ? 'passenger' : 'passengers' }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($pnrVerificationNote)
                    <p class="ota-confirm-hero-note">{{ $pnrVerificationNote }}</p>
                @endif
                @if ($supplierCheckoutNotice !== '')
                    <p class="ota-confirm-hero-note">{{ $supplierCheckoutNotice }}</p>
                @endif
                @if ($preCheckoutConfirmNote && $isSabreBooking)
                    <p class="ota-confirm-hero-note">{{ $preCheckoutConfirmNote }}</p>
                @endif
            </div>

            <div class="ota-confirm-grid">
                <article class="ota-confirm-card ota-confirm-card--wide">
                    <h2 class="ota-confirm-card__title"><i class="fa fa-plane" aria-hidden="true"></i> Trip summary</h2>
                    @if ($o)
                        <div class="ota-confirm-trip">
                            <div class="ota-confirm-trip__head">
                                <div class="ota-confirm-trip__brand">
                                    @if (!empty($airlineLogo))
                                        <img src="{{ $airlineLogo }}" alt="" class="ota-confirm-trip__logo" width="32" height="32">
                                    @endif
                                    <div>
                                        <p class="ota-confirm-trip__airline mb-0">{{ $o['airline_name'] ?? '' }}</p>
                                        <p class="ota-confirm-trip__flight-no mb-0">{{ $o['carrier_code'] ?? '' }}{{ $o['flight_number'] ?? '' }}</p>
                                    </div>
                                </div>
                                <span class="ota-confirm-trip__pill">{{ $tripTypeLabel }}</span>
                            </div>
                            <p class="ota-confirm-trip__route">{{ $routeLabel }}</p>

                            <div class="ota-confirm-trip__legs">
                                @if ($confirmJourneys !== [])
                                    @foreach ($confirmJourneys as $journey)
                                        @if (is_array($journey))
                                            <div class="ota-confirm-trip-leg">
                                                <div class="ota-confirm-trip-leg__head">
                                                    <span class="ota-confirm-trip-leg__label">{{ $journey['label'] ?? 'Flight' }}</span>
                                                    <span class="ota-confirm-trip-leg__meta">{{ $journey['stops_display'] ?? '' }}@if (!empty($journey['duration_display'])) · {{ $journey['duration_display'] }}@endif</span>
                                                </div>
                                                <p class="ota-confirm-trip-leg__route">
                                                    {{ $journey['origin'] ?? '' }}@if (!empty($journey['origin_city'])) ({{ $journey['origin_city'] }})@endif
                                                    <span aria-hidden="true">→</span>
                                                    {{ $journey['destination'] ?? '' }}@if (!empty($journey['destination_city'])) ({{ $journey['destination_city'] }})@endif
                                                </p>
                                                <div class="ota-confirm-trip-leg__times">
                                                    <span>{{ $journey['departure_time_display'] ?? '' }}</span>
                                                    <span class="ota-confirm-trip-leg__dash" aria-hidden="true">—</span>
                                                    <span>{{ $journey['arrival_time_display'] ?? '' }}@if (!empty($journey['arrival_day_offset'])) <em class="ota-confirm-trip-leg__offset">{{ $journey['arrival_day_offset'] }}</em>@endif</span>
                                                </div>
                                                <p class="ota-confirm-trip-leg__dates">
                                                    {{ $journey['departure_date_display'] ?? '' }}@if (!empty($journey['arrival_date_display'])) · {{ $journey['arrival_date_display'] }}@endif
                                                </p>
                                                <x-bookings.checkout-journey-layovers :journey="$journey" />
                                            </div>
                                        @endif
                                    @endforeach
                                @else
                                    <div class="ota-confirm-trip-leg">
                                        <div class="ota-confirm-trip-leg__head">
                                            <span class="ota-confirm-trip-leg__label">Outbound</span>
                                            <span class="ota-confirm-trip-leg__meta">{{ $confirmPresentation['stops_display'] ?? '' }}@if (!empty($confirmPresentation['itinerary_duration_display'])) · {{ $confirmPresentation['itinerary_duration_display'] }}@endif</span>
                                        </div>
                                        <p class="ota-confirm-trip-leg__route">{{ $cr['origin'] ?? '' }} <span aria-hidden="true">→</span> {{ $cr['destination'] ?? '' }}</p>
                                        <div class="ota-confirm-trip-leg__times">
                                            <span>{{ $confirmPresentation['departure_time_display'] ?? \Illuminate\Support\Carbon::parse($o['depart_at'] ?? '')->format('H:i') }}</span>
                                            <span class="ota-confirm-trip-leg__dash" aria-hidden="true">—</span>
                                            <span>{{ $confirmPresentation['arrival_time_display'] ?? \Illuminate\Support\Carbon::parse($o['arrive_at'] ?? '')->format('H:i') }}</span>
                                        </div>
                                        <p class="ota-confirm-trip-leg__dates">
                                            {{ $confirmPresentation['departure_date_display'] ?? \Illuminate\Support\Carbon::parse($o['depart_at'] ?? '')->format('D, j M') }}
                                            @if (!empty($confirmPresentation['arrival_date_display']))
                                                · {{ $confirmPresentation['arrival_date_display'] }}
                                            @endif
                                        </p>
                                        <x-bookings.checkout-journey-layovers :journey="[
                                            'origin' => $confirmPresentation['origin'] ?? ($cr['origin'] ?? ''),
                                            'destination' => $confirmPresentation['destination'] ?? ($cr['destination'] ?? ''),
                                            'stops_display' => $confirmPresentation['stops_display'] ?? '',
                                            'stops_count' => $confirmPresentation['stops_count'] ?? null,
                                            'duration_display' => $confirmPresentation['itinerary_duration_display'] ?? '',
                                            'segments_display' => $confirmPresentation['segments_display'] ?? [],
                                            'layovers_display' => $confirmPresentation['layovers_display'] ?? [],
                                            'layover_summary' => $confirmPresentation['layover_summary'] ?? [],
                                            'connection_details_unavailable' => $confirmPresentation['connection_details_unavailable'] ?? false,
                                        ]" />
                                    </div>
                                @endif
                            </div>

                            <ul class="ota-confirm-trip__tags">
                                <li><i class="fa fa-suitcase" aria-hidden="true"></i> {{ $checkoutFareRules['baggage_display'] ?? ($o['baggage'] ?? 'Baggage per fare rules') }}</li>
                                <li>{{ $cabinLabel }}</li>
                                @if ($selectedFareFamilyLabel !== '' && ! $selectedFareFamilyCheckout)
                                    <li>{{ $selectedFareFamilyLabel }}</li>
                                @endif
                                <li>
                                    @if (!empty($o['refundable']))
                                        <span class="ota-confirm-tag ota-confirm-tag--ok">Refundable</span>
                                    @else
                                        <span class="ota-confirm-tag">Non-refundable</span>
                                    @endif
                                </li>
                                <li>
                                    <span class="ota-confirm-tag {{ $hasPnr ? 'ota-confirm-tag--ok' : '' }}">{{ $pnrFieldLabel }}: {{ $hasPnr ? $sabrePnrLabel : 'Under review' }}</span>
                                </li>
                            </ul>
                            @if ($selectedFareFamilyCheckout)
                                <x-bookings.selected-fare-family-block :checkout="$selectedFareFamilyCheckout" class="mt-3" />
                            @endif
                        </div>
                    @else
                        <p class="ota-confirm-card__text">{{ $routeLabel }}</p>
                        <p class="ota-confirm-card__muted">
                            @if (!empty($cr['depart_date']))
                                {{ \Illuminate\Support\Carbon::parse($cr['depart_date'])->format('D, M j, Y') }}
                            @endif
                            · {{ $tripTypeLabel }}
                        </p>
                        <p class="ota-confirm-card__muted mb-0">{{ $pnrFieldLabel }}: {{ $hasPnr ? $sabrePnrLabel : 'Under review' }}</p>
                    @endif
                </article>

                <article class="ota-confirm-card">
                    <h2 class="ota-confirm-card__title"><i class="fa fa-user" aria-hidden="true"></i> Passenger &amp; contact</h2>
                    <dl class="ota-confirm-dl">
                        <div class="ota-confirm-dl__row">
                            <dt>Lead passenger</dt>
                            <dd>{{ $leadName !== '' ? $leadName : '—' }}</dd>
                        </div>
                        <div class="ota-confirm-dl__row">
                            <dt>Email</dt>
                            <dd>{{ $d['email'] ?? '—' }}</dd>
                        </div>
                        <div class="ota-confirm-dl__row">
                            <dt>Mobile</dt>
                            <dd>{{ $d['phone'] ?? '—' }}</dd>
                        </div>
                        @if (!empty($d['country']))
                            <div class="ota-confirm-dl__row">
                                <dt>Country</dt>
                                <dd>{{ $d['country'] }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if ($paxCount > 1)
                        <p class="ota-confirm-card__muted ota-confirm-card__muted--small mb-0">{{ $paxCount }} passengers on this booking.</p>
                    @endif
                </article>

                <article class="ota-confirm-card">
                    <h2 class="ota-confirm-card__title"><i class="fa fa-credit-card" aria-hidden="true"></i> Payment</h2>
                    <p class="ota-confirm-method mb-1">Booking method: {{ $bmLabel }}</p>
                    @php
                        $abhiPay = is_array($abhiPayCheckout ?? null) ? $abhiPayCheckout : [];
                    @endphp
                    <p class="small mb-2" data-testid="abhipay-payment-status">
                        <strong>Payment status:</strong> {{ $abhiPay['payment_status_label'] ?? 'Unpaid' }}
                    </p>
                    @if ($useSelectedFareEstimate)
                        <p class="ota-confirm-card__total mt-2 mb-0">
                            @if (!empty($selectedFareEstimate['price_is_approximate']))
                                <span class="ota-checkout-selected-fare-family__approx">Approx.</span>
                            @endif
                            {{ preg_replace('/^Approx\.\s*/i', '', (string) ($selectedFareEstimate['price_display'] ?? '')) }}
                        </p>
                        <p class="ota-confirm-card__muted ota-confirm-card__muted--small mb-0">{{ $selectedFareEstimate['label'] ?? 'Estimated selected fare' }}</p>
                    @elseif ($fare)
                        <p class="ota-confirm-card__total mt-2 mb-0">Rs {{ number_format((float) $fare->total, 0) }}</p>
                        <p class="ota-confirm-card__muted ota-confirm-card__muted--small mb-0">Final total snapshot</p>
                    @endif
                    @if (!empty($abhiPay['show_pay_button']) && (float) ($abhiPay['payable_amount'] ?? 0) > 0)
                        <div class="mt-3 p-3 border rounded" data-testid="abhipay-confirmation-option">
                            <div class="fw-semibold mb-1">Pay online by card / AbhiPay</div>
                            <p class="small text-secondary mb-2">Pay Rs {{ number_format((float) ($abhiPay['payable_amount'] ?? 0), 0) }} securely with your card.</p>
                            <p class="small text-secondary mb-2">{{ $abhiPay['ticketing_note'] ?? 'Ticketing will happen after payment verification.' }}</p>
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
                                    <button type="submit" class="btn btn-primary">Pay with AbhiPay</button>
                                </form>
                            @else
                                <p class="small text-warning mb-0">Online payment could not be started. Please use booking lookup or contact support.</p>
                            @endif
                        </div>
                    @elseif (!empty($abhiPay['blocked_message']))
                        <div class="alert alert-warning mt-3 mb-0 small" data-testid="abhipay-pia-ndc-blocked">
                            {{ $abhiPay['blocked_message'] }}
                        </div>
                    @elseif (($abhiPay['payment_status_label'] ?? '') === 'Paid')
                        <p class="ota-confirm-card__note mt-2 mb-0">Payment received. {{ $abhiPay['ticketing_note'] ?? 'Ticketing will happen after payment verification.' }}</p>
                    @else
                        <p class="ota-confirm-card__note mt-2 mb-0">No payment has been taken at this stage.</p>
                    @endif
                    @error('payment')
                        <div class="alert alert-danger mt-3 mb-0 small">{{ $message }}</div>
                    @enderror
                </article>

                <article class="ota-confirm-card ota-confirm-card--wide" data-confirmation-next-steps>
                    <h2 class="ota-confirm-card__title"><i class="fa fa-list-ul" aria-hidden="true"></i> Next steps</h2>
                    <ol class="ota-confirm-steps">
                        <li>Our team reviews your booking request.</li>
                        <li>We will contact you to confirm availability, fare, and payment instructions.</li>
                        <li>Ticketing will be completed after verification and payment where applicable.</li>
                    </ol>
                </article>
            </div>

            <nav class="ota-confirm-actions" aria-label="What would you like to do next?">
                <a href="{{ client_route('home') }}" class="btn btn-default btn-lg ota-confirm-btn-secondary">Back to home</a>
                <a href="{{ client_route('booking.lookup') }}" class="btn btn-primary btn-lg ota-confirm-btn-primary">Lookup booking</a>
                <a href="{{ client_route('support') }}" class="btn btn-default btn-lg ota-confirm-btn-secondary">Contact support</a>
                @if ($waUrl)
                    <a href="{{ $waUrl }}" class="btn btn-default btn-lg ota-confirm-btn-secondary ota-confirm-btn-whatsapp" target="_blank" rel="noopener noreferrer">
                        <i class="fa fa-whatsapp" aria-hidden="true"></i> WhatsApp
                    </a>
                @endif
            </nav>
        </div>
    </section>
