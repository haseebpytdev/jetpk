@extends('layouts.frontend')

@section('title', ($client['agency_name'] ?? config('app.name')).' — Flights')

@section('content')
    @php
        $client = $client ?? config('ota-client', []);
        $hero = $heroSection ?? [];
        $heroEnabled = $hero['enabled'] ?? true;
        $bgStyle = ! empty($hero['background_url'] ?? null)
            ? "--ota-hero-bg-image: url('".e($hero['background_url'])."')"
            : '';
    @endphp

    <section
        class="ota-hero ota-hero--banner ota-mobile-home-search-page {{ ($hero['background_url'] ?? null) ? 'ota-hero--has-image' : '' }}"
        id="ota-home-hero"
        @if($bgStyle !== '') style="{{ $bgStyle }}" @endif
    >
        <div class="ota-hero-banner__overlay" aria-hidden="true"></div>
        <div class="ota-hero-inner ota-hero-inner--banner">
            @if($heroEnabled)
                <div class="ota-hero-copy ota-home-desktop-content">
                    <h1>{{ $hero['title'] ?? '' }}</h1>
                    @if(($hero['body_html'] ?? '') !== '')
                        <div class="ota-hero-body ota-hero-lead">{!! $hero['body_html'] !!}</div>
                    @endif
                </div>
            @endif

            <div class="ota-mobile-home-trust-bar ota-mobile-home-only" aria-label="Booking assurances">
                <span><i class="fa fa-headphones" aria-hidden="true"></i> 24/7 Support</span>
                <span class="ota-mobile-home-trust-bar__divider" aria-hidden="true"></span>
                <span><i class="fa fa-lock" aria-hidden="true"></i> Secure booking</span>
            </div>

            <div class="ota-hero-search-wrap">
                @include('frontend.partials.ota-hero-flight-search', [
                    'context' => 'home',
                    'defaultDepart' => $defaultDepart ?? '',
                    'defaultOrigin' => $defaultOrigin ?? '',
                    'defaultDestination' => $defaultDestination ?? '',
                    'defaultReturnDate' => $defaultReturnDate ?? '',
                    'defaultTripType' => $defaultTripType ?? 'one_way',
                    'minDate' => $minDate ?? now()->format('Y-m-d'),
                ])
            </div>

            @if(($trustMetricsSection['enabled'] ?? true) && count($trustMetricsSection['items'] ?? []) > 0)
                <div class="ota-mobile-home-metrics ota-mobile-home-only">
                    @include('frontend.partials.ota-home-trust-metrics', ['trustMetricsSection' => $trustMetricsSection])
                </div>
            @endif
        </div>
    </section>

    <div class="ota-home-desktop-content">
        @if(($trustMetricsSection['enabled'] ?? true) && count($trustMetricsSection['items'] ?? []) > 0)
            @include('frontend.partials.ota-home-trust-metrics', ['trustMetricsSection' => $trustMetricsSection])
        @endif
        @if($featureCardsSection['enabled'] ?? true)
            @include('frontend.partials.ota-home-fares-preview', [
                'featureCardsSection' => $featureCardsSection,
                'dynamicFeaturedFares' => $dynamicFeaturedFares ?? [],
                'featuredFareRules' => $featuredFareRules ?? [],
                'recentFareOffers' => $recentFareOffers ?? [],
                'recentFareCriteria' => $recentFareCriteria ?? [],
            ])
        @endif
        @if(($popularRoutesSection['enabled'] ?? true) && count($popularRoutesSection['items'] ?? []) > 0)
            @include('frontend.partials.ota-popular-routes', [
                'popularRoutesSection' => $popularRoutesSection,
                'defaultDepart' => $defaultDepart ?? '',
            ])
        @endif
        @if(($whyChooseUsSection['enabled'] ?? true) && count($whyChooseUsSection['items'] ?? []) > 0)
            @include('frontend.partials.ota-landing-why', ['whyChooseUsSection' => $whyChooseUsSection])
        @endif
    </div>
@endsection
