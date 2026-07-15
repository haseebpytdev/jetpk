@extends(client_layout('agent-portal', 'agent'))

@section('title', 'My bookings')

@section('content')
@include('themes.frontend.jetpakistan.components.portal.flash')

<x-dashboard.breadcrumbs :items="[
    ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
    ['label' => 'My bookings'],
]" />

<div class="jp-portal-page-head">
    <div>
        <h1>My bookings</h1>
        <p>Filter by status and take action on your agency bookings.</p>
    </div>
    @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::BookingsCreate))
        <a href="{{ client_route('agent.bookings.create') }}" class="jp-portal-btn jp-portal-btn--primary jp-portal-btn--sm">New booking</a>
    @endif
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

<div class="jp-portal-tabs" data-testid="agent-bookings-filters">
    @foreach ($filters as $key => $label)
        <a href="{{ client_route('agent.bookings.index', ['filter' => $key]) }}" @class(['is-active' => ($filter ?? 'all') === $key])>{{ $label }}</a>
    @endforeach
</div>

<div class="jp-portal-card">
    <div class="jp-portal-card__body jp-portal-card__body--flush">
        <div class="jp-portal-table-wrap">
            <table class="jp-portal-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Customer</th>
                        <th>Route</th>
                        <th>Travel</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>PNR</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        @php
                            $pax = $booking->passengers->first();
                            $customer = trim(implode(' ', array_filter([$pax?->title, $pax?->first_name, $pax?->last_name]))) ?: ($booking->contact?->email ?? '—');
                            $paymentOp = \App\Support\Bookings\PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'));
                        @endphp
                        <tr>
                            <td data-label="Reference"><span class="jp-portal-trip__ref">{{ $booking->display_reference }}</span></td>
                            <td data-label="Customer">{{ $customer }}</td>
                            <td data-label="Route">{{ $booking->route ?? '—' }}</td>
                            <td data-label="Travel">{{ $booking->travel_date?->format('j M Y') ?? '—' }}</td>
                            <td data-label="Total" class="num">Rs {{ number_format((float) ($booking->fareBreakdown?->total ?? 0), 0) }}</td>
                            <td data-label="Status">@include('themes.frontend.jetpakistan.components.portal.status-badge', ['label' => ucfirst(str_replace('_', ' ', $booking->status?->value ?? ''))])</td>
                            <td data-label="Payment">@include('themes.frontend.jetpakistan.components.portal.status-badge', ['label' => $paymentOp['label'], 'tone' => 'amber'])</td>
                            <td data-label="PNR">{{ filled($booking->pnr) ? $booking->pnr : '—' }}</td>
                            <td data-label="Actions"><a href="{{ client_route('agent.bookings.show', ['booking' => $booking]) }}" class="jp-portal-btn jp-portal-btn--ghost jp-portal-btn--sm">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9">@include('themes.frontend.jetpakistan.components.portal.empty-state', ['title' => 'No bookings found', 'message' => 'Try another filter or create a new booking.'])</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bookings instanceof \Illuminate\Contracts\Pagination\Paginator && $bookings->hasPages())
            <div style="padding:var(--sp-4)">{{ $bookings->links() }}</div>
        @endif
    </div>
</div>
@endsection
