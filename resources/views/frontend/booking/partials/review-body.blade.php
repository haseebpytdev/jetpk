    @php
        use App\Support\Travel\TravelDocumentFormatter;
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
        $selectedFareFamilyLabel = trim((string) ($selectedFareFamilyCheckout['name'] ?? ''));
        if ($selectedFareFamilyLabel === '') {
            $selectedFareFamilyLabel = trim((string) ($o['fare_family'] ?? ''));
        }
        $cabinLabel = ucfirst(str_replace('_', ' ', (string) ($checkoutFareRules['cabin_display'] ?? $o['cabin'] ?? $cr['cabin'] ?? 'economy')));
        $allPassengers = $booking->passengers->sortBy('passenger_index')->values();
        $paxSummary = $leadPassenger ?? null;
        $passengerCounts = [
            'adults' => $allPassengers->where('passenger_type', 'adult')->count(),
            'children' => $allPassengers->where('passenger_type', 'child')->count(),
            'infants' => $allPassengers->where('passenger_type', 'infant')->count(),
            'total' => $allPassengers->count(),
        ];
        $fare = $booking->fareBreakdown;
        $protectionMode = (string) ($meta['protection_mode'] ?? '');
        $holdRef = (string) ($meta['supplier_hold_reference'] ?? '');
        $lockExpiresAt = (string) ($meta['checkout_lock_expires_at'] ?? '');
        $totalFromDb = (float) ($fare?->total ?? 0);
        $baseFromDb = (float) ($fare?->base_fare ?? 0);
        $taxesFromDb = (float) ($fare?->taxes ?? 0);
        $markupFromDb = (float) ($fare?->markup ?? 0);
        $feesFromDb = (float) ($fare?->fees ?? 0);
        $displayBaseFare = (float) (data_get($o, 'fare_breakdown.display_base_fare') ?? $o['base_fare'] ?? 0);
        $displayTaxes = (float) (data_get($o, 'fare_breakdown.display_taxes') ?? $o['taxes'] ?? 0);
        $agencyCharges = ($markupFromDb > 0 ? $markupFromDb : (float) ($o['markup'] ?? 0))
            + ($feesFromDb > 0 ? $feesFromDb : (float) ($o['service_fee'] ?? 0));
        $totalPayable = $totalFromDb > 0 ? $totalFromDb : (float) ($o['total'] ?? $o['final_customer_price'] ?? 0);
        $supplierPassengerPricing = is_array(data_get($o, 'fare_breakdown.passenger_pricing'))
            ? data_get($o, 'fare_breakdown.passenger_pricing')
            : [];
        $passengerPricingAvailable = (bool) (data_get($o, 'fare_breakdown.passenger_pricing_available') ?? (! empty($supplierPassengerPricing)));
        $holdUntil = (string) ($meta['payment_required_by'] ?? $meta['price_guarantee_expires_at'] ?? $meta['offer_expires_at'] ?? '');
        $fareRecheckedAt = (string) ($meta['fare_rechecked_at'] ?? '');
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
        $sabreCheckoutPendingApiInfo = (bool) ($sabreCheckoutPendingApiInfo ?? false);
        $sabreTripOrdersDryRunReview = (bool) ($sabreTripOrdersDryRunReview ?? false);
        $sabreTripOrdersFareBasisWarning = (bool) ($sabreTripOrdersFareBasisWarning ?? false);
        $groupedPassengerPricing = [
            'adult' => ['count' => 0, 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0],
            'child' => ['count' => 0, 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0],
            'infant' => ['count' => 0, 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0],
        ];
        foreach ($supplierPassengerPricing as $pp) {
            $type = strtolower((string) ($pp['passenger_type'] ?? 'adult'));
            if ($type === 'children') {
                $type = 'child';
            } elseif ($type === 'adults') {
                $type = 'adult';
            } elseif ($type === 'infants') {
                $type = 'infant';
            }
            if (! isset($groupedPassengerPricing[$type])) {
                $type = 'adult';
            }
            $groupedPassengerPricing[$type]['count'] += max(1, (int) ($pp['passenger_count'] ?? 1));
            $groupedPassengerPricing[$type]['base'] += (float) ($pp['base_amount'] ?? 0);
            $groupedPassengerPricing[$type]['tax'] += (float) ($pp['tax_amount'] ?? 0);
            $groupedPassengerPricing[$type]['total'] += (float) ($pp['total_amount'] ?? 0);
        }
    @endphp
    <div class="ota-rev-wrap ota-checkout-page ota-review-page">
        <div class="ota-container">
            <div class="ota-checkout-page-head ota-checkout-page-head--flush">
                <h1 class="ota-checkout-page-title">{{ $checkoutPageHeading ?? 'Review your booking' }}</h1>
                <p class="ota-checkout-page-lead">Please confirm your flight and traveller details before submitting your booking request.</p>
            </div>
            <x-bookings.fare-session-countdown
                :session-key="'review:'.($booking->booking_reference ?? $booking->id ?? '')"
                :expires-at-iso="($fareSessionExpiresAt ?? $lockExpiresAt) !== '' ? ($fareSessionExpiresAt ?? $lockExpiresAt) : null"
                :refresh-search-url="$refreshSearchUrl ?? url('/')"
                variant="desktop"
            />
            @if (session('offer_refresh_accepted'))
                <div class="alert alert-success" role="status">
                    {{ session('status') ?: __('Updated fare accepted. Continue booking.') }}
                </div>
            @endif
            @if ($complexItineraryNotice)
                <div class="alert alert-info" role="status">
                    {{ __('Your booking request will require staff confirmation before airline hold/PNR.') }}
                </div>
            @endif
            <x-bookings.iati-reservation-status :booking="$booking" variant="customer" />
            @if ($timelineSnapshotInvalid)
                <div class="alert alert-danger">
                    {{ __('Selected itinerary timing could not be verified. Please choose another fare.') }}
                </div>
            @endif
            @if ($sabreTripOrdersFareBasisWarning)
                <div class="alert alert-warning" role="status">
                    {{ __('Some fare details could not be verified from your selected flight. Our team will confirm the correct fare before proceeding — you can still submit your booking request.') }}
                </div>
            @endif
            @if (!empty($meta['validation_warnings']) && is_array($meta['validation_warnings']))
                <div class="alert alert-warning">
                    @foreach ($meta['validation_warnings'] as $warning)
                        <div>{{ $warning }}</div>
                    @endforeach
                </div>
            @endif
            @if (($passengerCounts['total'] ?? 0) >= 9 && isset($meta['supplier_hold_success']) && ! $meta['supplier_hold_success'])
                <div class="alert alert-warning">
                    We could not temporarily hold this fare with the airline. You can still submit a booking request, and our team will confirm availability manually.
                </div>
            @endif
            @if ($recheckRequired ?? false)
                <div class="alert alert-warning" role="alert">
                    {{ __('Your fare needs to be checked again before you can continue.') }}
                    <div class="mt-2">
                        <a class="btn btn-outline-primary btn-sm" href="{{ client_route('booking.passengers', [
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

            <div class="ota-checkout-grid ota-booking-layout">
                <div class="ota-checkout-main">
                    <div class="ota-checkout-card ota-review-flight-card">
                        <h2 class="ota-checkout-section-title">Flight summary</h2>
                        <div class="ota-review-flight">
                            <div class="ota-review-flight__header">
                                <div class="ota-review-flight__brand">
                                    @if(!empty($airlineLogo))
                                        <div class="ota-airline-logo ota-airline-logo--img"><img src="{{ $airlineLogo }}" alt="{{ $o['airline_name'] ?? 'Airline' }} logo"></div>
                                    @else
                                        <div class="ota-airline-logo">{{ $o['airline_code'] ?? 'XX' }}</div>
                                    @endif
                                    <div>
                                        <div class="ota-airline-name">{{ $o['airline_name'] ?? '' }}</div>
                                        <div class="ota-flight-no">{{ $o['carrier_code'] ?? '' }}{{ $o['flight_number'] ?? '' }}</div>
                                    </div>
                                </div>
                                <div class="ota-review-flight__route-block">
                                    <span class="ota-review-flight__trip-type">{{ $tripTypeLabel }}</span>
                                    <p class="ota-review-flight__route">{{ $routeLabel }}</p>
                                    <p class="ota-review-flight__date">{{ $reviewPresentation['departure_date_display'] ?? \Illuminate\Support\Carbon::parse($o['depart_at'] ?? '')->format('D, M j, Y') }}</p>
                                </div>
                            </div>

                            @if ($checkoutJourneys !== [])
                                <div class="ota-review-flight__legs">
                                    @foreach ($checkoutJourneys as $journey)
                                        @if (is_array($journey))
                                            <section class="ota-review-flight__leg">
                                                <h3 class="ota-review-flight__leg-label">{{ $journey['label'] ?? '' }}</h3>
                                                <div class="ota-review-flight__leg-grid">
                                                    <div class="ota-review-flight__leg-point">
                                                        <span class="ota-review-flight__leg-time">{{ $journey['departure_time_display'] ?? '' }}</span>
                                                        @if (!empty($journey['departure_date_display']))
                                                            <span class="ota-review-flight__leg-date">{{ $journey['departure_date_display'] }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="ota-review-flight__leg-mid">
                                                        @if (!empty($journey['duration_display']))
                                                            <span class="ota-review-flight__leg-duration">{{ $journey['duration_display'] }}</span>
                                                        @endif
                                                        <span class="ota-review-flight__leg-stops">{{ $journey['stops_display'] ?? '' }}</span>
                                                    </div>
                                                    <div class="ota-review-flight__leg-point ota-review-flight__leg-point--end">
                                                        <span class="ota-review-flight__leg-time">{{ $journey['arrival_time_display'] ?? '' }}</span>
                                                        @if (!empty($journey['arrival_date_display']))
                                                            <span class="ota-review-flight__leg-date">{{ $journey['arrival_date_display'] }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <x-bookings.checkout-journey-layovers :journey="$journey" />
                                            </section>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <section class="ota-review-flight__leg">
                                    <h3 class="ota-review-flight__leg-label">{{ $tripTypeLabel }}</h3>
                                    <div class="ota-review-flight__leg-grid">
                                        <div class="ota-review-flight__leg-point">
                                            <span class="ota-review-flight__leg-time">{{ $reviewPresentation['departure_time_display'] ?? \Illuminate\Support\Carbon::parse($o['depart_at'] ?? '')->format('H:i') }}</span>
                                            <span class="ota-review-flight__leg-airport">{{ $cr['origin'] ?? '' }}</span>
                                            @if (!empty($reviewPresentation['departure_date_display']))
                                                <span class="ota-review-flight__leg-date">{{ $reviewPresentation['departure_date_display'] }}</span>
                                            @endif
                                        </div>
                                        <div class="ota-review-flight__leg-mid">
                                            <span class="ota-review-flight__leg-duration">{{ $reviewPresentation['itinerary_duration_display'] ?? (($o['duration_h'] ?? 0).'h '.str_pad((string) ($o['duration_m'] ?? 0), 2, '0', STR_PAD_LEFT).'m') }}</span>
                                            <span class="ota-review-flight__leg-stops">{{ $reviewPresentation['stops_display'] ?? (($o['stops'] ?? 0) === 0 ? 'Direct' : (($o['stops'] ?? 0).' stop'.(($o['stops'] ?? 0) === 1 ? '' : 's'))) }}</span>
                                        </div>
                                        <div class="ota-review-flight__leg-point ota-review-flight__leg-point--end">
                                            <span class="ota-review-flight__leg-time">{{ $reviewPresentation['arrival_time_display'] ?? \Illuminate\Support\Carbon::parse($o['arrive_at'] ?? '')->format('H:i') }}</span>
                                            <span class="ota-review-flight__leg-airport">{{ $cr['destination'] ?? '' }}</span>
                                            @if (!empty($reviewPresentation['arrival_date_display']))
                                                <span class="ota-review-flight__leg-date">{{ $reviewPresentation['arrival_date_display'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <x-bookings.checkout-journey-layovers :journey="[
                                        'origin' => $reviewPresentation['origin'] ?? ($cr['origin'] ?? ''),
                                        'destination' => $reviewPresentation['destination'] ?? ($cr['destination'] ?? ''),
                                        'stops_display' => $reviewPresentation['stops_display'] ?? '',
                                        'stops_count' => $reviewPresentation['stops_count'] ?? null,
                                        'duration_display' => $reviewPresentation['itinerary_duration_display'] ?? '',
                                        'segments_display' => $reviewPresentation['segments_display'] ?? [],
                                        'layovers_display' => $reviewPresentation['layovers_display'] ?? [],
                                        'layover_summary' => $reviewPresentation['layover_summary'] ?? [],
                                        'connection_details_unavailable' => $reviewPresentation['connection_details_unavailable'] ?? false,
                                    ]" />
                                </section>
                            @endif

                            <ul class="ota-review-flight__tags">
                                <li><i class="fa fa-suitcase" aria-hidden="true"></i> {{ $checkoutFareRules['baggage_display'] ?? ($o['baggage'] ?? 'Baggage per fare rules') }}</li>
                                <li>{{ $cabinLabel }}</li>
                                @if ($selectedFareFamilyLabel !== '' && ! $selectedFareFamilyCheckout)
                                    <li><i class="fa fa-ticket" aria-hidden="true"></i> {{ $selectedFareFamilyLabel }}</li>
                                @endif
                                @if (!empty($o['refundable']))
                                    <li class="ota-review-flight__tag--ok">Refundable</li>
                                @else
                                    <li class="ota-review-flight__tag--warn">Non-refundable</li>
                                @endif
                            </ul>
                            @if ($selectedFareFamilyCheckout)
                                <x-bookings.selected-fare-family-block :checkout="$selectedFareFamilyCheckout" class="mt-3" />
                            @endif
                        </div>
                    </div>

                    <div class="ota-checkout-card ota-review-pax-card">
                        <h2 class="ota-checkout-section-title">Passenger &amp; contact</h2>
                        <p class="ota-review-pax-summary">
                            {{ $passengerCounts['total'] }} {{ $passengerCounts['total'] === 1 ? 'passenger' : 'passengers' }}
                            ({{ $passengerCounts['adults'] }} {{ $passengerCounts['adults'] === 1 ? 'adult' : 'adults' }},
                            {{ $passengerCounts['children'] }} {{ $passengerCounts['children'] === 1 ? 'child' : 'children' }},
                            {{ $passengerCounts['infants'] }} {{ $passengerCounts['infants'] === 1 ? 'infant' : 'infants' }})
                        </p>
                        <div class="ota-review-pax-grid">
                            @foreach ($allPassengers as $idx => $passenger)
                                <article class="ota-review-pax-item">
                                    <header class="ota-review-pax-item__head">
                                        <span class="ota-review-pax-item__index">Passenger {{ $idx + 1 }}</span>
                                        <span class="ota-review-pax-item__type">{{ ucfirst((string) $passenger->passenger_type) }}</span>
                                        @if($passenger->is_lead_passenger)
                                            <span class="ota-review-pax-item__badge">Lead</span>
                                        @endif
                                    </header>
                                    <dl class="ota-review-pax-dl">
                                        <div class="ota-review-pax-dl__row">
                                            <dt>Passenger</dt>
                                            <dd>{{ trim(($passenger->title ?? '').' '.($passenger->first_name ?? '').' '.($passenger->last_name ?? '')) }}</dd>
                                        </div>
                                        @if ($passenger->date_of_birth)
                                            <div class="ota-review-pax-dl__row">
                                                <dt>Date of birth</dt>
                                                <dd>{{ $passenger->date_of_birth->format('j M Y') }}</dd>
                                            </div>
                                        @endif
                                        @if ($passenger->nationality)
                                            <div class="ota-review-pax-dl__row">
                                                <dt>Nationality</dt>
                                                <dd>{{ strtoupper($passenger->nationality) }}</dd>
                                            </div>
                                        @endif
                                        @if ($passenger->passport_number || $passenger->national_id_number)
                                            <div class="ota-review-pax-dl__row">
                                                <dt>Passport</dt>
                                                <dd>
                                                    @if ($passenger->document_type === 'national_id' && $passenger->national_id_number)
                                                        {{ TravelDocumentFormatter::maskPassport($passenger->national_id_number) }} (National ID)
                                                    @elseif ($passenger->passport_number)
                                                        {{ TravelDocumentFormatter::maskPassport($passenger->passport_number) }}
                                                        @if ($passenger->passport_issuing_country)
                                                            · {{ strtoupper($passenger->passport_issuing_country) }}
                                                        @endif
                                                        @if ($passenger->passport_expiry_date)
                                                            · expires {{ $passenger->passport_expiry_date->format('j M Y') }}
                                                        @endif
                                                    @endif
                                                </dd>
                                            </div>
                                        @endif
                                    </dl>
                                </article>
                            @endforeach

                            <section class="ota-review-contact">
                                <h3 class="ota-review-contact__title">Contact details</h3>
                                <dl class="ota-review-pax-dl">
                                    <div class="ota-review-pax-dl__row">
                                        <dt>Contact email</dt>
                                        <dd>{{ $d['email'] ?? '—' }}</dd>
                                    </div>
                                    <div class="ota-review-pax-dl__row">
                                        <dt>Contact phone</dt>
                                        <dd>{{ $d['phone'] ?? '—' }}</dd>
                                    </div>
                                    <div class="ota-review-pax-dl__row">
                                        <dt>Country</dt>
                                        <dd>{{ !empty($d['country']) ? $d['country'] : '—' }}</dd>
                                    </div>
                                </dl>
                            </section>
                        </div>
                    </div>
                </div>

                <aside class="ota-checkout-aside ota-review-aside-stack" aria-label="Fare and confirmation">
                    <div class="ota-checkout-card ota-checkout-card--accent ota-review-fare-card">
                        <h2 class="ota-checkout-section-title">Fare breakdown</h2>
                        <x-bookings.checkout-fare-breakdown
                            :breakdown="$checkoutFareBreakdown ?? []"
                            :use-selected-fare-estimate="$useSelectedFareEstimate"
                            :selected-fare-estimate="$selectedFareEstimate"
                            :passenger-count-summary="$passengerCounts"
                        />
                        @if ($useSelectedFareEstimate)
                            <p class="ota-review-fare-note">Final payable will be confirmed before ticketing or payment.</p>
                        @elseif (! $useSelectedFareEstimate)
                            <p class="ota-review-fare-note">Final fare is shown in PKR and may be rechecked before confirmation.</p>
                        @endif
                    </div>

                    <form method="post" action="{{ client_url('/booking/review') }}" class="ota-checkout-form" id="ota-review-submit-form"
                        onsubmit='(function(f){if(f.hasAttribute("data-ota-submitting"))return false;var b=f.querySelector("button[type=submit]");if(!b||b.disabled)return false;f.setAttribute("data-ota-submitting","1");b.setAttribute("data-ota-submitting","1");b.disabled=true;b.setAttribute("aria-busy","true");b.textContent=@json(__('Please wait…'));})(this);'>
                        @csrf
                        @error('booking')
                            <div class="alert alert-danger mb-3" role="alert">{{ $message }}</div>
                            @if ($selectedFareFamilyCheckout)
                                <p class="small text-muted mb-3">This selected fare will be reconfirmed by airline validation before booking is finalized.</p>
                            @endif
                        @enderror
                        <div class="ota-checkout-card ota-review-method-card">
                            <h2 class="ota-checkout-section-title">{{ current_client_slug() === 'jetpk' ? 'Payment option' : 'Confirmation method' }}</h2>
                            <p class="ota-checkout-section-hint">{{ current_client_slug() === 'jetpk' ? 'Choose how you would like to complete payment for this booking.' : 'Choose how you want us to process this booking.' }}</p>

                            @include('frontend.booking.partials.checkout-payment-methods')
                        </div>

                        <div class="ota-review-total-hero" aria-live="polite">
                            @if ($useSelectedFareEstimate)
                                <span class="ota-review-total-hero__label">{{ $selectedFareEstimate['label'] ?? 'Estimated selected fare' }}</span>
                                <span class="ota-review-total-hero__value">
                                    @if (!empty($selectedFareEstimate['price_is_approximate']))
                                        <span class="ota-checkout-selected-fare-family__approx">Approx.</span>
                                    @endif
                                    {{ preg_replace('/^Approx\.\s*/i', '', (string) ($selectedFareEstimate['price_display'] ?? '')) }}
                                </span>
                            @else
                                <span class="ota-review-total-hero__label">Amount due (PKR)</span>
                                <span class="ota-review-total-hero__value">Rs {{ number_format($totalFromDb > 0 ? $totalFromDb : (float) ($o['total'] ?? 0), 0) }}</span>
                            @endif
                        </div>
                        @if ($sabreCheckoutSubmitDisabled)
                            <div class="alert alert-warning">
                                {{ __('Online airline confirmation is not available yet.') }} {{ __('You can review your details and submit a booking request for staff follow-up.') }}
                            </div>
                        @elseif ($sabreTripOrdersDryRunReview)
                            <div class="alert alert-info">
                                {{ __('Your booking request is being prepared. Airline confirmation will follow staff review.') }}
                            </div>
                        @elseif ($sabreCheckoutDryRunInfo)
                            <div class="alert alert-info">
                                {{ __('Submitting sends your booking request to our team for review. No airline confirmation is created until staff verify availability and fare.') }}
                            </div>
                        @endif
                        @if ($offerRefreshPending)
                            <div class="alert alert-warning" role="alert">
                                {{ __('An airline fare update must be accepted before you can continue.') }}
                            </div>
                        @endif
                        <button type="submit" class="ota-btn-primary-lg btn btn-lg btn-block ota-review-submit-btn" @if($sabreCheckoutSubmitDisabled || $offerRefreshPending) disabled aria-disabled="true" @endif>
                            @if ($offerRefreshPending)
                                {{ __('Accept updated fare to continue') }}
                            @elseif ($sabreCheckoutSubmitDisabled)
                                {{ __('Confirm Booking (unavailable)') }}
                            @else
                                {{ __('Confirm Booking') }}
                            @endif
                        </button>
                        @if (! $sabreCheckoutSubmitDisabled && ! $offerRefreshPending)
                            <p class="ota-checkout-disclaimer ota-review-submit-note">No payment is taken on this step.</p>
                        @endif
                    </form>
                </aside>
            </div>
        </div>
    </div>

    @if ($showOfferRefreshModal && $offerRefreshDisplay)
        <div id="ota-offer-refresh-modal" class="ota-fare-breakdown-modal" role="dialog" aria-modal="true" aria-labelledby="ota-offer-refresh-title">
            <div class="ota-fare-breakdown-modal__backdrop" tabindex="-1"></div>
            <div class="ota-fare-breakdown-modal__panel" role="document">
                <h4 id="ota-offer-refresh-title" class="ota-fare-breakdown-modal__title">{{ \App\Support\Bookings\SabreOfferRefreshAcceptance::CUSTOMER_MODAL_TITLE }}</h4>
                <p class="mb-3">{{ \App\Support\Bookings\SabreOfferRefreshAcceptance::CUSTOMER_MODAL_MESSAGE }}</p>
                <p class="small text-muted mb-2">{{ $routeLabel }} · {{ $reviewPresentation['departure_date_display'] ?? '' }}</p>
                @if (!empty($offerRefreshDisplay['brand_label']))
                    <p class="small mb-2">{{ __('Affected fare family') }}: <strong>{{ $offerRefreshDisplay['brand_label'] }}</strong></p>
                @endif
                <dl class="ota-fare-breakdown-modal__rows mb-3">
                    <dt>{{ __('Old fare') }}</dt>
                    <dd>Rs {{ number_format($offerRefreshDisplay['old_total'], 0) }}</dd>
                    <dt>{{ __('New fare') }}</dt>
                    <dd>Rs {{ number_format($offerRefreshDisplay['new_total'], 0) }}</dd>
                    <dt>{{ __('Difference') }}</dt>
                    <dd class="ota-fare-breakdown-modal__total">Rs {{ number_format(abs($offerRefreshDisplay['delta']), 0) }}{{ $offerRefreshDisplay['delta'] >= 0 ? ' +' : ' −' }}</dd>
                </dl>
                <div class="ota-fare-breakdown-modal__actions d-flex flex-column gap-2">
                    <form method="post" action="{{ client_route('booking.accept-updated-fare', ['booking' => $booking]) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-block w-100">{{ __('Accept new fare') }}</button>
                    </form>
                    <form method="post" action="{{ client_route('booking.decline-updated-fare', ['booking' => $booking]) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-block w-100">{{ __('Back to flight search') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <style>
            .ota-fare-breakdown-modal { position: fixed; inset: 0; z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 16px; }
            .ota-fare-breakdown-modal__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); }
            .ota-fare-breakdown-modal__panel { position: relative; z-index: 1; width: 100%; max-width: 440px; background: #fff; border-radius: 12px; padding: 20px 22px; box-shadow: 0 18px 48px rgba(15, 23, 42, 0.18); }
            .ota-fare-breakdown-modal__title { margin: 0 0 14px; font-size: 18px; font-weight: 700; }
            .ota-fare-breakdown-modal__rows { margin: 0; }
            .ota-fare-breakdown-modal__rows dt { font-weight: 600; margin: 0 0 2px; }
            .ota-fare-breakdown-modal__rows dd { margin: 0 0 10px; font-size: 16px; }
            .ota-fare-breakdown-modal__rows dd.ota-fare-breakdown-modal__total { font-size: 18px; font-weight: 700; }
            body.ota-offer-refresh-modal-open { overflow: hidden; }
        </style>
        <script>document.body.classList.add('ota-offer-refresh-modal-open');</script>
    @endif
