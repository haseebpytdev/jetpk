@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Create flight booking')

@section('account_title', 'Create flight booking')
@section('account_subtitle', 'Search real-time fares using the main booking flow. This booking will be linked to your agency.')

@section('account_content')
    @if (session('agent_booking_mode_notice'))
        <div class="alert alert-info border small mb-3" role="status" data-testid="agent-booking-mode-notice">
            {{ session('agent_booking_mode_notice') }}
        </div>
    @endif

    <div class="ota-account-card">
        <div class="ota-account-card__body">
            <p class="mb-3 text-secondary">
                Use the same flight search and checkout experience as the public site. Passenger details stay editable;
                agency contact information is applied at checkout.
            </p>
            <p class="mb-4">
                <strong>{{ $agencyName ?? 'Your agency' }}</strong> will be linked to bookings created in this session.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('home') }}" class="ota-account-btn ota-account-btn--primary" data-testid="agent-booking-search-flights">
                    Search flights
                </a>
                <a href="{{ route('agent.bookings.index') }}" class="ota-account-btn ota-account-btn--secondary">
                    Back to bookings
                </a>
            </div>
        </div>
    </div>
@endsection
