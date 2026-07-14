@extends('ui.site.v2.layouts.frontend')

@section('title', ($client['agency_name'] ?? config('app.name')).' — Flights')

@section('content')
    @include('partials.agent-booking-mode-banner')

    <div class="ota-v2-home" data-testid="v2-homepage">
        @include('ui.site.v2.partials.home-hero-search', [
            'heroSection' => $heroSection ?? [],
            'defaultDepart' => $defaultDepart ?? '',
            'defaultOrigin' => $defaultOrigin ?? '',
            'defaultDestination' => $defaultDestination ?? '',
            'defaultReturnDate' => $defaultReturnDate ?? '',
            'defaultTripType' => $defaultTripType ?? 'one_way',
            'minDate' => $minDate ?? now()->format('Y-m-d'),
            'groupFacets' => $groupFacets ?? [],
        ])

        @include('ui.site.v2.partials.trust-strip', [
            'trustMetricsSection' => $trustMetricsSection ?? [],
        ])

        @include('ui.site.v2.partials.group-departures', [
            'groupHomepageTiles' => $groupHomepageTiles ?? [],
        ])

        @include('ui.site.v2.partials.featured-fares', [
            'featureCardsSection' => $featureCardsSection ?? [],
            'dynamicFeaturedFares' => $dynamicFeaturedFares ?? [],
            'featuredFareRules' => $featuredFareRules ?? [],
            'recentFareOffers' => $recentFareOffers ?? [],
            'recentFareCriteria' => $recentFareCriteria ?? [],
        ])

        @include('ui.site.v2.partials.popular-corridors', [
            'popularRoutesSection' => $popularRoutesSection ?? [],
            'defaultDepart' => $defaultDepart ?? '',
        ])

        @include('ui.site.v2.partials.why-book', [
            'whyChooseUsSection' => $whyChooseUsSection ?? [],
            'agencySettings' => $agencySettings ?? null,
            'brandName' => $client['agency_name'] ?? config('app.name'),
        ])
    </div>
@endsection
