@php
    /** @var array<string, mixed> $card */
@endphp
<article class="ota-result-pro-card ota-group-result-card ota-group-result-card--premium" data-testid="group-result-card">
    <header class="ota-group-result-card__top">
        <div class="ota-group-result-card__brand">
            @if (! empty($card['airline_logo_url']))
                <div class="ota-group-result-card__logo ota-airline-logo ota-airline-logo--img">
                    <img src="{{ e($card['airline_logo_url']) }}" alt="{{ e($card['airline_name']) }} logo" loading="lazy">
                </div>
            @elseif (! empty($card['airline_code']))
                <div class="ota-group-result-card__logo ota-airline-logo">{{ e($card['airline_code']) }}</div>
            @else
                <div class="ota-group-result-card__logo ota-airline-logo" aria-hidden="true">—</div>
            @endif
            <div class="ota-group-result-card__airline-text">
                <p class="ota-airline-name">{{ e($card['airline_name']) }}</p>
                @if (! empty($card['title']))
                    <p class="ota-group-result-card__package">{{ e($card['title']) }}</p>
                @endif
            </div>
        </div>
        <span class="ota-umrah-groups-badge ota-umrah-groups-badge--{{ $card['seats_badge_variant'] }}">
            {{ e($card['seat_label']) }}
        </span>
    </header>

    <div class="ota-group-result-card__route">
        <p class="ota-group-result-card__route-line">{{ e($card['route_line']) }}</p>
        @if (! empty($card['sector_raw']))
            <p class="ota-group-result-card__sector-meta">Sector: {{ e($card['sector_raw']) }}</p>
        @endif
    </div>

    <dl class="ota-group-result-card__details">
        @if (! empty($card['departure_date']))
            <div class="ota-group-result-card__detail">
                <dt>Departure</dt>
                <dd>
                    <span class="ota-group-result-card__date-full">{{ e($card['departure_date']) }}</span>
                    @if (! empty($card['departure_date_short']))
                        <span class="ota-group-result-card__date-short">{{ e($card['departure_date_short']) }}</span>
                    @endif
                </dd>
            </div>
        @endif
        @if (! empty($card['baggage']['display']))
            <div class="ota-group-result-card__detail">
                <dt>Baggage</dt>
                <dd>
                    @if (! empty($card['baggage']['checked']) && ! empty($card['baggage']['cabin']))
                        <span>Checked {{ e($card['baggage']['checked']) }}</span>
                        <span class="ota-group-result-card__detail-sep" aria-hidden="true">·</span>
                        <span>Cabin {{ e($card['baggage']['cabin']) }}</span>
                    @else
                        {{ e($card['baggage']['display']) }}
                    @endif
                </dd>
            </div>
        @endif
        @if (! empty($card['refund_change_notes']))
            <div class="ota-group-result-card__detail ota-group-result-card__detail--wide">
                <dt>Refund / change</dt>
                <dd>{{ e($card['refund_change_notes']) }}</dd>
            </div>
        @endif
    </dl>

    <footer class="ota-group-result-card__foot">
        <div class="ota-group-result-card__price">
            <strong>{{ e($card['currency']) }} {{ e($card['price_formatted']) }}</strong>
            <span>per adult</span>
        </div>
        <a href="{{ $card['cta_url'] }}" class="ota-btn ota-btn-primary ota-btn-sm">{{ e($card['cta_label']) }}</a>
    </footer>
</article>
