@extends(client_layout('customer-account', 'customer'))

@section('title', 'My trips')

@section('content')
@include('themes.frontend.jetpakistan.components.portal.flash')

@php
    $customerName = trim((string) (auth()->user()?->name ?? 'Traveler'));
    $firstName = strtok($customerName !== '' ? $customerName : 'Traveler', ' ') ?: 'Traveler';
    $paymentProofHref = ($hasPendingPaymentBooking && ($firstPendingPaymentBooking ?? null))
        ? client_route('customer.bookings.show', ['booking' => $firstPendingPaymentBooking]).'#payment'
        : client_route('customer.bookings.index', ['filter' => 'pending_payment']);
@endphp

<div class="jp-portal-page-head">
    <div>
        <h1>Welcome back, {{ $firstName }}</h1>
        <p>Your flights, payments, and support in one place.</p>
    </div>
    <a href="{{ client_route('flights.search') }}" class="jp-portal-btn jp-portal-btn--primary jp-portal-btn--sm">Search flights</a>
</div>

<div class="jp-portal-stat-grid">
    <div class="jp-portal-stat"><div class="jp-portal-stat__v">{{ number_format((int) ($kpis['total'] ?? 0)) }}</div><div class="jp-portal-stat__l">Total bookings</div></div>
    <div class="jp-portal-stat jp-portal-stat--amber"><div class="jp-portal-stat__v">{{ number_format((int) ($kpis['pending_payment'] ?? 0)) }}</div><div class="jp-portal-stat__l">Pending payment</div></div>
    <div class="jp-portal-stat jp-portal-stat--teal"><div class="jp-portal-stat__v">{{ number_format((int) ($kpis['pnr_confirmed'] ?? 0)) }}</div><div class="jp-portal-stat__l">Confirmed trips</div></div>
    <div class="jp-portal-stat"><div class="jp-portal-stat__v">{{ number_format((int) ($supportTicketsCount ?? 0)) }}</div><div class="jp-portal-stat__l">Support tickets</div></div>
</div>

<div class="jp-portal-quick">
    <a href="{{ client_route('flights.search') }}">Search flights</a>
    <a href="{{ client_route('customer.bookings.index') }}">My bookings</a>
    <a href="{{ $paymentProofHref }}">Upload payment proof</a>
    <a href="{{ client_route('customer.support.tickets.index') }}">Contact support</a>
</div>

@if (($upcomingBooking ?? null))
    <div class="jp-portal-card">
        <div class="jp-portal-card__head"><h2 class="jp-portal-card__title">Upcoming trip</h2></div>
        <div class="jp-portal-card__body">
            <p style="margin:0 0 var(--sp-2)"><strong>{{ $upcomingBooking->display_reference }}</strong> — {{ $upcomingBooking->route ?? '—' }}</p>
            <p style="margin:0 0 var(--sp-4);color:var(--muted);font-size:var(--fs-14)">{{ $upcomingBooking->travel_date?->format('l, j M Y') ?? 'Date TBC' }}</p>
            <a href="{{ client_route('customer.bookings.show', ['booking' => $upcomingBooking]) }}" class="jp-portal-btn jp-portal-btn--primary jp-portal-btn--sm">View booking</a>
        </div>
    </div>
@endif

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
        <div class="jp-portal-card__body jp-portal-card__body--flush">
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

@include('themes.frontend.jetpakistan.components.portal.support-cta')
@endsection
