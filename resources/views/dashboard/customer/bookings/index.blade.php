@extends(client_layout('customer-account', 'customer'))

@section('title', 'My bookings')

@section('account_title', 'My bookings')
@section('account_subtitle', 'View and manage your flight requests and confirmations.')

@section('account_content')
    @php
        $filters = [
            'all' => 'All',
            'pending_payment' => 'Pending payment',
            'pnr_created' => 'PNR created',
            'needs_action' => 'Needs action',
            'cancelled' => 'Cancelled',
        ];
    @endphp

    <nav class="ota-account-filter-tabs" aria-label="Booking filters" data-testid="customer-bookings-filters">
        @foreach ($filters as $key => $label)
            <a
                href="{{ route('customer.bookings.index', ['filter' => $key]) }}"
                class="ota-account-filter-tabs__link {{ ($filter ?? 'all') === $key ? 'is-active' : '' }}"
            >{{ $label }}</a>
        @endforeach
    </nav>

    @if ($bookings->isEmpty())
        <div class="ota-account-card">
            <div class="ota-account-card__body">
                <div class="ota-account-empty ota-account-empty--compact">
                    <div class="ota-account-empty-icon" aria-hidden="true"><i class="ti ti-ticket"></i></div>
                    <p class="ota-account-empty-title">No bookings found for this filter</p>
                    <p class="ota-account-empty-help">Try another filter or search for new flights.</p>
                    <div class="ota-account-empty-action">
                        <a href="{{ route('flights.search') }}" class="ota-account-btn ota-account-btn--primary">Search flights</a>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="ota-account-card">
            <div class="ota-account-card__body ota-account-card__body--flush">
                <div class="ota-account-table-wrap ota-account-table--desktop">
                    <table class="ota-account-table mb-0">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Route</th>
                                <th>Travel date</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bookings as $booking)
                                @php
                                    $paymentOp = \App\Support\Bookings\PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'));
                                    $paymentBadgeClass = match ((string) ($booking->payment_status ?? 'unpaid')) {
                                        'paid', 'verified' => 'ota-account-badge--success',
                                        'partial', 'pending', 'submitted' => 'ota-account-badge--warning',
                                        'refunded' => 'ota-account-badge--info',
                                        default => 'ota-account-badge--muted',
                                    };
                                    $needsPayment = in_array((string) ($booking->payment_status ?? 'unpaid'), ['unpaid', 'partial'], true)
                                        || (float) ($booking->balance_due ?? 0) > 0;
                                    $invoiceDoc = $booking->documents->first(
                                        fn ($d) => $d->document_type->value === 'invoice' && $d->status->value === 'generated' && $d->file_path !== null
                                    );
                                @endphp
                                <tr>
                                    <td><strong class="ota-r-text-safe">{{ $booking->display_reference }}</strong></td>
                                    <td>{{ $booking->route ?? 'N/A' }}</td>
                                    <td>{{ $booking->travel_date?->format('j M Y') ?? 'N/A' }}</td>
                                    <td><x-dashboard.status-badge :status="$booking->status" /></td>
                                    <td><span class="ota-account-badge {{ $paymentBadgeClass }} text-capitalize">{{ $paymentOp['label'] }}</span></td>
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

                <div class="ota-account-list ota-account-list--mobile">
                    @foreach ($bookings as $booking)
                        @php
                            $paymentOp = \App\Support\Bookings\PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'));
                            $paymentBadgeClass = match ((string) ($booking->payment_status ?? 'unpaid')) {
                                'paid', 'verified' => 'ota-account-badge--success',
                                'partial', 'pending', 'submitted' => 'ota-account-badge--warning',
                                'refunded' => 'ota-account-badge--info',
                                default => 'ota-account-badge--muted',
                            };
                            $needsPayment = in_array((string) ($booking->payment_status ?? 'unpaid'), ['unpaid', 'partial'], true)
                                || (float) ($booking->balance_due ?? 0) > 0;
                            $invoiceDoc = $booking->documents->first(
                                fn ($d) => $d->document_type->value === 'invoice' && $d->status->value === 'generated' && $d->file_path !== null
                            );
                        @endphp
                        <article class="ota-account-list-card">
                            <div class="ota-account-list-card__head">
                                <span class="ota-account-list-card__ref ota-r-text-safe">{{ $booking->display_reference }}</span>
                                <x-dashboard.status-badge :status="$booking->status" />
                            </div>
                            <div class="ota-account-list-card__meta">
                                <span>{{ $booking->route ?? 'N/A' }}</span>
                                <span>Travel: {{ $booking->travel_date?->format('j M Y') ?? 'N/A' }}</span>
                                <span>Payment: <span class="ota-account-badge {{ $paymentBadgeClass }} text-capitalize">{{ $paymentOp['label'] }}</span></span>
                            </div>
                            <div class="ota-account-list-card__actions ota-portal-booking-actions--view-only">
                                <a href="{{ route('customer.bookings.show', $booking) }}" class="ota-account-btn ota-account-btn--primary ota-account-btn--sm">View booking</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="ota-account-pagination">{{ $bookings->links() }}</div>
    @endif
@endsection
