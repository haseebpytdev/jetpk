@extends('layouts.mobile-app')

@section('title', 'Review booking')

@section('content')
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
        $useSelectedFareEstimate = is_array($selectedFareEstimate) && ! empty($selectedFareEstimate['has_checkout_estimate']);
        $allPassengers = $booking->passengers->sortBy('passenger_index')->values();
        $passengerCounts = [
            'adults' => $allPassengers->where('passenger_type', 'adult')->count(),
            'children' => $allPassengers->where('passenger_type', 'child')->count(),
            'infants' => $allPassengers->where('passenger_type', 'infant')->count(),
            'total' => $allPassengers->count(),
        ];
        $fare = $booking->fareBreakdown;
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
        $requiresPriceConfirm = (bool) ($meta['requires_price_change_confirmation'] ?? false);
        $priceChangeOld = (float) ($meta['price_change_old_total'] ?? 0);
        $priceChangeNew = (float) ($meta['price_change_new_total'] ?? 0);
        $reviewPresentation = is_array($reviewPresentation ?? null) ? $reviewPresentation : [];
        $routeLabel = \App\Support\FlightSearch\FlightOfferDisplayPresenter::formatCriteriaRouteLabel($cr);
        if ($routeLabel === '') {
            $routeLabel = ($cr['origin'] ?? '').' → '.($cr['destination'] ?? '');
        }
        $offerRefreshPending = (bool) ($offerRefreshPending ?? false);
        $offerRefreshDisplay = is_array($offerRefreshDisplay ?? null) ? $offerRefreshDisplay : null;
        $showOfferRefreshModal = $offerRefreshPending && $offerRefreshDisplay !== null;
        $sabreCheckoutSubmitDisabled = (bool) ($sabreCheckoutSubmitDisabled ?? false);
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
        $passengersBackUrl = route('booking.passengers', [
            'flight_id' => (string) ($meta['original_offer_id'] ?? ($o['id'] ?? '')),
            'offer_id' => (string) ($meta['original_offer_id'] ?? ($o['id'] ?? '')),
            'search_id' => (string) ($meta['checkout_search_id'] ?? ''),
            'from' => (string) data_get($meta, 'search_criteria.origin', $cr['origin'] ?? ''),
            'to' => (string) data_get($meta, 'search_criteria.destination', $cr['destination'] ?? ''),
            'depart' => (string) data_get($meta, 'search_criteria.depart_date', $cr['depart_date'] ?? ''),
            'trip_type' => (string) data_get($meta, 'search_criteria.trip_type', $cr['trip_type'] ?? 'one_way'),
            'return_date' => (string) data_get($meta, 'search_criteria.return_date', $cr['return_date'] ?? ''),
            'cabin' => (string) data_get($meta, 'search_criteria.cabin', $cr['cabin'] ?? 'economy'),
            'adults' => (int) data_get($meta, 'search_criteria.adults', $cr['adults'] ?? 1),
            'children' => (int) data_get($meta, 'search_criteria.children', $cr['children'] ?? 0),
            'infants' => (int) data_get($meta, 'search_criteria.infants', $cr['infants'] ?? 0),
        ]);
    @endphp

    <div class="ota-mobile-booking" data-testid="ota-mobile-review" data-mobile-booking-review>
        <header class="ota-mobile-booking__header">
            <div class="ota-mobile-booking__header-row">
                <a href="{{ $passengersBackUrl }}" class="ota-mobile-booking__back" aria-label="Back to traveller details">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                </a>
                <div class="ota-mobile-booking__header-copy">
                    <h1 class="ota-mobile-booking__title">Review booking</h1>
                    @if ($routeLabel !== '')
                        <p class="ota-mobile-booking__subtitle">{{ $routeLabel }}</p>
                    @endif
                </div>
            </div>
        </header>

        <x-bookings.fare-session-countdown
            :session-key="'review:'.($booking->booking_reference ?? $booking->id ?? '')"
            :expires-at-iso="($fareSessionExpiresAt ?? '') !== '' ? $fareSessionExpiresAt : null"
            :refresh-search-url="$refreshSearchUrl ?? url('/')"
            variant="mobile"
        />

        @if (session('offer_refresh_accepted'))
            <div class="ota-mobile-booking__alert ota-mobile-booking__alert--success">{{ session('status') ?: __('Updated fare accepted. Continue booking.') }}</div>
        @endif
        @if ($complexItineraryNotice ?? false)
            <div class="ota-mobile-booking__alert ota-mobile-booking__alert--info">{{ __('Your booking request will require staff confirmation before airline hold/PNR.') }}</div>
        @endif
        @if ($timelineSnapshotInvalid ?? false)
            <div class="ota-mobile-booking__alert ota-mobile-booking__alert--danger">{{ __('Selected itinerary timing could not be verified. Please choose another fare.') }}</div>
        @endif
        @if ($recheckRequired ?? false)
            <div class="ota-mobile-booking__alert ota-mobile-booking__alert--warning">
                {{ __('Your fare needs to be checked again before you can continue.') }}
                <a href="{{ $passengersBackUrl }}" class="ota-mobile-booking__inline-link">{{ __('Recheck fare') }}</a>
            </div>
        @endif

        @include('mobile.bookings.partials.selected-flight-card', [
            'offer' => $o,
            'criteria' => $cr,
            'reviewPresentation' => $reviewPresentation,
            'airlineLogo' => $airlineLogo,
            'selectedFareFamilyOption' => $selectedFareFamilyOption,
            'selectedFareEstimate' => $selectedFareEstimate,
            'useSelectedFareEstimate' => $useSelectedFareEstimate,
            'totalPayable' => $totalPayable,
        ])

        <article class="ota-mobile-booking__card">
            <h2 class="ota-mobile-booking__card-title">Travellers &amp; contact</h2>
            <p class="ota-mobile-booking__muted">
                {{ $passengerCounts['total'] }} {{ $passengerCounts['total'] === 1 ? 'passenger' : 'passengers' }}
            </p>
            @foreach ($allPassengers as $idx => $passenger)
                <div class="ota-mobile-booking__review-pax">
                    <div class="ota-mobile-booking__review-pax-head">
                        <strong>Passenger {{ $idx + 1 }}</strong>
                        <span class="ota-mobile-booking__pax-badge">{{ ucfirst((string) $passenger->passenger_type) }}</span>
                    </div>
                    <dl class="ota-mobile-booking__review-dl">
                        <div><dt>Name</dt><dd>{{ trim(($passenger->title ?? '').' '.($passenger->first_name ?? '').' '.($passenger->last_name ?? '')) }}</dd></div>
                        @if ($passenger->date_of_birth)
                            <div><dt>Date of birth</dt><dd>{{ $passenger->date_of_birth->format('j M Y') }}</dd></div>
                        @endif
                        @if ($passenger->gender)
                            <div><dt>Gender</dt><dd>{{ $passenger->gender }}</dd></div>
                        @endif
                        @if ($passenger->nationality)
                            <div><dt>Nationality</dt><dd>{{ strtoupper($passenger->nationality) }}</dd></div>
                        @endif
                        @if ($passenger->passport_number || $passenger->national_id_number)
                            <div><dt>Document</dt><dd>
                                @if ($passenger->document_type === 'national_id' && $passenger->national_id_number)
                                    {{ TravelDocumentFormatter::maskPassport($passenger->national_id_number) }} (National ID)
                                @elseif ($passenger->passport_number)
                                    {{ TravelDocumentFormatter::maskPassport($passenger->passport_number) }}
                                @endif
                            </dd></div>
                        @endif
                    </dl>
                </div>
            @endforeach
            <div class="ota-mobile-booking__review-contact">
                <h3 class="ota-mobile-booking__sub-title">Contact details</h3>
                <dl class="ota-mobile-booking__review-dl">
                    <div><dt>Email</dt><dd>{{ $d['email'] ?? '—' }}</dd></div>
                    <div><dt>Mobile</dt><dd>{{ $d['phone'] ?? '—' }}</dd></div>
                    @if (! empty($d['country']))
                        <div><dt>Country</dt><dd>{{ $d['country'] }}</dd></div>
                    @endif
                </dl>
            </div>
        </article>

        @if ($o && ! empty($o['baggage']) && ! $selectedFareFamilyCheckout)
            <article class="ota-mobile-booking__card">
                <h2 class="ota-mobile-booking__card-title">Baggage summary</h2>
                <ul class="ota-mobile-booking__baggage-list">
                    <li>{{ $o['baggage'] }}</li>
                </ul>
            </article>
        @endif

        <x-bookings.checkout-fare-breakdown
            variant="mobile"
            :breakdown="$checkoutFareBreakdown ?? []"
            :use-selected-fare-estimate="$useSelectedFareEstimate"
            :selected-fare-estimate="$selectedFareEstimate"
            :passenger-count-summary="$passengerCounts"
        />

        <form method="post" action="{{ route('booking.review') }}" class="ota-mobile-booking__form" id="ota-mobile-review-submit-form"
            onsubmit='(function(f){if(f.hasAttribute("data-ota-submitting"))return false;var b=f.querySelector("button[type=submit]");if(!b||b.disabled)return false;f.setAttribute("data-ota-submitting","1");b.disabled=true;b.setAttribute("aria-busy","true");b.textContent=@json(__('Please wait…'));})(this);'>
            @csrf
            @error('booking')<div class="ota-mobile-booking__alert ota-mobile-booking__alert--danger">{{ $message }}</div>@enderror

            <article class="ota-mobile-booking__card">
                <h2 class="ota-mobile-booking__card-title">Confirmation method</h2>
                <label class="ota-mobile-booking__method">
                    <input type="radio" name="booking_method" value="pay_later" checked>
                    <span>
                        <strong>Booking request — pay after confirmation</strong>
                        <small>Submit your request; payment instructions follow once confirmed.</small>
                    </span>
                </label>
                <label class="ota-mobile-booking__method">
                    <input type="radio" name="booking_method" value="bank_transfer">
                    <span>
                        <strong>Bank transfer</strong>
                        <small>Pay via bank transfer using instructions from your consultant.</small>
                    </span>
                </label>
                <label class="ota-mobile-booking__method">
                    <input type="radio" name="booking_method" value="office">
                    <span>
                        <strong>Confirm with travel consultant</strong>
                        <small>Complete ticketing with your travel consultant in-office or by phone.</small>
                    </span>
                </label>
            </article>

            @if ($sabreCheckoutSubmitDisabled)
                <div class="ota-mobile-booking__alert ota-mobile-booking__alert--warning">
                    {{ __('Online airline confirmation is not available yet.') }} {{ __('You can review your details and submit a booking request for staff follow-up.') }}
                </div>
            @elseif ($sabreTripOrdersDryRunReview ?? false)
                <div class="ota-mobile-booking__alert ota-mobile-booking__alert--info">{{ __('Your booking request is being prepared. Airline confirmation will follow staff review.') }}</div>
            @elseif ($sabreCheckoutDryRunInfo ?? false)
                <div class="ota-mobile-booking__alert ota-mobile-booking__alert--info">{{ __('Submitting sends your booking request to our team for review. No airline confirmation is created until staff verify availability and fare.') }}</div>
            @endif
            @if ($offerRefreshPending)
                <div class="ota-mobile-booking__alert ota-mobile-booking__alert--warning">{{ __('An airline fare update must be accepted before you can continue.') }}</div>
            @endif
            @if ($requiresPriceConfirm && $priceChangeOld > 0 && $priceChangeNew > 0 && ! $offerRefreshPending)
                <div class="ota-mobile-booking__alert ota-mobile-booking__alert--warning">
                    <strong>{{ __('The fare has changed.') }}</strong>
                    <p>Old: Rs {{ number_format($priceChangeOld, 0) }} · New: Rs {{ number_format($priceChangeNew, 0) }}</p>
                    <label class="ota-mobile-booking__checkbox">
                        <input type="checkbox" name="confirm_updated_fare" id="mobile-confirm-updated-fare" value="1">
                        <span>{{ __('Continue with new fare — I accept the updated total of Rs :amount.', ['amount' => number_format($priceChangeNew, 0)]) }}</span>
                    </label>
                    @error('confirm_updated_fare')<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="ota-mobile-booking__sticky-cta">
                @if ($useSelectedFareEstimate)
                    <p class="ota-mobile-booking__total-label">{{ $selectedFareEstimate['label'] ?? 'Estimated selected fare' }}</p>
                    <p class="ota-mobile-booking__total-value">
                        @if (!empty($selectedFareEstimate['price_is_approximate']))
                            <span class="ota-mobile-booking__tag-note">Approx.</span>
                        @endif
                        {{ preg_replace('/^Approx\.\s*/i', '', (string) ($selectedFareEstimate['price_display'] ?? '')) }}
                    </p>
                @else
                    <p class="ota-mobile-booking__total-label">Amount due (PKR)</p>
                    <p class="ota-mobile-booking__total-value">Rs {{ number_format($totalPayable, 0) }}</p>
                @endif
                <button type="submit" class="ota-mobile-booking__cta" @if($sabreCheckoutSubmitDisabled) disabled aria-disabled="true" @endif>
                    @if ($offerRefreshPending)
                        {{ __('Accept updated fare to continue') }}
                    @elseif ($sabreCheckoutSubmitDisabled)
                        {{ __('Confirm Booking (unavailable)') }}
                    @else
                        {{ __('Confirm Booking') }}
                    @endif
                </button>
                @if (! $sabreCheckoutSubmitDisabled && ! $offerRefreshPending)
                    <p class="ota-mobile-booking__cta-note">No payment is taken on this step.</p>
                @endif
            </div>
        </form>
    </div>

    @if ($showOfferRefreshModal && $offerRefreshDisplay)
        <div id="ota-mobile-offer-refresh-modal" class="ota-mobile-booking__modal" role="dialog" aria-modal="true" aria-labelledby="ota-mobile-offer-refresh-title">
            <div class="ota-mobile-booking__modal-backdrop" tabindex="-1"></div>
            <div class="ota-mobile-booking__modal-panel">
                <h2 id="ota-mobile-offer-refresh-title" class="ota-mobile-booking__modal-title">{{ \App\Support\Bookings\SabreOfferRefreshAcceptance::CUSTOMER_MODAL_TITLE }}</h2>
                <p>{{ \App\Support\Bookings\SabreOfferRefreshAcceptance::CUSTOMER_MODAL_MESSAGE }}</p>
                <dl class="ota-mobile-booking__review-dl">
                    <div><dt>{{ __('Previous fare') }}</dt><dd>Rs {{ number_format($offerRefreshDisplay['old_total'], 0) }}</dd></div>
                    <div><dt>{{ __('Updated fare') }}</dt><dd>Rs {{ number_format($offerRefreshDisplay['new_total'], 0) }}</dd></div>
                    <div><dt>{{ __('Difference') }}</dt><dd>Rs {{ number_format(abs($offerRefreshDisplay['delta']), 0) }}{{ $offerRefreshDisplay['delta'] >= 0 ? ' +' : ' −' }}</dd></div>
                </dl>
                <form method="post" action="{{ route('booking.accept-updated-fare', $booking) }}">
                    @csrf
                    <button type="submit" class="ota-mobile-booking__cta">{{ __('Accept updated fare and continue') }}</button>
                </form>
                <form method="post" action="{{ route('booking.decline-updated-fare', $booking) }}">
                    @csrf
                    <button type="submit" class="ota-mobile-booking__cta ota-mobile-booking__cta--secondary">{{ __('Choose another flight') }}</button>
                </form>
            </div>
        </div>
    @endif
@endsection
