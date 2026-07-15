@php
    $context = $context ?? 'home';
    $isResults = $context === 'results';
    $widgetId = 'hs-'.substr(md5((string) microtime(true).(string) random_int(1000, 999999)), 0, 8);
    $minDate = $minDate ?? now()->format('Y-m-d');
    $defaultTripType = old('trip_type', $defaultTripType ?? 'one_way');
    $defaultOrigin = old('from', $defaultOrigin ?? '');
    $defaultDestination = old('to', $defaultDestination ?? '');
    $defaultOriginDisplay = old('from_display', $defaultOriginDisplay ?? $defaultOrigin);
    $defaultDestinationDisplay = old('to_display', $defaultDestinationDisplay ?? $defaultDestination);
    $defaultDepart = old('depart', $defaultDepart ?? '');
    $defaultReturnDate = old('return_date', $defaultReturnDate ?? '');
    $multiFrom = old('multi_from', []);
    $multiTo = old('multi_to', []);
    $multiDepart = old('multi_depart', []);
    if (! is_array($multiFrom)) { $multiFrom = []; }
    if (! is_array($multiTo)) { $multiTo = []; }
    if (! is_array($multiDepart)) { $multiDepart = []; }
    $multiCount = max(2, count($multiFrom), count($multiTo), count($multiDepart));
    $adultsVal = (int) old('adults', $adults ?? 1);
    $childrenVal = (int) old('children', $children ?? 0);
    $infantsVal = (int) old('infants', $infants ?? 0);
    $cabinVal = old('cabin', $cabin ?? 'economy');
    $cabinLabels = ['economy' => 'Economy', 'premium_economy' => 'Premium Economy', 'business' => 'Business', 'first' => 'First'];
    $paxSummary = $adultsVal.' adult'.($adultsVal === 1 ? '' : 's');
    if ($childrenVal > 0) { $paxSummary .= ', '.$childrenVal.' child'.($childrenVal === 1 ? '' : 'ren'); }
    if ($infantsVal > 0) { $paxSummary .= ', '.$infantsVal.' infant'.($infantsVal === 1 ? '' : 's'); }
    $paxSummary .= ' · '.($cabinLabels[$cabinVal] ?? 'Economy');
    $isRound = $defaultTripType === 'round_trip';
    $fromCode = strtoupper(trim((string) $defaultOrigin));
    $toCode = strtoupper(trim((string) $defaultDestination));
    $isHome = ($context ?? 'home') === 'home' && ! $isResults;
    $mobilePaxSummary = $adultsVal.' Adult'.($adultsVal === 1 ? '' : 's');
    $mobilePaxSummary .= ' · '.($cabinLabels[$cabinVal] ?? 'Economy');
@endphp

<section
    id="{{ $isResults ? 'ota-results-flight-search' : 'ota-flight-search' }}"
    class="ota-hero-search ota-mobile-shell ota-mobile-search{{ $isHome ? ' ota-hero-search--home-mobile' : '' }}{{ $isResults ? ' ota-hero-search--results' : '' }}"
    @if($isResults) data-inline-search @endif
    data-airport-widget="{{ $widgetId }}"
    data-min-date="{{ $minDate }}"
    data-trip-type="{{ $defaultTripType }}"
    data-airports-search-url="{{ url('/airports/search') }}"
    data-hero-search
    tabindex="-1"
>
    <div class="ota-hero-search-card">
        @if ($errors->any())
            <div class="ota-alert ota-alert--danger ota-hero-search-alert">
                <strong>Please fix the following:</strong>
                <ul>
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @unless($isHome)
        <div class="ota-mobile-search-trust" aria-label="Booking assurances">
            <span><i class="fa fa-headphones" aria-hidden="true"></i> 24/7 Support</span>
            <span><i class="fa fa-lock" aria-hidden="true"></i> Secure booking</span>
        </div>
        @endunless

        <div class="ota-hero-search-toolbar" data-floating-trip-options>
            <div class="ota-hero-search-toolbar__modes ota-mobile-trip-modes" role="radiogroup" aria-label="Trip type">
                <label class="ota-hero-search-mode">
                    <input type="radio" name="{{ $widgetId }}-trip-ui" value="round_trip" data-trip-radio data-trip-tab="round_trip" @checked($defaultTripType === 'round_trip')>
                    <span>Return</span>
                </label>
                <label class="ota-hero-search-mode">
                    <input type="radio" name="{{ $widgetId }}-trip-ui" value="one_way" data-trip-radio data-trip-tab="one_way" @checked($defaultTripType === 'one_way')>
                    <span>One-way</span>
                </label>
                <label class="ota-hero-search-mode">
                    <input type="radio" name="{{ $widgetId }}-trip-ui" value="multi_city" data-trip-radio data-trip-tab="multi_city" @checked($defaultTripType === 'multi_city')>
                    <span>Multi-city</span>
                </label>
            </div>
        </div>

        <form
            method="get"
            action="{{ route('flights.results') }}"
            class="ota-hero-search-form"
            id="{{ $widgetId }}-form"
            data-flight-search-form
            @if($isResults) data-inline-form @endif
            novalidate
        >
            <input type="hidden" name="trip_type" id="{{ $widgetId }}-trip-type" value="{{ $defaultTripType }}">

            <div
                data-trip-panel="one_way"
                class="ota-hero-search-panel"
                @if($defaultTripType === 'multi_city') hidden @endif
            >
                <div
                    class="ota-hero-search-row ota-hero-search-grid {{ $isRound ? 'ota-hero-search-row--return' : '' }}"
                    data-hero-search-row="simple"
                    data-search-dates
                >
                    <div class="ota-mobile-route-stack">
                    <div class="ota-hero-search-field ota-hero-search-field--from ota-from-wrap ota-mobile-search-route-field">
                        <label class="ota-hero-search-label" for="{{ $widgetId }}-from-display">{{ $isHome ? 'From' : 'Leaving from' }}</label>
                        <div class="ota-mobile-airport-shell{{ $fromCode !== '' ? ' ota-mobile-airport-shell--has-code' : '' }}">
                            <div class="ota-mobile-airport-viz" data-mobile-airport-viz="from" aria-hidden="true">
                                <span class="ota-mobile-airport-viz__code" data-mobile-airport-code="from">{{ $fromCode }}</span>
                                <span class="ota-mobile-airport-viz__sub" data-mobile-airport-sub="from">{{ $fromCode !== '' ? $defaultOriginDisplay : 'City or airport' }}</span>
                            </div>
                        <div class="ota-hero-search-input ota-mobile-airport-input">
                            <i class="fa fa-map-marker" aria-hidden="true"></i>
                            <input
                                class="ota-hero-search-control js-airport-autocomplete"
                                id="{{ $widgetId }}-from-display"
                                name="from_display"
                                data-airport-display="from"
                                data-hidden-target="{{ $widgetId }}-from"
                                type="text"
                                value="{{ $defaultOriginDisplay }}"
                                autocomplete="off"
                                placeholder="City or airport"
                            >
                        </div>
                        <input type="hidden" id="{{ $widgetId }}-from" name="from" data-airport-hidden="from" value="{{ $defaultOrigin }}">
                        <div class="ota-airport-suggest" data-for="{{ $widgetId }}-from" data-airport-dropdown="from" role="listbox" aria-label="Airport suggestions"></div>
                        </div>
                    </div>

                    <div class="ota-hero-search-field ota-hero-search-field--swap ota-mobile-search-swap-field">
                        <span class="ota-hero-search-label ota-hero-search-label--sr">Swap</span>
                        <button type="button" class="ota-hero-search-swap ota-mobile-route-swap" data-swap-routes title="Swap from / to" aria-label="Swap from and to airports">
                            <i class="fa fa-arrows-v" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="ota-hero-search-field ota-hero-search-field--to ota-to-wrap ota-mobile-search-route-field">
                        <label class="ota-hero-search-label" for="{{ $widgetId }}-to-display">{{ $isHome ? 'To' : 'Going to' }}</label>
                        <div class="ota-mobile-airport-shell{{ $toCode !== '' ? ' ota-mobile-airport-shell--has-code' : '' }}">
                            <div class="ota-mobile-airport-viz" data-mobile-airport-viz="to" aria-hidden="true">
                                <span class="ota-mobile-airport-viz__code" data-mobile-airport-code="to">{{ $toCode }}</span>
                                <span class="ota-mobile-airport-viz__sub" data-mobile-airport-sub="to">{{ $toCode !== '' ? $defaultDestinationDisplay : 'City or airport' }}</span>
                            </div>
                        <div class="ota-hero-search-input ota-mobile-airport-input">
                            <i class="fa fa-map-marker" aria-hidden="true"></i>
                            <input
                                class="ota-hero-search-control js-airport-autocomplete"
                                id="{{ $widgetId }}-to-display"
                                name="to_display"
                                data-airport-display="to"
                                data-hidden-target="{{ $widgetId }}-to"
                                type="text"
                                value="{{ $defaultDestinationDisplay }}"
                                autocomplete="off"
                                placeholder="City or airport"
                            >
                        </div>
                        <input type="hidden" id="{{ $widgetId }}-to" name="to" data-airport-hidden="to" value="{{ $defaultDestination }}">
                        <div class="ota-airport-suggest" data-for="{{ $widgetId }}-to" data-airport-dropdown="to" role="listbox" aria-label="Airport suggestions"></div>
                        </div>
                    </div>
                    </div>

                    <div class="ota-hero-search-dates ota-mobile-search-dates-field" data-hero-search-dates>
                        <div class="ota-hero-search-dates__pair" data-return-date-pair>
                            <div class="ota-hero-search-field ota-hero-search-field--date ota-hero-search-field--depart" data-return-date-part="depart">
                                <label class="ota-hero-search-label" for="{{ $isHome ? $widgetId.'-mobile-depart-trigger' : ($isRound ? $widgetId.'-depart-trigger' : $widgetId.'-depart') }}">
                                    <span class="ota-hero-search-date-label-long">{{ $isHome ? 'Departure date' : 'Departure date' }}</span>
                                    <span class="ota-hero-search-date-label-short">Depart</span>
                                </label>
                                <button type="button" class="ota-mobile-date-trigger" id="{{ $widgetId }}-mobile-depart-trigger" data-mobile-depart-trigger>
                                    <i class="fa fa-calendar" aria-hidden="true"></i>
                                    <span data-mobile-depart-label>Select date</span>
                                </button>
                                <button type="button" class="ota-hero-search-date-trigger" id="{{ $widgetId }}-depart-trigger" data-return-range-trigger="depart" aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $widgetId }}-return-range-picker">
                                    <span class="ota-hero-search-date-trigger__label" data-return-range-trigger-label="depart">Select departure</span>
                                    <i class="fa fa-calendar" aria-hidden="true"></i>
                                </button>
                                <div class="ota-hero-search-input ota-hero-search-input--date ota-hero-search-input--native">
                                    <input class="ota-hero-search-control ota-hero-search-control--date ota-hero-search-date-native" id="{{ $widgetId }}-depart" name="depart" type="date" data-return-range-native="depart" value="{{ $defaultDepart }}" min="{{ $minDate }}" autocomplete="off" @if($isRound) tabindex="-1" aria-hidden="true" @endif>
                                    <span class="ota-hero-search-input__icon" aria-hidden="true"><i class="fa fa-calendar"></i></span>
                                </div>
                            </div>

                            <div class="ota-hero-search-field ota-hero-search-field--date ota-hero-search-field--return" data-round-return data-return-date-part="return" @unless($isRound) hidden @endunless>
                                <label class="ota-hero-search-label" for="{{ $widgetId }}-return-trigger">
                                    <span class="ota-hero-search-date-label-long">Return date</span>
                                    <span class="ota-hero-search-date-label-short">Return</span>
                                </label>
                                <button type="button" class="ota-hero-search-date-trigger" id="{{ $widgetId }}-return-trigger" data-return-range-trigger="return" aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $widgetId }}-return-range-picker">
                                    <span class="ota-hero-search-date-trigger__label" data-return-range-trigger-label="return">Select return</span>
                                    <i class="fa fa-calendar" aria-hidden="true"></i>
                                </button>
                                <div class="ota-hero-search-input ota-hero-search-input--date ota-hero-search-input--native">
                                    <input class="ota-hero-search-control ota-hero-search-control--date ota-hero-search-date-native" id="{{ $widgetId }}-return" name="return_date" type="date" data-return-range-native="return" value="{{ $defaultReturnDate }}" min="{{ $defaultDepart ?: $minDate }}" autocomplete="off" @if($isRound) tabindex="-1" aria-hidden="true" @endif>
                                    <span class="ota-hero-search-input__icon" aria-hidden="true"><i class="fa fa-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="ota-return-range-picker" id="{{ $widgetId }}-return-range-picker" data-return-range-picker hidden role="dialog" aria-modal="true" aria-label="Select departure and return dates">
                            <div class="ota-return-range-picker__nav">
                                <button type="button" class="ota-return-range-picker__nav-btn" data-return-range-prev aria-label="Previous month"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>
                                <button type="button" class="ota-return-range-picker__nav-btn" data-return-range-next aria-label="Next month"><i class="fa fa-chevron-right" aria-hidden="true"></i></button>
                            </div>
                            <p class="ota-return-range-picker__hint" data-return-range-hint hidden></p>
                            <div class="ota-return-range-picker__months calendar" data-return-range-months></div>
                            <div class="ota-return-range-picker__footer">
                                <button type="button" class="ota-return-range-picker__btn ota-return-range-picker__btn--ghost" data-return-range-clear>Clear</button>
                                <button type="button" class="ota-return-range-picker__btn ota-return-range-picker__btn--ghost" data-return-range-cancel>Cancel</button>
                                <button type="button" class="ota-return-range-picker__btn ota-return-range-picker__btn--apply" data-return-range-apply>Apply</button>
                            </div>
                        </div>
                    </div>

                    @include('frontend.partials.ota-hero-search-travellers', [
                        'widgetId' => $widgetId,
                        'paxId' => 'ow',
                        'paxSummary' => $paxSummary,
                        'mobilePaxSummary' => $mobilePaxSummary,
                        'isHomeMobile' => $isHome,
                        'adultsVal' => $adultsVal,
                        'childrenVal' => $childrenVal,
                        'infantsVal' => $infantsVal,
                        'cabinVal' => $cabinVal,
                    ])

                    <div class="ota-hero-search-field ota-hero-search-field--submit ota-mobile-search-submit-field">
                        <span class="ota-hero-search-label ota-hero-search-label--sr">Search</span>
                        <button type="submit" class="ota-hero-search-submit ota-mobile-search-submit" data-flight-search-submit @if($isResults) data-inline-submit @endif>
                            <i class="fa fa-search" aria-hidden="true"></i>
                            <span>Search</span>
                        </button>
                    </div>
                </div>
            </div>

            <div
                data-trip-panel="multi_city"
                class="ota-hero-search-panel ota-hero-search-panel--multi"
                @if($defaultTripType !== 'multi_city') hidden @endif
            >
                <div data-multi-rows class="ota-hero-search-segments">
                    @for ($m = 0; $m < $multiCount; $m++)
                        <div class="ota-hero-search-segment ota-multiseg-row" data-segment-index="{{ $m + 1 }}">
                            <span class="ota-hero-search-segment__badge" aria-hidden="true">{{ $m + 1 }}</span>
                            <div class="ota-hero-search-segment__grid">
                                <div class="ota-hero-search-field ota-hero-search-field--from ota-from-wrap">
                                    <label class="ota-hero-search-label">Leaving from</label>
                                    <div class="ota-hero-search-input">
                                        <i class="fa fa-map-marker" aria-hidden="true"></i>
                                        <input
                                            class="ota-hero-search-control js-airport-autocomplete"
                                            id="{{ $widgetId }}-mf-{{ $m }}-from-display"
                                            name="multi_from_display[]"
                                            data-hidden-target="{{ $widgetId }}-mf-{{ $m }}-from"
                                            type="text"
                                            value="{{ $multiFrom[$m] ?? '' }}"
                                            autocomplete="off"
                                            placeholder="City or airport"
                                        >
                                    </div>
                                    <input type="hidden" id="{{ $widgetId }}-mf-{{ $m }}-from" name="multi_from[]" value="{{ $multiFrom[$m] ?? '' }}">
                                    <div class="ota-airport-suggest" data-for="{{ $widgetId }}-mf-{{ $m }}-from" role="listbox"></div>
                                </div>
                                <div class="ota-hero-search-field ota-hero-search-field--swap">
                                    <span class="ota-hero-search-label ota-hero-search-label--sr">Swap</span>
                                    <button type="button" class="ota-hero-search-swap" data-swap-multiseg title="Swap" aria-label="Swap segment airports">
                                        <i class="fa fa-exchange" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="ota-hero-search-field ota-hero-search-field--to ota-to-wrap">
                                    <label class="ota-hero-search-label">Going to</label>
                                    <div class="ota-hero-search-input">
                                        <i class="fa fa-map-marker" aria-hidden="true"></i>
                                        <input
                                            class="ota-hero-search-control js-airport-autocomplete"
                                            id="{{ $widgetId }}-mf-{{ $m }}-to-display"
                                            name="multi_to_display[]"
                                            data-hidden-target="{{ $widgetId }}-mf-{{ $m }}-to"
                                            type="text"
                                            value="{{ $multiTo[$m] ?? '' }}"
                                            autocomplete="off"
                                            placeholder="City or airport"
                                        >
                                    </div>
                                    <input type="hidden" id="{{ $widgetId }}-mf-{{ $m }}-to" name="multi_to[]" value="{{ $multiTo[$m] ?? '' }}">
                                    <div class="ota-airport-suggest" data-for="{{ $widgetId }}-mf-{{ $m }}-to" role="listbox"></div>
                                </div>
                                <div class="ota-hero-search-field ota-hero-search-field--date">
                                    <label class="ota-hero-search-label">Date</label>
                                    <div class="ota-hero-search-input ota-hero-search-input--date">
                                        <input class="ota-hero-search-control ota-hero-search-control--date" name="multi_depart[]" type="date" value="{{ $multiDepart[$m] ?? '' }}" min="{{ $minDate }}">
                                        <span class="ota-hero-search-input__icon" aria-hidden="true"><i class="fa fa-calendar"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>

                <div class="ota-hero-search-mc-footer">
                    <div class="ota-hero-search-mc-footer__actions">
                        <button type="button" class="ota-hero-search-btn-soft" data-multi-add>+ Add another flight</button>
                        <button type="button" class="ota-hero-search-btn-soft" data-multi-remove>Remove last segment</button>
                    </div>
                    <div class="ota-hero-search-mc-footer__book">
                        @include('frontend.partials.ota-hero-search-travellers', [
                            'widgetId' => $widgetId,
                            'paxId' => 'mc',
                            'paxSummary' => $paxSummary,
                            'adultsVal' => $adultsVal,
                            'childrenVal' => $childrenVal,
                            'infantsVal' => $infantsVal,
                            'cabinVal' => $cabinVal,
                        ])
                        <div class="ota-hero-search-field ota-hero-search-field--submit">
                            <span class="ota-hero-search-label ota-hero-search-label--sr">Search</span>
                            <button type="submit" class="ota-hero-search-submit" data-flight-search-submit @if($isResults) data-inline-submit @endif>
                                <i class="fa fa-search" aria-hidden="true"></i>
                                <span>Search</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div class="ota-hero-search-validate-overlay" data-flight-search-validate-overlay hidden>
            <div class="ota-hero-search-validate-modal" role="alertdialog" aria-modal="true" aria-labelledby="{{ $widgetId }}-validate-title">
                <h3 class="ota-hero-search-validate-modal__title" id="{{ $widgetId }}-validate-title">Please complete your search</h3>
                <p class="ota-hero-search-validate-modal__message" data-flight-search-validate-message></p>
                <button type="button" class="ota-hero-search-validate-modal__btn" data-flight-search-validate-close>OK</button>
            </div>
        </div>
        @if($isResults)
            <p class="ota-hero-search-inline-status" data-inline-status role="status" aria-live="polite"></p>
            <p class="ota-hero-search-inline-error" data-inline-error role="alert" hidden></p>
        @endif
    </div>
</section>

@push('styles')
<style>
.ota-hero-search-validate-overlay {
  position: fixed;
  inset: 0;
  z-index: 10050;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(15, 23, 42, 0.45);
}
.ota-hero-search-validate-overlay[hidden] {
  display: none !important;
}
.ota-hero-search-validate-modal {
  width: min(420px, 100%);
  padding: 1.25rem 1.35rem;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
}
.ota-hero-search-validate-modal__title {
  margin: 0 0 0.5rem;
  font-size: 1.1rem;
  font-weight: 700;
  color: #0f172a;
}
.ota-hero-search-validate-modal__message {
  margin: 0 0 1rem;
  font-size: 14px;
  line-height: 1.45;
  color: #b91c1c;
}
.ota-hero-search-validate-modal__btn {
  min-height: 44px;
  padding: 0.5rem 1rem;
  border: 0;
  border-radius: 10px;
  background: var(--client-primary, #0c4a6e);
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}
</style>
@endpush

@push('scripts')
<script>
(function () {
  var widgets = Array.prototype.slice.call(document.querySelectorAll('[data-hero-search][data-airport-widget]'));
  if (!widgets.length) return;

  widgets.forEach(function (widget) {
    if (widget.getAttribute('data-autocomplete-initialized') === 'true') return;
    widget.setAttribute('data-autocomplete-initialized', 'true');

    var minDate = widget.getAttribute('data-min-date') || '';
    var airportsSearchUrl = widget.getAttribute('data-airports-search-url') || '/airports/search';

    function focusField(el) {
      if (!el || el.disabled) return;
      if (el.type === 'date' && el.getAttribute('data-return-range-native') && currentTripType() === 'round_trip') {
        var part = el.getAttribute('data-return-range-native') || 'depart';
        var trig = widget.querySelector('[data-return-range-trigger="' + part + '"]');
        openReturnRangePicker(trig);
        return;
      }
      el.focus();
      if (el.tagName === 'SUMMARY') {
        var details = el.closest('details');
        if (details) details.open = true;
        return;
      }
      if (el.type === 'date' && typeof el.showPicker === 'function') {
        try { el.showPicker(); } catch (e) {}
      }
    }

    function activePaxTrigger() {
      var pickers = widget.querySelectorAll('[data-pax-picker]');
      for (var p = 0; p < pickers.length; p++) {
        var adults = pickers[p].querySelector('[name="adults"]');
        if (adults && !adults.disabled) {
          return pickers[p].querySelector('.ota-hero-search-pax__trigger');
        }
      }
      return null;
    }

    function nextAfterAirportPick(input) {
      var role = input.getAttribute('data-airport-display');
      if (role === 'from') {
        focusField(widget.querySelector('input[name="to_display"]'));
        return;
      }
      if (role === 'to') {
        if (currentTripType() === 'round_trip') {
          openReturnRangePicker(widget.querySelector('[data-return-range-trigger="depart"]'));
        } else {
          focusField(widget.querySelector('input[name="depart"]'));
        }
        return;
      }
      var inputName = input.getAttribute('name') || '';
      var row = input.closest('.ota-multiseg-row');
      if (!row) return;
      if (inputName.indexOf('multi_from_display') !== -1) {
        focusField(row.querySelector('input[name="multi_to_display[]"]'));
        return;
      }
      if (inputName.indexOf('multi_to_display') !== -1) {
        focusField(row.querySelector('input[name="multi_depart[]"]'));
      }
    }

    function nextAfterDepart() {
      if (currentTripType() === 'round_trip') {
        openReturnRangePicker(widget.querySelector('[data-return-range-trigger="depart"]'));
        return;
      }
      focusField(activePaxTrigger());
    }

    function nextAfterMultiDepart(input) {
      if (!input || !input.value) return;
      var row = input.closest('.ota-multiseg-row');
      if (!row) return;
      var rows = widget.querySelectorAll('.ota-multiseg-row');
      var idx = Array.prototype.indexOf.call(rows, row);
      if (idx >= 0 && idx < rows.length - 1) {
        focusField(rows[idx + 1].querySelector('input[name="multi_from_display[]"]'));
        return;
      }
      focusField(activePaxTrigger());
    }

    function bindMultiDepartInputs(root) {
      (root || widget).querySelectorAll('input[name="multi_depart[]"]').forEach(function (inp) {
        if (inp.getAttribute('data-depart-bound') === '1') return;
        inp.setAttribute('data-depart-bound', '1');
        inp.addEventListener('change', function () {
          if (inp.value) nextAfterMultiDepart(inp);
        });
      });
    }

    function closePaxPickers() {
      widget.querySelectorAll('[data-pax-picker]').forEach(function (details) {
        details.open = false;
        details.classList.remove('ota-hero-search-field--pax-flip');
      });
    }

    function positionPaxPanel(details) {
      if (!details || !details.open) return;
      var panel = details.querySelector('.ota-hero-search-pax__panel');
      if (!panel) return;
      if (window.matchMedia('(max-width: 767.98px)').matches) {
        details.classList.remove('ota-hero-search-field--pax-flip');
        return;
      }
      details.classList.remove('ota-hero-search-field--pax-flip');
      var rect = details.getBoundingClientRect();
      var panelH = panel.getBoundingClientRect().height || panel.offsetHeight || 300;
      var gap = 8;
      var below = window.innerHeight - rect.bottom - gap;
      var above = rect.top - gap;
      if (panelH > below && above > below) {
        details.classList.add('ota-hero-search-field--pax-flip');
      }
    }

    function bindPaxPickerUi() {
      widget.querySelectorAll('[data-pax-picker]').forEach(function (details) {
        var panel = details.querySelector('.ota-hero-search-pax__panel');
        if (panel && !panel.getAttribute('data-pax-panel-bound')) {
          panel.setAttribute('data-pax-panel-bound', '1');
          panel.addEventListener('click', function (e) {
            e.stopPropagation();
          });
        }
        if (details.getAttribute('data-pax-picker-bound') === '1') return;
        details.setAttribute('data-pax-picker-bound', '1');
        details.addEventListener('toggle', function () {
          if (details.open) {
            closeReturnRangePicker();
            widget.querySelectorAll('[data-pax-picker]').forEach(function (other) {
              if (other !== details) other.open = false;
            });
            window.requestAnimationFrame(function () {
              positionPaxPanel(details);
            });
          } else {
            details.classList.remove('ota-hero-search-field--pax-flip');
          }
        });
      });

      if (widget.getAttribute('data-pax-global-bound') === '1') return;
      widget.setAttribute('data-pax-global-bound', '1');
      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-pax-picker]')) return;
        closePaxPickers();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePaxPickers();
      });
      window.addEventListener('resize', function () {
        widget.querySelectorAll('[data-pax-picker][open]').forEach(positionPaxPanel);
      });
    }

    function showValidateModal(message, focusEl) {
      var overlay = widget.querySelector('[data-flight-search-validate-overlay]');
      var msgEl = widget.querySelector('[data-flight-search-validate-message]');
      if (msgEl) msgEl.textContent = message;
      if (overlay) overlay.hidden = false;
      if (focusEl) {
        window.setTimeout(function () { focusField(focusEl); }, 120);
      }
    }

    function hideValidateModal() {
      var overlay = widget.querySelector('[data-flight-search-validate-overlay]');
      if (overlay) overlay.hidden = true;
    }

    function isIataCode(value) {
      return /^[A-Z0-9]{3}$/i.test((value || '').trim());
    }

    function validateHeroForm(form) {
      var tripType = (form.querySelector('input[name="trip_type"]') || {}).value || 'one_way';

      if (tripType === 'multi_city') {
        var rows = form.querySelectorAll('.ota-multiseg-row');
        for (var i = 0; i < rows.length; i++) {
          var fromH = rows[i].querySelector('input[name="multi_from[]"]');
          var toH = rows[i].querySelector('input[name="multi_to[]"]');
          var fromD = rows[i].querySelector('input[name="multi_from_display[]"]');
          var toD = rows[i].querySelector('input[name="multi_to_display[]"]');
          var dep = rows[i].querySelector('input[name="multi_depart[]"]');
          var seg = i + 1;
          if (!isIataCode(fromH && fromH.value)) {
            return {message: 'Segment ' + seg + ': please select a valid origin airport.', focus: fromD || fromH};
          }
          if (fromD && fromD.value.trim() && !(fromH && fromH.value.trim())) {
            return {message: 'Segment ' + seg + ': please select a valid origin airport from the dropdown.', focus: fromD};
          }
          if (!isIataCode(toH && toH.value)) {
            return {message: 'Segment ' + seg + ': please select a valid destination airport.', focus: toD || toH};
          }
          if (toD && toD.value.trim() && !(toH && toH.value.trim())) {
            return {message: 'Segment ' + seg + ': please select a valid destination airport from the dropdown.', focus: toD};
          }
          if ((fromH.value || '').trim().toUpperCase() === (toH.value || '').trim().toUpperCase()) {
            return {message: 'Segment ' + seg + ': origin and destination cannot be the same.', focus: toD || toH};
          }
          if (!dep || !dep.value) {
            return {message: 'Segment ' + seg + ': please choose a departure date.', focus: dep};
          }
          if (minDate && dep.value < minDate) {
            return {message: 'Segment ' + seg + ': departure date cannot be in the past.', focus: dep};
          }
        }
        return null;
      }

      var fromDisplay = form.querySelector('input[name="from_display"]');
      var toDisplay = form.querySelector('input[name="to_display"]');
      var fromHidden = form.querySelector('input[name="from"]');
      var toHidden = form.querySelector('input[name="to"]');
      var depart = form.querySelector('input[name="depart"]');
      var returnDate = form.querySelector('input[name="return_date"]');

      if (!isIataCode(fromHidden && fromHidden.value)) {
        return {message: 'Please select a valid origin airport.', focus: fromDisplay || fromHidden};
      }
      if (fromDisplay && fromDisplay.value.trim() && !(fromHidden && fromHidden.value.trim())) {
        return {message: 'Please select a valid origin airport from the dropdown.', focus: fromDisplay};
      }
      if (!isIataCode(toHidden && toHidden.value)) {
        return {message: 'Please select a valid destination airport.', focus: toDisplay || toHidden};
      }
      if (toDisplay && toDisplay.value.trim() && !(toHidden && toHidden.value.trim())) {
        return {message: 'Please select a valid destination airport from the dropdown.', focus: toDisplay};
      }
      if ((fromHidden.value || '').trim().toUpperCase() === (toHidden.value || '').trim().toUpperCase()) {
        return {message: 'Origin and destination cannot be the same.', focus: toDisplay || toHidden};
      }
      if (!depart || !depart.value) {
        return {message: 'Please choose a departure date.', focus: depart};
      }
      if (minDate && depart.value < minDate) {
        return {message: 'Departure date cannot be in the past.', focus: depart};
      }
      if (tripType === 'round_trip') {
        if (!returnDate || !returnDate.value) {
          return {message: 'Please choose a return date.', focus: returnDate};
        }
        if (returnDate.value < depart.value) {
          return {message: 'Return date must be on or after the departure date.', focus: returnDate};
        }
      }
      return null;
    }

    var validateClose = widget.querySelector('[data-flight-search-validate-close]');
    var validateOverlay = widget.querySelector('[data-flight-search-validate-overlay]');
    if (validateClose) {
      validateClose.addEventListener('click', hideValidateModal);
    }
    if (validateOverlay) {
      validateOverlay.addEventListener('click', function (e) {
        if (e.target === validateOverlay) hideValidateModal();
      });
    }

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

    var departIn = widget.querySelector('input[name="depart"]');
    var returnIn = widget.querySelector('input[name="return_date"]');
    var datesWrap = widget.querySelector('[data-hero-search-dates]');
    var rangePicker = widget.querySelector('[data-return-range-picker]');
    var rangeMonthsEl = widget.querySelector('[data-return-range-months]');
    var rangeHint = widget.querySelector('[data-return-range-hint]');
    var rangePickerOpen = false;
    var viewYear = 0;
    var viewMonth = 0;
    var draftDepart = null;
    var draftReturn = null;
    var hasUserStartedRangeSelection = false;
    var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var dowNames = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    function currentTripType() {
      return (widget.querySelector('input[name="trip_type"]') || {}).value || 'one_way';
    }

    function isRoundTripMode() {
      return currentTripType() === 'round_trip';
    }

    function pad2(n) {
      return (n < 10 ? '0' : '') + n;
    }

    function compareIso(a, b) {
      if (!a || !b) return a ? 1 : (b ? -1 : 0);
      if (a === b) return 0;
      return a < b ? -1 : 1;
    }

    function parseIso(iso) {
      if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return null;
      var p = iso.split('-');
      return { y: parseInt(p[0], 10), m: parseInt(p[1], 10) - 1, d: parseInt(p[2], 10) };
    }

    function isoFromParts(y, m, d) {
      return y + '-' + pad2(m + 1) + '-' + pad2(d);
    }

    function isoToday() {
      var n = new Date();
      return isoFromParts(n.getFullYear(), n.getMonth(), n.getDate());
    }

    function syncTriggersFromInputs() {
      widget.querySelectorAll('[data-return-range-trigger-label]').forEach(function (el) {
        var part = el.getAttribute('data-return-range-trigger-label');
        var val = part === 'return' ? (returnIn && returnIn.value) : (departIn && departIn.value);
        el.textContent = val ? formatIsoDateLabel(val) : (part === 'return' ? 'Select return' : 'Select departure');
      });
    }

    function initDraftFromCommitted() {
      draftDepart = departIn && departIn.value ? departIn.value : null;
      draftReturn = returnIn && returnIn.value ? returnIn.value : null;
    }

    function hasDraftDepart() {
      return draftDepart != null && draftDepart !== '';
    }

    function hasDraftReturn() {
      return draftReturn != null && draftReturn !== '';
    }

    function setViewFromDraft() {
      var ref = draftDepart || draftReturn || minDate || isoToday();
      var p = parseIso(ref);
      if (p) {
        viewYear = p.y;
        viewMonth = p.m;
        return;
      }
      var now = new Date();
      viewYear = now.getFullYear();
      viewMonth = now.getMonth();
    }

    function clearRangeHint() {
      if (!rangeHint) return;
      rangeHint.hidden = true;
      rangeHint.textContent = '';
    }

    function updateRangeHint() {
      if (!rangeHint) return;
      if (!draftDepart) {
        rangeHint.textContent = 'Select your departure date';
        rangeHint.hidden = false;
        return;
      }
      if (!hasDraftReturn()) {
        rangeHint.textContent = 'Select a return date.';
        rangeHint.hidden = false;
        return;
      }
      rangeHint.hidden = true;
    }

    function setRangeTriggersExpanded(open) {
      widget.querySelectorAll('[data-return-range-trigger]').forEach(function (btn) {
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    function handleReturnRangeDayClick(iso) {
      if (!iso || (minDate && compareIso(iso, minDate) < 0)) return;

      if (!hasUserStartedRangeSelection) {
        draftDepart = iso;
        draftReturn = null;
        hasUserStartedRangeSelection = true;
        clearRangeHint();
        renderReturnRangePickerMonths();
        return;
      }

      if (hasDraftDepart() && !hasDraftReturn()) {
        if (compareIso(iso, draftDepart) >= 0) {
          draftReturn = iso;
        } else {
          draftDepart = iso;
          draftReturn = null;
        }
        clearRangeHint();
        renderReturnRangePickerMonths();
        return;
      }

      if (hasDraftDepart() && hasDraftReturn()) {
        draftDepart = iso;
        draftReturn = null;
        clearRangeHint();
        renderReturnRangePickerMonths();
        return;
      }

      draftDepart = iso;
      draftReturn = null;
      clearRangeHint();
      renderReturnRangePickerMonths();
    }

    function renderMonthPanel(year, month) {
      var html = '<div class="ota-return-range-picker__month">';
      html += '<p class="ota-return-range-picker__month-title">' + monthNames[month] + ' ' + year + '</p>';
      html += '<div class="ota-return-range-picker__grid ota-return-range-picker__grid--head">';
      for (var h = 0; h < 7; h++) {
        html += '<span class="ota-return-range-picker__dow">' + dowNames[h] + '</span>';
      }
      html += '</div><div class="ota-return-range-picker__grid">';
      var firstDow = new Date(year, month, 1).getDay();
      var dim = new Date(year, month + 1, 0).getDate();
      var i;
      for (i = 0; i < firstDow; i++) {
        html += '<span class="ota-return-range-picker__pad" aria-hidden="true"></span>';
      }
      for (i = 1; i <= dim; i++) {
        var iso = isoFromParts(year, month, i);
        var cls = 'ota-return-range-picker__day';
        var disabled = minDate && compareIso(iso, minDate) < 0;
        if (disabled) cls += ' ota-return-range-picker__day--disabled';
        if (iso === draftDepart) cls += ' ota-return-range-picker__day--start';
        if (iso === draftReturn) cls += ' ota-return-range-picker__day--end';
        if (hasDraftDepart() && hasDraftReturn() && compareIso(iso, draftDepart) > 0 && compareIso(iso, draftReturn) < 0) {
          cls += ' ota-return-range-picker__day--in-range';
        }
        html += '<button type="button" class="' + cls + '" data-return-range-day="' + iso + '"' + (disabled ? ' disabled aria-disabled="true"' : '') + '>' + i + '</button>';
      }
      html += '</div></div>';
      return html;
    }

    function renderReturnRangePickerMonths() {
      if (!rangeMonthsEl) return;
      var twoMonths = window.matchMedia('(min-width: 768px)').matches;
      var count = twoMonths ? 2 : 1;
      var html = '';
      var idx;
      for (idx = 0; idx < count; idx++) {
        var m = viewMonth + idx;
        var y = viewYear;
        while (m > 11) {
          m -= 12;
          y += 1;
        }
        html += renderMonthPanel(y, m);
      }
      rangeMonthsEl.innerHTML = html;
    }

    function closeReturnRangePicker() {
      if (!rangePicker) return;
      rangePicker.hidden = true;
      rangePicker.classList.remove('ota-return-range-picker--open');
      rangePicker.classList.remove('picker-open');
      if (datesWrap) datesWrap.classList.remove('ota-hero-search-dates--picker-open');
      rangePickerOpen = false;
      hasUserStartedRangeSelection = false;
      setRangeTriggersExpanded(false);
      clearRangeHint();
    }

    function cancelReturnRangePicker() {
      initDraftFromCommitted();
      hasUserStartedRangeSelection = false;
      closeReturnRangePicker();
    }

    function openReturnRangePicker(anchor) {
      if (!isRoundTripMode() || !rangePicker) return;
      initDraftFromCommitted();
      hasUserStartedRangeSelection = false;
      setViewFromDraft();
      renderReturnRangePickerMonths();
      updateRangeHint();
      rangePicker.hidden = false;
      rangePicker.classList.add('ota-return-range-picker--open');
      rangePicker.classList.add('picker-open');
      if (datesWrap) datesWrap.classList.add('ota-hero-search-dates--picker-open');
      rangePickerOpen = true;
      setRangeTriggersExpanded(true);
      if (anchor && anchor.focus) anchor.focus();
    }

    function applyReturnRangePicker() {
      if (!hasDraftDepart()) {
        if (rangeHint) {
          rangeHint.textContent = 'Select a departure date.';
          rangeHint.hidden = false;
        }
        return;
      }
      if (!hasDraftReturn()) {
        if (rangeHint) {
          rangeHint.textContent = 'Select a return date.';
          rangeHint.hidden = false;
        }
        return;
      }
      if (compareIso(draftReturn, draftDepart) < 0) {
        if (rangeHint) {
          rangeHint.textContent = 'Return date must be on or after departure.';
          rangeHint.hidden = false;
        }
        return;
      }
      if (departIn) {
        departIn.value = draftDepart;
        departIn.dispatchEvent(new Event('input', { bubbles: true }));
        departIn.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (returnIn) {
        returnIn.value = draftReturn;
        returnIn.min = draftDepart;
        returnIn.dispatchEvent(new Event('input', { bubbles: true }));
        returnIn.dispatchEvent(new Event('change', { bubbles: true }));
      }
      bumpReturnMin();
      syncReturnDateRangeUi();
      syncTriggersFromInputs();
      hasUserStartedRangeSelection = false;
      closeReturnRangePicker();
      focusField(activePaxTrigger());
    }

    function clearReturnRangePickerDraft() {
      draftDepart = null;
      draftReturn = null;
      hasUserStartedRangeSelection = true;
      clearRangeHint();
      renderReturnRangePickerMonths();
      updateRangeHint();
    }

    function bindReturnRangePickerUi() {
      if (!rangePicker) return;
      if (rangeMonthsEl && !rangeMonthsEl.getAttribute('data-range-click-bound')) {
        rangeMonthsEl.setAttribute('data-range-click-bound', '1');
        rangeMonthsEl.addEventListener('click', function (event) {
          var dayButton = event.target.closest('[data-return-range-day]');
          if (!dayButton || dayButton.disabled) return;
          event.preventDefault();
          event.stopPropagation();
          handleReturnRangeDayClick(dayButton.getAttribute('data-return-range-day'));
        });
      }
      if (!rangePicker.getAttribute('data-range-picker-bound')) {
        rangePicker.setAttribute('data-range-picker-bound', '1');
        rangePicker.addEventListener('click', function (event) {
          event.stopPropagation();
        });
      }
      widget.querySelectorAll('[data-return-range-trigger]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          if (rangePickerOpen) {
            closeReturnRangePicker();
          } else {
            openReturnRangePicker(btn);
          }
        });
      });
      var prevBtn = widget.querySelector('[data-return-range-prev]');
      var nextBtn = widget.querySelector('[data-return-range-next]');
      if (prevBtn) {
        prevBtn.addEventListener('click', function () {
          viewMonth -= 1;
          if (viewMonth < 0) {
            viewMonth = 11;
            viewYear -= 1;
          }
          renderReturnRangePickerMonths();
        });
      }
      if (nextBtn) {
        nextBtn.addEventListener('click', function () {
          viewMonth += 1;
          if (viewMonth > 11) {
            viewMonth = 0;
            viewYear += 1;
          }
          renderReturnRangePickerMonths();
        });
      }
      var applyBtn = widget.querySelector('[data-return-range-apply]');
      var cancelBtn = widget.querySelector('[data-return-range-cancel]');
      var clearBtn = widget.querySelector('[data-return-range-clear]');
      if (applyBtn) applyBtn.addEventListener('click', applyReturnRangePicker);
      if (cancelBtn) cancelBtn.addEventListener('click', cancelReturnRangePicker);
      if (clearBtn) clearBtn.addEventListener('click', clearReturnRangePickerDraft);
      document.addEventListener('click', function (e) {
        if (!rangePickerOpen) return;
        if (e.target.closest('[data-return-range-picker]')) return;
        if (e.target.closest('[data-return-range-trigger]')) return;
        closeReturnRangePicker();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && rangePickerOpen) closeReturnRangePicker();
      });
    }

    function formatIsoDateLabel(iso) {
      if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return '';
      var parts = iso.split('-');
      var dt = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
      if (isNaN(dt.getTime())) return iso;
      var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      return months[dt.getMonth()] + ' ' + dt.getDate() + ', ' + dt.getFullYear();
    }

    function formatMobileDateLabel(iso) {
      if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return '';
      var parts = iso.split('-');
      var dt = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
      if (isNaN(dt.getTime())) return iso;
      var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
      var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      return days[dt.getDay()] + ', ' + dt.getDate() + ' ' + months[dt.getMonth()];
    }

    function syncMobileDepartLabel() {
      var labelEl = widget.querySelector('[data-mobile-depart-label]');
      if (!labelEl || !departIn) return;
      labelEl.textContent = departIn.value ? formatMobileDateLabel(departIn.value) : 'Select date';
    }

    function syncMobileDateUi() {
      var mobileBtn = widget.querySelector('[data-mobile-depart-trigger]');
      var nativeWrap = widget.querySelector('[data-return-date-part="depart"] .ota-hero-search-input--native');
      var isHomeMobile = widget.classList.contains('ota-hero-search--home-mobile');
      var isMobileViewport = window.matchMedia('(max-width: 767.98px)').matches;
      var isRound = currentTripType() === 'round_trip';
      if (isHomeMobile && isMobileViewport && !isRound) {
        if (mobileBtn) mobileBtn.hidden = false;
        if (nativeWrap) nativeWrap.style.display = 'none';
        syncMobileDepartLabel();
        return;
      }
      if (mobileBtn) mobileBtn.hidden = true;
      if (nativeWrap) nativeWrap.style.display = '';
    }

    function syncReturnDateRangeUi() {
      var isRound = currentTripType() === 'round_trip';
      var wId = widget.getAttribute('data-airport-widget') || '';
      if (datesWrap) {
        datesWrap.classList.toggle('ota-hero-search-dates--round', isRound);
        datesWrap.classList.toggle('ota-hero-search-dates--has-depart', !!(departIn && departIn.value));
        datesWrap.classList.toggle('ota-hero-search-dates--has-return', !!(returnIn && returnIn.value));
        datesWrap.classList.toggle('ota-hero-search-dates--complete', isRound && !!(departIn && departIn.value && returnIn && returnIn.value));
        datesWrap.classList.toggle('ota-hero-search-dates--need-return', isRound && !!(departIn && departIn.value) && !(returnIn && returnIn.value));
      }
      widget.querySelectorAll('[data-return-range-trigger]').forEach(function (btn) {
        btn.hidden = !isRound;
      });
      [departIn, returnIn].forEach(function (inp) {
        if (!inp) return;
        if (isRound) {
          inp.tabIndex = -1;
          inp.setAttribute('aria-hidden', 'true');
        } else {
          inp.tabIndex = 0;
          inp.removeAttribute('aria-hidden');
        }
      });
      var departPart = widget.querySelector('[data-return-date-part="depart"]');
      var returnPart = widget.querySelector('[data-return-date-part="return"]');
      if (departPart) {
        var departLab = departPart.querySelector('.ota-hero-search-label');
        if (departLab) departLab.setAttribute('for', isRound ? (wId + '-depart-trigger') : (wId + '-depart'));
      }
      if (returnPart) {
        var returnLab = returnPart.querySelector('.ota-hero-search-label');
        if (returnLab) returnLab.setAttribute('for', isRound ? (wId + '-return-trigger') : (wId + '-return'));
      }
      if (!isRound) {
        closeReturnRangePicker();
        syncMobileDateUi();
        return;
      }
      syncTriggersFromInputs();
      syncMobileDateUi();
    }

    function bumpReturnMin() {
      if (!departIn || !returnIn) return;
      var d = departIn.value || minDate;
      returnIn.min = d;
      if (returnIn.value && returnIn.value < d) returnIn.value = d;
      syncReturnDateRangeUi();
    }

    function setTripType(mode) {
      syncTripHidden(mode);
      var owPanel = widget.querySelector('[data-trip-panel="one_way"]');
      var mcPanel = widget.querySelector('[data-trip-panel="multi_city"]');
      var rr = widget.querySelector('[data-round-return]');
      var simpleRow = widget.querySelector('[data-hero-search-row="simple"]');
      if (simpleRow) {
        simpleRow.classList.toggle('ota-hero-search-row--return', mode === 'round_trip');
      }
      if (rr) rr.hidden = mode !== 'round_trip';

      function setPanelPaxDisabled(panel, disabled) {
        if (!panel) return;
        panel.querySelectorAll('[name="cabin"],[name="adults"],[name="children"],[name="infants"]').forEach(function (el) {
          el.disabled = !!disabled;
        });
      }

      if (mode === 'multi_city') {
        if (owPanel) owPanel.hidden = true;
        if (mcPanel) mcPanel.hidden = false;
        setPanelPaxDisabled(owPanel, true);
        setPanelPaxDisabled(mcPanel, false);
      } else {
        if (owPanel) owPanel.hidden = false;
        if (mcPanel) mcPanel.hidden = true;
        setPanelPaxDisabled(owPanel, false);
        setPanelPaxDisabled(mcPanel, true);
      }

      widget.querySelectorAll('[data-trip-radio]').forEach(function (radio) {
        radio.checked = radio.value === mode;
      });

      if (mode !== 'round_trip') {
        closeReturnRangePicker();
      }

      if (mode === 'round_trip') {
        var departInput = widget.querySelector('input[name="depart"]');
        var returnInput = widget.querySelector('input[name="return_date"]');
        if (departInput && departInput.value) {
          if (returnInput) {
            var d = departInput.value || minDate;
            returnInput.min = d;
            if (returnInput.value && returnInput.value < d) returnInput.value = d;
          }
          if (!(returnInput && returnInput.value)) {
            window.setTimeout(function () {
              openReturnRangePicker(widget.querySelector('[data-return-range-trigger="depart"]'));
            }, 0);
          }
        }
      }
      syncReturnDateRangeUi();

    }

    widget.querySelectorAll('[data-trip-radio]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        if (radio.checked) setTripType(radio.value);
      });
    });

    function cabinLabel(val) {
      var map = {economy: 'Economy', premium_economy: 'Premium Economy', business: 'Business', first: 'First'};
      return map[val] || 'Economy';
    }

    function updatePaxSummary() {
      widget.querySelectorAll('[data-pax-summary]').forEach(function (summary) {
        var panel = summary.closest('[data-pax-picker]');
        if (!panel || panel.querySelector('[name="adults"]:disabled')) return;
        var adults = parseInt((panel.querySelector('[name="adults"]') || {}).value || '1', 10);
        var children = parseInt((panel.querySelector('[name="children"]') || {}).value || '0', 10);
        var infants = parseInt((panel.querySelector('[name="infants"]') || {}).value || '0', 10);
        var cabin = (panel.querySelector('[name="cabin"]') || {}).value || 'economy';
        var text = adults + ' adult' + (adults === 1 ? '' : 's');
        if (children > 0) text += ', ' + children + ' child' + (children === 1 ? '' : 'ren');
        if (infants > 0) text += ', ' + infants + ' infant' + (infants === 1 ? '' : 's');
        text += ' · ' + cabinLabel(cabin);
        if (summary.hasAttribute('data-pax-summary-mobile')) {
          summary.textContent = adults + ' Adult' + (adults === 1 ? '' : 's') + ' · ' + cabinLabel(cabin);
        } else {
          summary.textContent = text;
        }
      });
    }

    widget.querySelectorAll('[data-pax-input]').forEach(function (el) {
      el.addEventListener('change', updatePaxSummary);
    });
    updatePaxSummary();
    bindPaxPickerUi();
    setTripType(widget.getAttribute('data-trip-type') || 'one_way');

  var mobileDepartBtn = widget.querySelector('[data-mobile-depart-trigger]');
  if (mobileDepartBtn && departIn) {
    mobileDepartBtn.addEventListener('click', function () {
      if (typeof departIn.showPicker === 'function') {
        try { departIn.showPicker(); } catch (e) { departIn.focus(); }
      } else {
        departIn.focus();
      }
    });
  }

    if (departIn) {
      departIn.addEventListener('change', function () {
        bumpReturnMin();
        syncMobileDepartLabel();
        if (departIn.value && currentTripType() !== 'round_trip') nextAfterDepart();
      });
      departIn.addEventListener('input', function () {
        bumpReturnMin();
        syncMobileDepartLabel();
      });
    }
    if (returnIn) {
      returnIn.addEventListener('change', function () {
        bumpReturnMin();
        syncReturnDateRangeUi();
        if (returnIn.value && currentTripType() !== 'round_trip') focusField(activePaxTrigger());
      });
      returnIn.addEventListener('input', function () {
        bumpReturnMin();
        syncReturnDateRangeUi();
      });
    }
    bumpReturnMin();
    bindReturnRangePickerUi();
    syncReturnDateRangeUi();
    syncMobileDateUi();
    window.addEventListener('resize', syncMobileDateUi);
    bindMultiDepartInputs(widget);

    var multiRows = widget.querySelector('[data-multi-rows]');
    var multiAdd = widget.querySelector('[data-multi-add]');
    var multiRemove = widget.querySelector('[data-multi-remove]');
    var multiIdx = (multiRows ? multiRows.querySelectorAll('.ota-multiseg-row').length : 0) || 2;

    function bindMultiSuggestIds(row, idx) {
      row.querySelectorAll('.js-airport-autocomplete').forEach(function (inp, i) {
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
        multiIdx++;
        var row = document.createElement('div');
        row.className = 'ota-hero-search-segment ota-multiseg-row';
        row.setAttribute('data-segment-index', String(rows.length + 1));
        row.innerHTML =
          '<span class="ota-hero-search-segment__badge" aria-hidden="true">' + (rows.length + 1) + '</span>' +
          '<div class="ota-hero-search-segment__grid">' +
          '<div class="ota-hero-search-field ota-hero-search-field--from ota-from-wrap"><label class="ota-hero-search-label">Leaving from</label>' +
          '<div class="ota-hero-search-input"><i class="fa fa-map-marker" aria-hidden="true"></i>' +
          '<input class="ota-hero-search-control js-airport-autocomplete" name="multi_from_display[]" data-hidden-target="' + widget.getAttribute('data-airport-widget') + '-m' + multiIdx + '-0-hidden" type="text" autocomplete="off" placeholder="City or airport"></div>' +
          '<input type="hidden" id="' + widget.getAttribute('data-airport-widget') + '-m' + multiIdx + '-0-hidden" name="multi_from[]">' +
          '<div class="ota-airport-suggest" role="listbox"></div></div>' +
          '<div class="ota-hero-search-field ota-hero-search-field--swap"><span class="ota-hero-search-label ota-hero-search-label--sr">Swap</span>' +
          '<button type="button" class="ota-hero-search-swap" data-swap-multiseg aria-label="Swap segment airports"><i class="fa fa-exchange"></i></button></div>' +
          '<div class="ota-hero-search-field ota-hero-search-field--to ota-to-wrap"><label class="ota-hero-search-label">Going to</label>' +
          '<div class="ota-hero-search-input"><i class="fa fa-map-marker" aria-hidden="true"></i>' +
          '<input class="ota-hero-search-control js-airport-autocomplete" name="multi_to_display[]" data-hidden-target="' + widget.getAttribute('data-airport-widget') + '-m' + multiIdx + '-1-hidden" type="text" autocomplete="off" placeholder="City or airport"></div>' +
          '<input type="hidden" id="' + widget.getAttribute('data-airport-widget') + '-m' + multiIdx + '-1-hidden" name="multi_to[]">' +
          '<div class="ota-airport-suggest" role="listbox"></div></div>' +
          '<div class="ota-hero-search-field ota-hero-search-field--date"><label class="ota-hero-search-label">Date</label>' +
          '<div class="ota-hero-search-input ota-hero-search-input--date"><input class="ota-hero-search-control ota-hero-search-control--date" name="multi_depart[]" type="date" min="' + minDate + '"><span class="ota-hero-search-input__icon" aria-hidden="true"><i class="fa fa-calendar"></i></span></div></div></div>';
        multiRows.appendChild(row);
        bindMultiSuggestIds(row, multiIdx);
        wireAutocomplete(row);
        bindMultiDepartInputs(row);
        widget.querySelectorAll('[data-swap-multiseg]').forEach(function (btn) {
          if (btn.getAttribute('data-bound')) return;
          btn.setAttribute('data-bound', '1');
          btn.addEventListener('click', swapMultisegHandler);
        });
      });
    }

    function swapMultisegHandler() {
      var row = this.closest('.ota-multiseg-row');
      if (!row) return;
      var fromD = row.querySelector('input[name="multi_from_display[]"]');
      var toD = row.querySelector('input[name="multi_to_display[]"]');
      var fromH = row.querySelector('input[name="multi_from[]"]');
      var toH = row.querySelector('input[name="multi_to[]"]');
      if (fromD && toD) { var td = fromD.value; fromD.value = toD.value; toD.value = td; }
      if (fromH && toH) { var th = fromH.value; fromH.value = toH.value; toH.value = th; }
    }

    widget.querySelectorAll('[data-swap-multiseg]').forEach(function (btn) {
      btn.setAttribute('data-bound', '1');
      btn.addEventListener('click', swapMultisegHandler);
    });

    if (multiRemove && multiRows) {
      multiRemove.addEventListener('click', function () {
        var rows = multiRows.querySelectorAll('.ota-multiseg-row');
        if (rows.length <= 2) return;
        rows[rows.length - 1].remove();
        multiRows.querySelectorAll('.ota-multiseg-row').forEach(function (r, i) {
          var badge = r.querySelector('.ota-hero-search-segment__badge');
          if (badge) badge.textContent = String(i + 1);
          r.setAttribute('data-segment-index', String(i + 1));
        });
      });
    }

    var swap = widget.querySelector('[data-swap-routes]');
    if (swap) {
      swap.addEventListener('click', function () {
        var fromDisplay = widget.querySelector('input[name="from_display"]');
        var toDisplay = widget.querySelector('input[name="to_display"]');
        var fromHidden = widget.querySelector('input[name="from"]');
        var toHidden = widget.querySelector('input[name="to"]');
        if (fromDisplay && toDisplay) { var t = fromDisplay.value; fromDisplay.value = toDisplay.value; toDisplay.value = t; }
        if (fromHidden && toHidden) { var h = fromHidden.value; fromHidden.value = toHidden.value; toHidden.value = h; }
      });
    }

    var activeBox = null, activeItems = [], activeIndex = -1;
    var timers = new WeakMap(), controllers = new WeakMap();

    function closeAll() {
      widget.querySelectorAll('.ota-airport-suggest').forEach(function (box) {
        box.innerHTML = '';
        box.style.display = 'none';
      });
      activeBox = null; activeItems = []; activeIndex = -1;
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
      if (!items.length) { box.style.display = 'none'; return; }
      items.slice(0, 10).forEach(function (item, index) {
        var code = (item.iata || item.iata_code || '').toUpperCase();
        if (!code) return;
        var row = document.createElement('button');
        row.type = 'button';
        row.className = 'ota-airport-item';
        row.setAttribute('role', 'option');
        row.setAttribute('data-iata', code);
        row.innerHTML = '<span class="ota-airport-item-code">' + code + '</span><span class="ota-airport-item-main">' + (item.label || code) + '</span>';
        row.addEventListener('pointerdown', function (e) {
          e.preventDefault();
          var hiddenTarget = document.getElementById(input.getAttribute('data-hidden-target'));
          input.value = item.label || code;
          input.setAttribute('data-selected-iata', code);
          if (hiddenTarget) hiddenTarget.value = code;
          var role = input.getAttribute('data-airport-display');
          if (role === 'from' || role === 'to') {
            syncMobileAirportField(role);
          }
          closeAll();
          window.setTimeout(function () { nextAfterAirportPick(input); }, 0);
        });
        box.appendChild(row);
      });
      activeItems = Array.prototype.slice.call(box.querySelectorAll('.ota-airport-item'));
      activeIndex = -1;
      box.style.display = activeItems.length ? 'block' : 'none';
    }

    function fetchSuggestions(input) {
      var query = (input.value || '').trim();
      if (query.length < 2) { abortInputRequest(input); closeAll(); return; }
      abortInputRequest(input);
      var controller = new AbortController();
      controllers.set(input, controller);
      fetch(airportsSearchUrl + '?q=' + encodeURIComponent(query) + '&limit=10', {
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        signal: controller.signal
      }).then(function (r) { return r.ok ? r.json() : []; })
        .then(function (items) {
          if ((input.value || '').trim() !== query) return;
          renderSuggestions(input, Array.isArray(items) ? items : []);
        })
        .catch(function (err) { if (err && err.name === 'AbortError') return; closeAll(); });
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
      (root || widget).querySelectorAll('.js-airport-autocomplete').forEach(function (input) {
        if (input.getAttribute('data-ac-bound') === '1') return;
        input.setAttribute('data-ac-bound', '1');
        input.addEventListener('input', function () {
          var hiddenTarget = document.getElementById(input.getAttribute('data-hidden-target'));
          if (input.getAttribute('data-selected-iata') && input.value.indexOf(input.getAttribute('data-selected-iata')) === -1) {
            input.removeAttribute('data-selected-iata');
            if (hiddenTarget) hiddenTarget.value = '';
          }
          var role = input.getAttribute('data-airport-display');
          if (role === 'from' || role === 'to') {
            syncMobileAirportField(role);
          }
          var t = timers.get(input);
          if (t) window.clearTimeout(t);
          timers.set(input, window.setTimeout(function () { fetchSuggestions(input); }, 180));
        });
        input.addEventListener('focus', function () {
          if ((input.value || '').trim().length >= 2) fetchSuggestions(input);
        });
        input.addEventListener('blur', function () {
          window.setTimeout(closeAll, 260);
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
    widget.querySelectorAll('[data-swap-routes]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        window.setTimeout(syncAllMobileAirports, 0);
      });
    });

    var form = widget.querySelector('form');
    if (form) {
      form.addEventListener('submit', function (event) {
        closePaxPickers();
        var fail = validateHeroForm(form);
        if (fail) {
          event.preventDefault();
          event.stopImmediatePropagation();
          showValidateModal(fail.message, fail.focus);
        }
      });
    }

    document.addEventListener('click', function (event) {
      if (!widget.contains(event.target)) closeAll();
    });
  });

  document.querySelectorAll('a[href="#ota-flight-search"]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      var target = document.getElementById('ota-flight-search');
      if (!target) return;
      event.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      window.setTimeout(function () {
        var first = target.querySelector('.js-airport-autocomplete');
        if (first) first.focus();
      }, 350);
    });
  });
})();
</script>
@endpush
