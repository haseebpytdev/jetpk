@extends(client_layout('customer-account', 'customer'))

@section('title', 'My trips')

@section('content')
@include('themes.frontend.jetpakistan.components.portal.flash')

@php
    use Illuminate\Support\Facades\Route;

    $customerName = trim((string) (auth()->user()?->name ?? 'Traveler'));
    $firstName = strtok($customerName !== '' ? $customerName : 'Traveler', ' ') ?: 'Traveler';
    $paymentProofHref = ($hasPendingPaymentBooking && ($firstPendingPaymentBooking ?? null))
        ? client_route('customer.bookings.show', ['booking' => $firstPendingPaymentBooking]).'#payment'
        : client_route('customer.bookings.index', ['filter' => 'pending_payment']);
    $featured = $upcomingBooking ?? $recentBookings->first();
    $featuredLabel = $upcomingBooking !== null ? 'Upcoming trip' : 'Latest booking';
    $quickActions = array_values(array_filter([
        ['route' => 'flights.search', 'label' => 'Search flights'],
        ['route' => 'customer.bookings.index', 'label' => 'My bookings'],
        ['route' => 'booking.lookup', 'label' => 'Booking lookup'],
        ['route' => 'customer.support.tickets.index', 'label' => 'Support'],
        ['route' => 'profile.edit', 'label' => 'Profile'],
    ], fn ($action) => Route::has($action['route'])));
@endphp

<div class="jp-portal-dashboard" data-testid="jp-customer-dashboard">
    <div class="jp-portal-page-head">
        <div>
            <h1>Welcome back, {{ $firstName }}</h1>
            <p>Your flights, payments, and support in one place.</p>
        </div>
        @if (Route::has('flights.search'))
            <a href="{{ client_route('flights.search') }}" class="jp-portal-btn jp-portal-btn--primary jp-portal-btn--sm">Search flights</a>
        @endif
    </div>

    @if ($hasPendingPaymentBooking && $firstPendingPaymentBooking)
        <div class="jp-portal-alert jp-portal-alert--warn jp-portal-alert--row" role="status" data-testid="jp-customer-dashboard-pending-alert">
            <div>
                <strong>Payment pending</strong>
                <p class="jp-portal-alert__text">Booking <span class="jp-portal-trip__ref">{{ $firstPendingPaymentBooking->display_reference }}</span> is awaiting payment.</p>
            </div>
            <a href="{{ client_route('customer.bookings.show', ['booking' => $firstPendingPaymentBooking]) }}" class="jp-portal-btn jp-portal-btn--primary jp-portal-btn--sm">Complete payment</a>
        </div>
    @endif

    <div class="jp-portal-stat-grid" data-testid="jp-customer-dashboard-kpis">
        <div class="jp-portal-stat">
            <div class="jp-portal-stat__v">{{ number_format((int) ($kpis['total'] ?? 0)) }}</div>
            <div class="jp-portal-stat__l">Total bookings</div>
        </div>
        <div class="jp-portal-stat jp-portal-stat--amber">
            <div class="jp-portal-stat__v">{{ number_format((int) ($kpis['pending_payment'] ?? 0)) }}</div>
            <div class="jp-portal-stat__l">Pending payment</div>
        </div>
        <div class="jp-portal-stat jp-portal-stat--teal">
            <div class="jp-portal-stat__v">{{ number_format((int) ($kpis['pnr_confirmed'] ?? 0)) }}</div>
            <div class="jp-portal-stat__l">PNR confirmed</div>
        </div>
        <div class="jp-portal-stat jp-portal-stat--violet">
            <div class="jp-portal-stat__v">{{ number_format((int) ($kpis['cancellation_activity'] ?? 0)) }}</div>
            <div class="jp-portal-stat__l">Cancellation activity</div>
        </div>
    </div>

  @if ((int) ($supportTicketsCount ?? 0) > 0)
        <p class="jp-portal-dashboard__meta" data-testid="jp-customer-dashboard-support-count">
            {{ number_format((int) $supportTicketsCount) }} open support {{ (int) $supportTicketsCount === 1 ? 'ticket' : 'tickets' }}
            &middot; <a href="{{ client_route('customer.support.tickets.index') }}">View support</a>
        </p>
    @endif

    <div class="jp-portal-quick" data-testid="jp-customer-dashboard-quick-actions">
        @foreach ($quickActions as $action)
            <a href="{{ client_route($action['route']) }}">{{ $action['label'] }}</a>
        @endforeach
        <a href="{{ $paymentProofHref }}">Upload payment proof</a>
    </div>

    <div class="jp-portal-grid jp-portal-grid--2">
        <div>
            <div class="jp-portal-card">
                <div class="jp-portal-card__head">
                    <h2 class="jp-portal-card__title">Recent bookings</h2>
                    <a href="{{ client_route('customer.bookings.index') }}" class="jp-portal-btn jp-portal-btn--ghost jp-portal-btn--sm">View all</a>
                </div>
                @if (($recentBookings ?? collect())->isEmpty())
                    <div class="jp-portal-card__body">
                        @include('themes.frontend.jetpakistan.components.portal.empty-state', [
                            'title' => 'No bookings yet',
                            'message' => 'Search flights to book your next trip.',
                            'actionUrl' => client_route('flights.search'),
                            'actionLabel' => 'Search flights',
                        ])
                    </div>
                @else
                    <div class="jp-portal-card__body jp-portal-card__body--flush" data-testid="jp-customer-recent-bookings">
                        <div class="jp-portal-trip-list">
                            @foreach ($recentBookings as $booking)
                                <a href="{{ client_route('customer.bookings.show', ['booking' => $booking]) }}" class="jp-portal-trip">
                                    <div>
                                        <div class="jp-portal-trip__ref">{{ $booking->display_reference }}</div>
                                        <div class="jp-portal-trip__route">{{ $booking->route ?? '—' }}</div>
                                        <div class="jp-portal-trip__meta">{{ $booking->travel_date?->format('j M Y') ?? '—' }}</div>
                                    </div>
                                    <div>@include('themes.frontend.jetpakistan.components.portal.status-badge', ['label' => ucfirst(str_replace('_', ' ', $booking->status?->value ?? ''))])</div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <aside aria-label="Trip summary">
            @if ($featured)
                <div class="jp-portal-card jp-portal-highlight" data-testid="jp-customer-dashboard-upcoming">
                    <div class="jp-portal-card__head">
                        <h2 class="jp-portal-card__title">{{ $featuredLabel }}</h2>
                    </div>
                    <div class="jp-portal-card__body">
                        <p class="jp-portal-highlight__ref">{{ $featured->display_reference }}</p>
                        <p style="margin:0 0 var(--sp-2)"><strong>{{ $featured->route ?? '—' }}</strong></p>
                        <p style="margin:0 0 var(--sp-3);color:var(--muted);font-size:var(--fs-14)">Travel: {{ $featured->travel_date?->format('j M Y') ?? 'Date TBC' }}</p>
                        @include('themes.frontend.jetpakistan.components.portal.status-badge', ['label' => ucfirst(str_replace('_', ' ', $featured->status?->value ?? ''))])
                        <p style="margin:var(--sp-4) 0 0">
                            <a href="{{ client_route('customer.bookings.show', ['booking' => $featured]) }}" class="jp-portal-btn jp-portal-btn--primary jp-portal-btn--sm">View booking</a>
                            @if ($upcomingBooking !== null && (int) ($upcomingCount ?? 0) > 1)
                                <span style="color:var(--muted);font-size:var(--fs-13)"> &middot; {{ (int) $upcomingCount - 1 }} more upcoming</span>
                            @endif
                        </p>
                    </div>
                </div>
            @endif
        </aside>
    </div>
</div>

@include('themes.frontend.jetpakistan.components.portal.support-cta')
@endsection
