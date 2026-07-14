@php
    $o = is_array($offer ?? null) ? $offer : null;
    $routeLabel = trim((string) ($routeLabel ?? ''));
    if ($routeLabel === '' && $o !== null) {
        $routeLabel = trim((string) ($o['route'] ?? ''));
    }
    $refundable = $o !== null ? (bool) ($o['refundable'] ?? false) : false;
    $refundRule = trim((string) data_get($o, 'refund_rule', ''));
    $changeRule = trim((string) data_get($o, 'change_rule', ''));
    $baggageSummary = trim((string) data_get($o, 'baggage_summary_display', data_get($o, 'baggage', '')));
    $baggageCabin = trim((string) data_get($o, 'baggage_cabin_display', ''));
    $baggageChecked = trim((string) data_get($o, 'baggage_checked_display', ''));
    $baggageLines = is_array(data_get($o, 'baggage_lines')) ? data_get($o, 'baggage_lines') : [];
    $displayedPrice = isset($o['displayed_price']) ? (float) $o['displayed_price'] : null;
    $hasPkrQuote = (bool) data_get($o, 'has_confirmed_pkr_quote', false);
    $formatPkr = static function (?float $amount): string {
        if ($amount === null || $amount <= 0) {
            return '—';
        }

        return 'PKR '.number_format($amount, 0, '.', ',');
    };
@endphp
@if ($o)
    <article class="ota-mobile-flight-details__card ota-mobile-fare-summary" data-mobile-fare-summary>
        <h2 class="ota-mobile-flight-details__card-title">Fare Summary</h2>
        @if ($routeLabel !== '')
            <p class="ota-mobile-fare-summary__route">{{ $routeLabel }}</p>
        @endif

        <div class="ota-mobile-fare-summary__tabs" role="tablist" aria-label="Fare summary sections">
            <button type="button" class="ota-mobile-fare-summary__tab is-active" role="tab" data-mobile-fare-tab="overview" aria-selected="true" aria-controls="ota-mobile-fare-panel-overview">Flight Overview</button>
            <button type="button" class="ota-mobile-fare-summary__tab" role="tab" data-mobile-fare-tab="baggage" aria-selected="false" aria-controls="ota-mobile-fare-panel-baggage" tabindex="-1">Baggage Policy</button>
            <button type="button" class="ota-mobile-fare-summary__tab" role="tab" data-mobile-fare-tab="policy" aria-selected="false" aria-controls="ota-mobile-fare-panel-policy" tabindex="-1">Fare Policy</button>
            <button type="button" class="ota-mobile-fare-summary__tab" role="tab" data-mobile-fare-tab="details" aria-selected="false" aria-controls="ota-mobile-fare-panel-details" tabindex="-1">Fare Details</button>
        </div>

        <div class="ota-mobile-fare-summary__panels">
            <div id="ota-mobile-fare-panel-overview" class="ota-mobile-fare-summary__panel" data-mobile-fare-panel="overview" role="tabpanel">
                @include('mobile.flights.partials.details-flight-card', ['offer' => $o, 'criteria' => $criteria ?? []])
            </div>
            <div id="ota-mobile-fare-panel-baggage" class="ota-mobile-fare-summary__panel" data-mobile-fare-panel="baggage" role="tabpanel" hidden>
                @if ($baggageCabin !== '' || $baggageChecked !== '' || $baggageSummary !== '' || $baggageLines !== [])
                    @if ($baggageCabin !== '')
                        <div class="ota-mobile-fare-summary__baggage-row">
                            <span class="ota-mobile-fare-summary__baggage-label">Cabin bag</span>
                            <span class="ota-mobile-fare-summary__baggage-value">{{ $baggageCabin }}</span>
                        </div>
                    @endif
                    @if ($baggageChecked !== '')
                        <div class="ota-mobile-fare-summary__baggage-row">
                            <span class="ota-mobile-fare-summary__baggage-label">Checked bag</span>
                            <span class="ota-mobile-fare-summary__baggage-value">{{ $baggageChecked }}</span>
                        </div>
                    @endif
                    @if ($baggageSummary !== '' && $baggageCabin === '' && $baggageChecked === '')
                        <div class="ota-mobile-fare-summary__baggage-row">
                            <span class="ota-mobile-fare-summary__baggage-label">Baggage</span>
                            <span class="ota-mobile-fare-summary__baggage-value">{{ $baggageSummary }}</span>
                        </div>
                    @endif
                    @foreach ($baggageLines as $line)
                        @php
                            $lineText = is_array($line)
                                ? trim((string) (data_get($line, 'text') ?: data_get($line, 'value') ?: data_get($line, 'summary') ?: ''))
                                : trim((string) $line);
                            $lineLabel = is_array($line) ? trim((string) (data_get($line, 'label') ?: data_get($line, 'type') ?: 'Baggage')) : 'Baggage';
                        @endphp
                        @if ($lineText !== '')
                            <div class="ota-mobile-fare-summary__baggage-row">
                                <span class="ota-mobile-fare-summary__baggage-label">{{ $lineLabel }}</span>
                                <span class="ota-mobile-fare-summary__baggage-value">{{ $lineText }}</span>
                            </div>
                        @endif
                    @endforeach
                @else
                    <p class="ota-mobile-flight-details__fallback">Baggage allowance details are not available for this fare.</p>
                @endif
            </div>
            <div id="ota-mobile-fare-panel-policy" class="ota-mobile-fare-summary__panel" data-mobile-fare-panel="policy" role="tabpanel" hidden>
                @if ($refundRule !== '' || $changeRule !== '')
                    <ul class="ota-mobile-flight-details__rules">
                        @if ($refundRule !== '')
                            <li>{{ $refundRule }}</li>
                        @endif
                        @if ($changeRule !== '')
                            <li>{{ $changeRule }}</li>
                        @endif
                    </ul>
                @elseif ($o !== null && array_key_exists('refundable', $o))
                    <p class="ota-mobile-flight-details__rules-line">{{ $refundable ? 'Refundable fare' : 'Non-refundable fare' }}</p>
                @else
                    <p class="ota-mobile-flight-details__fallback">Fare policy is subject to airline rules and will be confirmed before ticketing.</p>
                @endif
            </div>
            <div id="ota-mobile-fare-panel-details" class="ota-mobile-fare-summary__panel" data-mobile-fare-panel="details" role="tabpanel" hidden>
                @include('mobile.flights.partials.details-fare-breakdown', ['offer' => $o, 'hideTitle' => true])
            </div>
        </div>

        @if ($hasPkrQuote && $displayedPrice !== null && $displayedPrice > 0)
            <div class="ota-mobile-fare-summary__inline-total">
                <span class="ota-mobile-fare-summary__inline-total-label">Grand total</span>
                <span class="ota-mobile-fare-summary__inline-total-value">{{ $formatPkr($displayedPrice) }}</span>
            </div>
        @endif
    </article>
@endif
