{{--
  dashboard/customer/dashboard.blade.php — Customer dashboard home (Phase 2 redesign).
  JETPK-DASHBOARD-UI-FOUNDATION · resolved via client_view('dashboard', 'customer').

  DATA CONTRACT (CustomerBookingController@dashboard — unchanged):
    $kpis, $recentBookings, $hasPendingPaymentBooking, $firstPendingPaymentBooking,
    $upcomingBooking, $upcomingCount, $supportTicketsCount
--}}
@extends(client_layout('customer-account', 'customer'))

@section('title', 'Dashboard')

@section('account_pretitle', 'Traveller')
@section('account_title', 'Dashboard')
@section('account_subtitle', 'Your bookings, payments, and travel documents in one place.')

@push('styles')
    <link rel="stylesheet" href="{{ ui_asset('css/ota-customer-dashboard.css') }}" />
@endpush

@section('account_content')
    @php
        $featured = $upcomingBooking ?? $recentBookings->first();
        $featuredLabel = $upcomingBooking !== null ? 'Upcoming trip' : 'Latest booking';

        $quickActions = array_values(array_filter([
            ['route' => 'flights.search', 'label' => 'Search flights', 'icon' => 'ti-plane-departure'],
            ['route' => 'customer.bookings.index', 'label' => 'My bookings', 'icon' => 'ti-calendar-event'],
            ['route' => 'booking.lookup', 'label' => 'Booking lookup', 'icon' => 'ti-search'],
            ['route' => 'customer.support.tickets.index', 'label' => 'Support', 'icon' => 'ti-headset'],
            ['route' => 'profile.edit', 'label' => 'Profile', 'icon' => 'ti-user-cog'],
        ], fn ($a) => \Illuminate\Support\Facades\Route::has($a['route'])));
    @endphp

    <div class="ota-customer-dashboard" data-testid="customer-dashboard">
        @if ($hasPendingPaymentBooking && $firstPendingPaymentBooking)
            <div class="ota-customer-dashboard__alert" role="status" data-testid="customer-dashboard-pending-alert">
                <div class="ota-customer-dashboard__alert-main">
                    <p class="ota-customer-dashboard__alert-title">Payment pending</p>
                    <p class="ota-customer-dashboard__alert-text">
                        Booking <strong class="ota-r-text-safe">{{ $firstPendingPaymentBooking->display_reference }}</strong> is awaiting payment.
                    </p>
                </div>
                <a href="{{ route('customer.bookings.show', $firstPendingPaymentBooking) }}" class="ota-account-btn ota-account-btn--primary ota-account-btn--sm">Complete payment</a>
            </div>
        @endif

        <div data-testid="customer-dashboard-kpis">
            <div class="ota-customer-dashboard__stats" data-testid="customer-dashboard-stats">
            <div class="ota-customer-dashboard__stat ota-customer-dashboard__stat--accent">
                <p class="ota-customer-dashboard__stat-label">Total bookings</p>
                <p class="ota-customer-dashboard__stat-value">{{ number_format((int) ($kpis['total'] ?? 0)) }}</p>
            </div>
            <div class="ota-customer-dashboard__stat ota-customer-dashboard__stat--amber">
                <p class="ota-customer-dashboard__stat-label">Pending payment</p>
                <p class="ota-customer-dashboard__stat-value">{{ number_format((int) ($kpis['pending_payment'] ?? 0)) }}</p>
            </div>
            <div class="ota-customer-dashboard__stat ota-customer-dashboard__stat--emerald">
                <p class="ota-customer-dashboard__stat-label">PNR confirmed</p>
                <p class="ota-customer-dashboard__stat-value">{{ number_format((int) ($kpis['pnr_confirmed'] ?? 0)) }}</p>
            </div>
            <div class="ota-customer-dashboard__stat ota-customer-dashboard__stat--violet">
                <p class="ota-customer-dashboard__stat-label">Cancellation activity</p>
                <p class="ota-customer-dashboard__stat-value">{{ number_format((int) ($kpis['cancellation_activity'] ?? 0)) }}</p>
            </div>
            </div>
        </div>

        <div class="ota-customer-dashboard__grid">
            <section class="ota-customer-dashboard__recent" aria-label="Recent bookings">
                <h2 class="ota-customer-dashboard__section-title">Recent bookings</h2>

                @if ($recentBookings->isEmpty())
                    <div class="ota-account-card">
                        <div class="ota-account-card__body">
                            <div class="ota-account-empty ota-account-empty--compact" data-testid="customer-recent-bookings-empty">
                                <div class="ota-account-empty-icon" aria-hidden="true"><i class="ti ti-ticket"></i></div>
                                <p class="ota-account-empty-title">No bookings yet</p>
                                <p class="ota-account-empty-help">Your recent and upcoming trips will appear here once you book.</p>
                                @if (\Illuminate\Support\Facades\Route::has('flights.search'))
                                    <div class="ota-account-empty-action">
                                        <a href="{{ route('flights.search') }}" class="ota-account-btn ota-account-btn--primary">Search flights</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="ota-account-card">
                        <div class="ota-account-card__body ota-account-card__body--flush">
                            <div class="ota-account-table-wrap">
                                <table class="ota-account-table mb-0" data-testid="customer-recent-bookings">
                                    <thead>
                                        <tr>
                                            <th>Reference</th>
                                            <th>Route</th>
                                            <th>Travel date</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($recentBookings as $booking)
                                            <tr>
                                                <td><strong class="ota-r-text-safe">{{ $booking->display_reference }}</strong></td>
                                                <td>{{ $booking->route ?? 'N/A' }}</td>
                                                <td>{{ $booking->travel_date?->format('j M Y') ?? 'N/A' }}</td>
                                                <td><x-dashboard.status-badge :status="$booking->status" /></td>
                                                <td class="text-end">
                                                    <div class="ota-portal-booking-actions--view-only">
                                                        <a href="{{ route('customer.bookings.show', $booking) }}" class="ota-account-btn ota-account-btn--primary ota-account-btn--sm">View</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <p class="ota-customer-dashboard__recent-foot">
                        <a href="{{ route('customer.bookings.index') }}">View all bookings &rarr;</a>
                    </p>
                @endif
            </section>

            <aside class="ota-customer-dashboard__aside" aria-label="Trip summary and quick actions">
                @if ($featured)
                    <div class="ota-customer-dashboard__highlight" data-testid="customer-dashboard-upcoming">
                        <p class="ota-customer-dashboard__highlight-eyebrow">{{ $featuredLabel }}</p>
                        <div class="ota-customer-dashboard__highlight-ref ota-r-text-safe">{{ $featured->display_reference }}</div>
                        <p class="ota-customer-dashboard__highlight-route">{{ $featured->route ?? 'N/A' }}</p>
                        <p class="ota-customer-dashboard__highlight-meta">Travel: {{ $featured->travel_date?->format('j M Y') ?? 'N/A' }}</p>
                        <x-dashboard.status-badge :status="$featured->status" />
                        <div class="ota-customer-dashboard__highlight-foot">
                            <a href="{{ route('customer.bookings.show', $featured) }}" class="ota-account-btn ota-account-btn--primary ota-account-btn--sm">View booking</a>
                            @if ($upcomingBooking !== null && (int) $upcomingCount > 1)
                                <span> &middot; {{ (int) $upcomingCount - 1 }} more upcoming</span>
                            @endif
                        </div>
                    </div>
                @endif

                <section class="ota-customer-dashboard__quick-wrap" aria-label="Quick actions" style="margin-top: var(--space-4, 16px);">
                    <h2 class="ota-customer-dashboard__section-title">Quick actions</h2>
                    <div class="ota-customer-dashboard__quick" data-testid="customer-dashboard-quick-actions">
                        @foreach ($quickActions as $action)
                            <a href="{{ route($action['route']) }}" class="ota-customer-dashboard__quick-card">
                                <span class="ota-customer-dashboard__quick-icon"><i class="ti {{ $action['icon'] }}" aria-hidden="true"></i></span>
                                <span class="ota-customer-dashboard__quick-label">{{ $action['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
