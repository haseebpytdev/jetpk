@extends(client_layout('customer-account', 'customer'))

@php
    $shell = 'account';
    $viewerMode = 'customer';
    $guest = false;
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $hasPnr = filled($booking->pnr);
    $provider = (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? ''));

    if (! isset($paymentSummary)) {
        $paymentSummary = \App\Support\Bookings\BookingPaymentSummaryPresenter::forBooking(
            $booking,
            $canSubmitPaymentProof ?? false,
            'customer',
        );
    }
    $timeline = $customerTimeline ?? [];
    $itinerary = $itineraryOverview ?? null;
    $paymentOp = $paymentOperational ?? ['label' => $paymentSummary['status_label'] ?? '', 'meaning' => $paymentSummary['status_meaning'] ?? ''];
    $safeCommEvents = ['booking_request_received', 'booking_confirmed', 'booking_status_changed', 'payment_verified', 'payment_rejected', 'ticket_issued'];
    $showCancelForm = ($canRequestCancellation ?? false)
        || ($booking->status->value !== 'cancelled' && ! $booking->cancellationRequests->contains(fn ($r) => in_array($r->status->value, ['requested', 'approved'], true)));
    $cancelAction = route('customer.bookings.cancellations.store', $booking);
@endphp

@section('title', 'Booking '.$booking->display_reference)

@section('account_title', 'View booking')
@section('account_subtitle', 'Trip details and documents')

@section('account_content')
    @if (session('status') === 'payment-proof-submitted')
        <div class="ota-account-alert ota-account-alert--success">Payment proof submitted. Our team will verify it shortly.</div>
    @elseif (session('status'))
        <div class="ota-account-alert ota-account-alert--success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="ota-account-alert ota-account-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <x-bookings.detail-summary-card
        :booking="$booking"
        :payment-label="$paymentOp['label']"
        :shell="$shell"
    />

    <div class="ota-account-detail-grid" data-testid="customer-booking-detail-layout">
        <div>
            <x-bookings.detail-timeline :timeline="$timeline" :shell="$shell" />
            <x-bookings.detail-itinerary :itinerary="$itinerary" test-id-prefix="customer" :shell="$shell" />
            <x-bookings.detail-passengers-contact :booking="$booking" :viewer-mode="$viewerMode" :shell="$shell" />
            <x-bookings.detail-updates :booking="$booking" :safe-comm-events="$safeCommEvents" :shell="$shell" />
        </div>

        <div class="ota-account-stack">
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
