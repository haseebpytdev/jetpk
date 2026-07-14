@if (! empty($agentBookingModeActive))
    <div class="ota-agent-booking-mode-banner alert alert-info border-0 rounded-0 mb-0 py-2" role="status" data-testid="agent-booking-mode-banner">
        <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span>
                Agency booking mode active — bookings will be linked to
                <strong>{{ $agentBookingAgencyName ?: 'your agency' }}</strong>.
            </span>
            @if (auth()->user()?->isAgentPortalUser() && Route::has('agent.bookings.exit-mode'))
                <a href="{{ route('agent.bookings.exit-mode') }}" class="alert-link fw-semibold">Exit agency booking mode</a>
            @endif
        </div>
    </div>
@endif
