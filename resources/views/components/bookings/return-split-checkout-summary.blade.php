@props(['summary'])

@php
    $summary = is_array($summary ?? null) ? $summary : [];
    $isSplit = ! empty($summary['is_return_split']);
    $out = is_array($summary['outbound'] ?? null) ? $summary['outbound'] : [];
    $ret = is_array($summary['return'] ?? null) ? $summary['return'] : [];
    $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
    $pricingMode = (string) ($totals['pricing_mode'] ?? $summary['pricing_mode'] ?? 'combo_total');
    $isComboTotal = $pricingMode === 'combo_total';
@endphp

@if ($isSplit)
    <div class="ota-checkout-split-summary" data-return-split-checkout-summary>
        @foreach (['outbound' => $out, 'return' => $ret] as $legKey => $leg)
            @if ($leg !== [])
                <section class="ota-checkout-split-summary__leg">
                    <h3 class="ota-checkout-split-summary__leg-title">{{ $legKey === 'outbound' ? 'Outbound' : 'Return' }}</h3>
                    @if (! empty($leg['route_label']))
                        <p class="ota-checkout-split-summary__route">{{ $leg['route_label'] }}</p>
                    @endif
                    @php
                        $scheduleParts = array_filter([
                            ! empty($leg['date_label']) ? $leg['date_label'] : null,
                            (! empty($leg['departure_time']) || ! empty($leg['arrival_time']))
                                ? trim(($leg['departure_time'] ?? '').' — '.($leg['arrival_time'] ?? ''))
                                : null,
                        ]);
                    @endphp
                    @if ($scheduleParts !== [])
                        <p class="ota-checkout-split-summary__schedule">{{ implode(' · ', $scheduleParts) }}</p>
                    @endif
                    @php
                        $flightMeta = array_filter([
                            ! empty($leg['flight_number']) ? $leg['flight_number'] : null,
                            ! empty($leg['stops_label']) ? $leg['stops_label'] : null,
                            ! empty($leg['duration']) ? $leg['duration'] : null,
                        ]);
                    @endphp
                    @if ($flightMeta !== [])
                        <p class="ota-checkout-split-summary__meta">{{ implode(' · ', $flightMeta) }}</p>
                    @endif
                    <dl class="ota-checkout-split-summary__fare-lines">
                        <div class="ota-checkout-split-summary__fare-line">
                            <dt>Fare</dt>
                            <dd>{{ $leg['fare_family_title'] ?? $leg['branded_fare_title'] ?? 'Standard fare' }}</dd>
                        </div>
                        @if (! empty($leg['baggage']))
                            <div class="ota-checkout-split-summary__fare-line">
                                <dt>Baggage</dt>
                                <dd>{{ $leg['baggage'] }}</dd>
                            </div>
                        @endif
                        @if (! $isComboTotal && ! empty($leg['price_display']))
                            <div class="ota-checkout-split-summary__fare-line">
                                <dt>Selected fare</dt>
                                <dd>{{ $leg['price_display'] }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if (is_array($leg['journey'] ?? null))
                        <x-bookings.checkout-journey-layovers :journey="$leg['journey']" />
                    @endif
                </section>
            @endif
        @endforeach

        @if (! empty($totals['base_price_display']) || ! empty($totals['selected_total_display']) || ! empty($totals['grand_total_display']) || ! empty($totals['fare_difference_display']))
            <section class="ota-checkout-split-summary__totals">
                <h3 class="ota-checkout-split-summary__totals-title">Price summary</h3>
                @if ($isComboTotal)
                    @if (! empty($totals['base_price_display']))
                        <div class="ota-checkout-split-summary__total-row">
                            <span>Base fare</span>
                            <strong>{{ $totals['base_price_display'] }}</strong>
                        </div>
                    @endif
                    @if (! empty($totals['fare_difference_display']))
                        <div class="ota-checkout-split-summary__total-row">
                            <span>Fare change</span>
                            <strong>{{ $totals['fare_difference_display'] }}</strong>
                        </div>
                    @endif
                    @if (! empty($totals['grand_total_display']))
                        <div class="ota-checkout-split-summary__total-row ota-checkout-split-summary__total-row--grand">
                            <span>Total</span>
                            <strong>{{ $totals['grand_total_display'] }}</strong>
                        </div>
                    @endif
                @else
                    @if (! empty($totals['outbound_price_display']))
                        <div class="ota-checkout-split-summary__total-row">
                            <span>Outbound selected fare</span>
                            <strong>{{ $totals['outbound_price_display'] }}</strong>
                        </div>
                    @endif
                    @if (! empty($totals['return_price_display']))
                        <div class="ota-checkout-split-summary__total-row">
                            <span>Return selected fare</span>
                            <strong>{{ $totals['return_price_display'] }}</strong>
                        </div>
                    @endif
                    @if (! empty($totals['grand_total_display']))
                        <div class="ota-checkout-split-summary__total-row ota-checkout-split-summary__total-row--grand">
                            <span>Total</span>
                            <strong>{{ $totals['grand_total_display'] }}</strong>
                        </div>
                    @endif
                @endif
            </section>
        @endif
    </div>
@endif
