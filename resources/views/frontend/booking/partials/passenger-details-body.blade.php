    @php
        $o = $offer;
        $cr = $criteria;
        $checkoutContact = $publicAgencyContact ?? \App\Support\Branding\PublicAgencyContactResolver::resolve($agencySettings ?? null);
        $waUrl = $checkoutContact->whatsappUrl() ?? '#';
        $selectedFareFamilyOption = is_array($draft['selected_fare_family_option'] ?? null)
            ? $draft['selected_fare_family_option']
            : null;
        $selectedFareFamilyCheckout = \App\Support\FlightSearch\FlightOfferDisplayPresenter::buildSelectedFareFamilyCheckoutView($selectedFareFamilyOption);
        $selectedFareEstimate = \App\Support\FlightSearch\FlightOfferDisplayPresenter::buildCheckoutSelectedFareEstimatePresentation($selectedFareFamilyOption);
        $checkoutFareRules = \App\Support\FlightSearch\FlightOfferDisplayPresenter::buildCheckoutFareRulesSidebar($o, $selectedFareFamilyOption);
        $useSelectedFareEstimate = is_array($selectedFareEstimate) && ! empty($selectedFareEstimate['has_checkout_estimate']);
        $selectedFareFamilyLabel = trim((string) ($selectedFareFamilyCheckout['name'] ?? ''));
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
        if (trim((string) ($draft['return_fare_option_key'] ?? '')) !== '') {
            $checkoutReturnParams['return_fare_option_key'] = (string) $draft['return_fare_option_key'];
        }
        if (trim((string) ($draft['outbound_fare_option_key'] ?? '')) !== '') {
            $checkoutReturnParams['outbound_fare_option_key'] = (string) $draft['outbound_fare_option_key'];
        }
        if (trim((string) ($draft['outbound_key'] ?? '')) !== '') {
            $checkoutReturnParams['outbound_key'] = (string) $draft['outbound_key'];
        }
        if (trim((string) ($draft['combo_id'] ?? '')) !== '') {
            $checkoutReturnParams['combo_id'] = (string) $draft['combo_id'];
        }
        $returnSplitSummary = is_array($returnSplitSummary ?? null) ? $returnSplitSummary : [];
        $isReturnSplitCheckout = ! empty($returnSplitSummary['is_return_split']);
        $checkoutReturnPath = client_url('/booking/passengers?'.http_build_query($checkoutReturnParams));
        $facebookSocialEnabled = \App\Http\Controllers\Auth\SocialAuthController::providerIsConfigured('facebook');
        $checkoutPresentation = is_array($checkoutPresentation ?? null) ? $checkoutPresentation : [];
        $checkoutJourneys = is_array($checkoutPresentation['journeys_display'] ?? null) ? $checkoutPresentation['journeys_display'] : [];
        $tripTypeLabel = \App\Support\FlightSearch\FlightOfferDisplayPresenter::formatCriteriaTripTypeLabel((string) ($cr['trip_type'] ?? 'one_way'));
        $routeLabel = \App\Support\FlightSearch\FlightOfferDisplayPresenter::formatCriteriaRouteLabel($cr);
        if ($routeLabel === '') {
            $routeLabel = ($cr['origin'] ?? '').' → '.($cr['destination'] ?? '');
        }
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
        $checkoutProtectionState = is_array($checkoutProtection ?? null) ? $checkoutProtection : [];
        $fareSessionExpiresAt = (string) ($checkoutProtectionState['checkout_lock_expires_at'] ?? '');
        $departureCarbon = null;
        $arrivalCarbon = null;
        $arrivalDayNote = null;
        $cabinLabel = ucfirst(str_replace('_', ' ', (string) (is_array($o) ? ($checkoutFareRules['cabin_display'] ?? $o['cabin'] ?? $cr['cabin'] ?? 'economy') : ($cr['cabin'] ?? 'economy'))));
        $displayBaseFare = is_array($o) ? (float) (data_get($o, 'fare_breakdown.display_base_fare') ?? $o['base_fare'] ?? 0) : 0;
        $displayTaxes = is_array($o) ? (float) (data_get($o, 'fare_breakdown.display_taxes') ?? $o['taxes'] ?? 0) : 0;
        $agencyCharges = is_array($o) ? (float) (($o['markup'] ?? 0) + ($o['service_fee'] ?? 0)) : 0;
        $totalPayable = is_array($o) ? (float) ($o['total'] ?? $o['final_customer_price'] ?? 0) : 0;
        $supplierPassengerPricing = is_array($o) && is_array(data_get($o, 'fare_breakdown.passenger_pricing'))
            ? data_get($o, 'fare_breakdown.passenger_pricing')
            : [];
        $passengerPricingAvailable = is_array($o) && (bool) (data_get($o, 'fare_breakdown.passenger_pricing_available') ?? ! empty($supplierPassengerPricing));
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
        if (is_array($o)) {
            try {
                $departureCarbon = ! empty($o['depart_at']) ? \Illuminate\Support\Carbon::parse($o['depart_at']) : null;
                $arrivalCarbon = ! empty($o['arrive_at']) ? \Illuminate\Support\Carbon::parse($o['arrive_at']) : null;
                if ($departureCarbon && $arrivalCarbon) {
                    $calDays = $departureCarbon->copy()->startOfDay()->diffInDays($arrivalCarbon->copy()->startOfDay());
                    if ($calDays > 0) {
                        $arrivalDayNote = $calDays === 1 ? '+1 day' : '+'.$calDays.' days';
                    }
                }
            } catch (\Throwable) {
                $departureCarbon = $arrivalCarbon = null;
            }
        }
    @endphp
    <div class="ota-book-wrap ota-checkout-page" data-checkout-page>
        <div class="ota-container ota-container-wide">
            <div class="ota-checkout-page-head ota-checkout-page-head--flush">
                <h1 class="ota-checkout-page-title">{{ $checkoutPageHeading ?? 'Passenger &amp; contact details' }}</h1>
            </div>
            <x-bookings.fare-session-countdown
                :session-key="'passenger:'.($draft['search_id'] ?? '').':'.($flightId ?? $draft['offer_id'] ?? '')"
                :expires-at-iso="$fareSessionExpiresAt !== '' ? $fareSessionExpiresAt : null"
                :refresh-search-url="$refreshSearchUrl ?? ($resultsBackUrl ?? url('/'))"
                variant="desktop"
            />
            @error('fare_option_key')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
            @if (!empty($validationAlert))
                <div class="alert alert-warning">
                    {{ $validationAlert }}
                    @if (is_array($validationResult ?? null) && ($validationResult['price_changed'] ?? false))
                        <div class="small" style="margin-top:4px;">
                            Old: Rs {{ number_format((float) ($validationResult['old_total'] ?? 0), 0) }}
                            · New: Rs {{ number_format((float) ($validationResult['new_total'] ?? 0), 0) }}
                        </div>
                    @endif
                </div>
            @endif

            @if (($o['supplier_provider'] ?? $o['supplier'] ?? '') === 'one_api')
                @include('frontend.bookings.one-api.extras', [
                    'workflowContextId' => data_get($validationResult ?? [], 'meta.one_api_workflow_context_id')
                        ?? data_get($o, 'raw_payload.provider_context.one_api_workflow_context_id'),
                    'supplierConnectionId' => $o['supplier_connection_id'] ?? null,
                    'holdDeadline' => data_get($draft, 'one_api_hold_deadline'),
                    'o' => $o,
                ])
            @endif

            <div class="ota-checkout-grid ota-booking-layout">
                <div class="ota-checkout-main">
                    @if (!empty($hideInlineAccount))
                        <div class="ota-checkout-access-card ota-checkout-access-card--signed-in">
                            <p class="mb-0">Signed in as <strong>{{ auth()->user()->name }}</strong>. This booking will be linked to your account.</p>
                        </div>
                    @else
                        <div class="ota-checkout-access-card" role="region" aria-label="Checkout options">
                            <div class="ota-checkout-access-card__guest">
                                <span class="ota-checkout-access-card__guest-icon" aria-hidden="true"><i class="fa fa-user-o"></i></span>
                                <div class="ota-checkout-access-card__guest-copy">
                                    <strong class="ota-checkout-access-card__guest-title">Continue as guest</strong>
                                    <span class="ota-checkout-access-card__guest-sub">No account needed — complete the form below</span>
                                </div>
                                <span class="ota-checkout-access-card__default-pill">Default</span>
                            </div>
                            <div class="ota-checkout-access-card__divider" aria-hidden="true"></div>
                            <div class="ota-checkout-access-card__account">
                                <p class="ota-checkout-access-card__account-label">Already have an account?</p>
                                <div class="ota-checkout-access-card__account-actions">
                                    <a href="{{ client_route('login', ['redirect' => $checkoutReturnPath, 'checkout_return' => $checkoutReturnPath]) }}" class="ota-checkout-btn-secondary">Sign in</a>
                                    <div class="ota-checkout-access-card__social">
                                        <a class="ota-checkout-social-pill" href="{{ route('social.redirect', ['provider' => 'google', 'redirect' => $checkoutReturnPath, 'checkout_return' => $checkoutReturnPath]) }}"><i class="fa fa-google" aria-hidden="true"></i> Google</a>
                                        @if ($facebookSocialEnabled)
                                        <a class="ota-checkout-social-pill ota-checkout-social-pill--facebook" href="{{ route('social.redirect', ['provider' => 'facebook', 'redirect' => $checkoutReturnPath, 'checkout_return' => $checkoutReturnPath]) }}"><i class="fa fa-facebook" aria-hidden="true"></i> Facebook</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <form method="post" action="{{ client_url('/booking/passengers') }}" class="ota-checkout-form" id="ota-checkout-passengers-form" data-checkout-passenger-form>
                        @csrf
                        <input type="hidden" name="flight_id" value="{{ old('flight_id', $flightId) }}">
                        <input type="hidden" name="offer_id" value="{{ old('offer_id', $draft['offer_id'] ?? $flightId) }}">
                        <input type="hidden" name="search_id" value="{{ old('search_id', $draft['search_id'] ?? '') }}">
                        @if (trim((string) ($draft['fare_option_key'] ?? '')) !== '')
                            <input type="hidden" name="fare_option_key" value="{{ old('fare_option_key', $draft['fare_option_key']) }}">
                        @endif
                        @if (trim((string) ($draft['return_fare_option_key'] ?? '')) !== '')
                            <input type="hidden" name="return_fare_option_key" value="{{ old('return_fare_option_key', $draft['return_fare_option_key']) }}">
                        @endif
                        @if (trim((string) ($draft['outbound_fare_option_key'] ?? '')) !== '')
                            <input type="hidden" name="outbound_fare_option_key" value="{{ old('outbound_fare_option_key', $draft['outbound_fare_option_key']) }}">
                        @endif
                        @if (trim((string) ($draft['outbound_key'] ?? '')) !== '')
                            <input type="hidden" name="outbound_key" value="{{ old('outbound_key', $draft['outbound_key']) }}">
                        @endif
                        @if (trim((string) ($draft['combo_id'] ?? '')) !== '')
                            <input type="hidden" name="combo_id" value="{{ old('combo_id', $draft['combo_id']) }}">
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
                            <div class="ota-checkout-form-errors mb-2">
                                @error('passengers')<div class="alert alert-danger py-2 mb-2">{{ $message }}</div>@enderror
                                @error('lead_passenger_index')<div class="alert alert-danger py-2 mb-2">{{ $message }}</div>@enderror
                                @error('total_passengers')<div class="alert alert-danger py-2 mb-2">{{ $message }}</div>@enderror
                                @error('infants')<div class="alert alert-danger py-2 mb-0">{{ $message }}</div>@enderror
                            </div>
                        @endif

                        <h2 class="ota-checkout-section-title ota-checkout-travellers-heading">Travellers <span class="ota-checkout-travellers-count">{{ $passengerCountSummary['total'] }}</span></h2>

                        @php
                            $oldPassengers = old('passengers', []);
                            $adultIndexes = collect($expectedPassengers)->filter(fn ($p) => $p['type'] === 'adult')->pluck('index')->values();
                            $leadPassengerIndex = (int) old('lead_passenger_index', $adultIndexes->first() ?? 0);
                            $titles = ['Mr', 'Ms', 'Mrs', 'Mx'];
                            $genders = ['M' => 'Male', 'F' => 'Female', 'X' => 'Unspecified'];
                            $pkDomesticDocs = $pkDomesticTravelDocuments ?? false;
                        @endphp

                        @foreach ($expectedPassengers as $pos => $pax)
                            @php
                                $i = $pax['index'];
                                $pp = $oldPassengers[$i] ?? [];
                                $type = $pax['type'];
                                $isLead = $leadPassengerIndex === $i;
                                $isAdult = $type === 'adult';
                            @endphp
                            <details class="ota-checkout-card ota-checkout-card--section ota-passenger-card" {{ $pos === 0 || $errors->any() ? 'open' : '' }}>
                                <summary class="ota-passenger-card__summary">
                                    <span class="ota-passenger-card__title">
                                        <span class="ota-passenger-card__index">{{ $pos + 1 }}</span>
                                        {{ ucfirst($type) }}
                                        @if($isLead)<span class="ota-passenger-card__badge">Lead</span>@endif
                                    </span>
                                    <span class="ota-passenger-card__chevron" aria-hidden="true"></span>
                                </summary>
                                <div class="ota-passenger-card__body">
                                    <input type="hidden" name="passengers[{{ $i }}][passenger_type]" value="{{ $type }}">
                                    @php
                                        $ppDoc = (string) ($pp['document_type'] ?? 'passport');
                                        $showPassportFields = ! $pkDomesticDocs || $ppDoc !== 'national_id';
                                        $showNationalIdFields = $pkDomesticDocs && $ppDoc === 'national_id';
                                        $nationalityValue = strtoupper((string) ($pp['nationality'] ?? ''));
                                        $passportIssuerValue = strtoupper((string) ($pp['passport_issuing_country'] ?? ''));
                                    @endphp

                                    <section class="ota-pax-section">
                                        <h3 class="ota-pax-section__title">Passenger details</h3>
                                        <div class="ota-pax-grid ota-pax-grid--identity">
                                            <div class="ota-pax-field ota-pax-field--title">
                                                <div class="ota-form-group">
                                                    <label class="ota-label">Title</label>
                                                    <select class="form-control ota-input js-pax-title" name="passengers[{{ $i }}][title]" required>
                                                        @foreach ($titles as $t)
                                                            <option value="{{ $t }}" @selected(($pp['title'] ?? 'Mr') === $t)>{{ $t }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error("passengers.$i.title")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                </div>
                                            </div>
                                            <div class="ota-pax-field ota-pax-field--fname">
                                                <div class="ota-form-group">
                                                    <label class="ota-label">First name</label>
                                                    <input class="form-control ota-input" type="text" name="passengers[{{ $i }}][first_name]" value="{{ $pp['first_name'] ?? '' }}" required>
                                                    @error("passengers.$i.first_name")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                </div>
                                            </div>
                                            <div class="ota-pax-field ota-pax-field--lname">
                                                <div class="ota-form-group">
                                                    <label class="ota-label">Last name</label>
                                                    <input class="form-control ota-input" type="text" name="passengers[{{ $i }}][last_name]" value="{{ $pp['last_name'] ?? '' }}" required>
                                                    @error("passengers.$i.last_name")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                </div>
                                            </div>
                                            <div class="ota-pax-field ota-pax-field--dob">
                                                <div class="ota-form-group">
                                                    <label class="ota-label">Date of birth</label>
                                                    <input class="form-control ota-input" type="date" name="passengers[{{ $i }}][date_of_birth]" value="{{ $pp['date_of_birth'] ?? '' }}" required>
                                                    @error("passengers.$i.date_of_birth")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                </div>
                                            </div>
                                            <div class="ota-pax-field ota-pax-field--gender">
                                                <div class="ota-form-group">
                                                    <label class="ota-label">Gender</label>
                                                    <select class="form-control ota-input js-pax-gender" name="passengers[{{ $i }}][gender]" required>
                                                        <option value="" disabled @selected(($pp['gender'] ?? '') === '')>Select</option>
                                                        @foreach ($genders as $gv => $gl)
                                                            <option value="{{ $gv }}" @selected(($pp['gender'] ?? '') === $gv)>{{ $gl }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error("passengers.$i.gender")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                </div>
                                            </div>
                                            <div class="ota-pax-field ota-pax-field--nationality">
                                                <div class="ota-form-group">
                                                    <label class="ota-label">Nationality</label>
                                                    <select class="form-control ota-input ota-checkout-country-select js-pax-nationality-input" name="passengers[{{ $i }}][nationality]" @if(! $pkDomesticDocs || $showPassportFields) required @endif data-pax-nationality-required="1">
                                                        <x-geo.country-select-options :countries="$checkoutCountries" :selected="$nationalityValue" />
                                                    </select>
                                                    @error("passengers.$i.nationality")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                </div>
                                            </div>
                                        </div>
                                    </section>

                                    <div class="ota-passenger-doc-block" data-pk-domestic="{{ $pkDomesticDocs ? '1' : '0' }}">
                                        @if ($pkDomesticDocs)
                                            <section class="ota-pax-section ota-pax-section--doc-type">
                                                <h3 class="ota-pax-section__title">Travel document</h3>
                                                <input type="hidden" class="ota-pax-document-type" name="passengers[{{ $i }}][document_type]" value="{{ $ppDoc }}">
                                                <div class="ota-pax-doc-type-switch" role="radiogroup" aria-label="Document type for passenger {{ $pos + 1 }}">
                                                    <label class="ota-pax-doc-type-switch__option">
                                                        <input type="radio" class="ota-pax-document-type-choice" name="passengers[{{ $i }}][document_type_ui]" value="national_id" @checked($ppDoc === 'national_id')>
                                                        <span class="ota-pax-doc-type-switch__label">National ID / CNIC</span>
                                                    </label>
                                                    <label class="ota-pax-doc-type-switch__option">
                                                        <input type="radio" class="ota-pax-document-type-choice" name="passengers[{{ $i }}][document_type_ui]" value="passport" @checked($ppDoc !== 'national_id')>
                                                        <span class="ota-pax-doc-type-switch__label">Passport</span>
                                                    </label>
                                                </div>
                                                @error("passengers.$i.document_type")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                            </section>
                                            <section class="ota-pax-section js-pax-national-id-fields {{ $showNationalIdFields ? '' : 'd-none' }}">
                                                <h3 class="ota-pax-section__title">National ID</h3>
                                                <div class="ota-pax-grid ota-pax-grid--national-id">
                                                    <div class="ota-pax-field ota-pax-field--national-id">
                                                        <div class="ota-form-group">
                                                            <label class="ota-label">National ID / CNIC number</label>
                                                            <input class="form-control ota-input" type="text" name="passengers[{{ $i }}][national_id_number]" value="{{ $pp['national_id_number'] ?? '' }}" autocomplete="off">
                                                            @error("passengers.$i.national_id_number")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </section>
                                        @else
                                            <input type="hidden" name="passengers[{{ $i }}][document_type]" value="passport">
                                        @endif

                                        <section class="ota-pax-section js-pax-passport-fields {{ $showPassportFields ? '' : 'd-none' }}">
                                            <h3 class="ota-pax-section__title">Passport details</h3>
                                            <div class="ota-pax-grid ota-pax-grid--passport">
                                                <div class="ota-pax-field ota-pax-field--passport-no">
                                                    <div class="ota-form-group">
                                                        <label class="ota-label">Passport number</label>
                                                        <input class="form-control ota-input js-pax-passport-input" type="text" name="passengers[{{ $i }}][passport_number]" value="{{ $pp['passport_number'] ?? '' }}" @if(! $pkDomesticDocs || $showPassportFields) required @endif data-pax-passport-required="1">
                                                        @error("passengers.$i.passport_number")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                    </div>
                                                </div>
                                                <div class="ota-pax-field ota-pax-field--passport-country">
                                                    <div class="ota-form-group">
                                                        <label class="ota-label">Issuing country</label>
                                                        <select class="form-control ota-input ota-checkout-country-select js-pax-passport-input" name="passengers[{{ $i }}][passport_issuing_country]" @if(! $pkDomesticDocs || $showPassportFields) required @endif data-pax-passport-required="1">
                                                            <x-geo.country-select-options :countries="$checkoutCountries" :selected="$passportIssuerValue" />
                                                        </select>
                                                        @error("passengers.$i.passport_issuing_country")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                    </div>
                                                </div>
                                                <div class="ota-pax-field ota-pax-field--passport-expiry">
                                                    <div class="ota-form-group">
                                                        <label class="ota-label">Expiry date</label>
                                                        <input class="form-control ota-input js-pax-passport-input" type="date" name="passengers[{{ $i }}][passport_expiry_date]" value="{{ $pp['passport_expiry_date'] ?? '' }}" @if(! $pkDomesticDocs || $showPassportFields) required @endif data-pax-passport-required="1">
                                                        @error("passengers.$i.passport_expiry_date")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                    </div>
                                                </div>
                                                <div class="ota-pax-field ota-pax-field--passport-issue">
                                                    <div class="ota-form-group">
                                                        <label class="ota-label">Issue date</label>
                                                        <input class="form-control ota-input js-pax-passport-input" type="date" name="passengers[{{ $i }}][passport_issue_date]" value="{{ $pp['passport_issue_date'] ?? '' }}" @if(! $pkDomesticDocs || $showPassportFields) required @endif data-pax-passport-required="1">
                                                        @error("passengers.$i.passport_issue_date")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                    </div>

                                    @if($isAdult && $adultIndexes->count() > 1)
                                        <div class="ota-pax-lead-select">
                                            <label class="ota-checkbox-label d-flex align-items-center gap-2 mb-0">
                                                <input type="radio" name="lead_passenger_index" value="{{ $i }}" @checked($isLead)>
                                                <span>Set as lead passenger</span>
                                            </label>
                                        </div>
                                    @elseif($isLead)
                                        <input type="hidden" name="lead_passenger_index" value="{{ $i }}">
                                    @endif
                                </div>
                            </details>
                        @endforeach

                        <div class="ota-checkout-card ota-checkout-card--section ota-checkout-contact-card">
                            <h2 class="ota-checkout-section-title">Contact details</h2>
                            @if (! empty($agentBookingMode))
                                <p class="small text-secondary mb-3">
                                    Agency contact for this booking (linked to {{ $agentAgencyName ?: 'your agency' }}).
                                    Traveller details above remain editable.
                                </p>
                            @endif
                            <div class="ota-pax-grid ota-pax-grid--contact">
                                <div class="ota-pax-field ota-pax-field--contact-name">
                                    <div class="ota-form-group">
                                        <label class="ota-label" for="checkout-contact-name">Contact name</label>
                                        <input class="form-control ota-input @error('contact_name') is-invalid @enderror" id="checkout-contact-name" type="text" name="contact_name" value="{{ old('contact_name', $checkoutContactPrefill['name'] ?? '') }}" data-checkout-contact-name autocomplete="name" @if(!empty($agentBookingContactLocked)) readonly @endif>
                                        @error('contact_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="ota-pax-field ota-pax-field--email">
                                    <div class="ota-form-group">
                                        <label class="ota-label" for="checkout-email">Email</label>
                                        <input class="form-control ota-input @error('email') is-invalid @enderror" id="checkout-email" type="email" name="email" value="{{ old('email', $draft['email'] ?? $checkoutContactPrefill['email'] ?? '') }}" required autocomplete="email" @if(!empty($agentBookingContactLocked)) readonly @endif>
                                        @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="ota-pax-field ota-pax-field--phone">
                                    <div class="ota-form-group">
                                        <label class="ota-label" for="checkout-phone-number">Mobile</label>
                                        <div class="ota-checkout-phone-row">
                                            <select id="checkout-phone-country-code" class="form-control ota-input ota-checkout-country-code-select" name="phone_country_code" aria-label="Country code" required @if(!empty($agentBookingContactLocked)) disabled @endif>
                                                @foreach ($checkoutPhoneDialCodes as $code => $label)
                                                    <option value="{{ $code }}" @selected((string) $contactPhoneCode === (string) $code) title="{{ $label }}">{{ $code }}</option>
                                                @endforeach
                                            </select>
                                            @if (! empty($agentBookingContactLocked))
                                                <input type="hidden" name="phone_country_code" value="{{ $contactPhoneCode }}">
                                            @endif
                                            <input class="form-control ota-input ota-checkout-phone-number @error('phone') is-invalid @enderror @error('phone_number') is-invalid @enderror" id="checkout-phone-number" type="tel" name="phone_number" value="{{ $contactPhoneNumber }}" required autocomplete="tel-national" inputmode="numeric" pattern="[0-9]*" maxlength="15" placeholder="310310300" @if(!empty($agentBookingContactLocked)) readonly @endif>
                                        </div>
                                        @error('phone_country_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        @error('phone_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="ota-pax-field ota-pax-field--contact-country">
                                    <div class="ota-form-group">
                                        <label class="ota-label" for="checkout-country">Country / region</label>
                                        <select class="form-control ota-input ota-checkout-country-select" id="checkout-country" name="country" data-checkout-contact-country @if(!empty($agentBookingContactLocked)) disabled @endif>
                                            <x-geo.country-select-options :countries="$checkoutCountries" :selected="$contactCountryValue" :include-empty="true" empty-label="Select country" />
                                            @if ($contactCountryValue !== '' && ! $contactCountryKnown)
                                                <option value="{{ $contactCountryValue }}" selected>{{ $contactCountryValue }}</option>
                                            @endif
                                        </select>
                                        @if (! empty($agentBookingContactLocked))
                                            <input type="hidden" name="country" value="{{ $contactCountryValue }}">
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if (empty($hideInlineAccount))
                                <input type="hidden" name="create_account" value="0">
                                <div class="ota-checkout-account-panel">
                                    <label class="ota-checkout-account-toggle">
                                        <input type="checkbox" name="create_account" value="1" id="checkout-create-account" @checked(old('create_account')) aria-controls="checkout-inline-account-fields" aria-expanded="{{ old('create_account') ? 'true' : 'false' }}">
                                        <span class="ota-checkout-account-toggle__box" aria-hidden="true"></span>
                                        <span class="ota-checkout-account-toggle__text">Create an account with this booking <em>(optional)</em></span>
                                    </label>
                                    <div id="checkout-inline-account-fields" class="ota-checkout-account-fields {{ old('create_account') ? 'is-open' : '' }}" aria-hidden="{{ old('create_account') ? 'false' : 'true' }}">
                                        <div class="ota-pax-grid ota-pax-grid--contact">
                                            <div class="ota-pax-field">
                                                <div class="ota-form-group mb-0">
                                                    <label class="ota-label" for="checkout-password">Password</label>
                                                    <input class="form-control ota-input @error('password') is-invalid @enderror" id="checkout-password" type="password" name="password" autocomplete="new-password">
                                                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                </div>
                                            </div>
                                            <div class="ota-pax-field">
                                                <div class="ota-form-group mb-0">
                                                    <label class="ota-label" for="checkout-password-confirm">Confirm password</label>
                                                    <input class="form-control ota-input" id="checkout-password-confirm" type="password" name="password_confirmation" autocomplete="new-password">
                                                    <div class="ota-checkout-field-hint is-error" id="checkout-password-mismatch" role="alert" hidden>Passwords do not match.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="ota-checkout-submit-bar ota-booking-actions">
                            <button
                                type="submit"
                                class="ota-btn-primary-lg btn btn-lg btn-block"
                                @if (($o['supplier_provider'] ?? $o['supplier'] ?? '') === 'one_api') data-one-api-continue disabled @endif
                            >Continue to review</button>
                        </div>
                    </form>
                </div>

                <aside class="ota-checkout-aside" aria-label="Trip summary">
                    @if ($o)
                        <div class="ota-checkout-card ota-checkout-card--accent ota-checkout-sticky-summary ota-checkout-trip-summary">
                            <div class="ota-checkout-trip-summary__head">
                                <h2 class="ota-checkout-aside-title mb-0">Selected flight</h2>
                                <span class="ota-checkout-trip-summary__pill">{{ $tripTypeLabel }}</span>
                            </div>
                            <p class="ota-checkout-trip-summary__route">{{ $routeLabel }}</p>

                            @if ($isReturnSplitCheckout)
                                <x-bookings.return-split-checkout-summary :summary="$returnSplitSummary" />
                            @else
                            <div class="ota-checkout-sidebar-block">
                                <h3 class="ota-checkout-sidebar-block__title">Flight summary</h3>
                            <div class="ota-checkout-trip-summary__legs">
                                @if ($checkoutJourneys !== [])
                                    @foreach ($checkoutJourneys as $journey)
                                        @if (is_array($journey))
                                            <div class="ota-checkout-trip-leg">
                                                <div class="ota-checkout-trip-leg__head">
                                                    <span class="ota-checkout-trip-leg__label">{{ $journey['label'] ?? 'Flight' }}</span>
                                                    <span class="ota-checkout-trip-leg__meta">{{ $journey['stops_display'] ?? '' }}@if (!empty($journey['duration_display'])) · {{ $journey['duration_display'] }}@endif</span>
                                                </div>
                                                <p class="ota-checkout-trip-leg__route">
                                                    {{ $journey['origin'] ?? '' }}@if (!empty($journey['origin_city'])) ({{ $journey['origin_city'] }})@endif
                                                    <span aria-hidden="true">→</span>
                                                    {{ $journey['destination'] ?? '' }}@if (!empty($journey['destination_city'])) ({{ $journey['destination_city'] }})@endif
                                                </p>
                                                <div class="ota-checkout-trip-leg__times">
                                                    <span>{{ $journey['departure_time_display'] ?? '' }}</span>
                                                    <span class="ota-checkout-trip-leg__dash" aria-hidden="true">—</span>
                                                    <span>{{ $journey['arrival_time_display'] ?? '' }}@if (!empty($journey['arrival_day_offset'])) <em class="ota-checkout-trip-leg__offset">{{ $journey['arrival_day_offset'] }}</em>@endif</span>
                                                </div>
                                                <p class="ota-checkout-trip-leg__dates">
                                                    {{ $journey['departure_date_display'] ?? '' }}@if (!empty($journey['arrival_date_display'])) · {{ $journey['arrival_date_display'] }}@endif
                                                </p>
                                                <x-bookings.checkout-journey-layovers :journey="$journey" />
                                            </div>
                                        @endif
                                    @endforeach
                                @else
                                    <div class="ota-checkout-trip-leg">
                                        <div class="ota-checkout-trip-leg__head">
                                            <span class="ota-checkout-trip-leg__label">Outbound</span>
                                            <span class="ota-checkout-trip-leg__meta">{{ $checkoutPresentation['stops_display'] ?? '' }}@if (!empty($checkoutPresentation['itinerary_duration_display'])) · {{ $checkoutPresentation['itinerary_duration_display'] }}@endif</span>
                                        </div>
                                        <div class="ota-checkout-trip-leg__times">
                                            <span>{{ $checkoutPresentation['departure_time_display'] ?? ($departureCarbon ? $departureCarbon->format('H:i') : '') }}</span>
                                            <span class="ota-checkout-trip-leg__dash" aria-hidden="true">—</span>
                                            <span>{{ $checkoutPresentation['arrival_time_display'] ?? ($arrivalCarbon ? $arrivalCarbon->format('H:i') : '') }}@if ($arrivalDayNote)<em class="ota-checkout-trip-leg__offset">{{ $arrivalDayNote }}</em>@endif</span>
                                        </div>
                                        <p class="ota-checkout-trip-leg__dates">
                                            {{ $checkoutPresentation['departure_date_display'] ?? ($departureCarbon ? $departureCarbon->format('D, j M') : '') }}
                                            @if (!empty($checkoutPresentation['arrival_date_display']) || $arrivalCarbon)
                                                · {{ $checkoutPresentation['arrival_date_display'] ?? ($arrivalCarbon ? $arrivalCarbon->format('D, j M') : '') }}
                                            @endif
                                        </p>
                                        <x-bookings.checkout-journey-layovers :journey="[
                                            'origin' => $checkoutPresentation['origin'] ?? ($cr['origin'] ?? ''),
                                            'destination' => $checkoutPresentation['destination'] ?? ($cr['destination'] ?? ''),
                                            'stops_display' => $checkoutPresentation['stops_display'] ?? '',
                                            'stops_count' => $checkoutPresentation['stops_count'] ?? null,
                                            'duration_display' => $checkoutPresentation['itinerary_duration_display'] ?? '',
                                            'segments_display' => $checkoutPresentation['segments_display'] ?? [],
                                            'layovers_display' => $checkoutPresentation['layovers_display'] ?? [],
                                            'layover_summary' => $checkoutPresentation['layover_summary'] ?? [],
                                            'connection_details_unavailable' => $checkoutPresentation['connection_details_unavailable'] ?? false,
                                        ]" />
                                    </div>
                                @endif
                            </div>
                            </div>
                            @endif

                            @if (! $isReturnSplitCheckout)
                            <div class="ota-checkout-sidebar-block">
                                <h3 class="ota-checkout-sidebar-block__title">Airline &amp; fare rules</h3>
                            <div class="ota-checkout-trip-summary__carrier">
                                @if (!empty($airlineLogo))
                                    <img src="{{ $airlineLogo }}" alt="" class="ota-checkout-trip-summary__logo" width="28" height="28">
                                @endif
                                <div>
                                    <p class="ota-checkout-trip-summary__airline mb-0">{{ $o['airline_name'] ?? '' }}</p>
                                    <p class="ota-checkout-trip-summary__flight-no mb-0">{{ $o['carrier_code'] ?? '' }}{{ $o['flight_number'] ?? '' }}</p>
                                </div>
                            </div>

                            <ul class="ota-checkout-trip-summary__tags">
                                <li><i class="fa fa-suitcase" aria-hidden="true"></i> {{ $checkoutFareRules['baggage_display'] ?? ($o['baggage'] ?? 'Baggage per fare rules') }}</li>
                                <li>{{ $cabinLabel }}</li>
                                <li>
                                    @if (!empty($o['refundable']))
                                        <span class="ota-checkout-tag ota-checkout-tag--ok">Refundable</span>
                                    @else
                                        <span class="ota-checkout-tag">Non-refundable</span>
                                    @endif
                                </li>
                            </ul>
                            </div>

                            @if ($selectedFareFamilyCheckout)
                                <x-bookings.selected-fare-family-block :checkout="$selectedFareFamilyCheckout" />
                            @endif

                            <div class="ota-checkout-sidebar-block ota-checkout-sidebar-block--fare">
                                <h3 class="ota-checkout-sidebar-block__title">Fare details</h3>
                            <div class="ota-checkout-trip-summary__fare">
                                <x-bookings.checkout-fare-breakdown
                                    :breakdown="$checkoutFareBreakdown ?? []"
                                    :use-selected-fare-estimate="$useSelectedFareEstimate"
                                    :selected-fare-estimate="$selectedFareEstimate"
                                    :selected-fare-estimate-drift-detected="! empty($selectedFareEstimateDriftDetected)"
                                    :passenger-count-summary="$passengerCountSummary"
                                />
                            </div>
                            </div>
                            @endif
                            <p class="ota-checkout-pay-note"><i class="fa fa-lock" aria-hidden="true"></i> No payment at this step</p>
                        </div>
                    @else
                        <div class="ota-checkout-card ota-checkout-card--muted">
                            <h2 class="ota-checkout-aside-title">No flight selected</h2>
                            <p class="ota-checkout-card__text">Choose a flight from results to see route, times, and fare here.</p>
                            <a href="{{ client_route('home') }}#jp-flight-search" class="btn btn-default btn-block">Browse flights</a>
                        </div>
                    @endif

                    <div class="ota-checkout-card ota-checkout-wa ota-checkout-wa--subtle">
                        <h2 class="ota-checkout-aside-title">Questions?</h2>
                        <p class="ota-checkout-card__text">Reach {{ $checkoutSupportAgencyName ?? $checkoutContact->agencyName }} on WhatsApp for help with this itinerary.</p>
                        @if ($checkoutContact->hasWhatsapp())
                            <a href="{{ $waUrl }}" class="ota-btn-wa btn btn-block" target="_blank" rel="noopener noreferrer">
                                <i class="fa fa-whatsapp"></i> Chat on WhatsApp
                            </a>
                            @if ($checkoutContact->hasPhone())
                                <p class="ota-checkout-wa-phone">{{ $checkoutContact->phone }}</p>
                            @endif
                        @else
                            <p class="text-muted small mb-0">Support number not configured.</p>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var cb = document.getElementById('checkout-create-account');
            var box = document.getElementById('checkout-inline-account-fields');
            var pwd = document.getElementById('checkout-password');
            var pwdConfirm = document.getElementById('checkout-password-confirm');
            var mismatch = document.getElementById('checkout-password-mismatch');

            function clearPasswordMismatchUi() {
                if (mismatch) {
                    mismatch.hidden = true;
                }
                if (pwdConfirm) {
                    pwdConfirm.classList.remove('is-invalid');
                    pwdConfirm.setAttribute('aria-invalid', 'false');
                }
                if (pwd) {
                    pwd.classList.remove('is-invalid');
                    pwd.setAttribute('aria-invalid', 'false');
                }
            }

            function syncPasswordMismatch() {
                if (!pwd || !pwdConfirm || !mismatch || !cb || !cb.checked) {
                    clearPasswordMismatchUi();
                    return;
                }
                var a = pwd.value;
                var b = pwdConfirm.value;
                var show = a !== '' && b !== '' && a !== b;
                mismatch.hidden = !show;
                pwdConfirm.classList.toggle('is-invalid', show);
                pwdConfirm.setAttribute('aria-invalid', show ? 'true' : 'false');
                if (show) {
                    pwd.classList.add('is-invalid');
                    pwd.setAttribute('aria-invalid', 'true');
                } else {
                    pwd.classList.remove('is-invalid');
                    pwd.setAttribute('aria-invalid', 'false');
                }
            }

            function syncAccountPanel() {
                if (!cb || !box) {
                    return;
                }
                var open = cb.checked;
                box.classList.toggle('is-open', open);
                box.setAttribute('aria-hidden', open ? 'false' : 'true');
                cb.setAttribute('aria-expanded', open ? 'true' : 'false');
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
        })();
        (function () {
            function syncPassengerDocBlock(root) {
                if (root.getAttribute('data-pk-domestic') !== '1') {
                    return;
                }
                var hiddenType = root.querySelector('.ota-pax-document-type');
                var selectedChoice = root.querySelector('.ota-pax-document-type-choice:checked');
                var docType = selectedChoice ? selectedChoice.value : (hiddenType ? hiddenType.value : 'passport');
                if (hiddenType) {
                    hiddenType.value = docType;
                }
                var nationalId = docType === 'national_id';
                root.querySelectorAll('.js-pax-passport-fields').forEach(function (row) {
                    row.classList.toggle('d-none', nationalId);
                });
                var nid = root.querySelector('.js-pax-national-id-fields');
                if (nid) {
                    nid.classList.toggle('d-none', !nationalId);
                }
                var cardBody = root.closest('.ota-passenger-card__body') || root;
                cardBody.querySelectorAll('[data-pax-passport-required]').forEach(function (el) {
                    el.required = !nationalId && !el.closest('.d-none');
                });
                cardBody.querySelectorAll('[data-pax-nationality-required]').forEach(function (el) {
                    el.required = !nationalId;
                });
            }
            document.querySelectorAll('.ota-passenger-doc-block[data-pk-domestic="1"]').forEach(function (root) {
                root.querySelectorAll('.ota-pax-document-type-choice').forEach(function (choice) {
                    choice.addEventListener('change', function () { syncPassengerDocBlock(root); });
                });
                syncPassengerDocBlock(root);
            });
        })();
        (function () {
            var form = document.getElementById('ota-checkout-passengers-form');
            if (!form) {
                return;
            }
            var contactName = form.querySelector('[data-checkout-contact-name]');
            var contactCountry = form.querySelector('[data-checkout-contact-country]');
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
        })();
        (function () {
            var titleGenderMap = { Mr: 'M', Mrs: 'F', Ms: 'F', Miss: 'F', Master: 'M' };

            function initTitleGender(card) {
                var titleSel = card.querySelector('.js-pax-title');
                var genderSel = card.querySelector('.js-pax-gender');
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
            }

            document.querySelectorAll('.ota-passenger-card').forEach(initTitleGender);
        })();
    </script>
