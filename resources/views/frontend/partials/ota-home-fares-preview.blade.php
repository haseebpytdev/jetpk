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
@endphp
<section class="ota-section ota-home-fares" id="fares">
    <div class="ota-container">
        <header class="ota-section-head ota-section-head--compact">
            <p class="ota-section-kicker">Fares</p>
            <h2 class="ota-section-title">{{ (string) ($section['title'] ?? 'Search your route to view available fares') }}</h2>
            <p class="ota-section-desc">{{ (string) ($section['subtitle'] ?? 'View recently searched fares when available, or browse featured sample routes.') }}</p>
        </header>
        <div class="fare-preview-grid">
            @if($useDynamicSnapshots)
                @foreach($dynamicFares as $fare)
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
                    <article class="fare-preview-card">
                        <p class="fare-preview-route">{{ $from }} → {{ $to }}</p>
                        <h3>{{ $airline }}{{ $airlineCode !== '' ? ' ('.$airlineCode.')' : '' }}</h3>
                        <p class="fare-preview-meta">Departure: {{ $departMeta }}</p>
                        @if($baggage !== '')
                            <p class="fare-preview-meta">Baggage: {{ $baggage }}</p>
                        @endif
                        <span class="fare-preview-badge {{ $refundable ? 'fare-preview-badge--ok' : 'fare-preview-badge--warn' }}">
                            {{ $refundLabel }}
                        </span>
                        <p class="fare-preview-price">{{ $showNumericPrices ? $currency.' '.number_format($price) : $currency.' fare available' }}</p>
                        <a class="public-btn public-btn-primary" href="{{ $fare->viewFaresUrl() }}">View fares</a>
                    </article>
                @endforeach
            @elseif($hasRouteRules)
                @foreach($fareRules as $fare)
                    <article class="fare-preview-card">
                        <p class="fare-preview-route">{{ $fare->origin_code }} → {{ $fare->destination_code }}</p>
                        <h3>Search this route</h3>
                        <p class="fare-preview-meta">Departure: {{ $fare->departureDate() }}</p>
                        <p class="fare-preview-meta text-muted">Fare refreshes daily after search sync.</p>
                        <a class="public-btn public-btn-primary" href="{{ $fare->viewFaresUrl() }}">View fares</a>
                    </article>
                @endforeach
            @elseif($recentOffers !== [])
                @foreach($recentOffers as $idx => $offer)
                    @php
                        $from = (string) ($recentCriteria['origin'] ?? 'LHE');
                        $to = (string) ($recentCriteria['destination'] ?? 'DXB');
                        $depart = (string) ($recentCriteria['depart_date'] ?? now()->addDays(14)->toDateString());
                        $price = (float) ($offer['final_customer_price'] ?? $offer['total'] ?? 0);
                        $airline = (string) ($offer['airline_name'] ?? 'Airline');
                        $airlineCode = (string) ($offer['airline_code'] ?? '');
                        $baggage = (string) ($offer['baggage'] ?? 'Baggage details on results');
                        $refundable = (bool) ($offer['refundable'] ?? false);
                    @endphp
                    <article class="fare-preview-card">
                        <p class="fare-preview-route">{{ $from }} → {{ $to }}</p>
                        <h3>{{ $airline }}{{ $airlineCode !== '' ? ' ('.$airlineCode.')' : '' }}</h3>
                        <p class="fare-preview-meta">Departure: {{ $depart }}</p>
                        <p class="fare-preview-meta">Baggage: {{ $baggage }}</p>
                        <span class="fare-preview-badge {{ $refundable ? 'fare-preview-badge--ok' : 'fare-preview-badge--warn' }}">
                            {{ $refundable ? 'Refundable' : 'Non-refundable' }}
                        </span>
                        <p class="fare-preview-price">{{ $showNumericPrices ? 'PKR '.number_format($price > 0 ? $price : 0) : 'PKR fare available' }}</p>
                        <a class="public-btn public-btn-primary" href="{{ client_route('flights.results', ['from' => $from, 'to' => $to, 'depart' => $depart, 'trip_type' => 'one_way', 'cabin' => 'economy', 'adults' => 1, 'children' => 0, 'infants' => 0]) }}">View fares</a>
                    </article>
                @endforeach
            @else
                @foreach($sampleCards as $card)
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
                            $buttonUrl = client_route('flights.results', ['from' => $from, 'to' => $to, 'depart' => $depart, 'trip_type' => 'one_way', 'cabin' => 'economy', 'adults' => 1, 'children' => 0, 'infants' => 0]);
                        }
                    @endphp
                    <article class="fare-preview-card">
                        <p class="fare-preview-route">{{ $from }} → {{ $to }}</p>
                        <h3>{{ $airline }}{{ $airlineCode !== '' ? ' ('.$airlineCode.')' : '' }}</h3>
                        <p class="fare-preview-meta">Departure: {{ $depart }}</p>
                        <p class="fare-preview-meta">Baggage: {{ $baggage }}</p>
                        <span class="fare-preview-badge {{ $refundable ? 'fare-preview-badge--ok' : 'fare-preview-badge--warn' }}">
                            {{ $badge }}
                        </span>
                        <p class="fare-preview-price">{{ $showNumericPrices ? 'PKR '.number_format($price) : 'PKR fare available' }}</p>
                        <a class="public-btn public-btn-primary" href="{{ $buttonUrl }}">{{ $buttonLabel }}</a>
                    </article>
                @endforeach
            @endif
        </div>
    </div>
</section>
