@php
    /** @var array<string, mixed> $summary */
    $summary = is_array($summary ?? null) ? $summary : [];
    $seatCount = max(1, (int) ($seatCount ?? ($summary['seat_count'] ?? 1)));
    $totalAmount = isset($totalAmount)
        ? (float) $totalAmount
        : (float) str_replace(',', '', (string) ($summary['total_formatted'] ?? '0'));
    $showPayNote = (bool) ($showPayNote ?? false);
    $summaryTitle = (string) ($summaryTitle ?? 'Booking summary');
    $productType = (string) ($summary['product_type'] ?? 'Group Ticketing');
@endphp
<aside class="ota-checkout-aside ota-checkout-summary" aria-label="Booking summary">
    <div class="ota-checkout-card ota-checkout-card--accent ota-checkout-sticky-summary ota-checkout-trip-summary">
        <div class="ota-checkout-trip-summary__head">
            <h2 class="ota-checkout-aside-title mb-0">{{ e($summaryTitle) }}</h2>
            <span class="ota-checkout-trip-summary__pill ota-checkout-product-pill">{{ e($productType) }}</span>
        </div>

        @if (! empty($summary['route_line']))
            <p class="ota-checkout-trip-summary__route">{{ e($summary['route_line']) }}</p>
        @endif

        @if (! empty($summary['sector_code']))
            <p class="ota-checkout-summary-meta"><strong>Sector:</strong> {{ e($summary['sector_code']) }}</p>
        @endif

        @if (! empty($summary['departure_date_short']))
            <p class="ota-checkout-summary-meta"><strong>Departure:</strong> {{ e($summary['departure_date_short']) }}</p>
        @endif

        @if (! empty($summary['baggage_display']))
            <p class="ota-checkout-summary-meta"><strong>Baggage:</strong> {{ e($summary['baggage_display']) }}</p>
        @endif

        <div class="ota-checkout-sidebar-block ota-checkout-sidebar-block--fare">
            <h3 class="ota-checkout-sidebar-block__title">Fare details</h3>
            <div class="ota-checkout-trip-summary__carrier">
                @if (! empty($summary['airline_logo_url']))
                    <img src="{{ $summary['airline_logo_url'] }}" alt="" class="ota-checkout-trip-summary__logo" width="28" height="28">
                @endif
                <div>
                    @if (! empty($summary['airline_name']))
                        <p class="ota-checkout-trip-summary__airline mb-0">{{ e($summary['airline_name']) }}</p>
                    @endif
                </div>
            </div>
            <dl class="ota-fare-dl ota-fare-dl--compact">
                <div class="ota-fare-dl__row"><dt>Seats selected</dt><dd>{{ $seatCount }}</dd></div>
                @if (! empty($summary['price_per_adult_formatted']))
                    <div class="ota-fare-dl__row">
                        <dt>Price per adult</dt>
                        <dd>{{ e($summary['currency'] ?? 'PKR') }} {{ e($summary['price_per_adult_formatted']) }}</dd>
                    </div>
                @endif
                <div class="ota-fare-dl__row ota-fare-dl__row--total">
                    <dt>Total</dt>
                    <dd>{{ e($summary['currency'] ?? 'PKR') }} {{ number_format($totalAmount, 0) }}</dd>
                </div>
            </dl>
        </div>

        @if ($showPayNote)
            <p class="ota-checkout-pay-note"><i class="fa fa-lock" aria-hidden="true"></i> No payment at this step</p>
        @endif
    </div>
</aside>
