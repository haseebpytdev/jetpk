@extends(client_layout('frontend', 'frontend'))

@php
    $brandName = $brandName ?? config('app.name');
    $footerAbout = $footerAbout ?? '';
    $officeCity = $officeCity ?? '';
    $aboutUs = $aboutUs ?? ['has_custom' => false, 'mode' => null, 'body_html' => ''];
    $aboutUsHasCustom = ! empty($aboutUs['has_custom']);
    $isJetPakistanTheme = is_client_preview() && client_theme()->frontendTheme() === 'jetpakistan';
    if ($isJetPakistanTheme) {
        $brandName = 'JetPakistan';
        $officeCity = 'Lahore';
    }
    $defaultStory = 'We help travellers and partners book flights with clear pricing, responsive support, and careful handling of documents and itinerary changes. Whether you are planning a single trip or managing bookings for others, we aim to make every step straightforward—from search and fare clarity to post-booking assistance.';
@endphp

@section('title', 'About us - '.$brandName)

@section('content')
    <section class="ota-section ota-form-page ota-about-page" data-about-premium aria-labelledby="ota-about-heading">
        <div class="ota-container">
            <header class="ota-section-head ota-about-hero">
                <p class="ota-section-kicker">About us</p>
                <h1 id="ota-about-heading" class="ota-section-title ota-about-page-title">About {{ $brandName }}</h1>
                @unless($aboutUsHasCustom)
                    <p class="ota-section-desc ota-about-hero-desc">
                        {{ $footerAbout !== '' ? $footerAbout : $defaultStory }}
                        Need booking help, ticket changes, or direct contact with our team? Visit our <a href="{{ client_route('support') }}">support &amp; contact</a> center anytime.
                    </p>
                @endunless
            </header>

            @if($isJetPakistanTheme)
            <div class="ota-about-panel ota-about-panel--lead">
                <h2 class="ota-about-panel-title">Pakistan-first online travel, built for clear booking decisions</h2>
                <p>JetPakistan helps travellers search, compare, and book domestic and international flights with a service model grounded in Pakistan. The public JetPakistan website highlights online ticket booking, low-fare discovery, mobile booking access, price alerts, and airline support across popular domestic and international routes.</p>
                <p>From Lahore to Karachi, Islamabad, Peshawar, Dubai, Jeddah, Abu Dhabi, Beijing, and New York, JetPakistan presents travel options in a way that keeps the fare, route, and support path easy to understand before checkout.</p>
            </div>

            <div class="row align-items-start g-4 ota-about-columns">
                <div class="col-12 col-lg-6 d-flex flex-column gap-3">
                    <div class="ota-about-panel">
                        <h2 class="ota-about-panel-title">What JetPakistan offers</h2>
                        <ul class="ota-support-list">
                            <li>Cheap air ticket search for domestic and international flights from Pakistan</li>
                            <li>Secure online flight booking with booking support before and after purchase</li>
                            <li>Group, tour, hotel, and travel package information where available through the public service</li>
                            <li>Mobile-friendly booking access for customers who prefer to search and manage travel on the go</li>
                            <li>Human support for changes, cancellations, invoices, and booking questions</li>
                        </ul>
                    </div>
                    <div class="ota-about-panel">
                        <h2 class="ota-about-panel-title">Why JetPakistan</h2>
                        <p>JetPakistan's public positioning is simple: help customers find affordable air tickets quickly, compare airline choices, and complete booking with confidence. This theme carries that promise into the OTA platform with clear PKR-first fare presentation, visible support links, and no dead-end navigation.</p>
                        <p>Customers can use the platform for normal flight search and booking while JetPakistan branding and content remain client-specific.</p>
                    </div>
                </div>
                <div class="col-12 col-lg-6 d-flex flex-column gap-3">
                    <div class="ota-about-panel">
                        <h2 class="ota-about-panel-title">Contact and office</h2>
                        <ul class="ota-support-list">
                            <li><strong>Website:</strong> <a href="https://www.jetpakistan.com" target="_blank" rel="noopener">www.jetpakistan.com</a></li>
                            <li><strong>Phone:</strong> 0311 1222427</li>
                            <li><strong>Email:</strong> <a href="mailto:ticketingjp@jetpakistan.com">ticketingjp@jetpakistan.com</a></li>
                            <li><strong>Office:</strong> Office No. 220, 2nd Floor, Century Tower, Kalma Chowk, Gulberg III, Lahore</li>
                        </ul>
                    </div>
                    <div class="ota-about-panel">
                        <h2 class="ota-about-panel-title">Services customers see publicly</h2>
                        <p>The JetPakistan public website lists Flights, Hotels, Tour, Online Check-in, Why JetPakistan, About Us, and Contact Us in its public navigation. In this OTA integration phase, flight booking, group search, booking lookup, auth, agent entry, and support remain active through the existing Master Client backend.</p>
                        <p>Future module visibility can be controlled later through Dev CP. This phase does not disable backend features.</p>
                    </div>
                </div>
            </div>
            @else
            @if($aboutUsHasCustom)
                <div class="ota-about-panel ota-about-custom-body" data-about-custom="{{ $aboutUs['mode'] ?? 'plain' }}">
                    {!! $aboutUs['body_html'] ?? '' !!}
                </div>
                <p class="ota-section-desc ota-about-hero-desc">
                    Need booking help, ticket changes, or direct contact with our team? Visit our <a href="{{ client_route('support') }}">support &amp; contact</a> center anytime.
                </p>
            @else
            <div class="ota-about-panel ota-about-panel--lead">
                <h2 class="ota-about-panel-title">Our story</h2>
                <p>{{ $brandName }} was built around a simple idea: flight booking should feel guided, not confusing. We combine search tools that respect your time with human support when schedules shift, documents need attention, or airlines update policies. {{ $officeCity !== '' ? 'From our base in '.$officeCity.', we serve travellers across routes our customers fly most often—always with an emphasis on clarity and follow-through.' : 'We focus on the routes our customers fly most often—always with an emphasis on clarity and follow-through.' }}</p>
                <p>Behind every booking is a coordinated workflow: fare checks, passenger details, payments and proofs, ticket issuance, and updates until you travel. We invest in that workflow so you spend less time chasing status and more time planning your trip.</p>
            </div>

            <div class="row align-items-start g-4 ota-about-columns">
                <div class="col-12 col-lg-6 d-flex flex-column gap-3">
                    <div class="ota-about-panel">
                        <h2 class="ota-about-panel-title">Who we are</h2>
                        <p>{{ $brandName }} is a travel booking partner for leisure flyers, business travellers, and agencies that want dependable fulfilment. Our team understands airline rules, fare conditions, and the operational realities of ticketing—so we can explain trade-offs in plain language before you commit.</p>
                        <p>We work as an extension of your travel planning: responsive on messaging channels when timing matters, structured when documentation and payments need to be right the first time.</p>
                    </div>
                    <div class="ota-about-panel">
                        <h2 class="ota-about-panel-title">What we do</h2>
                        <p>Our core services cover the full booking lifecycle—search and itinerary selection, fare review, passenger capture, payment coordination, ticket issuance, and post-ticket servicing aligned with airline policies.</p>
                        <ul class="ota-support-list">
                            <li>Flight search across preferred routes and cabins</li>
                            <li>Booking creation with transparent fare and fee context</li>
                            <li>E-tickets, receipts, and travel document coordination</li>
                            <li>Changes and cancellations handled according to carrier rules</li>
                            <li>Ongoing support until departure when plans evolve</li>
                        </ul>
                    </div>
                </div>
                <div class="col-12 col-lg-6 d-flex flex-column gap-3">
                    <div class="ota-about-panel">
                        <h2 class="ota-about-panel-title">How we work</h2>
                        <ul class="ota-support-list">
                            <li><strong>Clarity first</strong> — we spell out what is included, what may change, and what timelines to expect.</li>
                            <li><strong>Responsive channels</strong> — fast paths for urgent updates alongside structured email follow-up.</li>
                            <li><strong>Disciplined processes</strong> — passenger names, contacts, and payments captured consistently to reduce airline rejects.</li>
                            <li><strong>Partner-ready</strong> — structured onboarding for agents and corporates who need repeatable fulfilment.</li>
                        </ul>
                    </div>
                    <div class="ota-about-panel">
                        <h2 class="ota-about-panel-title">Travel with confidence</h2>
                        <p>International travel can mean visa considerations, name corrections, connection risk, and baggage rules that vary by airline. We help you understand what applies to your ticket and coordinate updates when airlines allow them—so you are never guessing alone at the last minute.</p>
                        <p>When disruptions happen, we prioritize communication: what the airline has confirmed, what options exist, and what we recommend based on your itinerary and fare rules.</p>
                    </div>
                </div>
            </div>

            <div class="ota-about-panel ota-about-panel--emphasis">
                <h2 class="ota-about-panel-title">Why travellers choose {{ $brandName }}</h2>
                <p>People return to us because the experience feels steady—fewer surprises on fares, clearer expectations on timelines, and staff who understand that travel plans change. We combine technology with accessible support so you are not stuck between a chatbot and an unanswered inbox.</p>
                <p>For businesses and agencies, we offer a scalable relationship: predictable processes for bookings, invoicing context where needed, and escalation paths when passenger situations get complex.</p>
                <p>Whether you book once a year or every month, we apply the same standards—accurate details, timely ticketing, and accountability through to departure.</p>
            </div>

            <div class="row align-items-start g-4">
                <div class="col-12 col-md-6">
                    <div class="ota-about-panel h-100">
                        <h2 class="ota-about-panel-title">Partners &amp; agents</h2>
                        <p>If you represent travellers—corporate mobility, retail agency, or independent advisor—we provide onboarding, policy-aligned workflows, and access aligned with how your organization sells.</p>
                        <p>Applications are reviewed before activation so every partner understands pricing posture, documentation expectations, and service boundaries. Learn more on our <a href="{{ client_route('agent.register') }}">Agent Network</a> page.</p>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="ota-about-panel h-100">
                        <h2 class="ota-about-panel-title">Talk to our team</h2>
                        <p>For partnerships, corporate travel programs, or urgent booking assistance, reach us through the <a href="{{ client_route('support') }}">support &amp; contact</a> page—office channels, WhatsApp where available, and forms for structured requests.</p>
                        <p class="ota-about-footnote">Flight availability and fares are subject to airline confirmation at the time of booking.</p>
                    </div>
                </div>
            </div>
            @endif
            @endif
        </div>
    </section>
@endsection
