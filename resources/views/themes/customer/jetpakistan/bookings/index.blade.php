@extends(client_layout('customer-account', 'customer'))

@section('title', 'My bookings')

@section('content')
@include('themes.frontend.jetpakistan.components.portal.flash')

<div class="jp-portal-page-head">
    <div>
        <h1>My bookings</h1>
        <p>View and manage your flight requests and confirmations.</p>
    </div>
    <a href="{{ client_route('flights.search') }}" class="jp-portal-btn jp-portal-btn--primary jp-portal-btn--sm">Search flights</a>
</div>

@php
    $filters = [
        'all' => 'All',
        'pending_payment' => 'Pending payment',
        'pnr_created' => 'PNR created',
        'needs_action' => 'Needs action',
        'cancelled' => 'Cancelled',
    ];
@endphp

<div class="jp-portal-tabs" data-testid="customer-bookings-filters">
    @foreach ($filters as $key => $label)
        <a href="{{ client_route('customer.bookings.index', ['filter' => $key]) }}" @class(['is-active' => ($filter ?? 'all') === $key])>{{ $label }}</a>
    @endforeach
</div>

@if ($bookings->isEmpty())
    <div class="jp-portal-card">
        <div class="jp-portal-card__body">
            @include('themes.frontend.jetpakistan.components.portal.empty-state', [
                'title' => 'No bookings found',
                'message' => 'Try another filter or search for new flights.',
                'actionUrl' => client_route('flights.search'),
                'actionLabel' => 'Search flights',
            ])
        </div>
    </div>
@else
    <div class="jp-portal-card" data-testid="jp-customer-bookings-list">
        <div class="jp-portal-card__body jp-portal-card__body--flush">
            <div class="jp-portal-table-wrap">
                <table class="jp-portal-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Route</th>
                            <th>Travel date</th>
                            <th>Passengers</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $booking)
                            @php
                                $paymentOp = \App\Support\Bookings\PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'));
                                $paxCount = $booking->passengers_count ?? $booking->passengers?->count() ?? 0;
                            @endphp
                            <tr>
                                <td data-label="Reference"><span class="jp-portal-trip__ref">{{ $booking->display_reference }}</span></td>
                                <td data-label="Route">{{ $booking->route ?? '—' }}</td>
                                <td data-label="Travel">{{ $booking->travel_date?->format('j M Y') ?? '—' }}</td>
                                <td data-label="Passengers">{{ max(1, (int) $paxCount) }}</td>
                                <td data-label="Status">@include('themes.frontend.jetpakistan.components.portal.status-badge', ['label' => ucfirst(str_replace('_', ' ', $booking->status?->value ?? ''))])</td>
                                <td data-label="Payment">@include('themes.frontend.jetpakistan.components.portal.status-badge', ['label' => $paymentOp['label'], 'tone' => 'amber'])</td>
                                <td data-label="Actions"><a href="{{ client_route('customer.bookings.show', ['booking' => $booking]) }}" class="jp-portal-btn jp-portal-btn--ghost jp-portal-btn--sm">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($bookings instanceof \Illuminate\Contracts\Pagination\Paginator && $bookings->hasPages())
                <div class="jp-portal-pagination">{{ $bookings->links() }}</div>
            @endif
        </div>
    </div>
@endif
@endsection
