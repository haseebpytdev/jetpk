@php
    $routeItems = collect(data_get($content, 'routes.items', []))->values();
    if ($routeItems->count() < 4) {
        $routeItems = $routeItems->pad(4, []);
    }
    $fareCache = data_get($content, '_fare_cache.routes', []);
    $offsetDays = (int) config('jetpk_homepage.route_date_offset_days', 7);
@endphp
<div class="jp-card jp-page-section jp-is-hidden" id="section-routes" data-jp-section-panel="routes">
    <div class="jp-between jp-section-toggle">
        <h2 class="jp-card__title" style="margin:0;">Trending routes</h2>
        <div class="jp-toggle">
            <input type="hidden" name="content[routes][enabled]" value="0">
            <input type="checkbox" id="routes-enabled" name="content[routes][enabled]" value="1" @checked(data_get($content, 'routes.enabled', '1') == '1')>
            <label for="routes-enabled">Enabled</label>
        </div>
    </div>
    <p class="jp-field__help">Dynamic fares search travel date = today + {{ $offsetDays }} days (read-only; no booking). Use toolbar “Refresh route fares” after publishing.</p>
    <div class="jp-grid jp-grid--2">
        <div class="jp-field">
            <label class="jp-field__label" for="routes-eyebrow">Eyebrow</label>
            <input id="routes-eyebrow" class="jp-control" name="content[routes][eyebrow]" value="{{ data_get($content, 'routes.eyebrow') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="routes-title">Heading</label>
            <input id="routes-title" class="jp-control" name="content[routes][title]" value="{{ data_get($content, 'routes.title') }}">
        </div>
    </div>
    <div class="jp-field">
        <label class="jp-field__label" for="routes-subtitle">Subtitle</label>
        <textarea id="routes-subtitle" class="jp-control jp-control--textarea" rows="2" name="content[routes][subtitle]">{{ data_get($content, 'routes.subtitle') }}</textarea>
    </div>
    <div class="jp-grid jp-grid--2">
        <div class="jp-field">
            <label class="jp-field__label" for="routes-cta-text">Section CTA label</label>
            <input id="routes-cta-text" class="jp-control" name="content[routes][cta_text]" value="{{ data_get($content, 'routes.cta_text') }}">
        </div>
        <div class="jp-field">
            <label class="jp-field__label" for="routes-cta-url">Section CTA URL</label>
            <input id="routes-cta-url" class="jp-control" name="content[routes][cta_url]" value="{{ data_get($content, 'routes.cta_url') }}">
        </div>
    </div>

    <div class="jp-repeatable-list" data-jp-repeatable="routes" data-jp-repeatable-max="{{ (int) config('jetpk_homepage.max_routes', 12) }}">
        @foreach ($routeItems as $i => $item)
            @php
                $item = is_array($item) ? $item : [];
                $routeId = data_get($item, 'id') ?: 'route-'.$i;
                $cache = is_array($fareCache[$routeId] ?? null) ? $fareCache[$routeId] : [];
            @endphp
            <div class="jp-repeatable-card" data-jp-repeatable-row>
                <div class="jp-between">
                    <p class="jp-muted" style="margin:0;">Route {{ $i + 1 }}</p>
                    <div class="jp-toggle">
                        <input type="hidden" name="content[routes][items][{{ $i }}][enabled]" value="0">
                        <input type="checkbox" id="route-enabled-{{ $i }}" name="content[routes][items][{{ $i }}][enabled]" value="1" @checked(data_get($item, 'enabled', '1') == '1')>
                        <label for="route-enabled-{{ $i }}">Active</label>
                    </div>
                </div>
                <input type="hidden" name="content[routes][items][{{ $i }}][id]" value="{{ $routeId }}">
                <input type="hidden" name="content[routes][items][{{ $i }}][sort_order]" value="{{ data_get($item, 'sort_order', $i) }}">
                <div class="jp-grid jp-grid--3">
                    <div class="jp-field">
                        <label class="jp-field__label">Origin IATA</label>
                        <input aria-label="Origin IATA" class="jp-control" name="content[routes][items][{{ $i }}][from]" value="{{ data_get($item, 'from') }}" maxlength="3" placeholder="KHI">
                    </div>
                    <div class="jp-field">
                        <label class="jp-field__label">Destination IATA</label>
                        <input aria-label="Destination IATA" class="jp-control" name="content[routes][items][{{ $i }}][to]" value="{{ data_get($item, 'to') }}" maxlength="3" placeholder="DXB">
                    </div>
                    <div class="jp-field">
                        <label class="jp-field__label">Trip type</label>
                        <select aria-label="Trip type" class="jp-control jp-control--select" name="content[routes][items][{{ $i }}][trip_type]">
                            <option value="one_way" @selected(data_get($item, 'trip_type', 'one_way') === 'one_way')>One-way</option>
                            <option value="return" @selected(data_get($item, 'trip_type') === 'return')>Return</option>
                        </select>
                    </div>
                    <div class="jp-field">
                        <label class="jp-field__label">Return stay (days)</label>
                        <input aria-label="Return stay (days)" type="number" min="1" max="30" class="jp-control" name="content[routes][items][{{ $i }}][return_stay_days]" value="{{ data_get($item, 'return_stay_days', config('jetpk_homepage.default_return_stay_days', 7)) }}">
                    </div>
                    <div class="jp-field">
                        <label class="jp-field__label">Manual fallback (PKR)</label>
                        <input aria-label="Manual fallback (PKR)" class="jp-control" name="content[routes][items][{{ $i }}][manual_fallback_price]" value="{{ data_get($item, 'manual_fallback_price', data_get($item, 'price')) }}">
                    </div>
                    <div class="jp-field">
                        <label class="jp-field__label">Badge</label>
                        <input aria-label="Badge" class="jp-control" name="content[routes][items][{{ $i }}][badge]" value="{{ data_get($item, 'badge') }}">
                    </div>
                </div>
                <div class="jp-grid jp-grid--3">
                    <div class="jp-toggle">
                        <input type="hidden" name="content[routes][items][{{ $i }}][dynamic_fare_enabled]" value="0">
                        <input type="checkbox" id="route-dynamic-{{ $i }}" name="content[routes][items][{{ $i }}][dynamic_fare_enabled]" value="1" @checked(data_get($item, 'dynamic_fare_enabled', '0') == '1')>
                        <label for="route-dynamic-{{ $i }}">Dynamic fare enabled</label>
                    </div>
                    <div class="jp-field">
                        <label class="jp-field__label">Adults</label>
                        <input aria-label="Adults" type="number" min="1" max="9" class="jp-control" name="content[routes][items][{{ $i }}][adults]" value="{{ data_get($item, 'adults', 1) }}">
                    </div>
                    <div class="jp-field">
                        <label class="jp-field__label">Cabin</label>
                        <select aria-label="Cabin" class="jp-control jp-control--select" name="content[routes][items][{{ $i }}][cabin]">
                            @foreach (['economy', 'premium_economy', 'business', 'first'] as $cabin)
                                <option value="{{ $cabin }}" @selected(data_get($item, 'cabin', 'economy') === $cabin)>{{ ucfirst(str_replace('_', ' ', $cabin)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if ($cache !== [])
                    <p class="jp-field__help">Last refresh: {{ data_get($cache, 'fare_refreshed_at', '—') }} · Status: {{ data_get($cache, 'fare_status', '—') }} · Fare: {{ data_get($cache, 'resolved_fare') ? 'PKR '.number_format((int) data_get($cache, 'resolved_fare')) : '—' }}</p>
                @endif
                <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-jp-repeatable-remove>Remove route</button>
            </div>
        @endforeach
    </div>
    <button type="button" class="jp-btn jp-btn--sm" data-jp-repeatable-add="routes">Add route</button>
</div>
