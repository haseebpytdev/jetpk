@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'My account')

@section('content')
    @php
        $user = auth()->user();
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $displayName = 'Traveller';
        if ($user !== null) {
            $rawName = trim((string) ($user->name ?? ''));
            if ($rawName !== '') {
                $displayName = explode(' ', $rawName)[0];
            }
        }
        $featured = $upcomingBooking ?? $recentBookings->first();
        $featuredLabel = ($upcomingBooking ?? null) !== null ? 'Upcoming trip' : 'Latest booking';
    @endphp

    <div class="ota-mobile-dashboard" data-testid="ota-mobile-customer-dashboard">
        <header class="ota-mobile-dashboard__header">
            <div class="ota-mobile-dashboard__header-row">
                <div>
                    <p class="ota-mobile-dashboard__greeting">{{ $greeting }},</p>
                    <h1 class="ota-mobile-dashboard__name">{{ $displayName }}</h1>
                </div>
                <a
                    href="{{ route('profile.edit') }}"
                    class="ota-mobile-dashboard__profile-btn"
                    aria-label="Account settings"
                    data-testid="ota-mobile-customer-profile-link"
                >
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true">
                        <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z"/>
                    </svg>
                </a>
            </div>
        </header>

        <section class="ota-mobile-dashboard__section" aria-label="Overview">
            <div class="ota-mobile-dashboard__stats" data-testid="ota-mobile-customer-dashboard-stats">
                @include('mobile.dashboard.partials.stat-card', [
                    'label' => 'Total bookings',
                    'value' => number_format((int) ($kpis['total'] ?? 0)),
                ])
                @include('mobile.dashboard.partials.stat-card', [
                    'label' => 'Upcoming trips',
                    'value' => number_format((int) ($upcomingCount ?? 0)),
                    'tone' => 'sky',
                ])
                @include('mobile.dashboard.partials.stat-card', [
                    'label' => 'Support tickets',
                    'value' => number_format((int) ($supportTicketsCount ?? 0)),
                    'tone' => 'violet',
                ])
                @include('mobile.dashboard.partials.stat-card', [
                    'label' => 'Pending payment',
                    'value' => number_format((int) ($kpis['pending_payment'] ?? 0)),
                    'tone' => 'amber',
                ])
            </div>
        </section>

        @if ($featured)
            <section class="ota-mobile-dashboard__section" aria-label="{{ $featuredLabel }}">
                @include('mobile.dashboard.partials.booking-list-card', [
                    'booking' => $featured,
                    'showUrl' => route('customer.bookings.show', $featured),
                    'sectionLabel' => $featuredLabel,
                ])
            </section>
        @else
            <section class="ota-mobile-dashboard__section" aria-label="Bookings">
                <div class="ota-mobile-dashboard__empty" data-testid="ota-mobile-customer-dashboard-empty">
                    <p class="ota-mobile-dashboard__empty-title">No bookings yet</p>
                    <p class="ota-mobile-dashboard__empty-help">Search flights to book your first trip.</p>
                    <a href="{{ route('flights.search') }}" class="ota-mobile-dashboard__btn ota-mobile-dashboard__btn--primary">Search flights</a>
                </div>
            </section>
        @endif

        <section class="ota-mobile-dashboard__section" aria-label="Quick actions">
            <h2 class="ota-mobile-dashboard__section-title">Quick actions</h2>
            <div class="ota-mobile-dashboard__quick-grid">
                @include('mobile.dashboard.partials.quick-action-card', [
                    'href' => route('flights.search'),
                    'title' => 'Search flights',
                    'icon' => 'search',
                ])
                @include('mobile.dashboard.partials.quick-action-card', [
                    'href' => route('customer.bookings.index'),
                    'title' => 'My bookings',
                    'icon' => 'bookings',
                ])
                @include('mobile.dashboard.partials.quick-action-card', [
                    'href' => route('booking.lookup'),
                    'title' => 'Booking lookup',
                    'icon' => 'lookup',
                ])
                @include('mobile.dashboard.partials.quick-action-card', [
                    'href' => route('customer.support.tickets.index'),
                    'title' => 'Support',
                    'icon' => 'support',
                ])
                @include('mobile.dashboard.partials.quick-action-card', [
                    'href' => route('profile.edit'),
                    'title' => 'Profile',
                    'icon' => 'profile',
                ])
            </div>
        </section>
    </div>
@endsection
