@extends(client_layout('dashboard', 'admin'))

@section('title', 'Bookings')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Bookings</h1>
            <p>Operational inbox for queue review, assignment, and ticketing.</p>
        </div>
    </div>
@endsection

@section('content')
@php
    $f = $filters ?? [];
    $staffOpts = $filterStaffUsers ?? collect();
    $statusCases = $statusEnumCases ?? [];
    $activeQueue = $activeQueue ?? ($f['queue'] ?? 'all');
    $queueTabs = [
        'all' => 'All',
        'needs_action' => 'Needs action',
        'payment_review' => 'Payments',
        'supplier_pnr' => 'Supplier / PNR',
        'ticketing' => 'Ticketing',
        'cancellations' => 'Cancellations',
        'refunds' => 'Refunds',
    ];
@endphp

<div data-bookings-page>
    <div class="jp-kpis jp-kpis--4">
        <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format($kpis['total'] ?? 0) }}</div><div class="jp-kpi__l">Total</div></div>
        <div class="jp-kpi t-amber"><div class="jp-kpi__v">{{ number_format($kpis['pending'] ?? $kpis['needs_action'] ?? 0) }}</div><div class="jp-kpi__l">Pending</div></div>
        <div class="jp-kpi t-blue"><div class="jp-kpi__v">{{ number_format($kpis['unpaid'] ?? $kpis['payment_pending'] ?? 0) }}</div><div class="jp-kpi__l">Unpaid</div></div>
        <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format($kpis['ticketed'] ?? $kpis['ticketing_pending'] ?? 0) }}</div><div class="jp-kpi__l">Ticketing queue</div></div>
    </div>

    <div class="jp-queue-tabs">
        @foreach ($queueTabs as $queueKey => $queueLabel)
            <a href="{{ client_route('admin.bookings', array_merge(request()->except('page'), ['queue' => $queueKey])) }}" class="jp-queue-tab {{ $activeQueue === $queueKey ? 'is-active' : '' }}">{{ $queueLabel }}</a>
        @endforeach
    </div>

    <form method="get" action="{{ client_route('admin.bookings') }}" class="jp-filterbar" id="bookings-filter-form">
        <input type="hidden" name="queue" value="{{ $activeQueue }}">
        <div class="jp-filterbar__field">
            <label class="jp-label" for="bookings-search-input">Search</label>
            <input type="text" name="search" id="bookings-search-input" class="jp-input" value="{{ $f['search'] ?? '' }}" placeholder="Reference, customer, phone">
        </div>
        <div class="jp-filterbar__field">
            <label class="jp-label" for="bookings-status">Status</label>
            <select name="status" id="bookings-status" class="jp-select">
                <option value="">All statuses</option>
                @foreach ($statusCases as $sc)
                    <option value="{{ $sc->value }}" @selected(($f['status'] ?? '') === $sc->value)>{{ str_replace('_', ' ', $sc->value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="jp-filterbar__field">
            <label class="jp-label" for="bookings-payment">Payment</label>
            <select name="payment_status" id="bookings-payment" class="jp-select">
                <option value="">All</option>
                @foreach (['unpaid', 'partial', 'paid', 'refunded'] as $ps)
                    <option value="{{ $ps }}" @selected(($f['payment_status'] ?? '') === $ps)>{{ ucfirst($ps) }}</option>
                @endforeach
            </select>
        </div>
        <div class="jp-filterbar__field">
            <label class="jp-label" for="bookings-staff">Staff</label>
            <select name="assigned_staff_id" id="bookings-staff" class="jp-select">
                <option value="">Any</option>
                @foreach ($staffOpts as $su)
                    <option value="{{ $su->id }}" @selected((string)($f['assigned_staff_id'] ?? '') === (string) $su->id)>{{ $su->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="jp-filterbar__actions">
            <button type="submit" class="jp-btn jp-btn--sm">Apply</button>
            <a href="{{ client_route('admin.bookings') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Reset</a>
        </div>
    </form>

    <div class="jp-dtable-wrap" data-bookings-list>
        <table class="jp-dtable jp-dtable--bookings">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Route</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Ticketing</th>
                    <th>Supplier</th>
                    <th>PNR</th>
                    <th class="num">Fare</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="bookings-table-body">
                @forelse ($bookings as $row)
                    @php
                        $refDisplay = ($row['booking_ref'] ?? '') !== '' ? $row['booking_ref'] : ('Draft #'.($row['id'] ?? ''));
                        $pnrDisplay = ($row['pnr'] ?? '') !== '' ? $row['pnr'] : (($row['supplier_reference'] ?? '') !== '' ? $row['supplier_reference'] : '—');
                        $providerLabel = ($row['supplier_provider'] ?? '') !== '' ? strtoupper($row['supplier_provider']) : '—';
                    @endphp
                    <tr class="jp-booking-row">
                        <td data-label="Reference"><span class="jp-cell-id">{{ $refDisplay }}</span></td>
                        <td data-label="Customer">
                            {{ $row['customer_name'] ?? '—' }}
                            <span class="jp-cell-sub">{{ ucfirst($row['customer_type'] ?? 'guest') }}</span>
                        </td>
                        <td data-label="Route">{{ $row['route'] ?? '—' }}<span class="jp-cell-sub">{{ $row['travel_date'] ?? '' }}</span></td>
                        <td data-label="Status"><x-themes.admin.jetpakistan.components.status-badge :label="$row['status_display'] ?? ucfirst(str_replace('_', ' ', $row['status'] ?? ''))" /></td>
                        <td data-label="Payment"><x-themes.admin.jetpakistan.components.status-badge :label="$row['payment_status_display'] ?? ucfirst($row['payment_status'] ?? 'unpaid')" tone="amber" /></td>
                        <td data-label="Ticketing"><x-themes.admin.jetpakistan.components.status-badge :label="$row['ticketing_status_display'] ?? 'Not started'" /></td>
                        <td data-label="Supplier"><x-themes.admin.jetpakistan.components.status-badge :label="$providerLabel" tone="blue" /></td>
                        <td data-label="PNR"><span class="jp-cell-id jp-cell-id--mono">{{ $pnrDisplay }}</span></td>
                        <td data-label="Fare" class="num">Rs {{ number_format((int) ($row['total_fare'] ?? 0), 0) }}</td>
                        <td data-label="Actions">
                            <a href="{{ client_route('admin.bookings.show', ['booking' => $row['id']]) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10"><x-themes.admin.jetpakistan.components.empty-state title="No bookings found" message="Try adjusting filters or queue." /></td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($bookings instanceof \Illuminate\Contracts\Pagination\Paginator && $bookings->hasPages())
            <div class="jp-pagination" id="bookings-pagination-wrap">{{ $bookings->links() }}</div>
        @endif
    </div>

    <div class="jp-booking-cards jp-only-mobile">
        @forelse ($bookings as $row)
            @php
                $refDisplay = ($row['booking_ref'] ?? '') !== '' ? $row['booking_ref'] : ('Draft #'.($row['id'] ?? ''));
                $pnrDisplay = ($row['pnr'] ?? '') !== '' ? $row['pnr'] : (($row['supplier_reference'] ?? '') !== '' ? $row['supplier_reference'] : '—');
            @endphp
            <article class="jp-booking-card">
                <div class="jp-booking-card__head">
                    <strong class="jp-cell-id">{{ $refDisplay }}</strong>
                    <x-themes.admin.jetpakistan.components.status-badge :label="$row['status_display'] ?? ucfirst(str_replace('_', ' ', $row['status'] ?? ''))" />
                </div>
                <div class="jp-booking-card__meta">{{ $row['customer_name'] ?? '—' }} · {{ $row['route'] ?? '—' }}</div>
                <div class="jp-booking-card__badges">
                    <x-themes.admin.jetpakistan.components.status-badge :label="$row['payment_status_display'] ?? 'Unpaid'" tone="amber" />
                    <x-themes.admin.jetpakistan.components.status-badge :label="$row['ticketing_status_display'] ?? 'Not started'" />
                    <span class="jp-cell-sub">PNR {{ $pnrDisplay }}</span>
                </div>
                <div class="jp-booking-card__foot">
                    <span class="jp-booking-card__fare">Rs {{ number_format((int) ($row['total_fare'] ?? 0), 0) }}</span>
                    <a href="{{ client_route('admin.bookings.show', ['booking' => $row['id']]) }}" class="jp-btn jp-btn--sm">Open</a>
                </div>
            </article>
        @empty
            <x-themes.admin.jetpakistan.components.empty-state title="No bookings found" />
        @endforelse
    </div>
</div>
@endsection
