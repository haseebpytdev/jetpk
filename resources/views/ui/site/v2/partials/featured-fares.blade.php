@php
    $section = $featureCardsSection ?? [];
    $dynamicFares = is_array($dynamicFeaturedFares ?? null) ? $dynamicFeaturedFares : collect($dynamicFeaturedFares ?? [])->all();
    $fareRules = is_array($featuredFareRules ?? null) ? $featuredFareRules : collect($featuredFareRules ?? [])->all();
    $recentOffers = is_array($recentFareOffers ?? null) ? $recentFareOffers : [];
    $recentCriteria = is_array($recentFareCriteria ?? null) ? $recentFareCriteria : [];
    $sampleCards = is_array($section['items'] ?? null) ? $section['items'] : [];
    $showNumericPrices = ! app()->environment('testing');
    $hasRouteRules = $fareRules !== [];
    $useDynamicSnapshots = $dynamicFares !== [];
    $enabled = $section['enabled'] ?? true;

    $preserveHref = static function (string $href): string {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            $parsed = parse_url($href);
            $path = $parsed['path'] ?? '/';
            $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?'.$parsed['query'] : '';

            return url(ui_preserve_url($path.$query));
        }

        return ui_preserve_url($href);
    };

    $hasCards = $useDynamicSnapshots || $hasRouteRules || $recentOffers !== [] || $sampleCards !== [];
@endphp
@if ($enabled)
<section class="ota-v2-section ota-v2-fare-section" id="fares" data-testid="v2-featured-fares">
    <div class="ota-v2-page-wrap">
        <header class="ota-v2-section__head">
            <p class="ota-v2-label">Fares</p>
            <h2 class="ota-v2-section-title">{{ (string) ($section['title'] ?? 'Featured fares') }}</h2>
            <p class="ota-v2-section__desc">{{ (string) ($section['subtitle'] ?? 'Recently searched or curated routes with clear pricing hierarchy.') }}</p>
        </header>

        @if ($hasCards)
            <div class="ota-v2-fare-grid">
                @if ($useDynamicSnapshots)
                    @foreach ($dynamicFares as $fare)
                        @php
                            $snap = is_array($fare->snapshot ?? null) ? $fare->snapshot : [];
                            $from = (string) ($snap['origin_code'] ?? $fare->origin_code);
                            $to = (string) ($snap['destination_code'] ?? $fare->destination_code);
                            $depart = (string) ($snap['departure_date'] ?? $fare->departureDate());
                            $airline = (string) ($snap['airline_name'] ?? 'Airline');
                            $airlineCode = (string) ($snap['airline_code'] ?? '');
                            $baggage = (string) ($snap['baggage_summary'] ?? 'Baggage details on results');
                            $refundLabel = (string) ($snap['refundable_label'] ?? 'Non-refundable');
                            $refundable = strcasecmp($refundLabel, 'Refundable') === 0;
                            $price = (float) ($snap['price_total'] ?? 0);
                            $currency = (string) ($snap['currency'] ?? 'PKR');
                            $departMeta = $depart;
                            if (! empty($snap['departure_time'])) {
                                $departMeta .= ' '.$snap['departure_time'];
                            }
                        @endphp
                        <article class="ota-v2-fare-card">
                            <p class="ota-v2-fare-card__route">{{ $from }} → {{ $to }}</p>
                            <h3 class="ota-v2-fare-card__airline">{{ $airline }}{{ $airlineCode !== '' ? ' ('.$airlineCode.')' : '' }}</h3>
                            <p class="ota-v2-fare-card__meta">Departure: {{ $departMeta }}</p>
                            @if ($baggage !== '')
                                <p class="ota-v2-fare-card__meta">Baggage: {{ $baggage }}</p>
                            @endif
                            <span @class(['ota-v2-fare-card__badge', 'ota-v2-fare-card__badge--ok' => $refundable, 'ota-v2-fare-card__badge--warn' => ! $refundable])>{{ $refundLabel }}</span>
                            <p class="ota-v2-fare-card__price">{{ $showNumericPrices ? $currency.' '.number_format($price) : $currency.' fare available' }}</p>
                            <a class="ota-v2-btn ota-v2-btn--primary ota-v2-fare-card__cta" href="{{ $preserveHref($fare->viewFaresUrl()) }}">View fares</a>
                        </article>
                    @endforeach
                @elseif ($hasRouteRules)
                    @foreach ($fareRules as $fare)
                        <article class="ota-v2-fare-card">
                            <p class="ota-v2-fare-card__route">{{ $fare->origin_code }} → {{ $fare->destination_code }}</p>
                            <h3 class="ota-v2-fare-card__airline">Search this route</h3>
                            <p class="ota-v2-fare-card__meta">Departure: {{ $fare->departureDate() }}</p>
                            <p class="ota-v2-fare-card__meta">Fare refreshes daily after search sync.</p>
                            <a class="ota-v2-btn ota-v2-btn--primary ota-v2-fare-card__cta" href="{{ $preserveHref($fare->viewFaresUrl()) }}">View fares</a>
                        </article>
                    @endforeach
                @elseif ($recentOffers !== [])
                    @foreach ($recentOffers as $offer)
                        @php
                            $from = (string) ($recentCriteria['origin'] ?? 'LHE');
                            $to = (string) ($recentCriteria['destination'] ?? 'DXB');
                            $depart = (string) ($recentCriteria['depart_date'] ?? now()->addDays(14)->toDateString());
                            $price = (float) ($offer['final_customer_price'] ?? $offer['total'] ?? 0);
                            $airline = (string) ($offer['airline_name'] ?? 'Airline');
                            $airlineCode = (string) ($offer['airline_code'] ?? '');
                            $baggage = (string) ($offer['baggage'] ?? 'Baggage details on results');
                            $refundable = (bool) ($offer['refundable'] ?? false);
                            $resultsUrl = route('flights.results', [
                                'from' => $from, 'to' => $to, 'depart' => $depart,
                                'trip_type' => 'one_way', 'cabin' => 'economy',
                                'adults' => 1, 'children' => 0, 'infants' => 0,
                            ]);
                        @endphp
                        <article class="ota-v2-fare-card">
                            <p class="ota-v2-fare-card__route">{{ $from }} → {{ $to }}</p>
                            <h3 class="ota-v2-fare-card__airline">{{ $airline }}{{ $airlineCode !== '' ? ' ('.$airlineCode.')' : '' }}</h3>
                            <p class="ota-v2-fare-card__meta">Departure: {{ $depart }}</p>
                            <p class="ota-v2-fare-card__meta">Baggage: {{ $baggage }}</p>
                            <span @class(['ota-v2-fare-card__badge', 'ota-v2-fare-card__badge--ok' => $refundable, 'ota-v2-fare-card__badge--warn' => ! $refundable])>
                                {{ $refundable ? 'Refundable' : 'Non-refundable' }}
                            </span>
                            <p class="ota-v2-fare-card__price">{{ $showNumericPrices ? 'PKR '.number_format($price > 0 ? $price : 0) : 'PKR fare available' }}</p>
                            <a class="ota-v2-btn ota-v2-btn--primary ota-v2-fare-card__cta" href="{{ $preserveHref($resultsUrl) }}">View fares</a>
                        </article>
                    @endforeach
                @else
                    @foreach ($sampleCards as $card)
                        @php
                            $from = (string) ($card['from'] ?? 'LHE');
                            $to = (string) ($card['to'] ?? 'DXB');
                            $depart = (string) ($card['depart'] ?? now()->addDays(14)->toDateString());
                            $airline = (string) ($card['airline'] ?? 'Sample Airline');
                            $airlineCode = (string) ($card['airline_code'] ?? '');
                            $baggage = (string) ($card['baggage'] ?? '20 kg checked + 7 kg cabin');
                            $refundable = (bool) ($card['refundable'] ?? false);
                            $badge = (string) ($card['badge'] ?? ($refundable ? 'Refundable' : 'Non-refundable'));
                            $price = (float) ($card['price'] ?? 0);
                            $buttonLabel = (string) ($card['button_label'] ?? 'View fares');
                            $buttonUrl = trim((string) ($card['button_url'] ?? ''));
                            if ($buttonUrl === '') {
                                $buttonUrl = route('flights.results', [
                                    'from' => $from, 'to' => $to, 'depart' => $depart,
                                    'trip_type' => 'one_way', 'cabin' => 'economy',
                                    'adults' => 1, 'children' => 0, 'infants' => 0,
                                ]);
                            }
                        @endphp
                        <article class="ota-v2-fare-card">
                            <p class="ota-v2-fare-card__route">{{ $from }} → {{ $to }}</p>
                            <h3 class="ota-v2-fare-card__airline">{{ $airline }}{{ $airlineCode !== '' ? ' ('.$airlineCode.')' : '' }}</h3>
                            <p class="ota-v2-fare-card__meta">Departure: {{ $depart }}</p>
                            <p class="ota-v2-fare-card__meta">Baggage: {{ $baggage }}</p>
                            <span @class(['ota-v2-fare-card__badge', 'ota-v2-fare-card__badge--ok' => $refundable, 'ota-v2-fare-card__badge--warn' => ! $refundable])>{{ $badge }}</span>
                            <p class="ota-v2-fare-card__price">{{ $showNumericPrices ? 'PKR '.number_format($price) : 'PKR fare available' }}</p>
                            <a class="ota-v2-btn ota-v2-btn--primary ota-v2-fare-card__cta" href="{{ $preserveHref($buttonUrl) }}">{{ $buttonLabel }}</a>
                        </article>
                    @endforeach
                @endif
            </div>
        @else
            <div class="ota-v2-empty-state">
                <p class="ota-v2-empty-state__title">No featured fares yet</p>
                <p class="ota-v2-empty-state__text">Search a route above to see live fares, or check back when featured routes are published.</p>
                <a href="#ota-home-hero" class="ota-v2-btn ota-v2-btn--primary">Search flights</a>
            </div>
        @endif
    </div>
</section>
@endif
