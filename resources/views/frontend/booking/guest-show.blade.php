@extends('layouts.guest-booking')

@php
    $shell = 'account';
    $viewerMode = $viewerMode ?? 'guest';
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $hasPnr = $hasPnr ?? filled($booking->pnr);
    $timeline = $customerTimeline ?? [];
    $itinerary = $itineraryOverview ?? null;
    $paymentOp = $paymentOperational ?? ['label' => $paymentSummary['status_label'] ?? '', 'meaning' => $paymentSummary['status_meaning'] ?? ''];
    $supplierOp = $supplierOperational ?? ['label' => '', 'meaning' => ''];
    $ticketingOp = $ticketingOperational ?? ['label' => '', 'meaning' => ''];
    $safeCommEvents = ['booking_request_received', 'booking_confirmed', 'booking_status_changed', 'payment_verified', 'payment_rejected', 'ticket_issued'];
    $hasLinkedAccount = $hasLinkedAccount ?? filled($booking->customer_id);
    $loginUrl = $loginUrl ?? client_route('login', ['redirect' => '/customer/bookings/'.$booking->id]);
    $allowGuestProofUpload = ($allowGuestProofUpload ?? false) && ! $hasLinkedAccount;
    $showGuestCancelForm = ($showGuestCancelForm ?? false) && ! $hasLinkedAccount;
    $cancelAction = route('guest.bookings.cancellations.store', ['booking' => $booking, 'token' => $guestToken]);
@endphp

@section('title', 'Booking '.$booking->display_reference)

@section('guest_pretitle', 'Booking lookup')
@section('guest_title', 'Your booking')
@section('guest_subtitle', 'Secure guest view — sensitive details are masked.')

@section('guest_actions')
    <a href="{{ client_route('booking.lookup') }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">Back to lookup</a>
@endsection

@section('guest_content')
    @if (session('status') === 'payment-proof-submitted')
        <div class="ota-account-alert ota-account-alert--success">Payment proof submitted. Our team will verify it shortly.</div>
    @elseif (session('status') === 'cancellation-requested')
        <div class="ota-account-alert ota-account-alert--success">Cancellation request submitted. Our team will review it shortly.</div>
    @elseif (session('status'))
        <div class="ota-account-alert ota-account-alert--success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="ota-account-alert ota-account-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if ($hasLinkedAccount)
        <x-bookings.detail-guest-account-cta :login-url="$loginUrl" :shell="$shell" />
    @endif

    <x-bookings.detail-summary-card
        :booking="$booking"
        :payment-label="$paymentOp['label']"
        :shell="$shell"
    />

    <div class="ota-account-detail-grid" data-testid="guest-booking-detail-layout">
        <div>
            <x-bookings.detail-timeline :timeline="$timeline" :shell="$shell" />
            <x-bookings.detail-itinerary :itinerary="$itinerary" test-id-prefix="guest" :shell="$shell" />
            <x-bookings.detail-passengers-contact :booking="$booking" :viewer-mode="$viewerMode" :shell="$shell" />
            <x-bookings.detail-updates :booking="$booking" :safe-comm-events="$safeCommEvents" :shell="$shell" />
        </div>

        <div class="ota-account-stack">
            <x-bookings.payment-documents-panel
                :booking="$booking"
                :summary="$paymentSummary"
                :guest="true"
                :guest-token="$guestToken"
                :viewer-mode="$viewerMode"
                :allow-guest-proof-upload="$allowGuestProofUpload"
                :login-url="$loginUrl"
                audience="customer"
                :shell="$shell"
            />

            <x-bookings.detail-cancellation
                :booking="$booking"
                :show-cancel-form="$showGuestCancelForm"
                :cancel-action="$cancelAction"
                :viewer-mode="$viewerMode"
                :has-linked-account="$hasLinkedAccount"
                :login-url="$loginUrl"
                :shell="$shell"
            />

            <x-bookings.detail-help-card :shell="$shell" />
        </div>
    </div>
@endsection
