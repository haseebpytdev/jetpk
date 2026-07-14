@extends(client_layout('customer-account', 'customer'))

@section('title', 'Dashboard')

@section('account_content')
    @php
        $customerName = trim((string) (auth()->user()?->name ?? 'Traveler'));
        $firstName = strtok($customerName !== '' ? $customerName : 'Traveler', ' ') ?: 'Traveler';
        $paymentProofHref = ($hasPendingPaymentBooking && $firstPendingPaymentBooking)
            ? route('customer.bookings.show', $firstPendingPaymentBooking).'#payment'
            : route('customer.bookings.index', ['filter' => 'pending_payment']);
        $homepageSections = $publicBranding['sections'] ?? collect();
        $homepageHeroSection = $homepageSections instanceof \Illuminate\Support\Collection
            ? $homepageSections->get('hero')
            : ($homepageSections['hero'] ?? null);
        $dashboardHero = app(\App\Services\Agencies\HomepageSectionPresenter::class)->presentHero(
            $homepageHeroSection,
            $agencySettings ?? null,
            config('ota-brand', []),
        );
        $dashboardHeroBg = $dashboardHero['background_url'] ?? null;
        $dashboardHeroBgStyle = filled($dashboardHeroBg)
            ? "--ota-hero-bg-image: url('".e($dashboardHeroBg)."')"
            : '';
    @endphp

    <section
        @class([
            'ota-dashboard-hero',
            'ota-dashboard-hero--homepage-image' => filled($dashboardHeroBg),
        ])
        data-testid="customer-dashboard-hero"
        @if($dashboardHeroBgStyle !== '') style="{{ $dashboardHeroBgStyle }}" @endif
    >
        <div class="ota-dashboard-hero__content">
            <span class="ota-dashboard-hero__badge">Your Journey, Our Priority</span>
            <h1>Welcome back, {{ $firstName }}</h1>
            <p>Manage your flights, travelers, payments, and support requests in one place.</p>
            <div class="ota-dashboard-hero__chips" aria-label="Customer benefits">
                <span><i class="ti ti-rosette-discount-check" aria-hidden="true"></i> Best Price Guarantee</span>
                <span><i class="ti ti-headset" aria-hidden="true"></i> 24/7 Support</span>
                <span><i class="ti ti-shield-check" aria-hidden="true"></i> Secure Bookings</span>
            </div>
        </div>
        @unless (filled($dashboardHeroBg))
            <div class="ota-dashboard-hero__visual" aria-hidden="true">
                <span class="ota-dashboard-hero__globe"><i class="ti ti-world"></i></span>
                <span class="ota-dashboard-hero__plane"><i class="ti ti-plane"></i></span>
                <span class="ota-dashboard-hero__cloud ota-dashboard-hero__cloud--one"><i class="ti ti-cloud"></i></span>
                <span class="ota-dashboard-hero__cloud ota-dashboard-hero__cloud--two"><i class="ti ti-cloud"></i></span>
            </div>
        @endunless
    </section>

    <section class="ota-dashboard-actions" aria-label="Quick actions">
        <a href="{{ route('flights.search') }}" class="ota-dashboard-action">
            <span class="ota-dashboard-action__icon"><i class="ti ti-search" aria-hidden="true"></i></span>
            <span><strong>Search Flights</strong><small>Find best fares</small></span>
        </a>
        <a href="{{ route('customer.bookings.index') }}" class="ota-dashboard-action">
            <span class="ota-dashboard-action__icon"><i class="ti ti-ticket" aria-hidden="true"></i></span>
            <span><strong>My Bookings</strong><small>View or change trips</small></span>
        </a>
        <a href="{{ $paymentProofHref }}" class="ota-dashboard-action">
            <span class="ota-dashboard-action__icon"><i class="ti ti-upload" aria-hidden="true"></i></span>
            <span><strong>Upload Payment Proof</strong><small>{{ $hasPendingPaymentBooking ? 'Verify pending payment' : 'Open pending payments' }}</small></span>
        </a>
        <a href="{{ route('customer.support.tickets.index') }}" class="ota-dashboard-action">
            <span class="ota-dashboard-action__icon"><i class="ti ti-headset" aria-hidden="true"></i></span>
            <span><strong>Contact Support</strong><small>Get booking help</small></span>
        </a>
    </section>

    <section class="ota-dashboard-kpis" data-testid="customer-dashboard-kpis">
        <div class="ota-dashboard-kpi">
            <span class="ota-dashboard-kpi__icon"><i class="ti ti-calendar-event" aria-hidden="true"></i></span>
            <span class="ota-dashboard-kpi__label">Total Bookings</span>
            <strong>{{ number_format((int) $kpis['total']) }}</strong>
            <a href="{{ route('customer.bookings.index') }}">View all bookings</a>
        </div>
        <div class="ota-dashboard-kpi ota-dashboard-kpi--amber">
            <span class="ota-dashboard-kpi__icon"><i class="ti ti-wallet" aria-hidden="true"></i></span>
            <span class="ota-dashboard-kpi__label">Pending Payment</span>
            <strong>{{ number_format((int) $kpis['pending_payment']) }}</strong>
            <a href="{{ route('customer.bookings.index', ['filter' => 'pending_payment']) }}">View pending payments</a>
        </div>
        <div class="ota-dashboard-kpi ota-dashboard-kpi--violet">
            <span class="ota-dashboard-kpi__icon"><i class="ti ti-circle-check" aria-hidden="true"></i></span>
            <span class="ota-dashboard-kpi__label">PNR Confirmed</span>
            <strong>{{ number_format((int) $kpis['pnr_confirmed']) }}</strong>
            <a href="{{ route('customer.bookings.index', ['filter' => 'pnr_created']) }}">View confirmed PNRs</a>
        </div>
        <div class="ota-dashboard-kpi ota-dashboard-kpi--emerald">
            <span class="ota-dashboard-kpi__icon"><i class="ti ti-refresh" aria-hidden="true"></i></span>
            <span class="ota-dashboard-kpi__label">Cancellation / Refund</span>
            <strong>{{ number_format((int) $kpis['cancellation_activity']) }}</strong>
            <a href="{{ route('customer.support.tickets.index') }}">View all requests</a>
        </div>
    </section>

    <section class="ota-dashboard-content-grid">
        <div class="ota-account-card">
            <div class="ota-account-card__head">
                <div>
                    <h2 class="ota-account-card__title">Recent Bookings</h2>
                    <p class="ota-account-card__lead">Your latest trips linked to this account.</p>
                </div>
                <a href="{{ route('customer.bookings.index') }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">View all bookings</a>
            </div>
            @if ($recentBookings->isEmpty())
                <div class="ota-account-card__body">
                    <div class="ota-account-empty" data-testid="customer-recent-bookings-empty">
                        <div class="ota-account-empty-icon" aria-hidden="true"><i class="ti ti-ticket"></i></div>
                        <p class="ota-account-empty-title">No bookings yet</p>
                        <p class="ota-account-empty-help">Search flights and complete checkout to see trips here.</p>
                        <div class="ota-account-empty-action">
                            <a href="{{ route('flights.search') }}" class="ota-account-btn ota-account-btn--primary">Search flights</a>
                        </div>
                    </div>
                </div>
            @else
                <div class="ota-account-card__body ota-account-card__body--flush">
                    <div class="ota-account-table-wrap">
                        <table class="ota-account-table ota-account-table--dashboard mb-0" data-testid="customer-recent-bookings">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Route</th>
                                    <th>Travel date</th>
                                    <th>Payment</th>
                                    <th>PNR / supplier</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentBookings as $booking)
                                    @php
                                        $hasPnr = filled($booking->pnr);
                                        $meta = is_array($booking->meta) ? $booking->meta : [];
                                        $supplierOp = \App\Support\Bookings\SupplierOperationalStatus::fromValues(
                                            (string) ($booking->supplier_booking_status ?? 'not_started'),
                                            (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? '')),
                                            $hasPnr,
                                            $meta,
                                        );
                                        $paymentOp = \App\Support\Bookings\PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'));
                                    @endphp
                                    <tr class="ota-dashboard-booking-row">
                                        <td class="fw-semibold ota-r-text-safe" data-label="Reference">
                                            <a href="{{ route('customer.bookings.show', $booking) }}" class="ota-dashboard-booking-link" aria-label="View booking {{ $booking->display_reference }}">
                                                {{ $booking->display_reference }}
                                            </a>
                                        </td>
                                        <td data-label="Route">
                                            <a href="{{ route('customer.bookings.show', $booking) }}" class="ota-dashboard-booking-link">
                                                {{ $booking->route ?? 'N/A' }}
                                            </a>
                                        </td>
                                        <td data-label="Travel date">
                                            <a href="{{ route('customer.bookings.show', $booking) }}" class="ota-dashboard-booking-link">
                                                {{ $booking->travel_date?->format('j M Y') ?? 'N/A' }}
                                            </a>
                                        </td>
                                        <td data-label="Payment">
                                            <a href="{{ route('customer.bookings.show', $booking) }}" class="ota-dashboard-booking-link">
                                                <span class="text-capitalize">{{ $paymentOp['label'] }}</span>
                                            </a>
                                        </td>
                                        <td class="ota-account-table__pnr" data-testid="customer-dashboard-pnr-cell" data-label="PNR / supplier">
                                            <a href="{{ route('customer.bookings.show', $booking) }}" class="ota-dashboard-booking-link">
                                                @if ($hasPnr)
                                                    <span class="ota-r-text-safe">{{ $booking->pnr }}</span>
                                                @else
                                                    <span class="text-secondary">{{ $supplierOp['label'] }}</span>
                                                @endif
                                            </a>
                                        </td>
                                        <td data-label="Status">
                                            <a href="{{ route('customer.bookings.show', $booking) }}" class="ota-dashboard-booking-link">
                                                <x-dashboard.status-badge :status="$booking->status" />
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <aside class="ota-dashboard-help-card">
            <span class="ota-dashboard-help-card__icon"><i class="ti ti-headset" aria-hidden="true"></i></span>
            <h2>We're here to help</h2>
            <p>Our travel experts are available for booking, payment, and itinerary support.</p>
            <ul>
                <li><i class="ti ti-circle-check" aria-hidden="true"></i> Booking support</li>
                <li><i class="ti ti-circle-check" aria-hidden="true"></i> Payment/refund help</li>
                <li><i class="ti ti-circle-check" aria-hidden="true"></i> Flight changes and cancellations</li>
            </ul>
            <a href="{{ route('customer.support.tickets.index') }}" class="ota-account-btn ota-account-btn--primary ota-account-btn--block">Contact Support</a>
        </aside>
    </section>
@endsection
