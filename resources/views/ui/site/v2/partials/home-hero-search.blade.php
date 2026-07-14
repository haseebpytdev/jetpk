@php
    $hero = $heroSection ?? [];
    $heroEnabled = $hero['enabled'] ?? true;
    $heroTitle = trim((string) ($hero['title'] ?? ''));
    if ($heroTitle === '') {
        $heroTitle = 'Book flights with clarity and confidence';
    }
    $heroLead = trim(strip_tags((string) ($hero['body_html'] ?? '')));
    if ($heroLead === '') {
        $heroLead = 'Search routes, compare fares, and manage bookings with a premium travel experience built for travelers and agents.';
    }
@endphp
<section class="ota-v2-hero" id="ota-home-hero" data-testid="v2-hero-search">
    <div class="ota-v2-hero__atmosphere" aria-hidden="true"></div>
    <div class="ota-v2-page-wrap ota-v2-hero__inner">
        @if ($heroEnabled)
            <div class="ota-v2-hero__copy">
                <p class="ota-v2-label">Flights &amp; groups</p>
                <h1 class="ota-v2-hero__title">{{ $heroTitle }}</h1>
                <p class="ota-v2-hero__lead">{{ $heroLead }}</p>
            </div>
        @endif

        @if (\App\Support\Platform\PlatformModuleGate::visible('public_flight_search'))
            <div class="ota-v2-search-card" data-testid="v2-search-card">
                <div class="ota-v2-search-card__head">
                    <h2 class="ota-v2-search-card__title">Search flights</h2>
                    <p class="ota-v2-search-card__hint">One search action — compare routes and fares in seconds.</p>
                </div>
                <div class="ota-v2-search-card__body">
                    @include('frontend.partials.ota-hero-flight-search', [
                        'context' => 'home',
                        'defaultDepart' => $defaultDepart ?? '',
                        'defaultOrigin' => $defaultOrigin ?? '',
                        'defaultDestination' => $defaultDestination ?? '',
                        'defaultReturnDate' => $defaultReturnDate ?? '',
                        'defaultTripType' => $defaultTripType ?? 'one_way',
                        'minDate' => $minDate ?? now()->format('Y-m-d'),
                        'groupFacets' => $groupFacets ?? [],
                    ])
                </div>
            </div>
        @endif
    </div>
</section>
