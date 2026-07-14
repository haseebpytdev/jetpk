@props([
    'booking',
    'showUrl',
])

@php
    $paymentOp = \App\Support\Bookings\PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'));
    $passengerCount = $booking->passengers_count ?? ($booking->relationLoaded('passengers') ? $booking->passengers->count() : null);
    $needsPayment = in_array((string) ($booking->payment_status ?? 'unpaid'), ['unpaid', 'partial'], true)
        || (float) ($booking->balance_due ?? 0) > 0;
    $invoiceDoc = $booking->relationLoaded('documents')
        ? $booking->documents->first(
            fn ($d) => $d->document_type->value === 'invoice' && $d->status->value === 'generated' && $d->file_path !== null
        )
        : null;
@endphp

<article class="ota-mobile-customer__card ota-mobile-customer__booking-card" data-testid="ota-mobile-customer-booking-card">
    <div class="ota-mobile-customer__card-head">
        <span class="ota-mobile-customer__ref">{{ $booking->booking_reference ?? 'N/A' }}</span>
        @include('mobile.customer.partials.booking-status-pill', ['status' => $booking->status])
    </div>
    <p class="ota-mobile-customer__route">{{ $booking->route ?? '—' }}</p>
    <dl class="ota-mobile-customer__meta">
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
            <dt>Payment</dt>
            <dd>{{ $paymentOp['label'] }}</dd>
        </div>
    </dl>
    <div class="ota-mobile-customer__actions">
        <a href="{{ $showUrl }}" class="ota-mobile-customer__btn ota-mobile-customer__btn--primary">View</a>
    </div>
</article>
