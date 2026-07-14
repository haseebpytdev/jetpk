@php
    $checkout = is_array($checkout ?? null) ? $checkout : null;
@endphp

@if ($checkout)
<section class="jp-fare-family-panel" data-jp-fare-family-panel>
    <h3 class="jp-fare-family-panel__title">Selected fare family</h3>
    <div class="jp-fare-family-panel__head">
        <p class="jp-fare-family-panel__name">
            <strong>{{ $checkout['name'] }}</strong>
            @if (!empty($checkout['brand_code']))
                <span class="jp-fare-family-panel__code">{{ $checkout['brand_code'] }}</span>
            @endif
        </p>
        @if (!empty($checkout['price_display']))
            <p class="jp-fare-family-panel__price">
                @if (!empty($checkout['price_is_approximate']))
                    <span class="jp-fare-family-panel__approx">Approx.</span>
                @endif
                {{ preg_replace('/^Approx\.\s*/i', '', (string) $checkout['price_display']) }}
            </p>
        @endif
    </div>
    <dl class="jp-kv-grid jp-kv-grid--fare-family">
        @if (!empty($checkout['baggage_summary']))
            <div class="jp-kv-grid__row"><dt>Baggage</dt><dd>{{ $checkout['baggage_summary'] }}</dd></div>
        @endif
        @if (!empty($checkout['cabin']))
            <div class="jp-kv-grid__row"><dt>Cabin</dt><dd>{{ $checkout['cabin'] }}</dd></div>
        @endif
        @if (!empty($checkout['booking_class']))
            <div class="jp-kv-grid__row"><dt>Booking class</dt><dd>{{ $checkout['booking_class'] }}</dd></div>
        @endif
        @if (!empty($checkout['fare_basis']))
            <div class="jp-kv-grid__row"><dt>Fare basis</dt><dd>{{ $checkout['fare_basis'] }}</dd></div>
        @endif
    </dl>
    <p class="jp-fare-family-panel__note">{{ \App\Support\FlightSearch\FlightOfferDisplayPresenter::SELECTED_FARE_VALIDATION_NOTE }}</p>
</section>
@endif
