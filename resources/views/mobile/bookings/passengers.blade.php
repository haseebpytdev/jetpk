@extends('layouts.mobile-app')

@section('title', 'Traveller Details')

@section('content')
    @php
        $o = $offer;
        $cr = $criteria;
        $checkoutPresentation = is_array($checkoutPresentation ?? null) ? $checkoutPresentation : [];
        $routeLabel = \App\Support\FlightSearch\FlightOfferDisplayPresenter::formatCriteriaRouteLabel($cr);
        if ($routeLabel === '') {
            $routeLabel = ($cr['origin'] ?? '').' → '.($cr['destination'] ?? '');
        }
        $displayBaseFare = is_array($o) ? (float) (data_get($o, 'fare_breakdown.display_base_fare') ?? $o['base_fare'] ?? 0) : 0;
        $displayTaxes = is_array($o) ? (float) (data_get($o, 'fare_breakdown.display_taxes') ?? $o['taxes'] ?? 0) : 0;
        $agencyCharges = is_array($o) ? (float) (($o['markup'] ?? 0) + ($o['service_fee'] ?? 0)) : 0;
        $totalPayable = is_array($o) ? (float) ($o['total'] ?? $o['final_customer_price'] ?? 0) : 0;
        $selectedFareFamilyOption = is_array($draft['selected_fare_family_option'] ?? null)
            ? $draft['selected_fare_family_option']
            : null;
        $selectedFareFamilyCheckout = \App\Support\FlightSearch\FlightOfferDisplayPresenter::buildSelectedFareFamilyCheckoutView($selectedFareFamilyOption);
        $selectedFareEstimate = \App\Support\FlightSearch\FlightOfferDisplayPresenter::buildCheckoutSelectedFareEstimatePresentation($selectedFareFamilyOption);
        $useSelectedFareEstimate = is_array($selectedFareEstimate) && ! empty($selectedFareEstimate['has_checkout_estimate']);
        $supplierPassengerPricing = is_array($o) && is_array(data_get($o, 'fare_breakdown.passenger_pricing'))
            ? data_get($o, 'fare_breakdown.passenger_pricing')
            : [];
        $passengerPricingAvailable = is_array($o) && (bool) (data_get($o, 'fare_breakdown.passenger_pricing_available') ?? ! empty($supplierPassengerPricing));
        $groupedPassengerPricing = [
            'adult' => ['count' => 0, 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0],
            'child' => ['count' => 0, 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0],
            'infant' => ['count' => 0, 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0],
        ];
        foreach ($supplierPassengerPricing as $ppRow) {
            $type = strtolower((string) ($ppRow['passenger_type'] ?? 'adult'));
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
            $groupedPassengerPricing[$type]['count'] += max(1, (int) ($ppRow['passenger_count'] ?? 1));
            $groupedPassengerPricing[$type]['base'] += (float) ($ppRow['base_amount'] ?? 0);
            $groupedPassengerPricing[$type]['tax'] += (float) ($ppRow['tax_amount'] ?? 0);
            $groupedPassengerPricing[$type]['total'] += (float) ($ppRow['total_amount'] ?? 0);
        }
        $oldPassengers = old('passengers', []);
        $adultIndexes = collect($expectedPassengers)->filter(fn ($p) => $p['type'] === 'adult')->pluck('index')->values();
        $leadPassengerIndex = (int) old('lead_passenger_index', $adultIndexes->first() ?? 0);
        $adultCount = $passengerCountSummary['adults'] ?? 1;
        $checkoutCountries = is_array($checkoutCountries ?? null) ? $checkoutCountries : [];
        $checkoutCountryCodes = array_column($checkoutCountries, 'code');
        $checkoutPhoneDialCodes = is_array($checkoutPhoneDialCodes ?? null) ? $checkoutPhoneDialCodes : ['+92' => 'Pakistan (+92)'];
        $checkoutContactPhone = is_array($checkoutContactPhone ?? null) ? $checkoutContactPhone : ['code' => '+92', 'number' => ''];
        $checkoutContactPrefill = is_array($checkoutContactPrefill ?? null) ? $checkoutContactPrefill : [];
        $contactPhoneCode = old('phone_country_code', $checkoutContactPhone['code'] ?? $checkoutContactPrefill['phone_code'] ?? '+92');
        if (is_string($contactPhoneCode) && $contactPhoneCode !== '' && ! str_starts_with($contactPhoneCode, '+')) {
            $contactPhoneCode = '+'.$contactPhoneCode;
        }
        $contactPhoneNumber = old('phone_number', $checkoutContactPhone['number'] ?? $checkoutContactPrefill['phone_number'] ?? '');
        $contactCountryValue = (string) old('country', $draft['country'] ?? $checkoutContactPrefill['country'] ?? '');
        $contactCountryKnown = in_array($contactCountryValue, $checkoutCountryCodes, true);
        $facebookSocialEnabled = \App\Http\Controllers\Auth\SocialAuthController::providerIsConfigured('facebook');
        $checkoutReturnParams = [
            'flight_id' => (string) ($flightId ?? ''),
            'offer_id' => (string) (($draft['offer_id'] ?? '') !== '' ? $draft['offer_id'] : ($flightId ?? '')),
            'search_id' => (string) ($draft['search_id'] ?? ''),
            'from' => (string) ($draft['search_from'] ?? ($criteria['origin'] ?? '')),
            'to' => (string) ($draft['search_to'] ?? ($criteria['destination'] ?? '')),
            'depart' => (string) ($draft['search_depart'] ?? ($criteria['depart_date'] ?? '')),
            'trip_type' => (string) ($draft['trip_type'] ?? ($criteria['trip_type'] ?? 'one_way')),
            'return_date' => (string) ($draft['return_date'] ?? ($criteria['return_date'] ?? '')),
            'cabin' => (string) ($draft['cabin'] ?? ($criteria['cabin'] ?? 'economy')),
            'adults' => (int) ($draft['adults'] ?? ($criteria['adults'] ?? 1)),
            'children' => (int) ($draft['children'] ?? ($criteria['children'] ?? 0)),
            'infants' => (int) ($draft['infants'] ?? ($criteria['infants'] ?? 0)),
        ];
        if (trim((string) ($draft['fare_option_key'] ?? '')) !== '') {
            $checkoutReturnParams['fare_option_key'] = (string) $draft['fare_option_key'];
        }
        $checkoutReturnPath = '/booking/passengers?'.http_build_query($checkoutReturnParams);
        $checkoutProtectionState = is_array($checkoutProtection ?? null) ? $checkoutProtection : [];
        $fareSessionExpiresAt = (string) ($checkoutProtectionState['checkout_lock_expires_at'] ?? '');
    @endphp

    <div class="ota-mobile-booking" data-testid="ota-mobile-passengers" data-mobile-booking-passengers>
        <header class="ota-mobile-booking__header">
            <div class="ota-mobile-booking__header-row">
                <a href="{{ $resultsBackUrl ?? route('home') }}" class="ota-mobile-booking__back" aria-label="Back to results">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                </a>
                <div class="ota-mobile-booking__header-copy">
                    <h1 class="ota-mobile-booking__title">Traveller Details</h1>
                    @if ($routeLabel !== '')
                        <p class="ota-mobile-booking__subtitle">{{ $routeLabel }}</p>
                    @endif
                </div>
            </div>
        </header>

        <x-bookings.fare-session-countdown
            :session-key="'passenger:'.($draft['search_id'] ?? '').':'.($flightId ?? $draft['offer_id'] ?? '')"
            :expires-at-iso="$fareSessionExpiresAt !== '' ? $fareSessionExpiresAt : null"
            :refresh-search-url="$refreshSearchUrl ?? ($resultsBackUrl ?? url('/'))"
            variant="mobile"
        />

        @error('fare_option_key')<div class="ota-mobile-booking__alert ota-mobile-booking__alert--danger">{{ $message }}</div>@enderror
        @if (! empty($validationAlert))
            <div class="ota-mobile-booking__alert ota-mobile-booking__alert--warning">
                {{ $validationAlert }}
                @if (is_array($validationResult ?? null) && ($validationResult['price_changed'] ?? false))
                    <span class="ota-mobile-booking__alert-detail">
                        Old: Rs {{ number_format((float) ($validationResult['old_total'] ?? 0), 0) }}
                        · New: Rs {{ number_format((float) ($validationResult['new_total'] ?? 0), 0) }}
                    </span>
                @endif
            </div>
        @endif
        @if ($complexItineraryNotice ?? false)
            <div class="ota-mobile-booking__alert ota-mobile-booking__alert--info">
                Your booking request will require staff confirmation before airline hold/PNR.
            </div>
        @endif

        @if ($o)
            @include('mobile.bookings.partials.selected-flight-card', [
                'offer' => $o,
                'criteria' => $cr,
                'checkoutPresentation' => $checkoutPresentation,
                'airlineLogo' => $airlineLogo,
                'totalPayable' => $totalPayable,
                'selectedFareFamilyOption' => $selectedFareFamilyCheckout,
                'selectedFareEstimate' => $selectedFareEstimate,
                'useSelectedFareEstimate' => $useSelectedFareEstimate,
            ])
        @endif

        @if (! empty($hideInlineAccount))
            <div class="ota-mobile-booking__notice">
                Signed in as <strong>{{ auth()->user()->name }}</strong>. This booking will be linked to your account.
            </div>
        @else
            <article class="ota-mobile-booking__card ota-mobile-booking__access-card">
                <p class="ota-mobile-booking__access-title">Continue as guest</p>
                <p class="ota-mobile-booking__access-sub">No account needed — complete the form below</p>
                <div class="ota-mobile-booking__access-actions">
                    <a href="{{ route('login', ['redirect' => $checkoutReturnPath, 'checkout_return' => $checkoutReturnPath]) }}" class="ota-mobile-booking__link-btn">Sign in</a>
                    <a href="{{ route('social.redirect', ['provider' => 'google', 'redirect' => $checkoutReturnPath, 'checkout_return' => $checkoutReturnPath]) }}" class="ota-mobile-booking__link-btn">Google</a>
                    @if ($facebookSocialEnabled)
                        <a href="{{ route('social.redirect', ['provider' => 'facebook', 'redirect' => $checkoutReturnPath, 'checkout_return' => $checkoutReturnPath]) }}" class="ota-mobile-booking__link-btn">Facebook</a>
                    @endif
                </div>
            </article>
        @endif

        <form method="post" action="{{ route('booking.passengers') }}" class="ota-mobile-booking__form" id="ota-mobile-checkout-passengers-form" data-mobile-checkout-passenger-form>
            @csrf
            <input type="hidden" name="flight_id" value="{{ old('flight_id', $flightId) }}">
            <input type="hidden" name="offer_id" value="{{ old('offer_id', $draft['offer_id'] ?? $flightId) }}">
            <input type="hidden" name="search_id" value="{{ old('search_id', $draft['search_id'] ?? '') }}">
            @if (trim((string) ($draft['fare_option_key'] ?? '')) !== '')
                <input type="hidden" name="fare_option_key" value="{{ old('fare_option_key', $draft['fare_option_key']) }}">
            @endif
            <input type="hidden" name="from" value="{{ old('from', $draft['search_from'] ?? ($criteria['origin'] ?? '')) }}">
            <input type="hidden" name="to" value="{{ old('to', $draft['search_to'] ?? ($criteria['destination'] ?? '')) }}">
            <input type="hidden" name="depart" value="{{ old('depart', $draft['search_depart'] ?? ($criteria['depart_date'] ?? '')) }}">
            <input type="hidden" name="trip_type" value="{{ old('trip_type', $draft['trip_type'] ?? ($criteria['trip_type'] ?? 'one_way')) }}">
            <input type="hidden" name="return_date" value="{{ old('return_date', $draft['return_date'] ?? ($criteria['return_date'] ?? '')) }}">
            <input type="hidden" name="cabin" value="{{ old('cabin', $draft['cabin'] ?? ($criteria['cabin'] ?? 'economy')) }}">
            <input type="hidden" name="adults" value="{{ old('adults', $passengerCountSummary['adults']) }}">
            <input type="hidden" name="children" value="{{ old('children', $passengerCountSummary['children']) }}">
            <input type="hidden" name="infants" value="{{ old('infants', $passengerCountSummary['infants']) }}">
            <input type="hidden" name="total_passengers" value="{{ old('total_passengers', $passengerCountSummary['total']) }}">

            @if ($errors->has('passengers') || $errors->has('lead_passenger_index') || $errors->has('total_passengers') || $errors->has('infants'))
                <div class="ota-mobile-booking__alert ota-mobile-booking__alert--danger">
                    @error('passengers')<p>{{ $message }}</p>@enderror
                    @error('lead_passenger_index')<p>{{ $message }}</p>@enderror
                    @error('total_passengers')<p>{{ $message }}</p>@enderror
                    @error('infants')<p>{{ $message }}</p>@enderror
                </div>
            @endif

            @foreach ($expectedPassengers as $pos => $pax)
                @php
                    $i = $pax['index'];
                    $pp = $oldPassengers[$i] ?? [];
                    $type = $pax['type'];
                    $isLead = $leadPassengerIndex === $i;
                @endphp
                @include('mobile.bookings.partials.traveller-card', [
                    'i' => $i,
                    'pos' => $pos,
                    'pp' => $pp,
                    'type' => $type,
                    'isLead' => $isLead,
                    'adultCount' => $adultCount,
                    'pkDomesticDocs' => $pkDomesticTravelDocuments ?? false,
                    'checkoutCountries' => $checkoutCountries,
                ])
            @endforeach

            <article class="ota-mobile-booking__card">
                <h2 class="ota-mobile-booking__card-title">Contact details</h2>
                <div class="ota-mobile-booking__field">
                    <label class="ota-mobile-booking__label" for="mobile-checkout-contact-name">Contact name</label>
                    <input class="ota-mobile-booking__input @error('contact_name') is-invalid @enderror" id="mobile-checkout-contact-name" type="text" name="contact_name" value="{{ old('contact_name', $checkoutContactPrefill['name'] ?? '') }}" data-mobile-checkout-contact-name autocomplete="name">
                    @error('contact_name')<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
                </div>
                <div class="ota-mobile-booking__field">
                    <label class="ota-mobile-booking__label" for="mobile-checkout-email">Email</label>
                    <input class="ota-mobile-booking__input @error('email') is-invalid @enderror" id="mobile-checkout-email" type="email" name="email" value="{{ old('email', $draft['email'] ?? $checkoutContactPrefill['email'] ?? '') }}" required autocomplete="email">
                    @error('email')<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
                </div>
                <div class="ota-mobile-booking__field">
                    <label class="ota-mobile-booking__label" for="mobile-checkout-phone-number">Mobile number</label>
                    <div class="ota-mobile-booking__phone-row">
                        <select id="mobile-checkout-phone-country-code" class="ota-mobile-booking__input ota-mobile-booking__phone-code" name="phone_country_code" aria-label="Country code" required>
                            @foreach ($checkoutPhoneDialCodes as $code => $label)
                                <option value="{{ $code }}" @selected((string) $contactPhoneCode === (string) $code) title="{{ $label }}">{{ $code }}</option>
                            @endforeach
                        </select>
                        <input class="ota-mobile-booking__input ota-mobile-booking__phone-number @error('phone') is-invalid @enderror @error('phone_number') is-invalid @enderror" id="mobile-checkout-phone-number" type="tel" name="phone_number" value="{{ $contactPhoneNumber }}" required autocomplete="tel-national" inputmode="numeric" pattern="[0-9]*" maxlength="15" placeholder="3001234567">
                    </div>
                    @error('phone_country_code')<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
                    @error('phone_number')<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
                    @error('phone')<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
                </div>
                <div class="ota-mobile-booking__field">
                    <label class="ota-mobile-booking__label" for="mobile-checkout-country">Country / region</label>
                    <select class="ota-mobile-booking__input" id="mobile-checkout-country" name="country" data-mobile-checkout-contact-country>
                        <x-geo.country-select-options :countries="$checkoutCountries" :selected="$contactCountryValue" :include-empty="true" empty-label="Select country" />
                    </select>
                </div>

                @if (empty($hideInlineAccount))
                    <input type="hidden" name="create_account" value="0">
                    <label class="ota-mobile-booking__checkbox">
                        <input type="checkbox" name="create_account" value="1" id="mobile-checkout-create-account" @checked(old('create_account')) aria-controls="mobile-checkout-inline-account-fields">
                        <span>Create an account with this booking (optional)</span>
                    </label>
                    <div id="mobile-checkout-inline-account-fields" class="ota-mobile-booking__account-fields {{ old('create_account') ? 'is-open' : '' }}">
                        <div class="ota-mobile-booking__field">
                            <label class="ota-mobile-booking__label" for="mobile-checkout-password">Password</label>
                            <input class="ota-mobile-booking__input @error('password') is-invalid @enderror" id="mobile-checkout-password" type="password" name="password" autocomplete="new-password">
                            @error('password')<p class="ota-mobile-booking__error">{{ $message }}</p>@enderror
                        </div>
                        <div class="ota-mobile-booking__field">
                            <label class="ota-mobile-booking__label" for="mobile-checkout-password-confirm">Confirm password</label>
                            <input class="ota-mobile-booking__input" id="mobile-checkout-password-confirm" type="password" name="password_confirmation" autocomplete="new-password">
                            <p class="ota-mobile-booking__error" id="mobile-checkout-password-mismatch" role="alert" hidden>Passwords do not match.</p>
                        </div>
                    </div>
                @endif
            </article>

            @if ($o && ! empty($o['baggage']))
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
                :selected-fare-estimate-drift-detected="! empty($selectedFareEstimateDriftDetected ?? false)"
                :passenger-count-summary="$passengerCountSummary"
            />

            <div class="ota-mobile-booking__sticky-cta">
                <button type="submit" class="ota-mobile-booking__cta">Continue to review</button>
                <p class="ota-mobile-booking__cta-note">No payment at this step</p>
            </div>
        </form>
    </div>
@endsection
