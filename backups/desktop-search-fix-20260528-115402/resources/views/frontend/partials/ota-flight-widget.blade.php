@php
    $variant = $variant ?? 'hero';
    $layout = $layout ?? 'classic';
    $isFloating = $layout === 'floating';
    $showIntro = $show_intro ?? true;
    $flightNote = 'Fares are shown based on current airline availability and airline confirmation.';
    $bannerSubtitle = 'Compare fares, choose your cabin, and search with confidence.';
    $widgetId = 'fw-'.substr(md5((string) microtime(true).(string) random_int(1000, 999999)), 0, 8);
    $minDate = $minDate ?? now()->format('Y-m-d');
    $defaultTripType = old('trip_type', $defaultTripType ?? 'one_way');
    $defaultOrigin = old('from', $defaultOrigin ?? '');
    $defaultDestination = old('to', $defaultDestination ?? '');
    $defaultOriginDisplay = old('from_display', $defaultOrigin);
    $defaultDestinationDisplay = old('to_display', $defaultDestination);
    $defaultDepart = old('depart', $defaultDepart ?? '');
    $defaultReturnDate = old('return_date', $defaultReturnDate ?? '');
    $multiFrom = old('multi_from', []);
    $multiTo = old('multi_to', []);
    $multiDepart = old('multi_depart', []);
    if (! is_array($multiFrom)) {
        $multiFrom = [];
    }
    if (! is_array($multiTo)) {
        $multiTo = [];
    }
    if (! is_array($multiDepart)) {
        $multiDepart = [];
    }
    $multiCount = max(2, count($multiFrom), count($multiTo), count($multiDepart));
    $adultsVal = (int) old('adults', 1);
    $childrenVal = (int) old('children', 0);
    $infantsVal = (int) old('infants', 0);
    $cabinVal = old('cabin', 'economy');
    $cabinLabels = [
        'economy' => 'Economy',
        'premium_economy' => 'Premium Economy',
        'business' => 'Business',
        'first' => 'First',
    ];
    $directChecked = old('stops') === 'direct';
    $paxSummary = $adultsVal.' adult'.($adultsVal === 1 ? '' : 's');
    if ($childrenVal > 0) {
        $paxSummary .= ', '.$childrenVal.' child'.($childrenVal === 1 ? '' : 'ren');
    }
    if ($infantsVal > 0) {
        $paxSummary .= ', '.$infantsVal.' infant'.($infantsVal === 1 ? '' : 's');
    }
    $paxSummary .= ' · '.($cabinLabels[$cabinVal] ?? 'Economy');
    $fromCode = strtoupper(trim((string) $defaultOrigin));
    $toCode = strtoupper(trim((string) $defaultDestination));
@endphp
<section id="ota-flight-search" class="ota-search-widget-section ota-mobile-shell ota-mobile-search {{ $isFloating ? 'ota-search-widget-section--floating' : '' }}" data-airport-widget="{{ $widgetId }}" data-min-date="{{ $minDate }}" data-trip-type="{{ $defaultTripType }}" data-airports-search-url="{{ url('/airports/search') }}" data-layout="{{ $layout }}">
    <div class="ota-search-card {{ $variant === 'standalone' ? 'ota-search-card--standalone' : '' }} {{ $isFloating ? 'ota-search-card--floating ota-search-card--horizontal' : '' }}">
        @unless($isFloating)
        <header class="ota-search-card-banner">
            <div class="ota-search-card-banner__decor" aria-hidden="true">
                <span class="ota-search-card-banner__decor-plane"><i class="fa fa-plane"></i></span>
            </div>
            <div class="ota-search-card-banner__inner">
                <span class="ota-search-card-banner__mark"><i class="fa fa-plane" aria-hidden="true"></i></span>
                <div class="ota-search-card-banner__text">
                    <h3 class="ota-search-card-banner__title">Search flights</h3>
                    <p class="ota-search-card-banner__subtitle">{{ $bannerSubtitle }}</p>
                </div>
            </div>
        </header>
        @endunless

        <div class="ota-search-card-body {{ $isFloating ? 'ota-search-card-body--floating' : '' }}">
            @if ($errors->any())
                <div class="ota-alert ota-alert--danger ota-search-card-alert">
                    <strong>Please fix the following:</strong>
                    <ul class="ota-search-card-alert__list">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($isFloating)
                <div class="ota-search-trip-toolbar" data-floating-trip-options>
                    <div class="ota-search-trip-toolbar__types" role="radiogroup" aria-label="Trip type">
                        <label class="ota-search-trip-radio">
                            <input type="radio" name="{{ $widgetId }}-trip-ui" value="round_trip" data-trip-radio data-trip-tab="round_trip" @checked($defaultTripType === 'round_trip')>
                            <span>Return</span>
                        </label>
                        <label class="ota-search-trip-radio">
                            <input type="radio" name="{{ $widgetId }}-trip-ui" value="one_way" data-trip-radio data-trip-tab="one_way" @checked($defaultTripType === 'one_way')>
                            <span>One-way</span>
                        </label>
                        <label class="ota-search-trip-radio">
                            <input type="radio" name="{{ $widgetId }}-trip-ui" value="multi_city" data-trip-radio data-trip-tab="multi_city" @checked($defaultTripType === 'multi_city')>
                            <span>Multi-city</span>
                        </label>
                    </div>
                    <label class="ota-search-direct" data-direct-filter>
                        <input type="checkbox" name="stops" value="direct" @checked($directChecked)>
                        <span>Direct</span>
                    </label>
                </div>
            @else
                <div class="ota-mobile-search-trust" aria-label="Booking assurances">
                    <span><i class="fa fa-headphones" aria-hidden="true"></i> 24/7 Support</span>
                    <span><i class="fa fa-lock" aria-hidden="true"></i> Secure booking</span>
                </div>
                <div class="ota-search-tabs ota-mobile-trip-tabs" role="tablist" aria-label="Trip type">
                    <button type="button" class="ota-tab {{ $defaultTripType === 'one_way' ? 'ota-tab-active' : '' }}" data-trip-tab="one_way">
                        <span class="ota-tab__icon" aria-hidden="true"><i class="fa fa-plane"></i></span>
                        <span class="ota-tab__label">One way</span>
                    </button>
                    <button type="button" class="ota-tab {{ $defaultTripType === 'round_trip' ? 'ota-tab-active' : '' }}" data-trip-tab="round_trip">
                        <span class="ota-tab__icon" aria-hidden="true"><i class="fa fa-refresh"></i></span>
                        <span class="ota-tab__label">Round trip</span>
                    </button>
                    <button type="button" class="ota-tab {{ $defaultTripType === 'multi_city' ? 'ota-tab-active' : '' }}" data-trip-tab="multi_city">
                        <span class="ota-tab__icon" aria-hidden="true"><i class="fa fa-map-marker"></i></span>
                        <span class="ota-tab__label">Multi-city</span>
                    </button>
                </div>
            @endif

            <form method="get" action="{{ route('flights.results') }}" class="ota-flight-form {{ $isFloating ? 'ota-flight-form--floating ota-flight-form--horizontal' : '' }}" id="{{ $widgetId }}-form" data-flight-search-form novalidate>
            <input type="hidden" name="trip_type" id="{{ $widgetId }}-trip-type" value="{{ $defaultTripType }}">
            <div data-trip-panel="one_way" style="{{ $defaultTripType !== 'one_way' && $defaultTripType !== 'round_trip' ? 'display:none;' : '' }}">
                <div class="{{ $isFloating ? 'ota-search-hbar' : '' }}">
                <div class="ota-from-to-row {{ $isFloating ? 'ota-search-hbar__route' : '' }} ota-mobile-search-route-row">
                    <div class="ota-from-wrap ota-hbar-cell ota-hbar-cell--from ota-mobile-search-route-field">
                        <label class="ota-field-label" for="{{ $widgetId }}-from-display">{{ $isFloating ? 'Leaving from' : 'From' }}</label>
                        <div class="ota-mobile-airport-shell{{ $fromCode !== '' ? ' ota-mobile-airport-shell--has-code' : '' }}">
                            <div class="ota-mobile-airport-viz" data-mobile-airport-viz="from" aria-hidden="true">
                                <span class="ota-mobile-airport-viz__code" data-mobile-airport-code="from">{{ $fromCode }}</span>
                                <span class="ota-mobile-airport-viz__sub" data-mobile-airport-sub="from">{{ $fromCode !== '' ? $defaultOriginDisplay : 'City or airport' }}</span>
                            </div>
                        <div class="ota-input-shell ota-mobile-airport-input">
                            <span class="ota-input-shell__icon" aria-hidden="true"><i class="fa fa-map-marker"></i></span>
                            <input class="ota-field ota-field--shell js-airport-autocomplete" id="{{ $widgetId }}-from-display" name="from_display" data-airport-display="from" data-hidden-target="{{ $widgetId }}-from" type="text" value="{{ $defaultOriginDisplay }}" autocomplete="off" placeholder="City or airport" inputmode="text">
                        </div>
                        <input type="hidden" id="{{ $widgetId }}-from" name="from" data-airport-hidden="from" value="{{ $defaultOrigin }}">
                        <div class="ota-airport-suggest" data-for="{{ $widgetId }}-from" data-airport-dropdown="from" role="listbox" aria-label="Airport suggestions"></div>
                        </div>
                    </div>
                    <div class="ota-swap-wrap ota-hbar-cell ota-hbar-cell--swap ota-mobile-search-swap-field">
                        <span class="ota-field-label ota-swap-wrap__label">Swap</span>
                        <button type="button" class="ota-swap-btn" data-swap-routes title="Swap from / to" aria-label="Swap from and to airports">
                            <i class="fa fa-arrows-h"></i>
                        </button>
                    </div>
                    <div class="ota-to-wrap ota-hbar-cell ota-hbar-cell--to ota-mobile-search-route-field">
                        <label class="ota-field-label" for="{{ $widgetId }}-to-display">{{ $isFloating ? 'Going to' : 'To' }}</label>
                        <div class="ota-mobile-airport-shell{{ $toCode !== '' ? ' ota-mobile-airport-shell--has-code' : '' }}">
                            <div class="ota-mobile-airport-viz" data-mobile-airport-viz="to" aria-hidden="true">
                                <span class="ota-mobile-airport-viz__code" data-mobile-airport-code="to">{{ $toCode }}</span>
                                <span class="ota-mobile-airport-viz__sub" data-mobile-airport-sub="to">{{ $toCode !== '' ? $defaultDestinationDisplay : 'City or airport' }}</span>
                            </div>
                        <div class="ota-input-shell ota-mobile-airport-input">
                            <span class="ota-input-shell__icon" aria-hidden="true"><i class="fa fa-map-marker"></i></span>
                            <input class="ota-field ota-field--shell js-airport-autocomplete" id="{{ $widgetId }}-to-display" name="to_display" data-airport-display="to" data-hidden-target="{{ $widgetId }}-to" type="text" value="{{ $defaultDestinationDisplay }}" autocomplete="off" placeholder="City or airport" inputmode="text">
                        </div>
                        <input type="hidden" id="{{ $widgetId }}-to" name="to" data-airport-hidden="to" value="{{ $defaultDestination }}">
                        <div class="ota-airport-suggest" data-for="{{ $widgetId }}-to" data-airport-dropdown="to" role="listbox" aria-label="Airport suggestions"></div>
                        </div>
                    </div>
                </div>

                <div class="ota-search-dates ota-hbar-cell ota-hbar-cell--dates ota-mobile-search-dates-field {{ $defaultTripType === 'round_trip' ? 'ota-search-dates--round' : '' }} {{ $isFloating ? 'ota-search-dates--floating' : '' }}" data-search-dates>
                    <div class="ota-search-dates__field">
                        <label class="ota-field-label" for="{{ $widgetId }}-depart">{{ $isFloating ? 'Departure date' : 'Departure' }}</label>
                        <div class="ota-input-shell ota-input-shell--date">
                            <input class="ota-field ota-field--date" id="{{ $widgetId }}-depart" name="depart" type="date" value="{{ $defaultDepart }}" min="{{ $minDate }}">
                            <span class="ota-input-shell__icon ota-input-shell__icon--end" aria-hidden="true"><i class="fa fa-calendar"></i></span>
                        </div>
                    </div>
                    <div class="ota-search-dates__field" data-round-return style="{{ $defaultTripType !== 'round_trip' ? 'display:none;' : '' }}">
                        <label class="ota-field-label" for="{{ $widgetId }}-return">{{ $isFloating ? 'Return date' : 'Return' }}</label>
                        <div class="ota-input-shell ota-input-shell--date">
                            <input class="ota-field ota-field--date" id="{{ $widgetId }}-return" name="return_date" type="date" value="{{ $defaultReturnDate }}" min="{{ $defaultDepart ?: $minDate }}">
                            <span class="ota-input-shell__icon ota-input-shell__icon--end" aria-hidden="true"><i class="fa fa-calendar"></i></span>
                        </div>
                    </div>
                </div>

                @if($isFloating)
                <details class="ota-hbar-pax ota-hbar-cell ota-hbar-cell--pax" data-pax-picker>
                    <summary class="ota-hbar-pax__trigger"><span data-pax-summary>{{ $paxSummary }}</span></summary>
                    <div class="ota-hbar-pax__panel">
                        <div class="ota-select-shell">
                            <label class="ota-field-label" for="{{ $widgetId }}-cabin">Cabin</label>
                            <select class="ota-field ota-field--select" id="{{ $widgetId }}-cabin" name="cabin" data-pax-input>
                                <option value="economy" @selected($cabinVal === 'economy')>Economy</option>
                                <option value="premium_economy" @selected($cabinVal === 'premium_economy')>Premium Economy</option>
                                <option value="business" @selected($cabinVal === 'business')>Business</option>
                                <option value="first" @selected($cabinVal === 'first')>First</option>
                            </select>
                        </div>
                        <div class="ota-hbar-pax__row">
                            <label class="ota-field-label" for="{{ $widgetId }}-adults">Adults</label>
                            <select class="ota-field ota-field--select" id="{{ $widgetId }}-adults" name="adults" data-pax-input>
                                @for ($a = 1; $a <= 9; $a++)
                                    <option value="{{ $a }}" @selected($adultsVal === $a)>{{ $a }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="ota-hbar-pax__row">
                            <label class="ota-field-label" for="{{ $widgetId }}-children">Children</label>
                            <select class="ota-field ota-field--select" id="{{ $widgetId }}-children" name="children" data-pax-input>
                                @for ($c = 0; $c <= 8; $c++)
                                    <option value="{{ $c }}" @selected($childrenVal === $c)>{{ $c }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="ota-hbar-pax__row">
                            <label class="ota-field-label" for="{{ $widgetId }}-infants">Infants</label>
                            <select class="ota-field ota-field--select" id="{{ $widgetId }}-infants" name="infants" data-pax-input>
                                @for ($i = 0; $i <= 9; $i++)
                                    <option value="{{ $i }}" @selected($infantsVal === $i)>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </details>
                <div class="ota-hbar-cell ota-hbar-cell--submit">
                    <span class="ota-field-label ota-hbar-submit-label" aria-hidden="true">Search</span>
                    <button type="submit" class="btn ota-search-submit ota-search-submit--floating" data-flight-search-submit>
                        <i class="fa fa-search" aria-hidden="true"></i> Search
                    </button>
                </div>
                @endif
                </div>
            </div>

            <div data-trip-panel="multi_city" style="{{ $defaultTripType !== 'multi_city' ? 'display:none;' : '' }}">
                @unless($isFloating)
                <p class="ota-field-hint" style="margin-bottom:10px;">Add between 2 and 6 segments. Use IATA codes (e.g. LHE) or pick from suggestions.</p>
                @endunless
                <div data-multi-rows>
                    @for ($m = 0; $m < $multiCount; $m++)
                        <div class="ota-multiseg-row {{ $isFloating ? 'ota-multiseg-row--hbar' : '' }}" @unless($isFloating) style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #e2e8f0;" @endunless>
                            <div class="ota-from-to-row {{ $isFloating ? 'ota-search-hbar ota-search-hbar--segment' : '' }}">
                                @if($isFloating)
                                <span class="ota-multiseg-badge">{{ $m + 1 }}</span>
                                @endif
                                <div class="ota-from-wrap {{ $isFloating ? 'ota-hbar-cell ota-hbar-cell--from' : '' }}">
                                    <label class="ota-field-label">{{ $isFloating ? 'Leaving from' : 'From' }}</label>
                                    <input class="ota-field js-airport-autocomplete" id="{{ $widgetId }}-mf-{{ $m }}-from-display" name="multi_from_display[]" data-hidden-target="{{ $widgetId }}-mf-{{ $m }}-from" type="text" value="{{ $multiFrom[$m] ?? '' }}" autocomplete="off" placeholder="City or airport">
                                    <input type="hidden" id="{{ $widgetId }}-mf-{{ $m }}-from" name="multi_from[]" value="{{ $multiFrom[$m] ?? '' }}">
                                    <div class="ota-airport-suggest" data-for="{{ $widgetId }}-mf-{{ $m }}-from" role="listbox"></div>
                                </div>
                                <div class="ota-swap-wrap ota-hbar-cell ota-hbar-cell--swap">
                                    <span class="ota-field-label ota-swap-wrap__label">Swap</span>
                                    <button type="button" class="ota-swap-btn" data-swap-multiseg title="Swap" aria-label="Swap segment airports"><i class="fa fa-arrows-h"></i></button>
                                </div>
                                <div class="ota-to-wrap {{ $isFloating ? 'ota-hbar-cell ota-hbar-cell--to' : '' }}">
                                    <label class="ota-field-label">{{ $isFloating ? 'Going to' : 'To' }}</label>
                                    <input class="ota-field js-airport-autocomplete" id="{{ $widgetId }}-mf-{{ $m }}-to-display" name="multi_to_display[]" data-hidden-target="{{ $widgetId }}-mf-{{ $m }}-to" type="text" value="{{ $multiTo[$m] ?? '' }}" autocomplete="off" placeholder="City or airport">
                                    <input type="hidden" id="{{ $widgetId }}-mf-{{ $m }}-to" name="multi_to[]" value="{{ $multiTo[$m] ?? '' }}">
                                    <div class="ota-airport-suggest" data-for="{{ $widgetId }}-mf-{{ $m }}-to" role="listbox"></div>
                                </div>
                                <div class="{{ $isFloating ? 'ota-hbar-cell ota-hbar-cell--date' : '' }}">
                                    <label class="ota-field-label">Date</label>
                                    <input class="ota-field" name="multi_depart[]" type="date" value="{{ $multiDepart[$m] ?? '' }}" min="{{ $minDate }}">
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
                @if($isFloating)
                <div class="ota-search-hbar-footer">
                    <div class="ota-search-hbar-footer__left">
                        <button type="button" class="ota-btn-soft" data-multi-add>+ Add another flight</button>
                        <button type="button" class="ota-btn-soft" data-multi-remove>Remove last segment</button>
                    </div>
                    <div class="ota-search-hbar-footer__right">

                    <details class="ota-hbar-pax ota-hbar-cell ota-hbar-cell--pax" data-pax-picker>
                        <summary class="ota-hbar-pax__trigger"><span data-pax-summary>{{ $paxSummary }}</span></summary>
                        <div class="ota-hbar-pax__panel">
                            <div class="ota-select-shell">
                                <label class="ota-field-label" for="{{ $widgetId }}-cabin-mc">Cabin</label>
                                <select class="ota-field ota-field--select" id="{{ $widgetId }}-cabin-mc" name="cabin" data-pax-input>
                                    <option value="economy" @selected($cabinVal === 'economy')>Economy</option>
                                    <option value="premium_economy" @selected($cabinVal === 'premium_economy')>Premium Economy</option>
                                    <option value="business" @selected($cabinVal === 'business')>Business</option>
                                    <option value="first" @selected($cabinVal === 'first')>First</option>
                                </select>
                            </div>
                            <div class="ota-hbar-pax__row">
                                <label class="ota-field-label" for="{{ $widgetId }}-adults-mc">Adults</label>
                                <select class="ota-field ota-field--select" id="{{ $widgetId }}-adults-mc" name="adults" data-pax-input>
                                    @for ($a = 1; $a <= 9; $a++)
                                        <option value="{{ $a }}" @selected($adultsVal === $a)>{{ $a }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="ota-hbar-pax__row">
                                <label class="ota-field-label" for="{{ $widgetId }}-children-mc">Children</label>
                                <select class="ota-field ota-field--select" id="{{ $widgetId }}-children-mc" name="children" data-pax-input>
                                    @for ($c = 0; $c <= 8; $c++)
                                        <option value="{{ $c }}" @selected($childrenVal === $c)>{{ $c }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="ota-hbar-pax__row">
                                <label class="ota-field-label" for="{{ $widgetId }}-infants-mc">Infants</label>
                                <select class="ota-field ota-field--select" id="{{ $widgetId }}-infants-mc" name="infants" data-pax-input>
                                    @for ($i = 0; $i <= 9; $i++)
                                        <option value="{{ $i }}" @selected($infantsVal === $i)>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    </details>
                    <div class="ota-hbar-cell ota-hbar-cell--submit">
                        <span class="ota-field-label ota-hbar-submit-label" aria-hidden="true">Search</span>
                        <button type="submit" class="btn ota-search-submit ota-search-submit--floating" data-flight-search-submit>
                            <i class="fa fa-search" aria-hidden="true"></i> Search
                        </button>
                    </div>

                    </div>
                </div>
                @else
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                    <button type="button" class="btn btn-default btn-sm" data-multi-add>Add segment</button>
                    <button type="button" class="btn btn-default btn-sm" data-multi-remove>Remove last segment</button>
                </div>
                @endif
            </div>

            @unless($isFloating)
            <div class="ota-search-pax-grid ota-mobile-search-pax-field">
                <div class="ota-select-shell">
                    <label class="ota-field-label" for="{{ $widgetId }}-cabin">Cabin</label>
                    <div class="ota-select-shell__inner">
                        <span class="ota-select-shell__icon" aria-hidden="true"><i class="fa fa-plane"></i></span>
                        <select class="ota-field ota-field--shell ota-field--select" id="{{ $widgetId }}-cabin" name="cabin">
                            <option value="economy" @selected(old('cabin', 'economy') === 'economy')>Economy</option>
                            <option value="premium_economy" @selected(old('cabin') === 'premium_economy')>Premium Economy</option>
                            <option value="business" @selected(old('cabin') === 'business')>Business</option>
                            <option value="first" @selected(old('cabin') === 'first')>First</option>
                        </select>
                        <span class="ota-select-shell__chev" aria-hidden="true"><i class="fa fa-angle-down"></i></span>
                    </div>
                </div>
                <div class="ota-select-shell">
                    <label class="ota-field-label" for="{{ $widgetId }}-adults">Adults</label>
                    <div class="ota-select-shell__inner">
                        <span class="ota-select-shell__icon" aria-hidden="true"><i class="fa fa-user"></i></span>
                        <select class="ota-field ota-field--shell ota-field--select" id="{{ $widgetId }}-adults" name="adults">
                            @for ($a = 1; $a <= 9; $a++)
                                <option value="{{ $a }}" @selected((int) old('adults', 1) === $a)>{{ $a }}</option>
                            @endfor
                        </select>
                        <span class="ota-select-shell__chev" aria-hidden="true"><i class="fa fa-angle-down"></i></span>
                    </div>
                </div>
                <div class="ota-select-shell">
                    <label class="ota-field-label" for="{{ $widgetId }}-children">Children</label>
                    <div class="ota-select-shell__inner">
                        <span class="ota-select-shell__icon" aria-hidden="true"><i class="fa fa-child"></i></span>
                        <select class="ota-field ota-field--shell ota-field--select" id="{{ $widgetId }}-children" name="children">
                            @for ($c = 0; $c <= 8; $c++)
                                <option value="{{ $c }}" @selected((int) old('children', 0) === $c)>{{ $c }}</option>
                            @endfor
                        </select>
                        <span class="ota-select-shell__chev" aria-hidden="true"><i class="fa fa-angle-down"></i></span>
                    </div>
                </div>
                <div class="ota-select-shell">
                    <label class="ota-field-label" for="{{ $widgetId }}-infants">Infants</label>
                    <div class="ota-select-shell__inner">
                        <span class="ota-select-shell__icon" aria-hidden="true"><i class="fa fa-smile-o"></i></span>
                        <select class="ota-field ota-field--shell ota-field--select" id="{{ $widgetId }}-infants" name="infants">
                            @for ($i = 0; $i <= 9; $i++)
                                <option value="{{ $i }}" @selected((int) old('infants', 0) === $i)>{{ $i }}</option>
                            @endfor
                        </select>
                        <span class="ota-select-shell__chev" aria-hidden="true"><i class="fa fa-angle-down"></i></span>
                    </div>
                </div>
            </div>
            @endunless

            @unless($isFloating)
            <div class="ota-flight-info-bar" role="note">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <span>Adults min 1 · total passengers max 9 · infants cannot exceed adults</span>
            </div>
            @endunless

            @unless($isFloating)
            <button type="submit" class="btn ota-search-submit ota-mobile-search-submit" data-flight-search-submit>
                <i class="fa fa-search" aria-hidden="true"></i> Search flights
            </button>
            @endunless
            @if($showIntro)
                <p class="ota-search-card-footnote">{{ $flightNote }}</p>
            @endif
        </form>
        </div>
    </div>
</section>

@push('scripts')
<script>
(function () {
  var widgets = Array.prototype.slice.call(document.querySelectorAll('[data-airport-widget]'));
  if (!widgets.length) return;

  widgets.forEach(function (widget) {
    if (widget.getAttribute('data-autocomplete-initialized') === 'true') return;
    widget.setAttribute('data-autocomplete-initialized', 'true');

    var minDate = widget.getAttribute('data-min-date') || '';
    var airportsSearchUrl = widget.getAttribute('data-airports-search-url') || '/airports/search';

    function syncTripHidden(val) {
      var el = widget.querySelector('input[name="trip_type"]');
      if (el) el.value = val;
      widget.setAttribute('data-trip-type', val);
    }

    function airportSuggestBox(input) {
      var cell = input.closest('.ota-from-wrap, .ota-to-wrap');
      if (cell) return cell.querySelector('.ota-airport-suggest');
      var row = input.closest('.ota-multiseg-row');
      return row ? row.querySelector('.ota-airport-suggest') : null;
    }

    function setTripType(mode) {
      syncTripHidden(mode);
      var owPanel = widget.querySelector('[data-trip-panel="one_way"]');
      var mcPanel = widget.querySelector('[data-trip-panel="multi_city"]');
      var rr = widget.querySelector('[data-round-return]');
      var datesRow = widget.querySelector('[data-search-dates]');
      if (datesRow) datesRow.classList.toggle('ota-search-dates--round', mode === 'round_trip');
      function setPanelPaxDisabled(panel, disabled) {
        if (!panel) return;
        panel.querySelectorAll('[name="cabin"],[name="adults"],[name="children"],[name="infants"]').forEach(function (el) {
          el.disabled = !!disabled;
        });
      }
      if (mode === 'multi_city') {
        if (owPanel) owPanel.style.display = 'none';
        if (mcPanel) mcPanel.style.display = '';
        setPanelPaxDisabled(owPanel, true);
        setPanelPaxDisabled(mcPanel, false);
      } else {
        if (owPanel) owPanel.style.display = '';
        if (mcPanel) mcPanel.style.display = 'none';
        if (rr) rr.style.display = (mode === 'round_trip') ? '' : 'none';
        setPanelPaxDisabled(owPanel, false);
        setPanelPaxDisabled(mcPanel, true);
      }
      widget.querySelectorAll('.ota-search-tabs .ota-tab').forEach(function (tab) {
        tab.classList.toggle('ota-tab-active', tab.getAttribute('data-trip-tab') === mode);
      });
      widget.querySelectorAll('[data-trip-radio]').forEach(function (radio) {
        radio.checked = radio.value === mode;
      });
      var tripOptions = widget.querySelector('[data-floating-trip-options]');
      if (tripOptions) {
        var types = tripOptions.querySelector('.ota-search-trip-toolbar__types');
        var direct = tripOptions.querySelector('[data-direct-filter]');
        if (types) types.style.display = mode === 'multi_city' ? 'none' : '';
        if (direct) direct.style.display = mode === 'multi_city' ? 'none' : '';
      }
    }

    widget.querySelectorAll('[data-trip-tab]').forEach(function (tab) {
      tab.addEventListener('click', function () {
        setTripType(tab.getAttribute('data-trip-tab'));
      });
    });

    widget.querySelectorAll('[data-trip-radio]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        if (radio.checked) {
          setTripType(radio.value);
        }
      });
    });


    function cabinLabel(val) {
      var map = {economy: 'Economy', premium_economy: 'Premium Economy', business: 'Business', first: 'First'};
      return map[val] || 'Economy';
    }
    function updatePaxSummary() {
      var summary = widget.querySelector('[data-pax-summary]');
      if (!summary) return;
      var adults = parseInt((widget.querySelector('[name="adults"]') || {}).value || '1', 10);
      var children = parseInt((widget.querySelector('[name="children"]') || {}).value || '0', 10);
      var infants = parseInt((widget.querySelector('[name="infants"]') || {}).value || '0', 10);
      var cabin = (widget.querySelector('[name="cabin"]') || {}).value || 'economy';
      var text = adults + ' adult' + (adults === 1 ? '' : 's');
      if (children > 0) text += ', ' + children + ' child' + (children === 1 ? '' : 'ren');
      if (infants > 0) text += ', ' + infants + ' infant' + (infants === 1 ? '' : 's');
      text += ' · ' + cabinLabel(cabin);
      summary.textContent = text;
    }
    widget.querySelectorAll('[data-pax-input]').forEach(function (el) {
      el.addEventListener('change', updatePaxSummary);
    });
    updatePaxSummary();
    var initial = widget.getAttribute('data-trip-type') || 'one_way';
    setTripType(initial);

    var departIn = widget.querySelector('input[name="depart"]');
    var returnIn = widget.querySelector('input[name="return_date"]');
    function bumpReturnMin() {
      if (!departIn || !returnIn) return;
      var d = departIn.value || minDate;
      returnIn.min = d;
      if (returnIn.value && returnIn.value < d) returnIn.value = d;
    }
    if (departIn) departIn.addEventListener('change', bumpReturnMin);
    bumpReturnMin();

    var multiRows = widget.querySelector('[data-multi-rows]');
    var multiAdd = widget.querySelector('[data-multi-add]');
    var multiRemove = widget.querySelector('[data-multi-remove]');
    var multiIdx = (multiRows ? multiRows.querySelectorAll('.ota-multiseg-row').length : 0) || 2;

    function bindMultiSuggestIds(row, idx) {
      var ins = row.querySelectorAll('.js-airport-autocomplete');
      ins.forEach(function (inp, i) {
        var sid = widget.getAttribute('data-airport-widget') + '-m' + idx + '-' + i;
        inp.id = sid;
        var box = airportSuggestBox(inp);
        if (box) box.setAttribute('data-for', sid);
      });
    }

    if (multiAdd && multiRows) {
      multiAdd.addEventListener('click', function () {
        var rows = multiRows.querySelectorAll('.ota-multiseg-row');
        if (rows.length >= 6) return;
        var row = document.createElement('div');
        row.className = 'ota-multiseg-row';
        row.style.marginBottom = '12px';
        row.style.paddingBottom = '12px';
        row.style.borderBottom = '1px solid #e2e8f0';
        multiIdx++;
        row.innerHTML = '<div class="ota-from-to-row">' +
          '<div class="ota-from-wrap"><label class="ota-field-label">From</label>' +
          '<input class="ota-field js-airport-autocomplete" name="multi_from_display[]" data-hidden-target="' + widget.getAttribute('data-airport-widget') + '-m' + multiIdx + '-0-hidden" type="text" autocomplete="off" placeholder="City or airport">' +
          '<input type="hidden" id="' + widget.getAttribute('data-airport-widget') + '-m' + multiIdx + '-0-hidden" name="multi_from[]">' +
          '<div class="ota-airport-suggest" role="listbox"></div></div>' +
          '<div class="ota-to-wrap"><label class="ota-field-label">To</label>' +
          '<input class="ota-field js-airport-autocomplete" name="multi_to_display[]" data-hidden-target="' + widget.getAttribute('data-airport-widget') + '-m' + multiIdx + '-1-hidden" type="text" autocomplete="off" placeholder="City or airport">' +
          '<input type="hidden" id="' + widget.getAttribute('data-airport-widget') + '-m' + multiIdx + '-1-hidden" name="multi_to[]">' +
          '<div class="ota-airport-suggest" role="listbox"></div></div>' +
          '<div><label class="ota-field-label">Date</label>' +
          '<input class="ota-field" name="multi_depart[]" type="date" min="' + minDate + '"></div></div>';
        multiRows.appendChild(row);
        bindMultiSuggestIds(row, multiIdx);
        wireAutocomplete(row);
      });
    }

    if (multiRemove && multiRows) {
      multiRemove.addEventListener('click', function () {
        var rows = multiRows.querySelectorAll('.ota-multiseg-row');
        if (rows.length <= 2) return;
        rows[rows.length - 1].remove();
      });
    }

    var swap = widget.querySelector('[data-swap-routes]');

    var activeBox = null;
    var activeItems = [];
    var activeIndex = -1;
    var timers = new WeakMap();
    var controllers = new WeakMap();

    function closeAll() {
      widget.querySelectorAll('.ota-airport-suggest').forEach(function (box) {
        box.innerHTML = '';
        box.style.display = 'none';
      });
      activeBox = null;
      activeItems = [];
      activeIndex = -1;
    }

    function abortInputRequest(input) {
      var c = controllers.get(input);
      if (c) c.abort();
      controllers.delete(input);
    }

    function renderSuggestions(input, items) {
      var box = airportSuggestBox(input);
      if (!box) return;
      activeBox = box;
      box.innerHTML = '';
      if (!items.length) {
        box.style.display = 'none';
        return;
      }

      items.slice(0, 10).forEach(function (item, index) {
        var code = (item.iata || item.iata_code || '').toUpperCase();
        if (!code) return;
        var row = document.createElement('button');
        row.type = 'button';
        row.className = 'ota-airport-item';
        row.setAttribute('role', 'option');
        row.setAttribute('data-airport-option', '1');
        row.setAttribute('data-iata', code);
        row.setAttribute('data-index', String(index));
        row.setAttribute('data-code', code);
        row.setAttribute('aria-selected', 'false');
        row.innerHTML =
          '<span class="ota-airport-item-code">' + code + '</span>' +
          '<span class="ota-airport-item-main">' + (item.label || ((item.city || '') + ' (' + code + ')')) + '</span>' +
          '<span class="ota-airport-item-sub">' + (item.description || item.name || '') + '</span>';
        row.addEventListener('pointerdown', function (event) {
          event.preventDefault();
          var hiddenTarget = document.getElementById(input.getAttribute('data-hidden-target'));
          input.value = (item.label || code);
          input.setAttribute('data-selected-iata', code);
          if (hiddenTarget) hiddenTarget.value = code;
          var role = input.getAttribute('data-airport-display');
          if (role === 'from' || role === 'to') {
            syncMobileAirportField(role);
          }
          closeAll();
        });
        box.appendChild(row);
      });

      activeItems = Array.prototype.slice.call(box.querySelectorAll('.ota-airport-item'));
      activeIndex = -1;
      box.style.display = activeItems.length ? 'block' : 'none';
    }

    function fetchSuggestions(input) {
      var query = (input.value || '').trim();
      if (query.length < 2) {
        abortInputRequest(input);
        closeAll();
        return;
      }

      abortInputRequest(input);
      var controller = new AbortController();
      controllers.set(input, controller);

      fetch(airportsSearchUrl + (airportsSearchUrl.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(query) + '&limit=10', {
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        signal: controller.signal
      })
        .then(function (r) { return r.ok ? r.json() : []; })
        .then(function (items) {
          if ((input.value || '').trim() !== query) return;
          renderSuggestions(input, Array.isArray(items) ? items : []);
        })
        .catch(function (error) {
          if (error && error.name === 'AbortError') return;
          closeAll();
        });
    }

    function moveHighlight(delta) {
      if (!activeItems.length) return;
      activeIndex += delta;
      if (activeIndex < 0) activeIndex = activeItems.length - 1;
      if (activeIndex >= activeItems.length) activeIndex = 0;
      activeItems.forEach(function (item) {
        item.classList.remove('is-active');
        item.setAttribute('aria-selected', 'false');
      });
      activeItems[activeIndex].classList.add('is-active');
      activeItems[activeIndex].setAttribute('aria-selected', 'true');
      if (typeof activeItems[activeIndex].scrollIntoView === 'function') {
        activeItems[activeIndex].scrollIntoView({ block: 'nearest' });
      }
    }

    function syncMobileAirportField(role) {
      var hidden = widget.querySelector('[data-airport-hidden="' + role + '"]');
      var display = widget.querySelector('[data-airport-display="' + role + '"]');
      var codeEl = widget.querySelector('[data-mobile-airport-code="' + role + '"]');
      var subEl = widget.querySelector('[data-mobile-airport-sub="' + role + '"]');
      var shell = codeEl ? codeEl.closest('.ota-mobile-airport-shell') : null;
      if (!codeEl) return;
      var code = hidden ? String(hidden.value || '').trim().toUpperCase() : '';
      codeEl.textContent = code;
      if (shell) {
        shell.classList.toggle('ota-mobile-airport-shell--has-code', code !== '');
      }
      if (subEl) {
        var sub = display ? String(display.value || '').trim() : '';
        if (code && sub) {
          sub = sub.replace(new RegExp('^\\s*' + code + '\\s*[-–,]?\\s*', 'i'), '').trim();
        }
        subEl.textContent = code ? (sub || 'City or airport') : 'City or airport';
      }
    }

    function syncAllMobileAirports() {
      syncMobileAirportField('from');
      syncMobileAirportField('to');
    }

    function wireAutocomplete(root) {
      var scope = root || widget;
      var localInputs = Array.prototype.slice.call(scope.querySelectorAll('.js-airport-autocomplete'));
      localInputs.forEach(function (input) {
        if (input.getAttribute('data-ac-bound') === '1') return;
        input.setAttribute('data-ac-bound', '1');

        input.addEventListener('input', function () {
          var hiddenTarget = document.getElementById(input.getAttribute('data-hidden-target'));
          var selected = input.getAttribute('data-selected-iata');
          if (selected && input.value.indexOf(selected) === -1) {
            input.removeAttribute('data-selected-iata');
            if (hiddenTarget) hiddenTarget.value = '';
          }
          var role = input.getAttribute('data-airport-display');
          if (role === 'from' || role === 'to') {
            syncMobileAirportField(role);
          }
          var t = timers.get(input);
          if (t) window.clearTimeout(t);
          var newT = window.setTimeout(function () { fetchSuggestions(input); }, 180);
          timers.set(input, newT);
        });

        input.addEventListener('focus', function () {
          if ((input.value || '').trim().length >= 2) fetchSuggestions(input);
        });

        input.addEventListener('blur', function () {
          window.setTimeout(closeAll, 260);
          var raw = (input.value || '').trim();
          if (raw === '') {
            var hidden = document.getElementById(input.getAttribute('data-hidden-target'));
            if (hidden) hidden.value = '';
            input.removeAttribute('data-selected-iata');
          }
        });

        input.addEventListener('keydown', function (event) {
          if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (!activeItems.length) {
              fetchSuggestions(input);
              return;
            }
            moveHighlight(1);
          } else if (event.key === 'ArrowUp') {
            if (!activeItems.length) return;
            event.preventDefault();
            moveHighlight(-1);
          } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            activeItems[activeIndex].click();
          } else if (event.key === 'Escape') {
            closeAll();
          }
        });
      });
    }

    wireAutocomplete(widget);
    syncAllMobileAirports();
    widget.querySelectorAll('[data-airport-display]').forEach(function (inp) {
      inp.addEventListener('change', function () {
        var role = inp.getAttribute('data-airport-display');
        if (role === 'from' || role === 'to') syncMobileAirportField(role);
      });
    });

    widget.querySelectorAll('[data-swap-multiseg]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = btn.closest('.ota-multiseg-row');
        if (!row) return;
        var fromD = row.querySelector('input[name="multi_from_display[]"]');
        var toD = row.querySelector('input[name="multi_to_display[]"]');
        var fromH = row.querySelector('input[name="multi_from[]"]');
        var toH = row.querySelector('input[name="multi_to[]"]');
        if (fromD && toD) {
          var td = fromD.value;
          fromD.value = toD.value;
          toD.value = td;
        }
        if (fromH && toH) {
          var th = fromH.value;
          fromH.value = toH.value;
          toH.value = th;
        }
      });
    });

    if (swap) {
      swap.addEventListener('click', function () {
        var fromDisplay = widget.querySelector('input[name="from_display"]');
        var toDisplay = widget.querySelector('input[name="to_display"]');
        var fromHidden = widget.querySelector('input[name="from"]');
        var toHidden = widget.querySelector('input[name="to"]');
        if (fromDisplay && toDisplay) {
          var tDisplay = fromDisplay.value;
          fromDisplay.value = toDisplay.value;
          toDisplay.value = tDisplay;
        }
        if (fromHidden && toHidden) {
          var tHidden = fromHidden.value;
          fromHidden.value = toHidden.value;
          toHidden.value = tHidden;
        }
        syncAllMobileAirports();
      });
    }

    var form = widget.querySelector('form');
    if (form) {
      form.addEventListener('submit', function (event) {
        var tripType = (widget.querySelector('input[name="trip_type"]') || {}).value || 'one_way';
        if (tripType !== 'multi_city') {
          var fromDisplay = widget.querySelector('input[name="from_display"]');
          var toDisplay = widget.querySelector('input[name="to_display"]');
          var fromHidden = widget.querySelector('input[name="from"]');
          var toHidden = widget.querySelector('input[name="to"]');
          if (fromDisplay && toDisplay && fromHidden && toHidden) {
            if (fromDisplay.value.trim() !== '' && fromHidden.value.trim() === '') {
              event.preventDefault();
              alert('Please select a valid origin airport from the dropdown.');
              fromDisplay.focus();
              return;
            }
            if (toDisplay.value.trim() !== '' && toHidden.value.trim() === '') {
              event.preventDefault();
              alert('Please select a valid destination airport from the dropdown.');
              toDisplay.focus();
              return;
            }
            if (fromHidden.value.trim() !== '' && fromHidden.value.trim() === toHidden.value.trim()) {
              event.preventDefault();
              alert('Origin and destination cannot be the same.');
              toDisplay.focus();
            }
          }
        }
      });
    }

    document.addEventListener('click', function (event) {
      if (!widget.contains(event.target)) closeAll();
    });
  });
})();
</script>
@endpush
