@extends(client_layout('customer-account', 'customer'))

@php
    $shell = 'account';
    $viewerMode = 'customer';
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $timeline = $customerTimeline ?? [];
    $itinerary = $itineraryOverview ?? null;
    $paymentOp = $paymentOperational ?? ['label' => $paymentSummary['status_label'] ?? '', 'meaning' => $paymentSummary['status_meaning'] ?? ''];
    $safeCommEvents = ['booking_request_received', 'booking_confirmed', 'booking_status_changed', 'payment_verified', 'payment_rejected', 'ticket_issued'];
    $showCancelForm = ($canRequestCancellation ?? false)
        || ($booking->status->value !== 'cancelled' && ! $booking->cancellationRequests->contains(fn ($r) => in_array($r->status->value, ['requested', 'approved'], true)));
    $cancelAction = route('customer.bookings.cancellations.store', $booking);

    if (! isset($paymentSummary)) {
        $paymentSummary = \App\Support\Bookings\BookingPaymentSummaryPresenter::forBooking(
            $booking,
            $canSubmitPaymentProof ?? false,
            'customer',
        );
    }
@endphp

@section('title', 'Booking '.$booking->display_reference)

@section('content')
@include('themes.frontend.jetpakistan.components.portal.flash')

@if (session('status') === 'payment-proof-submitted')
    <div class="jp-portal-alert jp-portal-alert--info">Payment proof submitted. Our team will verify it shortly.</div>
@elseif (session('status'))
    <div class="jp-portal-alert jp-portal-alert--info">{{ session('status') }}</div>
@endif

<div class="jp-portal-page-head">
    <div>
        <p class="jp-portal-backlink"><a href="{{ client_route('customer.bookings.index') }}">← My bookings</a></p>
        <h1>{{ $booking->display_reference }}</h1>
        <p>Trip details and documents.</p>
    </div>
</div>

<x-bookings.detail-summary-card :booking="$booking" :payment-label="$paymentOp['label']" :shell="$shell" />

<div class="jp-portal-grid jp-portal-grid--2" data-testid="customer-booking-detail-layout" style="margin-top:var(--sp-5)">
    <div>
        <x-bookings.detail-timeline :timeline="$timeline" :shell="$shell" />
        <x-bookings.detail-itinerary :itinerary="$itinerary" test-id-prefix="customer" :shell="$shell" />
        <x-bookings.detail-passengers-contact :booking="$booking" :viewer-mode="$viewerMode" :shell="$shell" />
        <x-bookings.detail-updates :booking="$booking" :safe-comm-events="$safeCommEvents" :shell="$shell" />
    </div>
    <div>
        <x-bookings.payment-documents-panel
            :booking="$booking"
            :summary="$paymentSummary"
            :guest="false"
            audience="customer"
            :viewer-mode="$viewerMode"
            :shell="$shell"
        />

        <x-bookings.detail-cancellation
            :booking="$booking"
            :show-cancel-form="$showCancelForm"
            :cancel-action="$cancelAction"
            :viewer-mode="$viewerMode"
            :shell="$shell"
        />

        <x-bookings.detail-help-card :shell="$shell" />
    </div>
</div>
@endsection
