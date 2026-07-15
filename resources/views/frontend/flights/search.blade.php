@extends(client_layout('frontend', 'frontend'))

@section('title', 'Flight search')

@section('content')
    <section class="ota-section ota-routes-section ota-flight-search-wrap">
        <div class="ota-container ota-container-narrow">
            <header class="ota-section-head">
                <p class="ota-section-kicker">Flights</p>
                <h1 class="ota-section-title">Book your next flight</h1>
                <p class="ota-section-desc">Search routes, compare fares, and continue to booking review with {{ $brandName }} support.</p>
            </header>
            @include('frontend.partials.ota-flight-widget', [
                'variant' => 'standalone',
                'show_intro' => false,
                'defaultDepart' => $defaults['depart'] ?? '',
                'defaultOrigin' => $defaults['origin'] ?? '',
                'defaultDestination' => $defaults['destination'] ?? '',
                'defaultReturnDate' => $defaults['return_date'] ?? '',
                'defaultTripType' => $defaults['trip_type'] ?? 'one_way',
                'minDate' => $minDate ?? now()->format('Y-m-d'),
            ])
        </div>
    </section>
@endsection
