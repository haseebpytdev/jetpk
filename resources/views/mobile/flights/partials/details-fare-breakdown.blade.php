@php
    $o = is_array($offer ?? null) ? $offer : null;
    $hasPkrQuote = (bool) ($o['has_confirmed_pkr_quote'] ?? false);
    $displayedPrice = isset($o['displayed_price']) ? (float) $o['displayed_price'] : null;
    $baseFare = (float) ($o['base_fare'] ?? 0);
    $taxes = (float) ($o['taxes'] ?? 0);
    $markup = (float) ($o['markup'] ?? 0);
    $serviceFee = (float) ($o['service_fee'] ?? 0);
    $finalPrice = (float) ($o['final_customer_price'] ?? 0);
    $discount = (float) ($o['discount'] ?? 0);
    $supplierPassengerPricing = is_array($o['passenger_pricing'] ?? null)
        ? $o['passenger_pricing']
        : (is_array(data_get($o, 'fare_breakdown.passenger_pricing')) ? data_get($o, 'fare_breakdown.passenger_pricing') : []);
    $passengerPricingAvailable = (bool) (
        $o['passenger_pricing_available']
        ?? data_get($o, 'fare_breakdown.passenger_pricing_available')
        ?? (! empty($supplierPassengerPricing))
    );
    $groupedPassengerPricing = [
        'adult' => ['count' => 0, 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0],
        'child' => ['count' => 0, 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0],
        'infant' => ['count' => 0, 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0],
    ];
    foreach ($supplierPassengerPricing as $ppRow) {
        if (! is_array($ppRow)) {
            continue;
        }
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
        $qty = max(1, (int) ($ppRow['passenger_count'] ?? 1));
        $groupedPassengerPricing[$type]['count'] += $qty;
        $groupedPassengerPricing[$type]['base'] += (float) ($ppRow['base_amount'] ?? 0);
        $groupedPassengerPricing[$type]['tax'] += (float) ($ppRow['tax_amount'] ?? 0);
        $groupedPassengerPricing[$type]['total'] += (float) ($ppRow['total_amount'] ?? 0);
    }
    $hasBreakdown = $hasPkrQuote && (
        ($passengerPricingAvailable && (
            $groupedPassengerPricing['adult']['count'] > 0
            || $groupedPassengerPricing['child']['count'] > 0
            || $groupedPassengerPricing['infant']['count'] > 0
        ))
        || $baseFare > 0
        || $taxes > 0
    );
    $priceNote = trim((string) ($o['price_note'] ?? ''));
    $formatPkr = static function (float $amount): string {
        return 'PKR '.number_format(max(0, $amount), 0, '.', ',');
    };
    $agencyCharges = $markup + $serviceFee;
@endphp
@if ($o)
    <article class="ota-mobile-flight-details__card ota-mobile-flight-details__card--fare">
        @if (empty($hideTitle))
            <h2 class="ota-mobile-flight-details__card-title">Fare Details</h2>
        @endif
        <dl class="ota-mobile-flight-details__fare-dl">
            @if ($hasBreakdown && $passengerPricingAvailable && (
                $groupedPassengerPricing['adult']['count'] > 0
                || $groupedPassengerPricing['child']['count'] > 0
                || $groupedPassengerPricing['infant']['count'] > 0
            ))
                @foreach (['adult' => 'Adult', 'child' => 'Child', 'infant' => 'Infant'] as $ptype => $plabel)
                    @if ($groupedPassengerPricing[$ptype]['count'] > 0)
                        <div class="ota-mobile-flight-details__fare-row ota-mobile-flight-details__fare-row--group">
                            <dt>{{ $plabel }} × {{ $groupedPassengerPricing[$ptype]['count'] }}</dt>
                            <dd></dd>
                        </div>
                        @if ($groupedPassengerPricing[$ptype]['base'] > 0)
                            <div class="ota-mobile-flight-details__fare-row ota-mobile-flight-details__fare-row--indent">
                                <dt>Base fare</dt>
                                <dd>{{ $formatPkr($groupedPassengerPricing[$ptype]['base']) }}</dd>
                            </div>
                        @endif
                        @if ($groupedPassengerPricing[$ptype]['tax'] > 0)
                            <div class="ota-mobile-flight-details__fare-row ota-mobile-flight-details__fare-row--indent">
                                <dt>Taxes &amp; fees</dt>
                                <dd>{{ $formatPkr($groupedPassengerPricing[$ptype]['tax']) }}</dd>
                            </div>
                        @endif
                        <div class="ota-mobile-flight-details__fare-row ota-mobile-flight-details__fare-row--indent">
                            <dt>Total</dt>
                            <dd>{{ $formatPkr($groupedPassengerPricing[$ptype]['total']) }}</dd>
                        </div>
                    @endif
                @endforeach
                @php
                    $grandBase = $groupedPassengerPricing['adult']['base'] + $groupedPassengerPricing['child']['base'] + $groupedPassengerPricing['infant']['base'];
                    $grandTax = $groupedPassengerPricing['adult']['tax'] + $groupedPassengerPricing['child']['tax'] + $groupedPassengerPricing['infant']['tax'];
                @endphp
                <div class="ota-mobile-flight-details__fare-row ota-mobile-flight-details__fare-row--group">
                    <dt>Grand total</dt>
                    <dd></dd>
                </div>
                @if ($grandBase > 0)
                    <div class="ota-mobile-flight-details__fare-row ota-mobile-flight-details__fare-row--indent">
                        <dt>Total base fare</dt>
                        <dd>{{ $formatPkr($grandBase) }}</dd>
                    </div>
                @endif
                @if ($grandTax > 0)
                    <div class="ota-mobile-flight-details__fare-row ota-mobile-flight-details__fare-row--indent">
                        <dt>Total taxes &amp; fees</dt>
                        <dd>{{ $formatPkr($grandTax) }}</dd>
                    </div>
                @endif
                @if ($agencyCharges > 0)
                    <div class="ota-mobile-flight-details__fare-row ota-mobile-flight-details__fare-row--indent">
                        <dt>Agency charges</dt>
                        <dd>{{ $formatPkr($agencyCharges) }}</dd>
                    </div>
                @endif
            @elseif ($hasBreakdown)
                @if ($baseFare > 0)
                    <div class="ota-mobile-flight-details__fare-row">
                        <dt>Base fare</dt>
                        <dd>{{ $formatPkr($baseFare) }}</dd>
                    </div>
                @endif
                @if ($taxes > 0)
                    <div class="ota-mobile-flight-details__fare-row">
                        <dt>Taxes &amp; fees</dt>
                        <dd>{{ $formatPkr($taxes) }}</dd>
                    </div>
                @endif
                @if ($markup > 0)
                    <div class="ota-mobile-flight-details__fare-row">
                        <dt>Markup</dt>
                        <dd>{{ $formatPkr($markup) }}</dd>
                    </div>
                @endif
                @if ($serviceFee > 0)
                    <div class="ota-mobile-flight-details__fare-row">
                        <dt>Service fee</dt>
                        <dd>{{ $formatPkr($serviceFee) }}</dd>
                    </div>
                @endif
                @if ($discount > 0)
                    <div class="ota-mobile-flight-details__fare-row ota-mobile-flight-details__fare-row--discount">
                        <dt>Discount</dt>
                        <dd>− {{ $formatPkr($discount) }}</dd>
                    </div>
                @endif
            @endif

            @if ($hasPkrQuote && $displayedPrice !== null && $displayedPrice > 0)
                <div class="ota-mobile-flight-details__fare-row ota-mobile-flight-details__fare-row--total">
                    <dt>{{ ($passengerPricingAvailable && ($groupedPassengerPricing['adult']['count'] + $groupedPassengerPricing['child']['count'] + $groupedPassengerPricing['infant']['count']) > 0) ? 'Final payable total' : 'Total' }}</dt>
                    <dd>{{ $formatPkr((float) $displayedPrice) }}</dd>
                </div>
            @elseif ($finalPrice > 0)
                <div class="ota-mobile-flight-details__fare-row ota-mobile-flight-details__fare-row--total">
                    <dt>Total</dt>
                    <dd>{{ $formatPkr($finalPrice) }}</dd>
                </div>
            @else
                <div class="ota-mobile-flight-details__fare-row">
                    <dt>Total</dt>
                    <dd>Fare unavailable</dd>
                </div>
            @endif
        </dl>

        @if (! $hasBreakdown && $hasPkrQuote)
            <p class="ota-mobile-flight-details__fallback">Detailed fare breakdown is not available for this option. The total shown includes taxes and fees where applicable.</p>
        @elseif (! $hasPkrQuote)
            <p class="ota-mobile-flight-details__fallback">{{ $priceNote !== '' ? $priceNote : 'PKR pricing could not be confirmed for this option.' }}</p>
        @elseif ($priceNote !== '')
            <p class="ota-mobile-flight-details__note">{{ $priceNote }}</p>
        @endif
    </article>
@endif
