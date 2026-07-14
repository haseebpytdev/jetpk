@extends(client_layout('agent-portal', 'agent'))

@php
    $shell = 'account';
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $hasPnr = filled($booking->pnr);
    $itinerary = $itineraryOverview ?? null;
    $paymentOp = $paymentOperational ?? ['label' => '', 'meaning' => ''];
    $timeline = $customerTimeline ?? [];
    $safeCommEvents = ['booking_request_received', 'booking_confirmed', 'booking_status_changed', 'payment_verified', 'payment_rejected', 'ticket_issued'];

    if (! isset($paymentSummary)) {
        $paymentSummary = \App\Support\Bookings\BookingPaymentSummaryPresenter::forBooking(
            $booking,
            true,
            'agent',
        );
    }
    $paymentOp = $paymentOperational ?? [
        'label' => $paymentSummary['status_label'] ?? '',
        'meaning' => $paymentSummary['status_meaning'] ?? '',
    ];
    $showCancelForm = $booking->status->value !== 'cancelled'
        && auth()->user()?->can('request', [\App\Models\BookingCancellationRequest::class, $booking]);
@endphp

@section('title', 'Booking '.$booking->display_reference)

@section('content')
@include('themes.frontend.jetpakistan.components.portal.flash')

@if (session('status') === 'booking-request-created')
    <div class="jp-portal-alert jp-portal-alert--info">Booking request submitted. Our team will review fare and proceed with supplier booking.</div>
@elseif (session('status') && session('status') !== 'payment-proof-submitted')
    <div class="jp-portal-alert jp-portal-alert--info">{{ session('status') }}</div>
@endif

<div class="jp-portal-page-head">
    <div>
        <p class="jp-portal-backlink"><a href="{{ client_route('agent.bookings.index') }}">← My bookings</a></p>
        <h1>{{ $booking->display_reference }}</h1>
        <p>Trip details, payment, and documents.</p>
    </div>
</div>

<x-bookings.detail-summary-card :booking="$booking" :payment-label="$paymentOp['label']" :shell="$shell" />

<div class="jp-portal-grid jp-portal-grid--2" style="margin-top:var(--sp-5)">
    <div>
        <x-bookings.detail-timeline :timeline="$timeline" :shell="$shell" />
        <x-bookings.detail-itinerary :itinerary="$itinerary" test-id-prefix="agent" :shell="$shell" />
        <x-bookings.detail-passengers-contact :booking="$booking" :shell="$shell" />
        <x-bookings.detail-updates :booking="$booking" :safe-comm-events="$safeCommEvents" :shell="$shell" />
    </div>
    <div>
        @if (auth()->user()?->isAgentAdmin())
            <div class="jp-portal-card">
                <div class="jp-portal-card__head"><h2 class="jp-portal-card__title">Your commission</h2></div>
                <div class="jp-portal-card__body">
                    @if ($commissionEntry ?? null)
                        <p style="margin:0 0 var(--sp-2)"><strong>Status:</strong> {{ ucfirst($commissionEntry->status->value) }}</p>
                        <p style="margin:0 0 var(--sp-2)"><strong>Amount:</strong> Rs {{ number_format((float) $commissionEntry->commission_amount, 2) }}</p>
                        <p style="margin:0 0 var(--sp-3);color:var(--muted);font-size:var(--fs-14)">{{ $commissionEntry->description ?? 'Commission for this booking.' }}</p>
                        <a href="{{ client_route('agent.commissions.index') }}" class="jp-portal-btn jp-portal-btn--ghost jp-portal-btn--sm">View commissions</a>
                    @else
                        <p style="margin:0;color:var(--muted);font-size:var(--fs-14)">Commission is recorded after ticketing.</p>
                    @endif
                </div>
            </div>
        @endif

        <x-bookings.payment-documents-panel :booking="$booking" :summary="$paymentSummary" audience="agent" :shell="$shell" />

        @can('request', [\App\Models\BookingCancellationRequest::class, $booking])
            <x-bookings.detail-cancellation
                :booking="$booking"
                :show-cancel-form="$showCancelForm"
                :cancel-action="route('agent.bookings.cancellations.store', $booking)"
                :shell="$shell"
            />
        @endcan

        <x-bookings.detail-help-card :shell="$shell" />
    </div>
</div>
@endsection
