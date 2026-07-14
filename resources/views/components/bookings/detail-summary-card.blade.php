@props([
    'booking',
    'paymentLabel' => '',
    'shell' => 'account',
])

@php
    $isAccount = $shell === 'account';
    $ref = $booking->display_reference;
    $routeLabel = $booking->route ?? 'Trip details';
@endphp

<div class="{{ $isAccount ? 'ota-account-card ota-booking-detail-summary mb-3' : 'card mb-3 border-0 shadow-sm ota-booking-detail-summary' }}" data-testid="booking-detail-summary">
    <div class="{{ $isAccount ? 'ota-account-card__body' : 'card-body' }}">
        <div class="ota-booking-detail-summary__inner">
            <div class="ota-booking-detail-summary__icon" aria-hidden="true">
                <i class="ti ti-ticket"></i>
            </div>
            <div class="ota-booking-detail-summary__main">
                <h2 class="ota-booking-detail-summary__title">Booking {{ $ref }}</h2>
                <p class="ota-booking-detail-summary__route ota-r-text-safe">{{ $routeLabel }}</p>
                <p class="ota-booking-detail-summary__meta">
                    Booking created on <x-time.local :value="$booking->created_at" context="public" :show-utc="false" />
                </p>
            </div>
            <div class="ota-booking-detail-summary__badges">
                <x-dashboard.status-badge :status="$booking->status" />
                @if (filled($paymentLabel))
                    <span class="{{ $isAccount ? 'ota-account-badge' : 'badge bg-secondary-lt' }} text-capitalize">{{ $paymentLabel }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
