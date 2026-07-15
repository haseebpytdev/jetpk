@extends('layouts.mobile-app')

@section('title', 'Booking '.$booking->display_reference)

@section('mobile_app_title', 'Booking details')

@section('mobile_app_back')
    <a href="{{ route('customer.bookings.index') }}" class="ota-mobile-app__back-btn" aria-label="Back to bookings">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    @php
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $itinerary = $itineraryOverview ?? null;
        $paymentOp = $paymentOperational ?? ['label' => $paymentSummary['status_label'] ?? '', 'meaning' => $paymentSummary['status_meaning'] ?? ''];
        $supplierOp = $supplierOperational ?? ['label' => '', 'meaning' => ''];
        $ticketingOp = $ticketingOperational ?? ['label' => '', 'meaning' => ''];
        $timeline = $customerTimeline ?? [];
        $safeCommEvents = ['booking_request_received', 'booking_confirmed', 'booking_status_changed', 'payment_verified', 'payment_rejected', 'ticket_issued'];
        $showCancelForm = $canRequestCancellation ?? false;
    @endphp

    <div class="ota-mobile-customer" data-testid="ota-mobile-customer-booking-show">
        <header class="ota-mobile-customer__hero">
            <p class="ota-mobile-customer__hero-ref">{{ $booking->display_reference }}</p>
            <h1 class="ota-mobile-customer__hero-route">{{ $booking->route ?? 'Trip details' }}</h1>
            <div class="ota-mobile-customer__hero-pills">
                @include('mobile.customer.partials.booking-status-pill', ['status' => $booking->status])
                @include('mobile.customer.partials.booking-status-pill', ['label' => $paymentOp['label'] ?? 'Payment'])
            </div>
        </header>

        @if (session('status') === 'payment-proof-submitted')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Payment proof submitted. Our team will verify it shortly.'])
        @elseif (session('status'))
            @include('mobile.components.alert', ['type' => 'success', 'message' => session('status')])
        @endif

        @if ($errors->any())
            @include('mobile.components.alert', ['type' => 'danger', 'message' => $errors->first()])
        @endif

        <section class="ota-mobile-customer__card" data-testid="customer-booking-timeline">
            <h2 class="ota-mobile-customer__card-title">Booking progress</h2>
            <ul class="ota-mobile-customer__timeline">
                @foreach ($timeline as $step)
                    @php
                        $state = $step['state'] ?? 'pending';
                        $tone = match ($state) {
                            'completed' => 'positive',
                            'active' => 'pending',
                            'warning' => 'cancelled',
                            default => 'muted',
                        };
                    @endphp
                    <li class="ota-mobile-customer__timeline-item">
                        <span class="ota-mobile-customer__pill ota-mobile-customer__pill--{{ $tone }}">{{ ucfirst($state) }}</span>
                        <div>
                            <p class="ota-mobile-customer__timeline-label">{{ $step['label'] }}</p>
                            @if (! empty($step['detail']))
                                <p class="ota-mobile-customer__note">{{ $step['detail'] }}</p>
                            @endif
                            @if (! empty($step['at']))
                                <p class="ota-mobile-customer__note"><x-time.local :value="$step['at']" context="public" :show-utc="false" /></p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

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
                        <span class="ota-mobile-customer__muted">· {{ ucfirst($passenger->passenger_type ?? 'adult') }}</span>
                    </p>
                    @if ($passenger->passport_number)
                        <p class="ota-mobile-customer__note">
                            Passport {{ \App\Support\Travel\TravelDocumentFormatter::maskPassport($passenger->passport_number) }}
                        </p>
                    @elseif($passenger->national_id_number)
                        <p class="ota-mobile-customer__note">
                            National ID {{ \App\Support\Travel\TravelDocumentFormatter::maskPassport($passenger->national_id_number) }}
                        </p>
                    @endif
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
            'guest' => false,
        ])

        <section class="ota-mobile-customer__card" id="cancellation">
            <h2 class="ota-mobile-customer__card-title">Cancellation</h2>
            @if (filled($booking->cancellation_status) && (string) $booking->cancellation_status !== 'none')
                <p class="ota-mobile-customer__note">
                    Status: {{ str_replace('_', ' ', (string) $booking->cancellation_status) }}
                </p>
            @endif
            @if ($showCancelForm)
                @php
                    $isTicketedBooking = $booking->tickets->isNotEmpty()
                        || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true)
                        || (is_object($booking->status) && ($booking->status->value ?? '') === 'ticketed');
                @endphp
                @if ($isTicketedBooking)
                    @include('mobile.components.alert', ['type' => 'info', 'message' => 'Ticketed bookings may require manual airline void/refund handling.'])
                @else
                    @include('mobile.components.alert', ['type' => 'info', 'message' => 'Submit a cancellation request and our team will review it. No airline action is taken automatically.'])
                @endif
                <form method="post" action="{{ route('customer.bookings.cancellations.store', $booking) }}" class="ota-mobile-customer__form">
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
            @endif
            @forelse($booking->cancellationRequests->sortByDesc('created_at')->take(5) as $cancelReq)
                <div class="ota-mobile-customer__cancel-row">
                    <span>{{ $cancelReq->cancellation_type->value }}</span>
                    <span class="ota-mobile-customer__muted">{{ $cancelReq->status->value }}</span>
                    <span class="ota-mobile-customer__note"><x-time.local :value="$cancelReq->created_at" context="public" :show-utc="false" /></span>
                </div>
            @empty
                <p class="ota-mobile-customer__note">No cancellation requests yet.</p>
            @endforelse
        </section>

        <section class="ota-mobile-customer__card">
            <h2 class="ota-mobile-customer__card-title">Updates</h2>
            @forelse($booking->communicationLogs->where('channel', 'email')->whereIn('event', $safeCommEvents)->sortByDesc('created_at') as $log)
                <p class="ota-mobile-customer__note">
                    <x-time.local :value="$log->created_at" context="public" :show-utc="false" /> — {{ str_replace('_', ' ', $log->event) }}
                </p>
            @empty
                <p class="ota-mobile-customer__note">No customer-facing updates yet.</p>
            @endforelse
        </section>

        <p class="ota-mobile-customer__footer-note">
            Need help?
            <a href="{{ route('customer.support.tickets.create', ['booking_id' => $booking->id]) }}" class="ota-mobile-customer__link">Contact support</a>
        </p>
    </div>
@endsection
