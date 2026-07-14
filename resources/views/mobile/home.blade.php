@extends('layouts.mobile-app')

@section('title', ($client['agency_name'] ?? config('app.name')).' — Flights')

@section('content')
    @include('partials.agent-booking-mode-banner')
    @php
        $client = $client ?? config('ota-client', []);
        $minDate = $minDate ?? now()->format('Y-m-d');
        $departDate = now()->addDays(14)->format('Y-m-d');
        $returnDate = now()->addDays(21)->format('Y-m-d');
        $departLabel = now()->addDays(14)->format('j M, Y');
        $returnLabel = now()->addDays(21)->format('j M, Y');

        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        $user = auth()->user();
        $displayName = 'Traveller';
        if ($user !== null) {
            $rawName = trim((string) ($user->name ?? ''));
            if ($rawName !== '') {
                $displayName = explode(' ', $rawName)[0];
            }
        }

        $myBookingsUrl = auth()->check() ? route('dashboard') : route('booking.lookup');
        $agentLoginUrl = route('login');
    @endphp

    <div
        class="ota-mobile-home"
        data-testid="ota-mobile-home"
        data-airports-search-url="{{ url('/airports/search') }}"
        data-min-date="{{ $minDate }}"
    >
        <header class="ota-mobile-home__header" aria-label="Welcome">
            <div class="ota-mobile-home__header-top">
                <div class="ota-mobile-home__greeting">
                    <p class="ota-mobile-home__greeting-eyebrow">{{ $greeting }},</p>
                    <h1 class="ota-mobile-home__greeting-name">{{ $displayName }} <span aria-hidden="true">👋</span></h1>
                </div>
                <a
                    href="{{ route('support') }}"
                    class="ota-mobile-home__notify-btn"
                    aria-label="Support and notifications"
                >
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true">
                        <path d="M12 2a7 7 0 00-7 7c0 2.38 1.19 4.47 3 5.74V17a2 2 0 002 2h4a2 2 0 002-2v-2.26c1.81-1.27 3-3.36 3-5.74a7 7 0 00-7-7zm-1 18h2v1a1 1 0 01-2 0v-1z"/>
                    </svg>
                </a>
            </div>
        </header>

        <section class="ota-mobile-home__search-card" aria-labelledby="ota-mobile-search-heading">
            <h2 id="ota-mobile-search-heading" class="ota-mobile-home__sr-only">Flight search</h2>

            <form
                method="get"
                action="{{ route('flights.results') }}"
                class="ota-mobile-home__search-form"
                id="ota-mobile-home-search-form"
                data-mobile-search-form
                data-testid="ota-mobile-home-search-form"
                novalidate
            >
                <input type="hidden" name="trip_type" value="round_trip" data-trip-type-input>
                <input type="hidden" name="adults" value="1" data-travellers-adults-input>
                <input type="hidden" name="children" value="0" data-travellers-children-input>
                <input type="hidden" name="infants" value="0" data-travellers-infants-input>
                <input type="hidden" name="cabin" value="economy" data-travellers-cabin-input>

                <div class="ota-mobile-home__trip-toggle" role="radiogroup" aria-label="Trip type">
                    <button
                        type="button"
                        class="ota-mobile-home__trip-btn"
                        data-trip-toggle="one_way"
                        aria-pressed="false"
                    >
                        One-way
                    </button>
                    <button
                        type="button"
                        class="ota-mobile-home__trip-btn is-active"
                        data-trip-toggle="round_trip"
                        aria-pressed="true"
                    >
                        Round-trip
                    </button>
                </div>

                <div class="ota-mobile-home__route-block">
                    <div class="ota-mobile-home__route-row">
                        <button
                            type="button"
                            class="ota-mobile-home__field ota-mobile-home__field--trigger"
                            data-airport-trigger="from"
                            aria-haspopup="dialog"
                        >
                            <span class="ota-mobile-home__field-label">From</span>
                            <span class="ota-mobile-home__field-value" data-airport-label="from">LHE Lahore</span>
                        </button>
                        <input type="hidden" name="from_display" id="ota-mobile-from-display" value="LHE Lahore" data-airport-display="from">
                        <input type="hidden" name="from" id="ota-mobile-from" value="LHE" data-airport-code="from">
                    </div>

                    <button
                        type="button"
                        class="ota-mobile-home__swap-btn"
                        data-swap-routes
                        aria-label="Swap from and to airports"
                    >
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                            <path d="M7 4l-4 4 4 4V8h10V6H7V4zm10 16l4-4-4-4v4H7v2h10v4z"/>
                        </svg>
                    </button>

                    <div class="ota-mobile-home__route-row">
                        <button
                            type="button"
                            class="ota-mobile-home__field ota-mobile-home__field--trigger"
                            data-airport-trigger="to"
                            aria-haspopup="dialog"
                        >
                            <span class="ota-mobile-home__field-label">To</span>
                            <span class="ota-mobile-home__field-value" data-airport-label="to">IST Istanbul</span>
                        </button>
                        <input type="hidden" name="to_display" id="ota-mobile-to-display" value="IST Istanbul" data-airport-display="to">
                        <input type="hidden" name="to" id="ota-mobile-to" value="IST" data-airport-code="to">
                    </div>
                </div>

                <div class="ota-mobile-home__dates">
                    <button
                        type="button"
                        class="ota-mobile-home__date-field ota-mobile-home__date-field--trigger"
                        data-date-trigger="depart"
                        aria-haspopup="dialog"
                    >
                        <span class="ota-mobile-home__field-label">Depart</span>
                        <span class="ota-mobile-home__date-display" data-date-label="depart">{{ $departLabel }}</span>
                    </button>
                    <input type="hidden" name="depart" id="ota-mobile-depart" value="{{ $departDate }}" data-date-input="depart" data-min="{{ $minDate }}">

                    <button
                        type="button"
                        class="ota-mobile-home__date-field ota-mobile-home__date-field--trigger"
                        data-date-trigger="return"
                        data-return-date-field
                        aria-haspopup="dialog"
                    >
                        <span class="ota-mobile-home__field-label">Return</span>
                        <span class="ota-mobile-home__date-display" data-date-label="return">{{ $returnLabel }}</span>
                    </button>
                    <input type="hidden" name="return_date" id="ota-mobile-return" value="{{ $returnDate }}" data-date-input="return">
                </div>

                <button type="button" class="ota-mobile-home__travellers" data-travellers-trigger aria-haspopup="dialog" aria-expanded="false">
                    <span>
                        <span class="ota-mobile-home__field-label">Travellers &amp; Cabin</span>
                        <span class="ota-mobile-home__travellers-summary" data-travellers-summary>1 Adult, Economy</span>
                    </span>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                        <path d="M9 6l6 6-6 6"/>
                    </svg>
                </button>

                <button type="submit" class="ota-mobile-home__search-btn" data-testid="ota-mobile-home-search-submit">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                        <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                    </svg>
                    Search Flights
                </button>
            </form>
        </section>

        <section class="ota-mobile-home__section" aria-labelledby="ota-mobile-quick-heading">
            <h2 id="ota-mobile-quick-heading" class="ota-mobile-home__section-title">Quick Actions</h2>
            <div class="ota-mobile-home__quick-grid">
                <a href="{{ $myBookingsUrl }}" class="ota-mobile-home__quick-item">
                    <span class="ota-mobile-home__quick-icon ota-mobile-home__quick-icon--bookings" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M7 3h10a2 2 0 012 2v14l-5-3-5 3V5a2 2 0 012-2z"/></svg>
                    </span>
                    <span class="ota-mobile-home__quick-label">My Bookings</span>
                </a>
                <a href="{{ route('support') }}" class="ota-mobile-home__quick-item">
                    <span class="ota-mobile-home__quick-icon ota-mobile-home__quick-icon--support" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M12 1a9 9 0 00-9 9v5a3 3 0 003 3h1v-4H5v-1a7 7 0 1114 0v1h-2v4h1a3 3 0 003-3v-5a9 9 0 00-9-9zm-2 18h4v2h-4v-2z"/></svg>
                    </span>
                    <span class="ota-mobile-home__quick-label">Support</span>
                </a>
                <a href="{{ $agentLoginUrl }}" class="ota-mobile-home__quick-item">
                    <span class="ota-mobile-home__quick-icon ota-mobile-home__quick-icon--agent" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z"/></svg>
                    </span>
                    <span class="ota-mobile-home__quick-label">Agent Login</span>
                </a>
            </div>
        </section>

        <div class="ota-mobile-sheet-backdrop" data-mobile-airport-backdrop aria-hidden="true"></div>
        <aside
            class="ota-mobile-airport-sheet"
            data-mobile-airport-sheet
            aria-labelledby="ota-mobile-airport-sheet-title"
            aria-hidden="true"
            role="dialog"
        >
            <header class="ota-mobile-airport-sheet__head">
                <button type="button" class="ota-mobile-airport-sheet__close" data-mobile-airport-close aria-label="Close airport search">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true">
                        <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                    </svg>
                </button>
                <h2 id="ota-mobile-airport-sheet-title" class="ota-mobile-airport-sheet__title" data-mobile-airport-sheet-title>Select airport</h2>
            </header>
            <div class="ota-mobile-airport-sheet__search-wrap">
                <input
                    type="search"
                    class="ota-mobile-airport-sheet__search"
                    data-mobile-airport-search
                    placeholder="Search city or airport code"
                    autocomplete="off"
                    inputmode="search"
                >
            </div>
            <p class="ota-mobile-airport-sheet__hint" data-mobile-airport-hint hidden>Type at least 2 characters to search.</p>
            <p class="ota-mobile-airport-sheet__error" data-mobile-airport-error hidden>Search unavailable. Enter a 3-letter airport code and tap Use code.</p>
            <ul class="ota-mobile-airport-sheet__list" data-mobile-airport-results role="listbox"></ul>
            <div class="ota-mobile-airport-sheet__fallback" data-mobile-airport-fallback hidden>
                <button type="button" class="ota-mobile-airport-sheet__use-code" data-mobile-airport-use-code>Use code</button>
            </div>
        </aside>

        <div class="ota-mobile-sheet-backdrop" data-mobile-calendar-backdrop aria-hidden="true"></div>
        <aside
            class="ota-mobile-calendar-sheet"
            data-mobile-calendar-sheet
            aria-labelledby="ota-mobile-calendar-sheet-title"
            aria-hidden="true"
            role="dialog"
        >
            <header class="ota-mobile-calendar-sheet__head">
                <button type="button" class="ota-mobile-calendar-sheet__close" data-mobile-calendar-close aria-label="Close calendar">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true">
                        <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                    </svg>
                </button>
                <h2 id="ota-mobile-calendar-sheet-title" class="ota-mobile-calendar-sheet__title" data-mobile-calendar-title>Select dates</h2>
            </header>
            <p class="ota-mobile-calendar-sheet__subtitle" data-mobile-calendar-subtitle></p>
            <div class="ota-mobile-calendar-sheet__months" data-mobile-calendar-months></div>
        </aside>

        <div class="ota-mobile-sheet-backdrop" data-mobile-travellers-backdrop aria-hidden="true"></div>
        <aside
            class="ota-mobile-travellers-sheet"
            data-mobile-travellers-sheet
            data-testid="ota-mobile-travellers-sheet"
            aria-labelledby="ota-mobile-travellers-sheet-title"
            aria-hidden="true"
            role="dialog"
        >
            <header class="ota-mobile-travellers-sheet__head">
                <button type="button" class="ota-mobile-travellers-sheet__close" data-mobile-travellers-close aria-label="Close travellers selector">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true">
                        <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                    </svg>
                </button>
                <h2 id="ota-mobile-travellers-sheet-title" class="ota-mobile-travellers-sheet__title">Travellers &amp; Cabin</h2>
            </header>
            <div class="ota-mobile-travellers-sheet__body">
                <div class="ota-mobile-travellers-sheet__row">
                    <div class="ota-mobile-travellers-sheet__row-label">
                        <span class="ota-mobile-travellers-sheet__row-title">Adults</span>
                        <span class="ota-mobile-travellers-sheet__row-hint">12+ years</span>
                    </div>
                    <div class="ota-mobile-travellers-sheet__stepper">
                        <button type="button" class="ota-mobile-travellers-sheet__step-btn" data-travellers-step="adults" data-travellers-delta="-1" aria-label="Decrease adults">−</button>
                        <span class="ota-mobile-travellers-sheet__step-value" data-travellers-count="adults">1</span>
                        <button type="button" class="ota-mobile-travellers-sheet__step-btn" data-travellers-step="adults" data-travellers-delta="1" aria-label="Increase adults">+</button>
                    </div>
                </div>
                <div class="ota-mobile-travellers-sheet__row">
                    <div class="ota-mobile-travellers-sheet__row-label">
                        <span class="ota-mobile-travellers-sheet__row-title">Children</span>
                        <span class="ota-mobile-travellers-sheet__row-hint">2–11 years</span>
                    </div>
                    <div class="ota-mobile-travellers-sheet__stepper">
                        <button type="button" class="ota-mobile-travellers-sheet__step-btn" data-travellers-step="children" data-travellers-delta="-1" aria-label="Decrease children">−</button>
                        <span class="ota-mobile-travellers-sheet__step-value" data-travellers-count="children">0</span>
                        <button type="button" class="ota-mobile-travellers-sheet__step-btn" data-travellers-step="children" data-travellers-delta="1" aria-label="Increase children">+</button>
                    </div>
                </div>
                <div class="ota-mobile-travellers-sheet__row">
                    <div class="ota-mobile-travellers-sheet__row-label">
                        <span class="ota-mobile-travellers-sheet__row-title">Infants</span>
                        <span class="ota-mobile-travellers-sheet__row-hint">Under 2 · max 1 per adult</span>
                    </div>
                    <div class="ota-mobile-travellers-sheet__stepper">
                        <button type="button" class="ota-mobile-travellers-sheet__step-btn" data-travellers-step="infants" data-travellers-delta="-1" aria-label="Decrease infants">−</button>
                        <span class="ota-mobile-travellers-sheet__step-value" data-travellers-count="infants">0</span>
                        <button type="button" class="ota-mobile-travellers-sheet__step-btn" data-travellers-step="infants" data-travellers-delta="1" aria-label="Increase infants">+</button>
                    </div>
                </div>
                <div class="ota-mobile-travellers-sheet__cabin">
                    <span class="ota-mobile-travellers-sheet__cabin-label">Cabin class</span>
                    <div class="ota-mobile-travellers-sheet__cabin-options" role="radiogroup" aria-label="Cabin class">
                        <button type="button" class="ota-mobile-travellers-sheet__cabin-btn is-active" data-travellers-cabin="economy" aria-pressed="true">Economy</button>
                        <button type="button" class="ota-mobile-travellers-sheet__cabin-btn" data-travellers-cabin="premium_economy" aria-pressed="false">Premium</button>
                        <button type="button" class="ota-mobile-travellers-sheet__cabin-btn" data-travellers-cabin="business" aria-pressed="false">Business</button>
                        <button type="button" class="ota-mobile-travellers-sheet__cabin-btn" data-travellers-cabin="first" aria-pressed="false">First</button>
                    </div>
                </div>
            </div>
            <footer class="ota-mobile-travellers-sheet__foot">
                <button type="button" class="ota-mobile-travellers-sheet__done" data-mobile-travellers-done>Done</button>
            </footer>
        </aside>
    </div>
@endsection
