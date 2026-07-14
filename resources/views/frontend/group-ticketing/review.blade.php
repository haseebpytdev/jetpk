@extends(client_layout('frontend', 'frontend'))

@section('title', 'Review group booking')

@section('content')
    @php
        $checkoutSummary = is_array($checkoutSummary ?? null) ? $checkoutSummary : [];
        $paxCount = $booking->passengers->count();
    @endphp
    <div class="ota-rev-wrap ota-checkout-page ota-review-page ota-checkout-page--group">
        <div class="ota-container ota-container-wide">
            @include('frontend.checkout.partials.shell', [
                'productLabel' => 'Group Ticketing',
                'title' => 'Review your booking',
                'lead' => 'Reference: <strong>'.e($booking->reference).'</strong>',
                'activeStep' => $activeStep ?? 'review',
            ])

            @error('reservation')
                <div class="alert alert-warning">{{ $message }}</div>
            @enderror

            <div class="ota-checkout-grid ota-booking-layout">
                <div class="ota-checkout-main">
                    <div class="ota-checkout-card ota-review-flight-card">
                        <h2 class="ota-checkout-section-title">Group package summary</h2>
                        <div class="ota-review-flight">
                            <div class="ota-review-flight__header">
                                <div class="ota-review-flight__brand">
                                    @if (! empty($checkoutSummary['airline_logo_url']))
                                        <div class="ota-airline-logo ota-airline-logo--img"><img src="{{ $checkoutSummary['airline_logo_url'] }}" alt="{{ $checkoutSummary['airline_name'] ?? 'Airline' }} logo"></div>
                                    @elseif (! empty($checkoutSummary['airline_name']))
                                        <div class="ota-airline-logo">{{ strtoupper(substr($checkoutSummary['airline_name'], 0, 2)) }}</div>
                                    @endif
                                    <div>
                                        <div class="ota-airline-name">{{ e($checkoutSummary['airline_name'] ?? '') }}</div>
                                        <div class="ota-flight-no">{{ e($checkoutSummary['sector_code'] ?? '') }}</div>
                                    </div>
                                </div>
                                <div class="ota-review-flight__route-block">
                                    <span class="ota-review-flight__trip-type">Group Ticketing</span>
                                    <p class="ota-review-flight__route">{{ e($checkoutSummary['route_line'] ?? '') }}</p>
                                    @if (! empty($checkoutSummary['departure_date_short']))
                                        <p class="ota-review-flight__date">{{ e($checkoutSummary['departure_date_short']) }}</p>
                                    @endif
                                </div>
                            </div>
                            @if (! empty($checkoutSummary['baggage_display']))
                                <ul class="ota-review-flight__tags">
                                    <li><i class="fa fa-suitcase" aria-hidden="true"></i> {{ e($checkoutSummary['baggage_display']) }}</li>
                                    <li>{{ $booking->seat_count }} seat{{ $booking->seat_count === 1 ? '' : 's' }}</li>
                                </ul>
                            @endif
                        </div>
                    </div>

                    <div class="ota-checkout-card ota-review-pax-card">
                        <h2 class="ota-checkout-section-title">Passenger &amp; contact</h2>
                        <p class="ota-review-pax-summary">
                            {{ $paxCount }} {{ $paxCount === 1 ? 'passenger' : 'passengers' }}
                        </p>
                        <div class="ota-review-pax-grid">
                            @foreach ($booking->passengers as $idx => $passenger)
                                <article class="ota-review-pax-item">
                                    <header class="ota-review-pax-item__head">
                                        <span class="ota-review-pax-item__index">Passenger {{ $idx + 1 }}</span>
                                        <span class="ota-review-pax-item__type">{{ ucfirst((string) ($passenger->passenger_type ?? 'adult')) }}</span>
                                    </header>
                                    <dl class="ota-review-pax-dl">
                                        <div class="ota-review-pax-dl__row">
                                            <dt>Passenger</dt>
                                            <dd>{{ e($passenger->fullName()) }}</dd>
                                        </div>
                                        @if ($passenger->date_of_birth)
                                            <div class="ota-review-pax-dl__row">
                                                <dt>Date of birth</dt>
                                                <dd>{{ $passenger->date_of_birth->format('j M Y') }}</dd>
                                            </div>
                                        @endif
                                        @if ($passenger->nationality)
                                            <div class="ota-review-pax-dl__row">
                                                <dt>Nationality</dt>
                                                <dd>{{ e($passenger->nationality) }}</dd>
                                            </div>
                                        @endif
                                        @if ($passenger->passport_number)
                                            <div class="ota-review-pax-dl__row">
                                                <dt>{{ ($passenger->document_type ?? '') === 'national_id' ? 'ID' : 'Passport' }}</dt>
                                                <dd>
                                                    {{ e($passenger->passport_number) }}
                                                    @if ($passenger->passport_expiry)
                                                        · expires {{ $passenger->passport_expiry->format('j M Y') }}
                                                    @endif
                                                </dd>
                                            </div>
                                        @endif
                                    </dl>
                                </article>
                            @endforeach

                            <section class="ota-review-contact">
                                <h3 class="ota-review-contact__title">Contact details</h3>
                                <dl class="ota-review-pax-dl">
                                    <div class="ota-review-pax-dl__row">
                                        <dt>Contact name</dt>
                                        <dd>{{ e($booking->contact_name ?? '—') }}</dd>
                                    </div>
                                    <div class="ota-review-pax-dl__row">
                                        <dt>Email</dt>
                                        <dd>{{ e($booking->contact_email ?? '—') }}</dd>
                                    </div>
                                    <div class="ota-review-pax-dl__row">
                                        <dt>Phone</dt>
                                        <dd>{{ e($booking->contact_phone ?? '—') }}</dd>
                                    </div>
                                </dl>
                            </section>
                        </div>
                    </div>

                    <p class="ota-checkout-disclaimer ota-review-submit-note">
                        Your seats will be reserved for {{ $holdMinutes }} minutes after confirmation.
                    </p>

                    <form method="POST" action="{{ route('group-ticketing.booking.review.confirm', $booking) }}" class="ota-checkout-form">
                        @csrf
                        <div class="ota-review-total-hero" aria-live="polite">
                            <span class="ota-review-total-hero__label">Amount due ({{ e($booking->currency) }})</span>
                            <span class="ota-review-total-hero__value">{{ e($booking->currency) }} {{ number_format((float) $booking->total_amount, 0) }}</span>
                        </div>
                        <button type="submit" class="ota-btn-primary-lg btn btn-lg btn-block ota-review-submit-btn">Confirm reservation</button>
                        <p class="ota-checkout-disclaimer ota-review-submit-note">No payment is taken on this step.</p>
                        <a href="{{ client_route('group-ticketing.show', ['inventory' => $booking->inventory->public_id ?: $booking->inventory_id]) }}" class="ota-btn ota-btn-secondary btn btn-block mt-2">Back</a>
                    </form>
                </div>

                @include('frontend.checkout.partials.summary-card', [
                    'summary' => $checkoutSummary,
                    'seatCount' => $booking->seat_count,
                    'totalAmount' => (float) $booking->total_amount,
                    'showPayNote' => true,
                ])
            </div>
        </div>
    </div>
@endsection
