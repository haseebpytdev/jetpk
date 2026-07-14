@extends(client_layout('dashboard', 'staff'))

@section('title', 'Bookings')

@section('page-header')
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Operations</div>
            <h1 class="page-title">Bookings</h1>
            <div class="text-secondary mt-1">Agency-scoped queue - use filters or queue chips below.</div>
        </div>
    </div>
@endsection

@section('content')
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

    <div class="d-flex flex-wrap gap-2 mb-3 ota-admin-queue-tabs" data-testid="staff-bookings-queues">
        @foreach ($queueTabs as $queueKey => $queueLabel)
            @php
                $queueParams = $queueKey === 'all'
                    ? array_filter(['assigned_to_me' => $f['assigned_to_me'] ?? null])
                    : ($queueKey === 'assigned'
                        ? ['assigned_to_me' => 1]
                        : ['queue' => $queueKey]);
            @endphp
            <a
                href="{{ route('staff.bookings.index', $queueParams) }}"
                class="btn btn-sm {{ ($queueKey === 'assigned' && ! empty($f['assigned_to_me'])) || ($queueKey !== 'assigned' && $activeQueue === $queueKey) ? 'btn-primary' : 'btn-outline-secondary' }}"
            >{{ $queueLabel }}</a>
        @endforeach
    </div>

    <div class="card mb-3 ota-admin-filter-bar">
        <div class="card-body">
            <form method="get" action="{{ route('staff.bookings.index') }}" class="row g-2 align-items-end">
                @if ($activeQueue !== 'all')
                    <input type="hidden" name="queue" value="{{ $activeQueue }}">
                @endif
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" value="{{ $f['search'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        @foreach ($statusCases as $sc)
                            <option value="{{ $sc->value }}" @selected(($f['status'] ?? '') === $sc->value)>{{ str_replace('_', ' ', $sc->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment</label>
                    <select name="payment_status" class="form-select">
                        <option value="">All</option>
                        @foreach (['unpaid', 'partial', 'paid', 'refunded'] as $ps)
                            <option value="{{ $ps }}" @selected(($f['payment_status'] ?? '') === $ps)>{{ ucfirst($ps) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Source</label>
                    <select name="source" class="form-select" data-testid="bookings-source-filter">
                        <option value="">All bookings</option>
                        <option value="guest" @selected(($f['source'] ?? '') === 'guest')>Guest bookings</option>
                        <option value="customer" @selected(($f['source'] ?? '') === 'customer')>Registered customer</option>
                        <option value="agent" @selected(($f['source'] ?? '') === 'agent')>Agent / agency</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" value="{{ $f['date_from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" value="{{ $f['date_to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-12">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="assigned_to_me" value="1" id="atm" @checked(!empty($f['assigned_to_me']))>
                        <label class="form-check-label" for="atm">Assigned to me</label>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('staff.bookings.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm ota-admin-table">
        <div class="table-responsive ota-r-table-wrap">
            <table class="table table-vcenter card-table ota-admin-table table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ref</th>
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
                            <td class="fw-semibold">{{ ($row['booking_ref'] ?? '') !== '' ? $row['booking_ref'] : 'Draft #'.$row['id'] }}</td>
                            <td>{{ $row['customer_name'] }}</td>
                            <td class="small">{{ $row['route'] }}</td>
                            <td><x-dashboard.status-badge :status="$row['status_display'] ?? $row['status']" /></td>
                            <td><span class="small">{{ $row['payment_status_display'] ?? '' }}</span></td>
                            <td><span class="small">{{ $row['supplier_status_display'] ?? '' }}</span></td>
                            <td class="text-end"><a href="{{ route('staff.bookings.show', $row['id']) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-secondary text-center py-4">No bookings match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bookings instanceof \Illuminate\Contracts\Pagination\Paginator && $bookings->hasPages())
            <div class="card-footer d-flex justify-content-center">{{ $bookings->links() }}</div>
        @endif
    </div>
@endsection

