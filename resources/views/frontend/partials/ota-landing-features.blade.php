@php $cards = is_array(($featureCardsContent['cards'] ?? null)) ? $featureCardsContent['cards'] : []; @endphp
<section class="ota-section" id="features">
    <div class="ota-container">
    <div class="ota-section-head">
        <div class="ota-section-kicker">Travel experience</div>
        <h2 class="ota-section-title">{{ (string) ($featureCardsContent['title'] ?? 'Everything travellers expect') }}</h2>
        <p class="ota-section-desc">{{ (string) ($featureCardsContent['subtitle'] ?? 'From flight discovery to booking follow-up, '.$brandName.' keeps your journey simple and supported.') }}</p>
    </div>
    <div class="ota-features-grid">
        <article class="ota-feature-card">
            <div class="ota-feature-icon"><i class="fa fa-ticket"></i></div>
            <h3>{{ (string) ($cards[0]['title'] ?? 'Booking management') }}</h3>
            <p>{{ (string) ($cards[0]['text'] ?? 'Search, hold, ticket, and track payments with a clear pipeline view for your team.') }}</p>
        </article>
        <article class="ota-feature-card">
            <div class="ota-feature-icon"><i class="fa fa-building"></i></div>
            <h3>{{ (string) ($cards[1]['title'] ?? 'Agent portal') }}</h3>
            <p>{{ (string) ($cards[1]['text'] ?? 'Give partners their own lane: bookings, commissions, and limits without sharing full admin.') }}</p>
        </article>
        <article class="ota-feature-card">
            <div class="ota-feature-icon"><i class="fa fa-sliders"></i></div>
            <h3>{{ (string) ($cards[2]['title'] ?? 'Markup controls') }}</h3>
            <p>{{ (string) ($cards[2]['text'] ?? 'Route, airline, and channel rules so you protect margin while staying competitive.') }}</p>
        </article>
        <article class="ota-feature-card">
            <div class="ota-feature-icon"><i class="fa fa-plug"></i></div>
            <h3>{{ (string) ($cards[3]['title'] ?? 'Airline and partner connectivity') }}</h3>
            <p>{{ (string) ($cards[3]['text'] ?? 'Sabre, PIA, and airline-direct connectivity is prepared and enabled after credentials are approved.') }}</p>
        </article>
    </div>
    </div>
</section>
