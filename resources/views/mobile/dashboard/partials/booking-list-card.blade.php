@props([
    'booking',
    'showUrl',
    'passengerCount' => null,
    'priceLabel' => null,
    'sectionLabel' => 'Booking',
])

@php
    $status = $booking->status;
    $display = is_object($status) && enum_exists($status::class)
        ? str_replace('_', ' ', (string) ($status->value ?? $status->name))
        : str_replace('_', ' ', (string) $status);
    $stStr = strtolower(trim($display));
    $statusClass = match (true) {
        str_contains($stStr, 'cancel') => 'cancelled',
        str_contains($stStr, 'ticket') || str_contains($stStr, 'paid') || str_contains($stStr, 'confirm') => 'positive',
        str_contains($stStr, 'pending') || str_contains($stStr, 'review') => 'pending',
        default => 'muted',
    };
@endphp

<article class="ota-mobile-dashboard__booking-card" data-testid="ota-mobile-dashboard-booking-card">
    <div class="ota-mobile-dashboard__booking-head">
        <p class="ota-mobile-dashboard__booking-eyebrow">{{ $sectionLabel }}</p>
        <span class="ota-mobile-dashboard__status ota-mobile-dashboard__status--{{ $statusClass }}">{{ ucwords($display) }}</span>
    </div>
    <p class="ota-mobile-dashboard__booking-route">{{ $booking->route ?? '—' }}</p>
    <dl class="ota-mobile-dashboard__booking-meta">
        <div>
            <dt>Date</dt>
            <dd>{{ $booking->travel_date?->format('j M Y') ?? '—' }}</dd>
        </div>
        <div>
            <dt>Reference</dt>
            <dd class="ota-mobile-dashboard__text-safe">{{ $booking->booking_reference ?? '—' }}</dd>
        </div>
        @if ($passengerCount !== null)
            <div>
                <dt>Passengers</dt>
                <dd>{{ $passengerCount }}</dd>
            </div>
        @endif
        @if ($priceLabel !== null)
            <div>
                <dt>Price</dt>
                <dd>{{ $priceLabel }}</dd>
            </div>
        @endif
    </dl>
    <a href="{{ $showUrl }}" class="ota-mobile-dashboard__btn ota-mobile-dashboard__btn--primary">View booking</a>
</article>
