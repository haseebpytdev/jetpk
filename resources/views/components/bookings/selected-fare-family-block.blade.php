@props([
    'checkout' => null,
    'variant' => 'desktop',
])

@php
    $selectedFareFamilyCheckout = is_array($checkout) ? $checkout : null;
@endphp

@if ($selectedFareFamilyCheckout)
    @if ($variant === 'mobile')
        <div class="ota-mobile-booking__selected-fare" {{ $attributes }}>
            <p class="ota-mobile-booking__selected-fare-title">Selected fare family</p>
            <p class="ota-mobile-booking__selected-fare-name">
                <strong>{{ $selectedFareFamilyCheckout['name'] }}</strong>
                @if (!empty($selectedFareFamilyCheckout['brand_code']))
                    ({{ $selectedFareFamilyCheckout['brand_code'] }})
                @endif
            </p>
            @if (!empty($selectedFareFamilyCheckout['price_display']))
                <p class="ota-mobile-booking__selected-fare-price">
                    @if (!empty($selectedFareFamilyCheckout['price_is_approximate']))
                        <span class="ota-mobile-booking__tag-note">Approx.</span>
                    @endif
                    {{ preg_replace('/^Approx\.\s*/i', '', (string) $selectedFareFamilyCheckout['price_display']) }}
                </p>
            @endif
            <ul class="ota-mobile-booking__tags ota-mobile-booking__tags--selected-fare">
                @if (!empty($selectedFareFamilyCheckout['baggage_summary']))
                    <li>Baggage: {{ $selectedFareFamilyCheckout['baggage_summary'] }}</li>
                @endif
                @if (!empty($selectedFareFamilyCheckout['cabin']))
                    <li>Cabin: {{ $selectedFareFamilyCheckout['cabin'] }}</li>
                @endif
                @if (!empty($selectedFareFamilyCheckout['booking_class']))
                    <li>Class: {{ $selectedFareFamilyCheckout['booking_class'] }}</li>
                @endif
                @if (!empty($selectedFareFamilyCheckout['fare_basis']))
                    <li>Fare basis: {{ $selectedFareFamilyCheckout['fare_basis'] }}</li>
                @endif
            </ul>
            <p class="ota-mobile-booking__tag-note">{{ \App\Support\FlightSearch\FlightOfferDisplayPresenter::SELECTED_FARE_VALIDATION_NOTE }}</p>
        </div>
    @else
        <div class="ota-checkout-sidebar-block ota-checkout-selected-fare-family" {{ $attributes }}>
            <h3 class="ota-checkout-sidebar-block__title">Selected fare family</h3>
            <p class="ota-checkout-selected-fare-family__heading mb-1">
                <strong>{{ $selectedFareFamilyCheckout['name'] }}</strong>
                @if (!empty($selectedFareFamilyCheckout['brand_code']))
                    <span class="text-muted">({{ $selectedFareFamilyCheckout['brand_code'] }})</span>
                @endif
            </p>
            @if (!empty($selectedFareFamilyCheckout['price_display']))
                <p class="ota-checkout-selected-fare-family__price">
                    @if (!empty($selectedFareFamilyCheckout['price_is_approximate']))
                        <span class="ota-checkout-selected-fare-family__approx">Approx.</span>
                    @endif
                    {{ preg_replace('/^Approx\.\s*/i', '', (string) $selectedFareFamilyCheckout['price_display']) }}
                </p>
            @endif
            <dl class="ota-fare-dl ota-fare-dl--compact ota-checkout-selected-fare-family__dl">
                @if (!empty($selectedFareFamilyCheckout['baggage_summary']))
                    <div class="ota-fare-dl__row"><dt>Baggage</dt><dd>{{ $selectedFareFamilyCheckout['baggage_summary'] }}</dd></div>
                @endif
                @if (!empty($selectedFareFamilyCheckout['cabin']))
                    <div class="ota-fare-dl__row"><dt>Cabin</dt><dd>{{ $selectedFareFamilyCheckout['cabin'] }}</dd></div>
                @endif
                @if (!empty($selectedFareFamilyCheckout['booking_class']))
                    <div class="ota-fare-dl__row"><dt>Booking class</dt><dd>{{ $selectedFareFamilyCheckout['booking_class'] }}</dd></div>
                @endif
                @if (!empty($selectedFareFamilyCheckout['fare_basis']))
                    <div class="ota-fare-dl__row"><dt>Fare basis</dt><dd>{{ $selectedFareFamilyCheckout['fare_basis'] }}</dd></div>
                @endif
            </dl>
            <p class="ota-checkout-selected-fare-family__note">{{ \App\Support\FlightSearch\FlightOfferDisplayPresenter::SELECTED_FARE_VALIDATION_NOTE }}</p>
        </div>
    @endif
@endif
