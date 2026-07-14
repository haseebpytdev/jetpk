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
                    <svg class="ota-group-result-row__route-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                        <path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                    </svg>
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
            <svg class="ota-group-result-row__meal-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true">
                <path d="M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8H21V2c-2.76 0-5 2.24-5 4z"/>
            </svg>
            @if ($mealStatus === 'included')
                <svg class="ota-group-result-row__meal-status" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true">
                    <path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
            @elseif ($mealStatus === 'excluded')
                <svg class="ota-group-result-row__meal-status" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true">
                    <path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
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
