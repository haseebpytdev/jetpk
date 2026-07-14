@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Agent dashboard')

@section('content')
@include('themes.frontend.jetpakistan.components.portal.flash')

@php
    $agentName = trim((string) (auth()->user()?->name ?? 'Agent'));
    $firstName = strtok($agentName !== '' ? $agentName : 'Agent', ' ') ?: 'Agent';
    $perm = $portalPermissions ?? [];
    $bk = $bookingKpis ?? [];
    $fin = $financeSummary ?? [];
    $recentRows = $recentBookings ?? collect();
@endphp

<div class="jp-portal-page-head">
    <div>
        <h1>Welcome back, {{ $firstName }}</h1>
        <p>{{ $agencyName ?? 'Agent portal' }} — bookings, payments, and agency support.</p>
    </div>
    @if ($perm['bookings_create'] ?? false)
        <a href="{{ client_route('agent.bookings.create') }}" class="jp-portal-btn jp-portal-btn--primary jp-portal-btn--sm">New booking</a>
    @endif
</div>

@if ($perm['bookings_view'] ?? false)
    <div class="jp-portal-stat-grid">
        <div class="jp-portal-stat"><div class="jp-portal-stat__v">{{ number_format((int) ($bk['total'] ?? 0)) }}</div><div class="jp-portal-stat__l">Total bookings</div></div>
        <div class="jp-portal-stat jp-portal-stat--amber"><div class="jp-portal-stat__v">{{ number_format((int) ($bk['pending_payment'] ?? 0)) }}</div><div class="jp-portal-stat__l">Pending payment</div></div>
        <div class="jp-portal-stat jp-portal-stat--teal"><div class="jp-portal-stat__v">{{ number_format((int) ($bk['pnr_confirmed'] ?? 0)) }}</div><div class="jp-portal-stat__l">PNR confirmed</div></div>
        @if ($perm['commissions_view'] ?? false)
            <div class="jp-portal-stat"><div class="jp-portal-stat__v">Rs {{ number_format((float) ($fin['balance'] ?? 0), 0) }}</div><div class="jp-portal-stat__l">Commission balance</div></div>
        @endif
    </div>
@endif

<div class="jp-portal-quick">
    @if ($perm['bookings_create'] ?? false)
        <a href="{{ client_route('agent.bookings.create') }}">New flight booking</a>
    @endif
    @if ($perm['bookings_view'] ?? false)
        <a href="{{ client_route('agent.bookings.index') }}">All bookings</a>
    @endif
    @if ($perm['support_manage'] ?? false)
        <a href="{{ client_route('agent.support.tickets.index') }}">Support tickets</a>
    @endif
    @if ($perm['wallet_view'] ?? false)
        <a href="{{ client_route('agent.wallet.show') }}">Wallet</a>
    @endif
</div>

@if ($perm['bookings_view'] ?? false)
    <div class="jp-portal-card">
        <div class="jp-portal-card__head">
            <h2 class="jp-portal-card__title">Recent bookings</h2>
            <a href="{{ client_route('agent.bookings.index') }}" class="jp-portal-btn jp-portal-btn--ghost jp-portal-btn--sm">View all</a>
        </div>
        @if ($recentRows->isEmpty())
            <div class="jp-portal-card__body">
                @include('themes.frontend.jetpakistan.components.portal.empty-state', [
                    'title' => 'No bookings yet',
                    'message' => 'Create a booking to see it here.',
                    'actionUrl' => client_route('agent.bookings.create'),
                    'actionLabel' => 'New booking',
                ])
            </div>
        @else
            <div class="jp-portal-card__body jp-portal-card__body--flush">
                <div class="jp-portal-trip-list">
                    @foreach ($recentRows as $booking)
                        <a href="{{ client_route('agent.bookings.show', ['booking' => $booking]) }}" class="jp-portal-trip">
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
@endif

@if (($hasPendingPaymentBooking ?? false) && ($firstPendingPaymentBooking ?? null))
    <div class="jp-portal-alert jp-portal-alert--warn">
        You have a booking awaiting payment.
        <a href="{{ client_route('agent.bookings.show', ['booking' => $firstPendingPaymentBooking]) }}" class="jp-portal-btn jp-portal-btn--sm jp-portal-btn--primary" style="margin-left:8px">Review</a>
    </div>
@endif
@endsection
