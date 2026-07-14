@props([
    'booking',
    'showUrl',
])

@php
    $paymentOp = \App\Support\Bookings\PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'));
    $pax = $booking->passengers->first();
    $customer = trim(implode(' ', array_filter([$pax?->title, $pax?->first_name, $pax?->last_name]))) ?: ($booking->contact?->email ?? '—');
    $passengerCount = $booking->passengers_count ?? ($booking->relationLoaded('passengers') ? $booking->passengers->count() : null);
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $hasPnr = filled($booking->pnr);
    $supplierOp = \App\Support\Bookings\SupplierOperationalStatus::fromValues(
        (string) ($booking->supplier_booking_status ?? 'not_started'),
        (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? '')),
        $hasPnr,
        $meta,
    );
    $ticketingOp = \App\Support\Bookings\TicketingOperationalStatus::fromValues(
        (string) ($booking->ticketing_status ?? 'not_started'),
        (string) ($booking->payment_status ?? 'unpaid'),
        $hasPnr,
        $booking->relationLoaded('tickets') ? $booking->tickets->isNotEmpty() : false,
        (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? '')),
        (string) ($booking->cancellation_status ?? ''),
    );
@endphp

<article class="ota-mobile-agent__card ota-mobile-agent__booking-card" data-testid="ota-mobile-agent-booking-card">
    <div class="ota-mobile-agent__card-head">
        <span class="ota-mobile-agent__ref">{{ $booking->booking_reference ?? 'Draft' }}</span>
        @include('mobile.agent.partials.agent-status-pill', ['status' => $booking->status])
    </div>
    <p class="ota-mobile-agent__route">{{ $booking->route ?? '—' }}</p>
    <p class="ota-mobile-agent__muted ota-mobile-agent__customer">{{ $customer }}</p>
    <dl class="ota-mobile-agent__meta">
        <div>
            <dt>Travel date</dt>
            <dd>{{ $booking->travel_date?->format('j M Y') ?? '—' }}</dd>
        </div>
        @if ($passengerCount !== null && $passengerCount > 0)
            <div>
                <dt>Passengers</dt>
                <dd>{{ $passengerCount }}</dd>
            </div>
        @endif
        <div>
            <dt>Amount</dt>
            <dd>Rs {{ number_format((float) ($booking->fareBreakdown?->total ?? 0), 0) }}</dd>
        </div>
        <div>
            <dt>Payment</dt>
            <dd>{{ $paymentOp['label'] }}</dd>
        </div>
        <div>
            <dt>Ticketing</dt>
            <dd>{{ $ticketingOp['label'] ?? '—' }}</dd>
        </div>
    </dl>
    <div class="ota-mobile-agent__actions">
        <a href="{{ $showUrl }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">View</a>
    </div>
</article>
