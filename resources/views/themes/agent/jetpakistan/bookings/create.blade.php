@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Create flight booking')

@section('content')
@include('themes.frontend.jetpakistan.components.portal.flash')

@if (session('agent_booking_mode_notice'))
    <div class="jp-portal-alert jp-portal-alert--info">{{ session('agent_booking_mode_notice') }}</div>
@endif

<div class="jp-portal-page-head">
    <div>
        <h1>Create flight booking</h1>
        <p>Search real-time fares using the main booking flow — linked to {{ $agencyName ?? 'your agency' }}.</p>
    </div>
    <a href="{{ client_route('agent.bookings.index') }}" class="jp-portal-btn jp-portal-btn--ghost jp-portal-btn--sm">Back to bookings</a>
</div>

<div class="jp-portal-card">
    <div class="jp-portal-card__body">
        <p style="margin:0 0 var(--sp-4)">Use the same flight search and checkout experience as the public site. Passenger details stay editable; agency contact information is applied at checkout.</p>
        <p style="margin:0 0 var(--sp-5)"><strong>{{ $agencyName ?? 'Your agency' }}</strong> will be linked to bookings created in this session.</p>
        <div style="display:flex;flex-wrap:wrap;gap:var(--sp-3)">
            <a href="{{ client_route('home') }}" class="jp-portal-btn jp-portal-btn--primary" data-testid="agent-booking-search-flights">Search flights</a>
            <a href="{{ client_route('agent.bookings.index') }}" class="jp-portal-btn jp-portal-btn--ghost">Back to bookings</a>
        </div>
    </div>
</div>
@endsection
