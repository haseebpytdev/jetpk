@extends(client_layout('dashboard', 'staff'))

@section('title', 'Bookings')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Bookings</h1>
            <p>Agency-scoped queue — filters and assignment below.</p>
        </div>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

@php
    $f = $filters ?? [];
    $statusCases = $statusEnumCases ?? [];
    $activeQueue = $activeQueue ?? ($f['queue'] ?? 'all');
    $queueTabs = [
        'all' => 'All',
        'assigned' => 'Assigned to me',
        'payment_review' => 'Payment review',
        'needs_action' => 'Needs action',
        'supplier_pnr' => 'PNR / supplier',
        'cancellations' => 'Cancellations',
        'refunds' => 'Refunds',
    ];
@endphp

<div class="jp-queue-tabs">
    @foreach ($queueTabs as $queueKey => $queueLabel)
        @php
            $queueParams = $queueKey === 'all'
                ? array_filter(['assigned_to_me' => $f['assigned_to_me'] ?? null])
                : ($queueKey === 'assigned'
                    ? ['assigned_to_me' => 1]
                    : ['queue' => $queueKey]);
        @endphp
        <a href="{{ client_route('staff.bookings.index', $queueParams) }}" class="jp-queue-tab {{ ($queueKey === 'assigned' && ! empty($f['assigned_to_me'])) || ($queueKey !== 'assigned' && $activeQueue === $queueKey) ? 'is-active' : '' }}">{{ $queueLabel }}</a>
    @endforeach
</div>

<form method="get" action="{{ client_route('staff.bookings.index') }}" class="jp-filterbar">
    @if ($activeQueue !== 'all')
        <input type="hidden" name="queue" value="{{ $activeQueue }}">
    @endif
    <div class="jp-filterbar__field">
        <label class="jp-label" for="staff-bookings-search">Search</label>
        <input type="text" name="search" id="staff-bookings-search" class="jp-input" value="{{ $f['search'] ?? '' }}" placeholder="Reference, customer, phone">
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="staff-bookings-status">Status</label>
        <select name="status" id="staff-bookings-status" class="jp-select">
            <option value="">All</option>
            @foreach ($statusCases as $sc)
                <option value="{{ $sc->value }}" @selected(($f['status'] ?? '') === $sc->value)>{{ str_replace('_', ' ', $sc->value) }}</option>
            @endforeach
        </select>
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="staff-bookings-payment">Payment</label>
        <select name="payment_status" id="staff-bookings-payment" class="jp-select">
            <option value="">All</option>
            @foreach (['unpaid', 'partial', 'paid', 'refunded'] as $ps)
                <option value="{{ $ps }}" @selected(($f['payment_status'] ?? '') === $ps)>{{ ucfirst($ps) }}</option>
            @endforeach
        </select>
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="staff-bookings-source">Source</label>
        <select name="source" id="staff-bookings-source" class="jp-select">
            <option value="">All bookings</option>
            <option value="guest" @selected(($f['source'] ?? '') === 'guest')>Guest</option>
            <option value="customer" @selected(($f['source'] ?? '') === 'customer')>Customer</option>
            <option value="agent" @selected(($f['source'] ?? '') === 'agent')>Agent</option>
        </select>
    </div>
    <div class="jp-filterbar__field jp-filterbar__field--check">
        <label class="jp-check">
            <input type="checkbox" name="assigned_to_me" value="1" @checked(!empty($f['assigned_to_me']))>
            <span>Assigned to me</span>
        </label>
    </div>
    <div class="jp-filterbar__actions">
        <button type="submit" class="jp-btn jp-btn--sm">Apply</button>
        <a href="{{ client_route('staff.bookings.index') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Reset</a>
    </div>
</form>

<div class="jp-dtable-wrap">
    <table class="jp-dtable jp-dtable--bookings">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Customer</th>
                <th>Route</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Supplier / PNR</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bookings as $row)
                <tr>
                    <td data-label="Reference"><span class="jp-cell-id">{{ ($row['booking_ref'] ?? '') !== '' ? $row['booking_ref'] : 'Draft #'.$row['id'] }}</span></td>
                    <td data-label="Customer">{{ $row['customer_name'] ?? '—' }}</td>
                    <td data-label="Route">{{ $row['route'] ?? '—' }}</td>
                    <td data-label="Status"><x-themes.admin.jetpakistan.components.status-badge :label="$row['status_display'] ?? ucfirst(str_replace('_', ' ', $row['status'] ?? ''))" /></td>
                    <td data-label="Payment"><x-themes.admin.jetpakistan.components.status-badge :label="$row['payment_status_display'] ?? 'Unpaid'" tone="amber" /></td>
                    <td data-label="Supplier"><span class="jp-cell-sub">{{ $row['supplier_status_display'] ?? '—' }}</span></td>
                    <td data-label="Actions"><a href="{{ client_route('staff.bookings.show', ['booking' => $row['id']]) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-themes.admin.jetpakistan.components.empty-state title="No bookings match" message="Try adjusting filters or queue." /></td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($bookings instanceof \Illuminate\Contracts\Pagination\Paginator && $bookings->hasPages())
        <div class="jp-pagination">{{ $bookings->links() }}</div>
    @endif
</div>
@endsection
