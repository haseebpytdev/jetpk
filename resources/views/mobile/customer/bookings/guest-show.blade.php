@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Booking '.($booking->booking_reference ?? '#'.$booking->id))

@section('mobile_app_title', 'Booking lookup')

@section('mobile_app_back')
    <a href="{{ route('booking.lookup') }}" class="ota-mobile-app__back-btn" aria-label="Back to lookup">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    @php
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $hasPnr = filled($booking->pnr);
        $provider = (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? ''));
        $itineraryOverview = \App\Support\Bookings\BookingItineraryOverviewPresenter::fromBookingMeta($meta, $hasPnr);
        $paymentOperational = \App\Support\Bookings\PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'));
        $supplierOperational = \App\Support\Bookings\SupplierOperationalStatus::fromValues(
            (string) ($booking->supplier_booking_status ?? 'not_started'),
            $provider,
            $hasPnr,
            $meta,
        );
        $ticketingOperational = \App\Support\Bookings\TicketingOperationalStatus::fromValues(
            (string) ($booking->ticketing_status ?? 'not_started'),
            (string) ($booking->payment_status ?? 'unpaid'),
            $hasPnr,
            $booking->tickets->isNotEmpty(),
            $provider,
            (string) ($booking->cancellation_status ?? ''),
        );
        $paymentOp = $paymentOperational;
        $supplierOp = $supplierOperational;
        $ticketingOp = $ticketingOperational;
        $itinerary = $itineraryOverview;
        $safeCommEvents = ['booking_request_received', 'booking_confirmed', 'booking_status_changed', 'payment_verified', 'payment_rejected', 'ticket_issued'];
        $showCancelForm = $booking->status->value !== 'cancelled'
            && ! $booking->cancellationRequests->contains(fn ($r) => in_array($r->status->value, ['requested', 'approved'], true));
    @endphp

    <div class="ota-mobile-customer" data-testid="ota-mobile-guest-booking-show">
        <header class="ota-mobile-customer__hero">
            <p class="ota-mobile-customer__hero-ref">{{ $booking->booking_reference ?? '#'.$booking->id }}</p>
            <h1 class="ota-mobile-customer__hero-route">{{ $booking->route ?? 'Trip details' }}</h1>
            <div class="ota-mobile-customer__hero-pills">
                @include('mobile.customer.partials.booking-status-pill', ['status' => $booking->status])
                @include('mobile.customer.partials.booking-status-pill', ['label' => $paymentOp['label'] ?? 'Payment'])
            </div>
            <p class="ota-mobile-customer__guest-note">Guest view — verified with your booking reference and contact details.</p>
        </header>

        @if (session('status') === 'payment-proof-submitted')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Payment proof submitted. Our team will verify it shortly.'])
        @elseif (session('status'))
            @include('mobile.components.alert', ['type' => 'success', 'message' => session('status')])
        @endif

        @if ($errors->any())
            @include('mobile.components.alert', ['type' => 'danger', 'message' => $errors->first()])
        @endif

        <section class="ota-mobile-customer__card">
            <div class="ota-mobile-customer__card-head">
                <h2 class="ota-mobile-customer__card-title">Itinerary</h2>
                @if ($itinerary)
                    <span class="ota-mobile-customer__muted">{{ $itinerary['itinerary_source_label'] ?? '' }}</span>
                @endif
            </div>
            @if ($itinerary && ($itinerary['show_snapshot_itinerary_warning'] ?? false))
                @include('mobile.components.alert', ['type' => 'info', 'message' => 'Search/checkout snapshot — final airline itinerary not yet synced.'])
            @endif
            @if ($itinerary && ! empty($itinerary['segment_lines']))
                <p class="ota-mobile-customer__note">{{ $itinerary['journey_od'] ?? '' }} · {{ $itinerary['stops_label'] ?? '' }}</p>
                @foreach ($itinerary['segment_lines'] as $line)
                    <p class="ota-mobile-customer__itinerary-line {{ str_starts_with($line, '   —') ? 'is-sub' : '' }}">{{ $line }}</p>
                @endforeach
            @else
                <p class="ota-mobile-customer__note">Itinerary details will appear when your flight segments are available.</p>
            @endif
        </section>

        <section class="ota-mobile-customer__card">
            <h2 class="ota-mobile-customer__card-title">Passengers and contact</h2>
            @foreach($booking->passengers->sortBy('passenger_index') as $passenger)
                <div class="ota-mobile-customer__passenger">
                    <p class="ota-mobile-customer__passenger-name">
                        {{ $passenger->title }} {{ $passenger->first_name }} {{ $passenger->last_name }}
                    </p>
                </div>
            @endforeach
            <dl class="ota-mobile-customer__meta">
                <div>
                    <dt>Email</dt>
                    <dd class="ota-mobile-customer__text-safe">{{ $booking->contact?->email ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt>Phone</dt>
                    <dd>{{ $booking->contact?->phone ?? 'N/A' }}</dd>
                </div>
            </dl>
        </section>

        @include('mobile.customer.partials.payment-summary-card', [
            'booking' => $booking,
            'summary' => $paymentSummary,
            'guest' => true,
            'guestToken' => $guestToken,
        ])

        <section class="ota-mobile-customer__card">
            <h2 class="ota-mobile-customer__card-title">PNR and ticketing</h2>
            <p class="ota-mobile-customer__note"><strong>PNR:</strong> {{ $booking->pnr ?? 'Not yet assigned' }}</p>
            @if (! empty($supplierOp['meaning']))
                <p class="ota-mobile-customer__note">{{ $supplierOp['meaning'] }}</p>
            @endif
            @if (! empty($ticketingOp['meaning']))
                <p class="ota-mobile-customer__note">{{ $ticketingOp['meaning'] }}</p>
            @endif
        </section>

        @if ($showCancelForm)
            <section class="ota-mobile-customer__card" id="cancellation">
                <h2 class="ota-mobile-customer__card-title">Cancellation</h2>
                @include('mobile.components.alert', ['type' => 'info', 'message' => 'Ticketed bookings may require manual airline void/refund handling.'])
                <form method="post" action="{{ route('guest.bookings.cancellations.store', ['booking' => $booking, 'token' => $guestToken]) }}" class="ota-mobile-customer__form">
                    @csrf
                    <div class="ota-mobile-customer__field">
                        <label class="ota-mobile-customer__label" for="cancellation_type">Type</label>
                        <select name="cancellation_type" id="cancellation_type" class="ota-mobile-customer__input" required>
                            @foreach (['booking_cancel', 'ticket_void', 'ticket_refund', 'supplier_cancel'] as $type)
                                <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ota-mobile-customer__field">
                        <label class="ota-mobile-customer__label" for="cancel_reason">Reason (optional)</label>
                        <textarea name="reason" id="cancel_reason" rows="2" class="ota-mobile-customer__input"></textarea>
                    </div>
                    <button class="ota-mobile-customer__btn ota-mobile-customer__btn--danger ota-mobile-customer__btn--block" type="submit">Request cancellation</button>
                </form>
            </section>
        @endif

        <section class="ota-mobile-customer__card">
            <h2 class="ota-mobile-customer__card-title">Updates</h2>
            @forelse($booking->communicationLogs->where('channel', 'email')->whereIn('event', $safeCommEvents)->sortByDesc('created_at') as $log)
                <p class="ota-mobile-customer__note">
                    {{ $log->created_at?->format('j M Y, g:i A') }} — {{ str_replace('_', ' ', $log->event) }}
                </p>
            @empty
                <p class="ota-mobile-customer__note">No customer-facing updates yet.</p>
            @endforelse
        </section>

        <p class="ota-mobile-customer__footer-note">
            Need help? <a href="{{ route('support') }}" class="ota-mobile-customer__link">Contact support</a>
        </p>
    </div>
@endsection
