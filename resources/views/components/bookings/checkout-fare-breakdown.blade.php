@props([
    'breakdown' => [],
    'variant' => 'desktop',
    'useSelectedFareEstimate' => false,
    'selectedFareEstimate' => null,
    'selectedFareEstimateDriftDetected' => false,
    'passengerCountSummary' => null,
])

@php
    $breakdown = is_array($breakdown ?? null) ? $breakdown : [];
    $rows = is_array($breakdown['rows'] ?? null) ? $breakdown['rows'] : [];
    $currency = strtoupper(trim((string) ($breakdown['currency'] ?? 'PKR')));
    $prefix = $currency === 'PKR' ? 'Rs' : $currency;
    $selectedFareEstimate = is_array($selectedFareEstimate ?? null) ? $selectedFareEstimate : null;
    $useSelectedFareEstimate = (bool) $useSelectedFareEstimate && is_array($selectedFareEstimate) && ! empty($selectedFareEstimate['has_checkout_estimate']);
    $passengerMix = is_array($breakdown['passenger_mix'] ?? null) ? $breakdown['passenger_mix'] : null;
    $passengerCountSummary = is_array($passengerCountSummary ?? null) ? $passengerCountSummary : $passengerMix;
    $isMobile = ($variant ?? 'desktop') === 'mobile';
    $rowClass = $isMobile ? 'ota-mobile-booking__price-row' : 'ota-fare-dl__row';
    $totalRowClass = $rowClass.($isMobile ? ' ota-mobile-booking__price-row--total' : ' ota-fare-dl__row--total');
@endphp

@if ($isMobile)
    <article class="ota-mobile-booking__card ota-mobile-booking__price-card">
        <h2 class="ota-mobile-booking__card-title">Price breakdown</h2>
        <dl class="ota-mobile-booking__price-dl">
@else
    <dl class="ota-fare-dl ota-fare-dl--compact">
@endif
        @if ($useSelectedFareEstimate)
            <div class="{{ $rowClass }}{{ $isMobile ? ' ota-mobile-booking__price-row--selected-estimate' : ' ota-fare-dl__row--selected-estimate' }}">
                <dt>{{ $selectedFareEstimate['label'] ?? 'Estimated selected fare' }}</dt>
                <dd>
                    @if (! empty($selectedFareEstimate['price_is_approximate']))
                        <span class="{{ $isMobile ? 'ota-mobile-booking__tag-note' : 'ota-checkout-selected-fare-family__approx' }}">Approx.</span>
                    @endif
                    {{ preg_replace('/^Approx\.\s*/i', '', (string) ($selectedFareEstimate['price_display'] ?? '')) }}
                </dd>
            </div>
            <p class="{{ $isMobile ? 'ota-mobile-booking__tag-note' : 'ota-checkout-selected-fare-estimate__note' }}">{{ $selectedFareEstimate['validation_note'] ?? \App\Support\FlightSearch\FlightOfferDisplayPresenter::SELECTED_FARE_VALIDATION_NOTE }}</p>
            @if ($selectedFareEstimateDriftDetected)
                <p class="{{ $isMobile ? 'ota-mobile-booking__tag-note' : 'ota-checkout-selected-fare-estimate__note ota-checkout-selected-fare-estimate__note--drift' }}">Selected fare estimate kept from your results selection; airline validation may adjust the final price.</p>
            @endif
        @else
            @foreach ($rows as $row)
                @if (! is_array($row))
                    @continue
                @endif
                @php
                    $rowType = (string) ($row['type'] ?? '');
                    $isTotal = $rowType === 'total';
                    $classes = $isTotal ? $totalRowClass : $rowClass;
                @endphp
                <div class="{{ $classes }}">
                    <dt>{{ $row['label'] ?? '' }}</dt>
                    <dd>{{ $prefix }} {{ number_format((float) ($row['amount'] ?? 0), 0) }}</dd>
                </div>
            @endforeach
            @if (($breakdown['mode'] ?? '') === 'total_only' && is_array($passengerCountSummary))
                <div class="{{ $rowClass }}">
                    <dt>Travellers</dt>
                    <dd>{{ $passengerCountSummary['adults'] ?? 0 }} ad · {{ $passengerCountSummary['children'] ?? 0 }} ch · {{ $passengerCountSummary['infants'] ?? 0 }} inf</dd>
                </div>
            @endif
        @endif
@if ($isMobile)
        </dl>
    </article>
@else
    </dl>
@endif
