@extends(client_layout('frontend', 'frontend'))

@section('title', 'Booking confirmation')

@section('content')
    @php
        use App\Enums\GroupBookingStatus;

        $checkoutSummary = is_array($checkoutSummary ?? null) ? $checkoutSummary : [];
        $paxCount = $booking->passengers->count();
        $ref = $booking->reference;
        $statusBadge = match ($booking->status) {
            GroupBookingStatus::Confirmed => ['label' => 'Confirmed', 'class' => 'ota-confirm-status--confirmed'],
            GroupBookingStatus::ManualPaymentPendingReview, GroupBookingStatus::ManualPaymentSubmitted => [
                'label' => 'Payment under review',
                'class' => 'ota-confirm-status--review',
            ],
            GroupBookingStatus::ReservedAwaitingPayment, GroupBookingStatus::PaymentPending => [
                'label' => 'Awaiting payment',
                'class' => 'ota-confirm-status--pending',
            ],
            default => ['label' => $booking->status->label(), 'class' => 'ota-confirm-status--pending'],
        };
        $heroTitle = match ($booking->status) {
            GroupBookingStatus::Confirmed => 'Booking confirmed',
            GroupBookingStatus::ManualPaymentPendingReview, GroupBookingStatus::ManualPaymentSubmitted => 'Payment submitted',
            default => 'Booking update',
        };
        $heroSub = match ($booking->status) {
            GroupBookingStatus::Confirmed => 'Your group booking is confirmed. Thank you for your payment.',
            GroupBookingStatus::ManualPaymentPendingReview, GroupBookingStatus::ManualPaymentSubmitted => 'Your payment has been submitted and is pending admin verification. We will notify you once confirmed.',
            GroupBookingStatus::ReservedAwaitingPayment, GroupBookingStatus::PaymentPending => 'Your reservation is held. Please complete payment before it expires.',
            default => 'Status: '.$booking->status->label(),
        };
        $paymentStatusLabel = match ($booking->status) {
            GroupBookingStatus::Confirmed => 'Payment verified',
            GroupBookingStatus::ManualPaymentPendingReview, GroupBookingStatus::ManualPaymentSubmitted => 'Manual payment pending admin review',
            GroupBookingStatus::ReservedAwaitingPayment, GroupBookingStatus::PaymentPending => 'Awaiting payment',
            default => $booking->status->label(),
        };
        $wa = config('ota-client.support_whatsapp', '');
        $waUrl = $wa !== '' ? 'https://wa.me/'.preg_replace('/\D+/', '', (string) $wa) : null;
    @endphp
    <section class="ota-confirmation-wrap ota-confirmation-page ota-checkout-page--group">
        <div class="ota-container ota-container-narrow">
            @include('frontend.checkout.partials.stepper', ['activeStep' => $activeStep ?? 'confirmation'])

            <div class="ota-confirm-hero-card">
                <div class="ota-confirm-success-ring" aria-hidden="true">
                    <span class="ota-confirm-success-icon"><i class="fa fa-check"></i></span>
                </div>
                <h1 class="ota-confirm-title">{{ $heroTitle }}</h1>
                <p class="ota-confirm-sub">{{ $heroSub }}</p>

                <div class="ota-confirm-hero-meta">
                    <div class="ota-confirm-ref">
                        <span class="ota-confirm-ref__label">Booking reference</span>
                        <span class="ota-confirm-ref__value ota-confirm-ref__value--hero">{{ e($ref) }}</span>
                    </div>
                    <span class="ota-confirm-status {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>
                </div>

                <dl class="ota-confirm-hero-facts">
                    @if ($paxCount > 0)
                        <div class="ota-confirm-hero-facts__row">
                            <dt>Passengers</dt>
                            <dd>{{ $paxCount }} {{ $paxCount === 1 ? 'passenger' : 'passengers' }}</dd>
                        </div>
                    @endif
                    <div class="ota-confirm-hero-facts__row">
                        <dt>Payment status</dt>
                        <dd>{{ $paymentStatusLabel }}</dd>
                    </div>
                    <div class="ota-confirm-hero-facts__row">
                        <dt>Total</dt>
                        <dd>{{ e($booking->currency) }} {{ number_format((float) $booking->total_amount, 0) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="ota-confirm-grid">
                <article class="ota-confirm-card ota-confirm-card--wide">
                    <h2 class="ota-confirm-card__title"><i class="fa fa-users" aria-hidden="true"></i> Group package</h2>
                    <div class="ota-confirm-trip">
                        <div class="ota-confirm-trip__head">
                            <div class="ota-confirm-trip__brand">
                                @if (! empty($checkoutSummary['airline_logo_url']))
                                    <img src="{{ $checkoutSummary['airline_logo_url'] }}" alt="" class="ota-confirm-trip__logo" width="32" height="32">
                                @endif
                                <div>
                                    <p class="ota-confirm-trip__airline mb-0">{{ e($checkoutSummary['airline_name'] ?? 'Group Ticketing') }}</p>
                                    <p class="ota-confirm-trip__flight-no mb-0">{{ e($checkoutSummary['sector_code'] ?? '') }}</p>
                                </div>
                            </div>
                            <span class="ota-confirm-trip__pill">Group Ticketing</span>
                        </div>
                        <p class="ota-confirm-trip__route">{{ e($checkoutSummary['route_line'] ?? '') }}</p>
                        @if (! empty($checkoutSummary['departure_date_short']))
                            <p class="ota-confirm-card__muted mb-2">{{ e($checkoutSummary['departure_date_short']) }}</p>
                        @endif
                        @if (! empty($checkoutSummary['baggage_display']))
                            <ul class="ota-confirm-trip__tags">
                                <li><i class="fa fa-suitcase" aria-hidden="true"></i> {{ e($checkoutSummary['baggage_display']) }}</li>
                                <li>{{ $booking->seat_count }} seat{{ $booking->seat_count === 1 ? '' : 's' }}</li>
                            </ul>
                        @endif
                    </div>
                </article>

                <article class="ota-confirm-card">
                    <h2 class="ota-confirm-card__title"><i class="fa fa-user" aria-hidden="true"></i> Contact</h2>
                    <dl class="ota-confirm-dl">
                        <div class="ota-confirm-dl__row">
                            <dt>Name</dt>
                            <dd>{{ e($booking->contact_name ?? '—') }}</dd>
                        </div>
                        <div class="ota-confirm-dl__row">
                            <dt>Email</dt>
                            <dd>{{ e($booking->contact_email ?? '—') }}</dd>
                        </div>
                        <div class="ota-confirm-dl__row">
                            <dt>Phone</dt>
                            <dd>{{ e($booking->contact_phone ?? '—') }}</dd>
                        </div>
                    </dl>
                </article>

                <article class="ota-confirm-card">
                    <h2 class="ota-confirm-card__title"><i class="fa fa-info-circle" aria-hidden="true"></i> Next steps</h2>
                    @if ($booking->status === GroupBookingStatus::ManualPaymentPendingReview || $booking->status === GroupBookingStatus::ManualPaymentSubmitted)
                        <ul class="ota-confirm-steps mb-0">
                            <li>Our team will verify your payment proof and reference.</li>
                            <li>You will receive an update once your booking is confirmed.</li>
                            <li>Keep your booking reference handy for support enquiries.</li>
                        </ul>
                    @elseif ($booking->status === GroupBookingStatus::Confirmed)
                        <ul class="ota-confirm-steps mb-0">
                            <li>Your group seats are confirmed.</li>
                            <li>Travel documents and further instructions will follow by email.</li>
                        </ul>
                    @else
                        <ul class="ota-confirm-steps mb-0">
                            <li>Complete payment before your reservation expires.</li>
                            <li>Submit your payment reference and proof on the payment page.</li>
                        </ul>
                    @endif
                </article>
            </div>

            <div class="ota-confirm-actions">
                <a href="{{ client_route('group-ticketing.search') }}" class="ota-btn ota-btn-primary">Back to group search</a>
                @if ($waUrl)
                    <a href="{{ $waUrl }}" class="ota-btn-wa" target="_blank" rel="noopener noreferrer">
                        <i class="fa fa-whatsapp" aria-hidden="true"></i> Contact support on WhatsApp
                    </a>
                @else
                    <p class="ota-confirm-card__muted mb-0">Need help? Contact our customer support team with your booking reference.</p>
                @endif
            </div>
        </div>
    </section>
@endsection
