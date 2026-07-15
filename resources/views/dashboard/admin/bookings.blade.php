@extends(client_layout('dashboard', 'admin'))

@section('title', 'Bookings')

@push('styles')
<style>
    .booking-row-clickable { cursor: pointer; }
    .bookings-loading { opacity: .65; pointer-events: none; transition: opacity .15s ease; }
    @media (max-width: 767.98px) {
        /* Clearance for dashboard fixed filter bar (Apply filters / Reset) on mobile */
        html:has([data-bookings-page]) {
            scroll-padding-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px));
        }
        [data-bookings-page] .booking-card-actions,
        [data-bookings-page] .booking-card-actions .btn,
        [data-bookings-page] .preview-actions .btn {
            scroll-margin-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px));
        }
        [data-bookings-page] .bookings-cards,
        [data-bookings-page] .bookings-preview,
        [data-bookings-page] #bookings-pagination-wrap {
            padding-bottom: calc(6rem + env(safe-area-inset-bottom, 0px));
        }
    }
</style>
@endpush

@section('page-header')
    <div class="jp-between ota-admin-page-header">
        <div class="col">
            <div class="page-pretitle">Operations</div>
            <h1 class="jp-page-title">Bookings management</h1>
            <div class="text-secondary mt-1">Operational inbox for bookings queue, review, and assignment.</div>
        </div>
    </div>
@endsection

@section('content')
@php
    $b = $selectedBooking;
    $f = $filters ?? [];
    $staffOpts = $filterStaffUsers ?? collect();
    $statusCases = $statusEnumCases ?? [];
    $activeQueue = $activeQueue ?? ($f['queue'] ?? 'all');
    $queueTabs = [
        'all' => 'All bookings',
        'needs_action' => 'Needs action',
        'payment_review' => 'Payment review',
        'supplier_pnr' => 'Supplier / PNR',
        'ticketing' => 'Ticketing',
        'cancellations' => 'Cancellations',
        'refunds' => 'Refunds',
        'invoices' => 'Invoices',
        'documents' => 'Documents',
    ];
@endphp

<div data-bookings-page data-bookings-list>
    <div class="row row-cards bookings-kpi mb-3 ota-admin-kpi-card" data-bookings-kpis>
        <div class="col-sm-6 col-xl-3">
            <a class="bookings-kpi-link {{ $activeQueue === 'all' ? 'is-active' : '' }}" href="{{ route('admin.bookings', array_merge(request()->except('page'), ['queue' => 'all'])) }}">
                <div class="card card-sm"><div class="jp-card__body"><div class="text-secondary small">Total bookings</div><div class="h2 mb-0">{{ number_format($kpis['total'] ?? 0) }}</div></div></div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a class="bookings-kpi-link {{ $activeQueue === 'needs_action' ? 'is-active' : '' }}" href="{{ route('admin.bookings', array_merge(request()->except('page'), ['queue' => 'needs_action'])) }}">
                <div class="card card-sm"><div class="jp-card__body"><div class="text-secondary small">Needs action</div><div class="h2 mb-0 text-warning">{{ number_format($kpis['needs_action'] ?? 0) }}</div></div></div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a class="bookings-kpi-link {{ $activeQueue === 'payment_review' ? 'is-active' : '' }}" href="{{ route('admin.bookings', array_merge(request()->except('page'), ['queue' => 'payment_review'])) }}">
                <div class="card card-sm"><div class="jp-card__body"><div class="text-secondary small">Payment pending</div><div class="h2 mb-0 text-danger">{{ number_format($kpis['payment_pending'] ?? 0) }}</div></div></div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a class="bookings-kpi-link {{ $activeQueue === 'ticketing' ? 'is-active' : '' }}" href="{{ route('admin.bookings', array_merge(request()->except('page'), ['queue' => 'ticketing'])) }}">
                <div class="card card-sm"><div class="jp-card__body"><div class="text-secondary small">Ticketing pending</div><div class="h2 mb-0 text-primary">{{ number_format($kpis['ticketing_pending'] ?? 0) }}</div></div></div>
            </a>
        </div>
    </div>

    <div class="bookings-queue-tabs ota-admin-queue-tabs" data-bookings-tabs>
        @foreach ($queueTabs as $queueKey => $queueLabel)
            <a href="{{ route('admin.bookings', array_merge(request()->except('page'), ['queue' => $queueKey])) }}" class="bookings-queue-tab ota-admin-queue-tab {{ $activeQueue === $queueKey ? 'is-active' : '' }}">{{ $queueLabel }}</a>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-8 col-lg-7">
            <div class="bookings-filters ota-admin-filter-bar mb-3" data-bookings-filter-bar>
                <form method="get" action="{{ route('admin.bookings') }}" class="jp-form-grid jp-form-grid--filter ota-r-form-grid" id="bookings-filter-form">
                    <input type="hidden" name="queue" value="{{ $activeQueue }}">
                    <div class="col-12 col-xl-4 col-lg-5 col-md-6">
                        <label class="jp-label">Search</label>
                        <input type="text" name="search" value="{{ $f['search'] ?? '' }}" class="jp-control" placeholder="Search booking, customer, phone" id="bookings-search-input" list="bookings-search-suggestions" autocomplete="off">
                        <datalist id="bookings-search-suggestions"></datalist>
                    </div>
                    <div class="col-12 col-xl-2 col-lg-2 col-md-6">
                        <label class="jp-label">Status</label>
                        <select name="status" class="jp-control">
                            <option value="">All statuses</option>
                            @foreach ($statusCases as $sc)
                                <option value="{{ $sc->value }}" @selected(($f['status'] ?? '') === $sc->value)>{{ str_replace('_', ' ', $sc->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-xl-2 col-lg-2 col-md-6">
                        <label class="jp-label">Payment</label>
                        <select name="payment_status" class="jp-control">
                            <option value="">All</option>
                            @foreach (['unpaid', 'partial', 'paid', 'refunded'] as $ps)
                                <option value="{{ $ps }}" @selected(($f['payment_status'] ?? '') === $ps)>{{ ucfirst($ps) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-xl-2 col-lg-2 col-md-6">
                        <label class="jp-label">Source</label>
                        <select name="source" class="jp-control" data-testid="bookings-source-filter">
                            @php $activeSource = $f['source'] ?? ($f['agent_customer'] ?? ''); @endphp
                            <option value="">All bookings</option>
                            <option value="guest" @selected($activeSource === 'guest')>Guest bookings</option>
                            <option value="customer" @selected($activeSource === 'customer')>Registered customer</option>
                            <option value="agent" @selected($activeSource === 'agent')>Agent / agency</option>
                        </select>
                    </div>
                    <div class="col-12 col-xl-2 col-lg-3 col-md-6">
                        <label class="jp-label">Assigned staff</label>
                        <select name="assigned_staff_id" class="jp-control">
                            <option value="">Any</option>
                            @foreach ($staffOpts as $su)
                                <option value="{{ $su->id }}" @selected((string)($f['assigned_staff_id'] ?? '') === (string) $su->id)>{{ $su->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-xl-1 col-lg-3 col-md-6">
                        <label class="jp-label">From</label>
                        <input type="date" name="date_from" value="{{ $f['date_from'] ?? '' }}" class="jp-control">
                    </div>
                    <div class="col-12 col-xl-1 col-lg-3 col-md-6">
                        <label class="jp-label">To</label>
                        <input type="date" name="date_to" value="{{ $f['date_to'] ?? '' }}" class="jp-control">
                    </div>
                    <div class="col-12">
                        <details>
                            <summary class="small text-secondary">More filters</summary>
                            <div class="row g-2 mt-1">
                                <div class="col-md-3"><label class="jp-label">Airline</label><input type="text" name="airline" value="{{ $f['airline'] ?? '' }}" class="jp-control" placeholder="Emirates"></div>
                                <div class="col-md-3"><label class="jp-label">Route</label><input type="text" name="route" value="{{ $f['route'] ?? '' }}" class="jp-control" placeholder="LHE - DXB"></div>
                                <div class="col-md-2">
                                    <label class="jp-label">Product</label>
                                    <select name="product" class="jp-control">
                                        <option value="">Flights (default)</option>
                                        <option value="group" @selected(($f['product'] ?? '') === 'group')>Group ticketing</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="jp-label">Booking type</label>
                                    <select name="booking_type" class="jp-control">
                                        <option value="">Any</option>
                                        <option value="public" @selected(($f['booking_type'] ?? '') === 'public')>Public</option>
                                        <option value="agent_portal" @selected(($f['booking_type'] ?? '') === 'agent_portal')>Agent portal</option>
                                        <option value="direct" @selected(($f['booking_type'] ?? '') === 'direct')>Direct</option>
                                    </select>
                                </div>
                                <div class="col-md-2"><label class="jp-label">Fare min (PKR)</label><input type="number" min="0" step="1" name="fare_min" value="{{ $f['fare_min'] ?? '' }}" class="jp-control" placeholder="0"></div>
                                <div class="col-md-2"><label class="jp-label">Fare max (PKR)</label><input type="number" min="0" step="1" name="fare_max" value="{{ $f['fare_max'] ?? '' }}" class="jp-control" placeholder="500000"></div>
                                <div class="col-md-6"><label class="jp-label">Created date</label><div class="jp-control-plaintext small text-secondary">Use the top bar date range (From/To).</div></div>
                                <div class="col-md-3"><label class="jp-label">Travel from</label><input type="date" name="travel_date_from" value="{{ $f['travel_date_from'] ?? '' }}" class="jp-control"></div>
                                <div class="col-md-3"><label class="jp-label">Travel to</label><input type="date" name="travel_date_to" value="{{ $f['travel_date_to'] ?? '' }}" class="jp-control"></div>
                            </div>
                        </details>
                    </div>
                    <div class="col-12 mt-2 d-flex gap-2 flex-wrap ota-r-action-bar">
                        <button type="submit" class="jp-btn jp-btn--primary">Apply filters</button>
                        <a href="{{ route('admin.bookings') }}" class="jp-btn jp-btn--ghost">Reset</a>
                    </div>
                </form>
            </div>

            <div class="card bookings-table-wrap" data-bookings-list>
                <div class="card-header border-0 pb-0"><h3 class="jp-card__title">All bookings</h3></div>
                <div id="bookings-table-body" class="bookings-cards">
                    @forelse ($bookings as $row)
                        @php
                            $ctype = $row['customer_type'] ?? 'guest';
                            $st = $row['status'] ?? 'pending';
                            $stDisplay = $row['status_display'] ?? ucfirst(str_replace('_', ' ', $st));
                            $pay = $row['payment_status'] ?? 'unpaid';
                            $payDisplay = $row['payment_status_display'] ?? ucfirst(str_replace('_', ' ', $pay));
                            $refDisplay = ($row['booking_ref'] ?? '') !== '' ? $row['booking_ref'] : ('Draft #'.($row['id'] ?? ''));
                            $isSelected = isset($selectedPreviewKey) && (string)($row['preview_query'] ?? '') === (string)$selectedPreviewKey;
                            $previewUrl = route('admin.bookings', array_merge(request()->except('preview'), ['preview' => $row['preview_query']]));
                        @endphp
                        <article class="booking-queue-card {{ $isSelected ? 'is-active' : '' }}" data-booking-row data-preview-url="{{ $previewUrl }}" data-booking-id="{{ $row['id'] }}" data-preview-key="{{ $row['preview_query'] }}" tabindex="0" role="button" aria-label="Preview booking {{ $refDisplay }}">
                                <div class="booking-card-top">
                                <a href="{{ $previewUrl }}" class="booking-card-ref">{{ $refDisplay }}</a>
                                    <div class="booking-card-statusline">{{ $stDisplay }}{{ display_sep_dot() }}{{ $payDisplay }}</div>
                            </div>
                            <div class="booking-card-passenger">{{ $row['customer_name'] }}</div>
                            <div class="booking-card-trip">{{ $row['route'] }}{{ display_sep_dot() }}{{ $row['airline'] }}{{ display_sep_dot() }}{{ $row['travel_date'] }}</div>
                            <div class="booking-card-meta">Rs {{ number_format((int) ($row['total_fare'] ?? 0), 0) }}{{ display_sep_dot() }}{{ ucfirst($ctype) }}{{ display_sep_dot() }}{{ (int)($row['passengers_count'] ?? 0) }} passenger{{ (int)($row['passengers_count'] ?? 0) === 1 ? '' : 's' }}</div>
                            <div class="booking-card-actions ota-admin-action-group ota-r-action-bar">
                                <a href="{{ route('admin.bookings.show', $row['id']) }}" class="jp-btn jp-btn--sm jp-btn--outline" data-booking-open-link>Open</a>
                                <a href="{{ route('admin.bookings.show', $row['id']) }}?tab=communication#assign-staff-panel" class="jp-btn jp-btn--sm jp-btn--ghost">Assign</a>
                                <a href="{{ route('admin.bookings.show', $row['id']) }}?tab=payments#payments" class="jp-btn jp-btn--sm jp-btn--ghost">Payment</a>
                            </div>
                        </article>
                    @empty
                        <div class="bookings-empty-state">No bookings found. Try adjusting filters or create/search a booking.</div>
                    @endforelse
                </div>
                @if ($bookings instanceof \Illuminate\Contracts\Pagination\Paginator && $bookings->hasPages())
                    <div class="card-footer d-flex justify-content-center" id="bookings-pagination-wrap">{{ $bookings->links() }}</div>
                @else
                    <div class="card-footer d-flex justify-content-center" id="bookings-pagination-wrap"></div>
                @endif
            </div>
        </div>

        <div class="col-xl-4 col-lg-5" data-booking-preview data-bookings-preview>
            <div class="bookings-preview">
                <div class="jp-card">
                    <div class="jp-card__head">
                        <h3 class="jp-card__title">Selected booking</h3>
                        <div class="jp-card__subtitle text-secondary" id="bookings-preview-subtitle">
                            @if (($previewRef ?? '') !== '') Preview: <code>{{ $previewRef }}</code> @else Default preview (first row). @endif
                        </div>
                    </div>
                    <div class="jp-card__body" id="bookings-preview-body">
                        @if ($b)
                            @php
                                $previewRef = ($b['booking_ref'] ?? '') !== '' ? $b['booking_ref'] : ('Draft #'.($b['id'] ?? ''));
                                $travelRaw = $b['travel_date'] ?? null;
                                $travelLabel = (is_string($travelRaw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $travelRaw))
                                    ? \Illuminate\Support\Carbon::parse($travelRaw)->format('d M Y')
                                    : '—';
                                $paxCount = (int) ($b['passengers_count'] ?? 0);
                                $totalFare = (int) ($b['total_fare'] ?? 0);
                            @endphp
                            <h4 class="mb-1 ota-admin-section-title">{{ $previewRef }}</h4>
                            <div class="preview-trip-line">{{ $b['route'] }}{{ display_sep_dot() }}{{ $b['airline'] }}</div>
                            <div class="preview-trip-line mb-3">{{ $travelLabel }}</div>

                            <h4 class="ota-admin-section-title">Customer</h4>
                            <div class="preview-block ota-admin-compact-card">
                                <div class="fw-semibold">{{ $b['customer_name'] }}</div>
                                <div class="small text-secondary mb-2">{{ ucfirst($b['customer_type'] ?? 'guest') }}{{ display_sep_dot() }}{{ $paxCount }} passenger{{ $paxCount === 1 ? '' : 's' }}</div>
                                <div class="small text-secondary">{{ $b['contact_phone'] }} / {{ $b['contact_email'] }}</div>
                            </div>

                            <h4 class="ota-admin-section-title">Financial</h4>
                            <div class="preview-block ota-admin-compact-card">
                                <div class="preview-kv ota-admin-kv-row"><span>Total:</span><strong>Rs {{ number_format($totalFare, 0) }}</strong></div>
                                <div class="preview-kv ota-admin-kv-row"><span>Paid:</span><strong>Rs 0</strong></div>
                                <div class="preview-kv ota-admin-kv-row"><span>Balance:</span><strong>Rs {{ number_format($totalFare, 0) }}</strong></div>
                            </div>

                            <h4 class="ota-admin-section-title">Current status</h4>
                            <div class="preview-block ota-admin-compact-card">
                                <div class="preview-kv ota-admin-kv-row"><span>Booking:</span><strong>{{ ucfirst(str_replace('_', ' ', $b['status'] ?? 'draft')) }}</strong></div>
                                <div class="preview-kv ota-admin-kv-row"><span>Payment:</span><strong>{{ $b['payment_status_display'] ?? ucfirst(str_replace('_', ' ', $b['payment_status'] ?? 'unpaid')) }}</strong></div>
                                <div class="preview-kv ota-admin-kv-row"><span>Supplier:</span><strong>{{ $b['supplier_status_display'] ?? 'not started' }}</strong></div>
                                <div class="preview-kv ota-admin-kv-row"><span>Ticketing:</span><strong>{{ $b['ticketing_status_display'] ?? 'not started' }}</strong></div>
                                <div class="preview-kv ota-admin-kv-row"><span>Assigned:</span><strong>{{ $b['assigned_staff_name'] ?? 'Unassigned' }}</strong></div>
                            </div>

                            <h4 class="ota-admin-section-title">Next action</h4>
                            <div class="preview-actions ota-admin-action-group ota-r-action-bar">
                                <a href="{{ route('admin.bookings.show', $b['id']) }}" class="jp-btn jp-btn--primary">Open full record</a>
                                <a href="{{ route('admin.bookings.show', $b['id']) }}?tab=payments#payments" class="jp-btn jp-btn--ghost">Record payment</a>
                                <a href="{{ route('admin.bookings.show', $b['id']) }}?tab=communication#assign-staff-panel" class="jp-btn jp-btn--ghost">Assign staff</a>
                            </div>
                        @else
                            <p class="text-secondary mb-0">No booking selected.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.getElementById('bookings-filter-form');
        var cardsWrap = document.getElementById('bookings-table-body');
        var paginationWrap = document.getElementById('bookings-pagination-wrap');
        var previewBody = document.getElementById('bookings-preview-body');
        var previewSubtitle = document.getElementById('bookings-preview-subtitle');
        var searchInput = document.getElementById('bookings-search-input');
        var suggestionsList = document.getElementById('bookings-search-suggestions');
        if (!form || !cardsWrap || !paginationWrap || !previewBody || !previewSubtitle) return;

        var dataUrl = @json(route('admin.bookings.data'));
        var suggestionsUrl = @json(route('admin.bookings.suggestions'));
        var previewBaseUrl = @json(url('/admin/bookings'));
        var state = {
            page: 1,
            preview: @json($selectedPreviewKey ?? ''),
            loading: false,
            previewLoading: false,
            searchTimer: null,
            suggestTimer: null
        };

        function esc(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function badgeClass(type, value) {
            if (type === 'customer_type') {
                return value === 'agent' ? 'bg-warning' : (value === 'customer' ? 'bg-primary' : 'bg-secondary');
            }
            if (type === 'status') {
                if (value === 'ticketed') return 'bg-success';
                if (value === 'confirmed') return 'bg-info';
                if (value === 'cancelled') return 'bg-dark';
                if (value === 'draft') return 'bg-secondary';
                return 'bg-warning';
            }
            if (type === 'payment_status') {
                if (value === 'paid') return 'bg-success';
                if (value === 'partial') return 'bg-warning';
                if (value === 'refunded') return 'bg-secondary';
                return 'bg-danger';
            }
            return 'bg-secondary';
        }

        function rowHtml(row, selectedKey) {
            var ctype = row.customer_type || 'guest';
            var st = row.status || 'pending';
            var stDisplay = row.status_display || String(st).replace('_', ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            var pay = row.payment_status || 'unpaid';
            var payDisplay = row.payment_status_display || String(pay).replace('_', ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            var isSelected = String(row.preview_query || '') === String(selectedKey || '');
            var refDisplay = (row.booking_ref || '') !== '' ? row.booking_ref : ('Draft #' + (row.id || ''));
            var searchParams = new URLSearchParams(currentFilters());
            searchParams.set('preview', String(row.preview_query || ''));
            var previewUrl = @json(route('admin.bookings')) + '?' + searchParams.toString();
            var paxCount = Number(row.passengers_count || 0);
            return '' +
                '<article class="booking-queue-card ' + (isSelected ? 'is-active ' : '') + '" data-booking-row data-booking-id="' + esc(row.id || '') + '" data-preview-key="' + esc(row.preview_query || '') + '" data-preview-url="' + esc(previewUrl) + '" tabindex="0" role="button" aria-label="Preview booking ' + esc(refDisplay) + '">' +
                '<div class="booking-card-top"><a href="' + esc(previewUrl) + '" class="booking-card-ref">' + esc(refDisplay) + '</a><div class="booking-card-statusline">' + esc(stDisplay) + '{{ display_sep_dot() }}' + esc(payDisplay) + '</div></div>' +
                '<div class="booking-card-passenger">' + esc(row.customer_name || '') + '</div>' +
                '<div class="booking-card-trip">' + esc(row.route || '--') + '{{ display_sep_dot() }}' + esc(row.airline || '--') + '{{ display_sep_dot() }}' + esc(row.travel_date || '--') + '</div>' +
                '<div class="booking-card-meta">Rs ' + esc(Number(row.total_fare || 0).toLocaleString()) + '{{ display_sep_dot() }}' + esc(ctype.charAt(0).toUpperCase() + ctype.slice(1)) + '{{ display_sep_dot() }}' + esc(paxCount) + ' passenger' + (paxCount === 1 ? '' : 's') + '</div>' +
                '<div class="booking-card-actions"><a href="' + esc(@json(url('/admin/bookings')) + '/' + row.id) + '" class="jp-btn jp-btn--sm jp-btn--outline" data-booking-open-link>Open</a><a href="' + esc(@json(url('/admin/bookings')) + '/' + row.id + '?tab=communication#assign-staff-panel') + '" class="jp-btn jp-btn--sm jp-btn--ghost">Assign</a><a href="' + esc(@json(url('/admin/bookings')) + '/' + row.id + '?tab=payments#payments') + '" class="jp-btn jp-btn--sm jp-btn--ghost">Payment</a></div>' +
                '</article>';
        }

        function previewHtml(b) {
            if (!b) return '<p class="text-secondary mb-0">No booking selected.</p>';
            var ref = (b.booking_ref || '') !== '' ? b.booking_ref : ('Draft #' + (b.id || ''));
            var ctype = b.customer_type || 'guest';
            var paxCount = Number(b.passengers_count || 0);
            var totalFare = Number(b.total_fare || 0);
            var travelLabel = (function () {
                if (!b.travel_date || b.travel_date === '--') return '--';
                var d = new Date(b.travel_date);
                if (isNaN(d.getTime())) return esc(b.travel_date);
                return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
            })();
            return '' +
                '<h4 class="mb-1 ota-admin-section-title">' + esc(ref) + '</h4>' +
                '<div class="preview-trip-line">' + esc(b.route || '--') + '{{ display_sep_dot() }}' + esc(b.airline || '--') + '</div>' +
                '<div class="preview-trip-line mb-3">' + esc(travelLabel) + '</div>' +
                '<h4 class="ota-admin-section-title">Customer</h4>' +
                '<div class="preview-block ota-admin-compact-card"><div class="fw-semibold">' + esc(b.customer_name || '') + '</div><div class="small text-secondary mb-2">' + esc(ctype.charAt(0).toUpperCase() + ctype.slice(1)) + '{{ display_sep_dot() }}' + esc(paxCount) + ' passenger' + (paxCount === 1 ? '' : 's') + '</div><div class="small text-secondary">' + esc(b.contact_phone || '--') + ' / ' + esc(b.contact_email || '--') + '</div></div>' +
                '<h4 class="ota-admin-section-title">Financial</h4>' +
                '<div class="preview-block ota-admin-compact-card"><div class="preview-kv ota-admin-kv-row"><span>Total:</span><strong>Rs ' + esc(totalFare.toLocaleString()) + '</strong></div><div class="preview-kv ota-admin-kv-row"><span>Paid:</span><strong>Rs 0</strong></div><div class="preview-kv ota-admin-kv-row"><span>Balance:</span><strong>Rs ' + esc(totalFare.toLocaleString()) + '</strong></div></div>' +
                '<h4 class="ota-admin-section-title">Current status</h4>' +
                '<div class="preview-block ota-admin-compact-card"><div class="preview-kv ota-admin-kv-row"><span>Booking:</span><strong>' + esc(String(b.status || 'draft').replace('_', ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); })) + '</strong></div><div class="preview-kv ota-admin-kv-row"><span>Payment:</span><strong>' + esc((b.payment_status_display || String(b.payment_status || 'unpaid').replace('_', ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }))) + '</strong></div><div class="preview-kv ota-admin-kv-row"><span>Supplier:</span><strong>' + esc((b.supplier_status_display || 'not started')) + '</strong></div><div class="preview-kv ota-admin-kv-row"><span>Ticketing:</span><strong>' + esc((b.ticketing_status_display || 'not started')) + '</strong></div><div class="preview-kv ota-admin-kv-row"><span>Assigned:</span><strong>' + esc(b.assigned_staff_name || 'Unassigned') + '</strong></div></div>' +
                '<h4 class="ota-admin-section-title">Next action</h4>' +
                '<div class="preview-actions ota-admin-action-group"><a href="' + esc(@json(url('/admin/bookings')) + '/' + b.id) + '" class="jp-btn jp-btn--primary">Open full record</a><a href="' + esc(@json(url('/admin/bookings')) + '/' + b.id + '?tab=payments#payments') + '" class="jp-btn jp-btn--ghost">Record payment</a><a href="' + esc(@json(url('/admin/bookings')) + '/' + b.id + '?tab=communication#assign-staff-panel') + '" class="jp-btn jp-btn--ghost">Assign staff</a></div>';
        }

        function currentFilters() {
            var fd = new FormData(form);
            var out = {};
            fd.forEach(function (v, k) {
                if (String(v).trim() !== '') out[k] = String(v);
            });
            return out;
        }

        function setLoading(on) {
            state.loading = on;
            var wrap = document.querySelector('.bookings-table-wrap');
            if (wrap) wrap.classList.toggle('bookings-loading', on);
        }

        function renderPagination(meta) {
            if (!meta || !meta.total) {
                paginationWrap.innerHTML = '';
                return;
            }
            var prevDisabled = !meta.prev_page_url ? 'disabled' : '';
            var nextDisabled = !meta.next_page_url ? 'disabled' : '';
            paginationWrap.innerHTML = '' +
                '<div class="d-flex align-items-center gap-2 flex-wrap w-100 justify-content-between">' +
                '<div class="text-secondary small">Showing ' + esc(meta.from || 0) + ' - ' + esc(meta.to || 0) + ' of ' + esc(meta.total || 0) + '</div>' +
                '<div class="btn-group">' +
                '<button type="button" class="jp-btn jp-btn--ghost btn-sm" data-page-nav="prev" ' + prevDisabled + '>Previous</button>' +
                '<button type="button" class="jp-btn jp-btn--ghost btn-sm" data-page-nav="next" ' + nextDisabled + '>Next</button>' +
                '</div></div>';
            var prevBtn = paginationWrap.querySelector('[data-page-nav="prev"]');
            var nextBtn = paginationWrap.querySelector('[data-page-nav="next"]');
            if (prevBtn) prevBtn.addEventListener('click', function () { fetchRows(Math.max(1, meta.current_page - 1)); });
            if (nextBtn) nextBtn.addEventListener('click', function () { fetchRows(Math.min(meta.last_page, meta.current_page + 1)); });
        }

        function fetchRows(page) {
            if (state.loading) return Promise.resolve();
            state.page = page || 1;
            var params = new URLSearchParams(currentFilters());
            params.set('page', String(state.page));
            params.set('per_page', '25');
            if (state.preview) params.set('preview', state.preview);
            setLoading(true);
            return fetch(dataUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (json) {
                    var rows = Array.isArray(json.rows) ? json.rows : [];
                    state.preview = String(json.selected_preview_key || '');
                    if (!rows.length) {
                        cardsWrap.innerHTML = '<div class="bookings-empty-state">No bookings found for current filters.</div>';
                    } else {
                        cardsWrap.innerHTML = rows.map(function (row) { return rowHtml(row, state.preview); }).join('');
                    }
                    previewBody.innerHTML = previewHtml(json.selected_booking || null);
                    previewSubtitle.innerHTML = state.preview ? ('Preview: <code>' + esc(state.preview) + '</code>') : 'Default preview (first row).';
                    renderPagination(json.pagination || null);
                })
                .finally(function () { setLoading(false); });
        }

        function highlightSelected(previewKey) {
            var rows = cardsWrap.querySelectorAll('[data-booking-id]');
            rows.forEach(function (r) {
                var active = String(r.getAttribute('data-preview-key') || '') === String(previewKey || '');
                r.classList.toggle('is-active', active);
            });
        }

        function setPreviewLoading(on) {
            state.previewLoading = on;
            if (previewBody) previewBody.classList.toggle('bookings-loading', on);
        }

        function syncPreviewInUrl(previewKey) {
            try {
                var url = new URL(window.location.href);
                if (previewKey && String(previewKey).trim() !== '') {
                    url.searchParams.set('preview', String(previewKey));
                } else {
                    url.searchParams.delete('preview');
                }
                window.history.replaceState({}, '', url.toString());
            } catch (e) {}
        }

        function fetchPreviewForRow(row) {
            if (state.previewLoading) return;
            var bookingId = row.getAttribute('data-booking-id');
            if (!bookingId) return;
            var previewKey = row.getAttribute('data-preview-key') || '';
            var previewUrl = previewBaseUrl + '/' + encodeURIComponent(String(bookingId)) + '/preview';
            setPreviewLoading(true);
            fetch(previewUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (json) {
                    var selected = json && (json.selected_booking || json.booking) ? (json.selected_booking || json.booking) : null;
                    state.preview = String((json && (json.selected_preview_key || json.preview_key)) || (selected && selected.preview_query) || previewKey || '');
                    previewBody.innerHTML = previewHtml(selected);
                    var subtitleRef = json && json.preview_ref ? String(json.preview_ref) : state.preview;
                    previewSubtitle.innerHTML = subtitleRef ? ('Preview: <code>' + esc(subtitleRef) + '</code>') : 'Default preview (first row).';
                    highlightSelected(state.preview);
                    syncPreviewInUrl(state.preview);
                })
                .catch(function () {
                    var fallbackUrl = row.getAttribute('data-preview-url');
                    if (fallbackUrl) window.location.href = fallbackUrl;
                })
                .finally(function () {
                    setPreviewLoading(false);
                });
        }

        cardsWrap.addEventListener('click', function (event) {
            var row = event.target.closest('[data-booking-id]');
            if (!row) return;
            if (event.target.closest('[data-booking-open-link]')) return;
            var link = event.target.closest('a');
            if (link) {
                event.preventDefault();
            }
            fetchPreviewForRow(row);
        });
        cardsWrap.addEventListener('keydown', function (event) {
            var row = event.target.closest('[data-booking-id]');
            if (!row) return;
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            fetchPreviewForRow(row);
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            state.page = 1;
            state.preview = '';
            fetchRows(1).catch(function () {
                form.submit();
            });
        });
        form.querySelectorAll('select,input[type="date"]').forEach(function (el) {
            el.addEventListener('change', function () {
                state.page = 1;
                state.preview = '';
                fetchRows(1).catch(function () {
                    form.submit();
                });
            });
        });
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                if (state.searchTimer) clearTimeout(state.searchTimer);
                state.searchTimer = setTimeout(function () {
                    state.page = 1;
                    state.preview = '';
                    fetchRows(1).catch(function () {
                        form.submit();
                    });
                }, 260);

                var q = (searchInput.value || '').trim();
                if (state.suggestTimer) clearTimeout(state.suggestTimer);
                if (q.length < 2) {
                    suggestionsList.innerHTML = '';
                    return;
                }
                state.suggestTimer = setTimeout(function () {
                    fetch(suggestionsUrl + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                        .then(function (json) {
                            var rows = Array.isArray(json.suggestions) ? json.suggestions : [];
                            suggestionsList.innerHTML = rows.map(function (s) {
                                return '<option value="' + esc(s.value || '') + '" label="' + esc(s.label || '') + '"></option>';
                            }).join('');
                        })
                        .catch(function () {});
                }, 180);
            });
        }
    })();
</script>
@endpush

