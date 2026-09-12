@php
    /** @var array<string, mixed> $card */
    $originLabel = trim((string) ($card['origin_label'] ?? ''));
    $destLabel = trim((string) ($card['dest_label'] ?? ''));
    $hasOrigin = $originLabel !== '' && $originLabel !== '—';
    $hasDest = $destLabel !== '' && $destLabel !== '—';
@endphp
<article class="ota-group-result-row" data-testid="group-result-row">
    <div class="ota-group-result-row__brand">
        @if (! empty($card['airline_logo_url']))
            <div class="ota-group-result-row__logo ota-airline-logo ota-airline-logo--img">
                <img src="{{ e($card['airline_logo_url']) }}" alt="{{ e($card['airline_name']) }} logo" loading="lazy">
            </div>
        @elseif (! empty($card['airline_code']))
            <div class="ota-group-result-row__logo ota-airline-logo">{{ e($card['airline_code']) }}</div>
        @else
            <div class="ota-group-result-row__logo ota-airline-logo" aria-hidden="true">—</div>
        @endif
        <div class="ota-group-result-row__airline">
            <span class="ota-group-result-row__airline-name">{{ e($card['airline_name']) }}</span>
        </div>
    </div>

    <div class="ota-group-result-row__route">
        @if ($hasOrigin || $hasDest)
            <p class="ota-group-result-row__route-line">
                @if ($hasOrigin)
                    <span class="ota-group-result-row__route-endpoint">{{ e($originLabel) }}</span>
                @endif
                @if ($hasOrigin && $hasDest)
                    <x-jp.icon name="plane" class="ota-group-result-row__route-icon" style="width:16px;height:16px" />
                @endif
                @if ($hasDest)
                    <span class="ota-group-result-row__route-endpoint">{{ e($destLabel) }}</span>
                @endif
            </p>
        @elseif (! empty($card['route_line']) && ($card['route_line'] ?? '') !== '—')
            <p class="ota-group-result-row__route-line">
                <span class="ota-group-result-row__route-endpoint">{{ e($card['route_line']) }}</span>
            </p>
        @endif
        @if (! empty($card['baggage_line']))
            <p class="ota-group-result-row__baggage-line">{{ e($card['baggage_line']) }}</p>
        @endif
        @php
            $mealStatus = (string) ($card['meal_status'] ?? 'unspecified');
            $mealLabel = (string) ($card['meal_label'] ?? 'Meal: Not specified');
        @endphp
        <p class="ota-group-result-row__meal ota-group-result-row__meal--{{ $mealStatus }}" aria-label="{{ e($mealLabel) }}">
            <x-jp.icon name="utensils" class="ota-group-result-row__meal-icon" style="width:14px;height:14px" />
            @if ($mealStatus === 'included')
                <x-jp.icon name="check" class="ota-group-result-row__meal-status" style="width:12px;height:12px" />
            @elseif ($mealStatus === 'excluded')
                <x-jp.icon name="x" class="ota-group-result-row__meal-status" style="width:12px;height:12px" />
            @endif
            <span class="ota-group-result-row__meal-text">{{ e($mealLabel) }}</span>
        </p>
    </div>

    <div class="ota-group-result-row__date">
        <span class="ota-group-result-row__date-label">Departure</span>
        <span class="ota-group-result-row__date-value">{{ e($card['departure_datetime_display'] ?? $card['departure_date_short'] ?? $card['departure_date'] ?? '—') }}</span>
        @if (! empty($card['arrival_time_display']))
            <span class="ota-group-result-row__arrival-time">Arrival: {{ e($card['arrival_time_display']) }}</span>
        @endif
    </div>

    @if (! empty($card['sector_line']))
        <p class="ota-group-result-row__sector-line">{{ e($card['sector_line']) }}</p>
    @endif

    <div class="ota-group-result-row__price">
        <strong>{{ e($card['currency']) }} {{ e($card['price_formatted']) }}</strong>
        <span>per adult</span>
    </div>

    <div class="ota-group-result-row__seats">
        <span class="ota-umrah-groups-badge ota-umrah-groups-badge--{{ $card['seats_badge_variant'] }}">
            {{ e($card['seat_label']) }}
        </span>
    </div>

    <div class="ota-group-result-row__cta">
        @if (! empty($card['cta_disabled']))
            <span class="ota-group-result-row__restricted" title="{{ e($card['cta_message'] ?? '') }}">{{ e($card['cta_label']) }}</span>
        @else
            <a href="{{ $card['cta_url'] }}" class="ota-btn ota-btn-primary ota-btn-sm">{{ e($card['cta_label']) }}</a>
        @endif
    </div>
</article>
