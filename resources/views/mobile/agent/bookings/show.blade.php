@extends('layouts.mobile-app')

@section('title', 'Booking '.($booking->booking_reference ?? '#'.$booking->id))

@section('mobile_app_title', 'Booking details')

@section('mobile_app_back')
    <a href="{{ route('agent.bookings.index') }}" class="ota-mobile-app__back-btn" aria-label="Back to bookings">
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
        $pax = $booking->passengers->first();
        $contact = $booking->contact;
        $fare = $booking->fareBreakdown;
    @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-booking-show">
        <header class="ota-mobile-agent__hero">
            <p class="ota-mobile-agent__hero-ref">{{ $booking->booking_reference ?? 'Draft' }}</p>
            <h1 class="ota-mobile-agent__hero-route">{{ $booking->route ?? 'Trip details' }}</h1>
            <div class="ota-mobile-agent__hero-pills">
                @include('mobile.agent.partials.agent-status-pill', ['status' => $booking->status])
                @include('mobile.agent.partials.agent-status-pill', ['label' => $paymentOp['label'] ?? 'Payment'])
            </div>
        </header>

        @if (session('status') === 'booking-request-created')
            @include('mobile.components.alert', ['type' => 'success', 'message' => 'Booking request submitted. Our team will review fare and proceed with supplier booking.'])
        @elseif (session('status'))
            @include('mobile.components.alert', ['type' => 'success', 'message' => session('status')])
        @endif

        @if ($errors->any())
            @include('mobile.components.alert', ['type' => 'danger', 'message' => $errors->first()])
        @endif

        <section class="ota-mobile-agent__card">
            <h2 class="ota-mobile-agent__card-title">Trip summary</h2>
            <dl class="ota-mobile-agent__meta">
                <div>
                    <dt>Route</dt>
                    <dd>{{ $booking->route ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Airline</dt>
                    <dd>{{ $booking->airline ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Travel date</dt>
                    <dd>{{ $booking->travel_date?->format('j M Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Source</dt>
                    <dd>{{ str_replace('_', ' ', (string) ($booking->source_channel ?? 'agent_portal')) }}</dd>
                </div>
            </dl>
        </section>

        <section class="ota-mobile-agent__card">
            <div class="ota-mobile-agent__card-head">
                <h2 class="ota-mobile-agent__card-title">Itinerary</h2>
                @if ($itinerary)
                    <span class="ota-mobile-agent__muted">{{ $itinerary['itinerary_source_label'] ?? '' }}</span>
                @endif
            </div>
            @if ($itinerary && ($itinerary['show_snapshot_itinerary_warning'] ?? false))
                @include('mobile.components.alert', ['type' => 'info', 'message' => 'Search/checkout snapshot — final airline itinerary not yet synced.'])
            @endif
            @if ($itinerary && ! empty($itinerary['segment_lines']))
                <p class="ota-mobile-agent__note">{{ $itinerary['journey_od'] ?? '' }} · {{ $itinerary['stops_label'] ?? '' }}</p>
                @foreach ($itinerary['segment_lines'] as $line)
                    <p class="ota-mobile-agent__itinerary-line {{ str_starts_with($line, '   —') ? 'is-sub' : '' }}">{{ $line }}</p>
                @endforeach
            @else
                <p class="ota-mobile-agent__note">Itinerary details will appear when flight segments are available.</p>
            @endif
        </section>

        <section class="ota-mobile-agent__card">
            <h2 class="ota-mobile-agent__card-title">Passengers and contact</h2>
            @foreach ($booking->passengers->sortBy('passenger_index') as $passenger)
                <div class="ota-mobile-agent__passenger">
                    <p class="ota-mobile-agent__passenger-name">
                        {{ trim(implode(' ', array_filter([$passenger->title, $passenger->first_name, $passenger->last_name]))) ?: '—' }}
                    </p>
                </div>
            @endforeach
            <dl class="ota-mobile-agent__meta">
                <div>
                    <dt>Email</dt>
                    <dd class="ota-mobile-agent__text-safe">{{ $contact?->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Phone</dt>
                    <dd>{{ $contact?->phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Country</dt>
                    <dd>{{ $contact?->country ?? '—' }}</dd>
                </div>
            </dl>
            @if (filled($booking->notes))
                <p class="ota-mobile-agent__note"><strong>Note:</strong> {{ $booking->notes }}</p>
            @endif
        </section>

        <section class="ota-mobile-agent__card">
            <h2 class="ota-mobile-agent__card-title">Fare breakdown</h2>
            <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                <div><dt>Base fare</dt><dd class="ota-mobile-agent__amount">Rs {{ number_format((float) ($fare?->base_fare ?? 0), 0) }}</dd></div>
                <div><dt>Taxes</dt><dd class="ota-mobile-agent__amount">Rs {{ number_format((float) ($fare?->taxes ?? 0), 0) }}</dd></div>
                <div><dt>Fees</dt><dd class="ota-mobile-agent__amount">Rs {{ number_format((float) ($fare?->fees ?? 0), 0) }}</dd></div>
                <div><dt>Markup</dt><dd class="ota-mobile-agent__amount">Rs {{ number_format((float) ($fare?->markup ?? 0), 0) }}</dd></div>
                <div><dt>Total</dt><dd class="ota-mobile-agent__amount ota-mobile-agent__amount--total">Rs {{ number_format((float) ($fare?->total ?? 0), 0) }}</dd></div>
            </dl>
            @if (! empty($paymentOp['meaning']))
                <p class="ota-mobile-agent__note">{{ $paymentOp['meaning'] }}</p>
            @endif
        </section>

        @include('mobile.agent.partials.payment-summary-card', [
            'booking' => $booking,
            'summary' => $paymentSummary,
        ])

        <section class="ota-mobile-agent__card">
            <h2 class="ota-mobile-agent__card-title">PNR and ticketing</h2>
            <p class="ota-mobile-agent__note"><strong>PNR:</strong> {{ $booking->pnr ?? 'Not yet assigned' }}</p>
            @if (! empty($supplierOp['meaning']))
                <p class="ota-mobile-agent__note">{{ $supplierOp['meaning'] }}</p>
            @endif
            @if (! empty($ticketingOp['meaning']))
                <p class="ota-mobile-agent__note">{{ $ticketingOp['meaning'] }}</p>
            @endif
            @foreach ($booking->tickets as $ticket)
                <p class="ota-mobile-agent__note">{{ $ticket->ticket_number }} — {{ $ticket->airline_code ?? 'N/A' }}</p>
            @endforeach
        </section>

        @if (auth()->user()?->isAgentAdmin())
            <section class="ota-mobile-agent__card" data-testid="agent-booking-commission">
                <h2 class="ota-mobile-agent__card-title">Your commission</h2>
                @if ($commissionEntry ?? null)
                    <dl class="ota-mobile-agent__meta">
                        <div><dt>Status</dt><dd>{{ ucfirst($commissionEntry->status->value) }}</dd></div>
                        <div><dt>Amount</dt><dd class="ota-mobile-agent__amount">Rs {{ number_format((float) $commissionEntry->commission_amount, 2) }}</dd></div>
                    </dl>
                    @if ($commissionEntry->description)
                        <p class="ota-mobile-agent__note">{{ $commissionEntry->description }}</p>
                    @endif
                    <a href="{{ route('agent.commissions.index') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary">View all commissions</a>
                @else
                    <p class="ota-mobile-agent__note">Commission is recorded after ticketing. Check back once tickets are issued.</p>
                @endif
            </section>
        @endif

        <section class="ota-mobile-agent__card">
            <h2 class="ota-mobile-agent__card-title">Status timeline</h2>
            @forelse ($booking->statusLogs as $log)
                <div class="ota-mobile-agent__timeline-item">
                    <p class="ota-mobile-agent__timeline-label">
                        {{ str_replace('_', ' ', (string) ($log->from_status ?? 'draft')) }}
                        →
                        {{ str_replace('_', ' ', $log->to_status) }}
                    </p>
                    <p class="ota-mobile-agent__muted">{{ $log->created_at?->format('j M Y, g:i A') }}</p>
                </div>
            @empty
                <p class="ota-mobile-agent__note">No status events yet.</p>
            @endforelse
        </section>

        @can('request', [\App\Models\BookingCancellationRequest::class, $booking])
            <section class="ota-mobile-agent__card" id="cancellation">
                <h2 class="ota-mobile-agent__card-title">Cancellation request</h2>
                @if ($booking->status->value !== 'cancelled')
                    @include('mobile.components.alert', ['type' => 'info', 'message' => 'Ticketed bookings require manual supplier void/refund handling.'])
                    <form method="post" action="{{ route('agent.bookings.cancellations.store', $booking) }}" class="ota-mobile-agent__form">
                        @csrf
                        <div class="ota-mobile-agent__field">
                            <label class="ota-mobile-agent__label" for="cancellation_type">Type</label>
                            <select name="cancellation_type" id="cancellation_type" class="ota-mobile-agent__input" required>
                                @foreach (['booking_cancel', 'ticket_void', 'ticket_refund', 'supplier_cancel'] as $type)
                                    <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ota-mobile-agent__field">
                            <label class="ota-mobile-agent__label" for="cancel_reason">Reason (optional)</label>
                            <textarea name="reason" id="cancel_reason" rows="2" class="ota-mobile-agent__input"></textarea>
                        </div>
                        <button class="ota-mobile-agent__btn ota-mobile-agent__btn--danger ota-mobile-agent__btn--block" type="submit">Request cancellation</button>
                    </form>
                @endif
                @forelse ($booking->cancellationRequests->sortByDesc('created_at')->take(5) as $cancelReq)
                    <div class="ota-mobile-agent__cancel-row">
                        <span>{{ $cancelReq->cancellation_type->value }}</span>
                        <span class="ota-mobile-agent__muted">{{ $cancelReq->status->value }}</span>
                        <span class="ota-mobile-agent__note">{{ $cancelReq->created_at?->format('j M Y') }}</span>
                    </div>
                @empty
                    <p class="ota-mobile-agent__note">No cancellation requests yet.</p>
                @endforelse
            </section>
        @endcan

        @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::SupportManage))
            <p class="ota-mobile-agent__footer-note">
                Need help?
                <a href="{{ route('agent.support.tickets.create', ['booking_id' => $booking->id]) }}" class="ota-mobile-agent__link">Contact support</a>
            </p>
        @endif
    </div>
@endsection
