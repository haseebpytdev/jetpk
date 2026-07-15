@php
    $displayBaseFare = (float) ($displayBaseFare ?? 0);
    $displayTaxes = (float) ($displayTaxes ?? 0);
    $agencyCharges = (float) ($agencyCharges ?? 0);
    $totalPayable = (float) ($totalPayable ?? 0);
    $discount = (float) ($discount ?? 0);
    $passengerPricingAvailable = (bool) ($passengerPricingAvailable ?? false);
    $groupedPassengerPricing = is_array($groupedPassengerPricing ?? null) ? $groupedPassengerPricing : [];
    $showPassengerMix = $passengerPricingAvailable && $groupedPassengerPricing !== [];
    $selectedFareEstimate = is_array($selectedFareEstimate ?? null) ? $selectedFareEstimate : null;
    $useSelectedFareEstimate = (bool) ($useSelectedFareEstimate ?? false) && is_array($selectedFareEstimate) && ! empty($selectedFareEstimate['has_checkout_estimate']);
@endphp
<article class="ota-mobile-booking__card ota-mobile-booking__price-card">
    <h2 class="ota-mobile-booking__card-title">Price breakdown</h2>
    <dl class="ota-mobile-booking__price-dl">
        @if ($useSelectedFareEstimate)
            <div class="ota-mobile-booking__price-row ota-mobile-booking__price-row--total ota-mobile-booking__price-row--selected-estimate">
                <dt>{{ $selectedFareEstimate['label'] ?? 'Estimated selected fare' }}</dt>
                <dd>
                    @if (!empty($selectedFareEstimate['price_is_approximate']))
                        <span class="ota-mobile-booking__tag-note">Approx.</span>
                    @endif
                    {{ preg_replace('/^Approx\.\s*/i', '', (string) ($selectedFareEstimate['price_display'] ?? '')) }}
                </dd>
            </div>
            <p class="ota-mobile-booking__tag-note">{{ $selectedFareEstimate['validation_note'] ?? \App\Support\FlightSearch\FlightOfferDisplayPresenter::SELECTED_FARE_VALIDATION_NOTE }}</p>
        @else
        @if ($showPassengerMix)
            @php
                $pricedBase = ($groupedPassengerPricing['adult']['base'] ?? 0)
                    + ($groupedPassengerPricing['child']['base'] ?? 0)
                    + ($groupedPassengerPricing['infant']['base'] ?? 0);
                $pricedTax = ($groupedPassengerPricing['adult']['tax'] ?? 0)
                    + ($groupedPassengerPricing['child']['tax'] ?? 0)
                    + ($groupedPassengerPricing['infant']['tax'] ?? 0);
            @endphp
            <div class="ota-mobile-booking__price-row">
                <dt>Base fare</dt>
                <dd>Rs {{ number_format($pricedBase, 0) }}</dd>
            </div>
            <div class="ota-mobile-booking__price-row">
                <dt>Taxes &amp; fees</dt>
                <dd>Rs {{ number_format($pricedTax, 0) }}</dd>
            </div>
        @elseif ($displayBaseFare > 0 || $displayTaxes > 0)
            <div class="ota-mobile-booking__price-row">
                <dt>Base fare</dt>
                <dd>Rs {{ number_format($displayBaseFare, 0) }}</dd>
            </div>
            <div class="ota-mobile-booking__price-row">
                <dt>Taxes &amp; fees</dt>
                <dd>Rs {{ number_format($displayTaxes, 0) }}</dd>
            </div>
        @endif
        @if ($agencyCharges > 0)
            <div class="ota-mobile-booking__price-row">
                <dt>Service fee</dt>
                <dd>Rs {{ number_format($agencyCharges, 0) }}</dd>
            </div>
        @endif
        @if ($discount > 0)
            <div class="ota-mobile-booking__price-row ota-mobile-booking__price-row--discount">
                <dt>Discount</dt>
                <dd>− Rs {{ number_format($discount, 0) }}</dd>
            </div>
        @endif
        <div class="ota-mobile-booking__price-row ota-mobile-booking__price-row--total">
            <dt>Total amount</dt>
            <dd>Rs {{ number_format($totalPayable, 0) }}</dd>
        </div>
        @endif
    </dl>
</article>
