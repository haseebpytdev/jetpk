@extends(client_layout('agent-portal', 'agent'))

@section('title', 'My Bookings')

@section('account_title', 'My bookings')
@section('account_subtitle', 'Filter by status and take action on your booking requests.')

@section('account_actions')
    @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::BookingsCreate))
        <a href="{{ route('agent.bookings.create') }}" class="ota-account-btn ota-account-btn--primary" data-testid="agent-bookings-create-link">New flight booking</a>
    @endif
@endsection

@section('account_content')
    @php
        $canCreateBookings = auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::BookingsCreate) ?? false;
        $canUploadPayments = auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::PaymentsUpload) ?? false;
        $canViewCommissions = auth()->user()?->isAgentAdmin() ?? false;
        $filters = [
            'all' => 'All',
            'pending_payment' => 'Pending payment',
            'pnr_created' => 'PNR created',
            'needs_action' => 'Needs action',
            'cancelled' => 'Cancelled',
        ];
    @endphp

    <div class="ota-account-toolbar mb-3" data-testid="agent-bookings-filters">
        @foreach ($filters as $key => $label)
            <a
                href="{{ route('agent.bookings.index', ['filter' => $key]) }}"
                class="ota-account-btn ota-account-btn--sm {{ ($filter ?? 'all') === $key ? 'ota-account-btn--primary' : 'ota-account-btn--secondary' }}"
            >{{ $label }}</a>
        @endforeach
    </div>

    <div class="ota-account-card">
        <div class="ota-account-card__body ota-account-card__body--flush">
            <div class="ota-account-table-wrap">
            <table class="ota-account-table mb-0">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Customer</th>
                        <th>Route</th>
                        <th>Travel date</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>PNR / supplier</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        @php
                            $pax = $booking->passengers->first();
                            $customer = trim(implode(' ', array_filter([$pax?->title, $pax?->first_name, $pax?->last_name]))) ?: display_unknown($booking->contact?->email);
                            $hasPnr = filled($booking->pnr);
                            $meta = is_array($booking->meta) ? $booking->meta : [];
                            $paymentOp = \App\Support\Bookings\PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'));
                            $supplierOp = \App\Support\Bookings\SupplierOperationalStatus::fromValues(
                                (string) ($booking->supplier_booking_status ?? 'not_started'),
                                (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? '')),
                                $hasPnr,
                                $meta,
                            );
                            $needsPayment = in_array((string) ($booking->payment_status ?? 'unpaid'), ['unpaid', 'partial'], true)
                                || (float) ($booking->balance_due ?? 0) > 0;
                            $commissionEntry = $booking->commissionEntries->sortByDesc('created_at')->first();
                        @endphp
                        <tr>
                            <td class="fw-semibold text-secondary ota-r-text-safe">{{ $booking->display_reference }}</td>
                            <td>{{ $customer }}</td>
                            <td>{{ display_unknown($booking->route) }}</td>
                            <td>{{ $booking->travel_date?->format('j M Y') ?? display_unknown() }}</td>
                            <td class="text-end fw-semibold">Rs {{ number_format((float) ($booking->fareBreakdown?->total ?? 0), 0) }}</td>
                            <td><x-dashboard.status-badge :status="$booking->status" /></td>
                            <td><span class="small">{{ $paymentOp['label'] }}</span></td>
                            <td>
                                @if ($hasPnr)
                                    <span class="small">PNR {{ $booking->pnr }}</span>
                                @else
                                    <span class="small text-secondary">{{ $supplierOp['label'] }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="ota-portal-booking-actions--view-only">
                                    <a class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm" href="{{ route('agent.bookings.show', $booking) }}">View</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-secondary py-4">
                                No bookings found for this filter.
                                @if ($canCreateBookings)
                                    <a href="{{ route('agent.bookings.create') }}" class="ms-1" data-testid="agent-bookings-empty-create">Create booking</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
        @if ($bookings->hasPages())
            <div class="ota-account-card__footer">{{ $bookings->links() }}</div>
        @endif
    </div>
@endsection

