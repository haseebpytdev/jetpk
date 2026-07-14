@extends('layouts.mobile-app')

@php
    $brandName = $brandName ?? config('app.name');
    $footerAbout = $footerAbout ?? '';
    $officeCity = $officeCity ?? '';
    $aboutUs = $aboutUs ?? ['has_custom' => false, 'mode' => null, 'body_html' => ''];
    $aboutUsHasCustom = ! empty($aboutUs['has_custom']);
    $defaultStory = 'We help travellers and partners book flights with clear pricing, responsive support, and careful handling of documents and itinerary changes.';
@endphp

@section('title', 'About us')

@section('content')
    <div class="ota-mobile-public" data-testid="ota-mobile-about">
        <header class="ota-mobile-public__header">
            <p class="ota-mobile-public__kicker">About us</p>
            <h1 class="ota-mobile-public__title">About {{ $brandName }}</h1>
            @unless($aboutUsHasCustom)
                <p class="ota-mobile-public__lead">
                    {{ $footerAbout !== '' ? $footerAbout : $defaultStory }}
                </p>
            @endunless
        </header>

        @if($aboutUsHasCustom)
            <section class="ota-mobile-public__card ota-about-custom-body" data-about-custom="{{ $aboutUs['mode'] ?? 'plain' }}">
                {!! $aboutUs['body_html'] ?? '' !!}
            </section>
        @else
        <section class="ota-mobile-public__card">
            <h2 class="ota-mobile-public__card-title">Our story</h2>
            <p>
                {{ $brandName }} was built around a simple idea: flight booking should feel guided, not confusing.
                @if($officeCity !== '')
                    From our base in {{ $officeCity }}, we serve travellers with clarity and follow-through.
                @else
                    We focus on the routes our customers fly most often with clarity and follow-through.
                @endif
            </p>
        </section>

        <section class="ota-mobile-public__card">
            <h2 class="ota-mobile-public__card-title">What we do</h2>
            <ul class="ota-mobile-public__list">
                <li>Flight search across preferred routes and cabins</li>
                <li>Booking creation with transparent fare context</li>
                <li>E-tickets, receipts, and travel document coordination</li>
                <li>Changes and cancellations per carrier rules</li>
                <li>Ongoing support until departure</li>
            </ul>
        </section>

        <section class="ota-mobile-public__card">
            <h2 class="ota-mobile-public__card-title">Partners and agents</h2>
            <p>If you represent travellers, learn more on our <a href="{{ route('agent.register') }}">Agent Network</a> page.</p>
        </section>

        @endif

        <nav class="ota-mobile-auth__quick-actions" aria-label="About page links">
            <a href="{{ route('support') }}" class="ota-mobile-auth__quick-link">Support and contact</a>
            <a href="{{ route('home') }}" class="ota-mobile-auth__quick-link">Search flights</a>
        </nav>
    </div>
@endsection
