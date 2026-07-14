@props([
    'booking',
    'showCancelForm' => false,
    'cancelAction' => '',
    'viewerMode' => 'customer',
    'hasLinkedAccount' => false,
    'loginUrl' => null,
    'shell' => 'account',
])

@php
    $isAccount = $shell === 'account';
    $isGuest = $viewerMode === 'guest';
    $showGuestFallback = $isGuest && (! $showCancelForm || $hasLinkedAccount);
@endphp

<div class="{{ $isAccount ? 'ota-account-card mb-3' : 'card mb-3 border-0 shadow-sm' }}" id="cancellation">
    <div class="{{ $isAccount ? 'ota-account-card__head' : 'card-header border-0' }}">
        <h3 class="{{ $isAccount ? 'ota-account-card__title' : 'card-title mb-0' }}">Cancellation request</h3>
    </div>
    <div class="{{ $isAccount ? 'ota-account-card__body' : 'card-body' }}" data-testid="booking-cancellation-card">
        @if (filled($booking->cancellation_status) && (string) $booking->cancellation_status !== 'none')
            <p class="small mb-2">Cancellation status: <span class="text-capitalize">{{ str_replace('_', ' ', (string) $booking->cancellation_status) }}</span></p>
        @endif

        @if ($showGuestFallback)
            <p class="small mb-2" data-testid="guest-cancellation-login-hint">Need to cancel? Login to manage this booking or contact support.</p>
            <div class="d-flex flex-wrap gap-2 mb-2">
                @if ($loginUrl)
                    <a href="{{ $loginUrl }}" class="{{ $isAccount ? 'ota-account-btn ota-account-btn--primary ota-account-btn--sm' : 'btn btn-primary btn-sm' }}">Login</a>
                @endif
                <a href="{{ route('support') }}" class="{{ $isAccount ? 'ota-account-btn ota-account-btn--secondary ota-account-btn--sm' : 'btn btn-outline-secondary btn-sm' }}">Contact support</a>
            </div>
        @elseif ($showCancelForm)
            @php
                $isTicketedBooking = $booking->tickets->isNotEmpty()
                    || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true)
                    || (is_object($booking->status) && ($booking->status->value ?? '') === 'ticketed');
            @endphp
            @if ($isTicketedBooking)
                <div class="{{ $isAccount ? 'ota-account-alert ota-account-alert--warning' : 'alert alert-warning' }} py-2 px-3 small">
                    Ticketed bookings may require manual airline void/refund handling.
                </div>
            @else
                <p class="small text-secondary mb-2">Submit a cancellation request and our team will review it. No airline action is taken automatically.</p>
            @endif
            <form method="post" action="{{ $cancelAction }}" data-testid="{{ $isGuest ? 'guest-cancellation-form' : 'booking-cancellation-form' }}">
                @csrf
                <div class="mb-2">
                    <label class="form-label visually-hidden" for="cancellation_type">Cancellation type</label>
                    <select class="form-select" id="cancellation_type" name="cancellation_type" required>
                        @foreach (['booking_cancel', 'ticket_void', 'ticket_refund', 'supplier_cancel'] as $type)
                            <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label visually-hidden" for="cancellation_reason">Reason</label>
                    <textarea class="form-control" id="cancellation_reason" name="reason" rows="2" placeholder="Reason (optional)"></textarea>
                </div>
                <button class="{{ $isAccount ? 'ota-account-btn ota-account-btn--danger ota-account-btn--block' : 'btn btn-outline-danger w-100' }}" type="submit">Request cancellation</button>
            </form>
        @endif

        @if (! $isGuest)
            <div class="small text-secondary mt-3 mb-1">Your requests</div>
            @forelse($booking->cancellationRequests->sortByDesc('created_at')->take(5) as $cancelReq)
                <div class="ota-booking-detail-cancel-req small">
                    <div class="d-flex justify-content-between gap-2">
                        <span>{{ $cancelReq->cancellation_type->value }}</span>
                        <span class="text-capitalize">{{ $cancelReq->status->value }}</span>
                    </div>
                    <div class="text-secondary"><x-time.local :value="$cancelReq->created_at" context="public" :show-utc="false" /></div>
                </div>
            @empty
                <div class="small text-secondary">No cancellation requests yet.</div>
            @endforelse
        @endif
    </div>
</div>
