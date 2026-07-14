@extends(client_layout('agent-portal', 'agent'))

@php
    $shell = 'account';
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $hasPnr = filled($booking->pnr);
    $itinerary = $itineraryOverview ?? null;
    $paymentOp = $paymentOperational ?? ['label' => '', 'meaning' => ''];
    $supplierOp = $supplierOperational ?? ['label' => '', 'meaning' => ''];
    $ticketingOp = $ticketingOperational ?? ['label' => '', 'meaning' => ''];
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

@section('account_title', 'View booking')
@section('account_subtitle', 'Trip details and documents')

@section('account_actions')
    <a href="{{ route('agent.bookings.index') }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">Back to my bookings</a>
@endsection

@section('account_content')
    @if (session('status') === 'booking-request-created')
        <div class="ota-account-alert ota-account-alert--success">Booking request submitted. Our team will review fare and proceed with supplier booking.</div>
    @elseif (session('status'))
        <div class="ota-account-alert ota-account-alert--success">{{ session('status') }}</div>
    @endif

    <x-bookings.detail-summary-card
        :booking="$booking"
        :payment-label="$paymentOp['label']"
        :shell="$shell"
    />

    <div class="ota-account-detail-grid">
        <div>
            <x-bookings.detail-timeline :timeline="$timeline" :shell="$shell" />
            <x-bookings.detail-itinerary :itinerary="$itinerary" test-id-prefix="agent" :shell="$shell" />
            <x-bookings.detail-passengers-contact :booking="$booking" :shell="$shell" />
            <x-bookings.detail-updates :booking="$booking" :safe-comm-events="$safeCommEvents" :shell="$shell" />
        </div>

        <div class="ota-account-stack">
            @if (auth()->user()?->isAgentAdmin())
                <div class="ota-account-card mb-3">
                    <div class="ota-account-card__head">
                        <h3 class="ota-account-card__title">Your commission</h3>
                    </div>
                    <div class="ota-account-card__body" data-testid="agent-booking-commission">
                        @if ($commissionEntry ?? null)
                            <p class="mb-1"><strong>Status:</strong> <span class="text-capitalize">{{ $commissionEntry->status->value }}</span></p>
                            <p class="mb-1"><strong>Amount:</strong> Rs {{ number_format((float) $commissionEntry->commission_amount, 2) }}</p>
                            <p class="mb-0 small text-secondary">{{ $commissionEntry->description ?? 'Commission for this booking.' }}</p>
                            <a href="{{ route('agent.commissions.index') }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm mt-2">View all commissions</a>
                        @else
                            <p class="mb-0 small text-secondary">Commission is recorded after ticketing. Check back once tickets are issued.</p>
                        @endif
                    </div>
                </div>
            @endif

            <x-bookings.payment-documents-panel
                :booking="$booking"
                :summary="$paymentSummary"
                audience="agent"
                :shell="$shell"
            />

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
