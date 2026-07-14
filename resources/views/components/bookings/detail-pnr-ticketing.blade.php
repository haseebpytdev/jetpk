@props([
    'booking',
    'supplierOp' => [],
    'ticketingOp' => [],
    'shell' => 'account',
])

@php $isAccount = $shell === 'account'; @endphp

<div class="{{ $isAccount ? 'ota-account-card mb-3' : 'card mb-3 border-0 shadow-sm' }}">
    <div class="{{ $isAccount ? 'ota-account-card__head' : 'card-header border-0' }}">
        <h3 class="{{ $isAccount ? 'ota-account-card__title' : 'card-title mb-0' }}">PNR & ticketing</h3>
    </div>
    <div class="{{ $isAccount ? 'ota-account-card__body' : 'card-body' }}" data-testid="booking-pnr-ticketing">
        <dl class="ota-booking-detail-kv mb-0">
            <div class="ota-booking-detail-kv__row">
                <dt>PNR</dt>
                <dd class="ota-r-text-safe">{{ $booking->pnr ?? 'Not yet assigned' }}</dd>
            </div>
            <div class="ota-booking-detail-kv__row">
                <dt>Supplier booking</dt>
                <dd>{{ $supplierOp['label'] ?? '—' }}</dd>
            </div>
            <div class="ota-booking-detail-kv__row">
                <dt>Ticketing</dt>
                <dd>{{ $ticketingOp['label'] ?? '—' }}</dd>
            </div>
        </dl>
        @if (! empty($supplierOp['meaning']))
            <p class="small text-secondary mb-1 mt-2">{{ $supplierOp['meaning'] }}</p>
        @endif
        @if (! empty($ticketingOp['meaning']))
            <p class="small text-secondary mb-0">{{ $ticketingOp['meaning'] }}</p>
        @endif
        @foreach($booking->tickets as $ticket)
            <div class="small mt-2 ota-r-text-safe">{{ $ticket->ticket_number }} — {{ $ticket->airline_code ?? 'N/A' }}</div>
        @endforeach
    </div>
</div>
