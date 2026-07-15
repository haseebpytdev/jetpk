@extends('layouts.mobile-app')

@php
    $brand = config('ota-brand', []);
    $client = config('ota-client', []);
    $brandName = $client['agency_name'] ?? ($brand['product_name'] ?? config('app.name'));
    $supportEmail = $client['support_email'] ?? ($brand['support_email'] ?? '');
    $supportPhone = $client['support_phone'] ?? ($brand['support_phone'] ?? '');
    $supportWhatsapp = $client['support_whatsapp'] ?? ($brand['support_whatsapp'] ?? '');
    $officeCity = $client['office_city'] ?? '';
@endphp

@section('title', 'Support')

@section('content')
    <div class="ota-mobile-support" data-testid="ota-mobile-support">
        <header class="ota-mobile-support__header">
            <h1 class="ota-mobile-support__title">Help and support</h1>
            <p class="ota-mobile-support__subtitle">
                Get help with bookings, payments, travel documents, and general inquiries.
            </p>
        </header>

        <div class="ota-mobile-support__quick-grid" aria-label="Quick actions">
            <a href="{{ route('home') }}" class="ota-mobile-support__quick">
                <span class="ota-mobile-support__quick-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                </span>
                <span class="ota-mobile-support__quick-title">Search flights</span>
            </a>
            <a href="{{ route('booking.lookup') }}" class="ota-mobile-support__quick">
                <span class="ota-mobile-support__quick-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M7 3h10a2 2 0 012 2v14l-5-3-5 3V5a2 2 0 012-2z"/></svg>
                </span>
                <span class="ota-mobile-support__quick-title">Booking lookup</span>
            </a>
        </div>

        <section class="ota-mobile-support__card" aria-labelledby="ota-mobile-support-categories">
            <h2 id="ota-mobile-support-categories" class="ota-mobile-support__card-title">Help categories</h2>
            <ul class="ota-mobile-support__list">
                <li>Booking status</li>
                <li>Payment proof</li>
                <li>E-ticket support</li>
                <li>Cancellations</li>
            </ul>
        </section>

        <section class="ota-mobile-support__card" aria-labelledby="ota-mobile-support-faq">
            <h2 id="ota-mobile-support-faq" class="ota-mobile-support__card-title">FAQs</h2>
            <div class="ota-mobile-support__faq">
                <p><strong>How fast do you respond?</strong><br>Most requests are answered within 2–6 business hours.</p>
                <p><strong>Can I get help on WhatsApp?</strong><br>Yes, use WhatsApp for urgent travel updates.</p>
                <p><strong>Can I edit a booking after submission?</strong><br>Contact support with your booking reference and requested changes.</p>
            </div>
        </section>

        <section class="ota-mobile-support__card" aria-labelledby="ota-mobile-support-channels">
            <h2 id="ota-mobile-support-channels" class="ota-mobile-support__card-title">Contact channels</h2>
            <p class="ota-mobile-support__lead">{{ $brandName }} helps customers and partners with flight booking and travel servicing.</p>
            <ul class="ota-mobile-support__channels">
                @if($supportPhone !== '')
                    <li><span class="ota-mobile-support__channel-label">Phone</span> {{ $supportPhone }}</li>
                @endif
                @if($supportWhatsapp !== '')
                    <li>
                        <span class="ota-mobile-support__channel-label">WhatsApp</span>
                        <a href="https://wa.me/{{ $supportWhatsapp }}" target="_blank" rel="noopener">Chat on WhatsApp</a>
                    </li>
                @endif
                @if($supportEmail !== '')
                    <li>
                        <span class="ota-mobile-support__channel-label">Email</span>
                        <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                    </li>
                @endif
                @if($officeCity !== '')
                    <li><span class="ota-mobile-support__channel-label">City</span> {{ $officeCity }}</li>
                @endif
            </ul>
        </section>

        @auth
            @if (auth()->user()->isCustomer())
                <section class="ota-mobile-support__card" aria-labelledby="ota-mobile-support-account">
                    <h2 id="ota-mobile-support-account" class="ota-mobile-support__card-title">Signed-in support</h2>
                    <p class="ota-mobile-support__lead">Open a support ticket linked to your booking for structured help.</p>
                    <a href="{{ route('customer.support.tickets.create') }}" class="ota-mobile-auth__btn ota-mobile-auth__btn--primary">Create support ticket</a>
                </section>
            @endif
        @endauth
    </div>
@endsection
