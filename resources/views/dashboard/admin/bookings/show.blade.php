@extends(client_layout('dashboard', 'admin'))

@php
    use App\Http\Controllers\Admin\BookingManagementController;
    use App\Support\Bookings\BookingItineraryOverviewPresenter;
    use App\Support\Bookings\BookingOperationalStatus;
    use App\Support\Bookings\DocumentOperationalState;
    use App\Support\Bookings\PaymentOperationalStatus;
    use App\Support\Bookings\SabrePassengerRecordsItineraryGuardDigest;
    use App\Support\Bookings\SupplierOperationalStatus;
    use App\Support\Bookings\TicketingOperationalStatus;
    use App\Support\Bookings\PiaNdcVoidLocalReconciliation;
    use App\Support\Bookings\SabreOfferRefreshAcceptance;
    use App\Support\Bookings\TicketingReadinessPresenter;
    $p = $portal ?? 'admin';
    $statusUrl = $p === 'staff' ? route('staff.bookings.status', $booking) : route('admin.bookings.status', $booking);
    $noteUrl = $p === 'staff' ? route('staff.bookings.notes', $booking) : route('admin.bookings.notes', $booking);
    $assignUrl = $p === 'admin' ? route('admin.bookings.assign-staff', $booking) : null;
    $listUrl = $p === 'staff' ? route('staff.bookings.index') : route('admin.bookings');
    $docConfirmationUrl = $p === 'staff' ? route('staff.bookings.documents.confirmation', $booking) : route('admin.bookings.documents.confirmation', $booking);
    $docInvoiceUrl = $p === 'staff' ? route('staff.bookings.documents.invoice', $booking) : route('admin.bookings.documents.invoice', $booking);
    $docItineraryUrl = $p === 'staff' ? route('staff.bookings.documents.ticket-itinerary', $booking) : route('admin.bookings.documents.ticket-itinerary', $booking);
    $docDownloadRoute = $p === 'staff' ? 'staff.bookings.documents.download' : 'admin.bookings.documents.download';
    $docReceiptRoute = $p === 'staff' ? 'staff.bookings.payments.documents.receipt' : 'admin.bookings.payments.documents.receipt';
    $cancelStoreUrl = $p === 'staff' ? route('staff.bookings.cancellations.store', $booking) : route('admin.bookings.cancellations.store', $booking);
    $refundStoreUrl = $p === 'staff' ? route('staff.bookings.refunds.store', $booking) : route('admin.bookings.refunds.store', $booking);
    $communicationSendUrl = $p === 'admin' ? route('admin.bookings.communication.send', $booking) : null;
    $syncPnrItineraryRoute = $p === 'staff' ? route('staff.bookings.sync-pnr-itinerary', $booking) : route('admin.bookings.sync-pnr-itinerary', $booking);
    $prepareSupplierContextRoute = $p === 'staff' ? route('staff.bookings.prepare-supplier-pnr-context', $booking) : route('admin.bookings.prepare-supplier-pnr-context', $booking);
@endphp

@section('title', 'Booking '.$booking->booking_reference ?: '#'.$booking->id)

@push('styles')
<style>
    .booking-detail .card { border: 1px solid rgba(98,105,118,.16); }
    .booking-detail h3 { font-size: 1rem; font-weight: 600; }
    .booking-command-header { border: 1px solid rgba(59,130,246,.22); box-shadow: 0 6px 24px rgba(30,64,175,.08); }
    .booking-command-top { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .65rem; }
    .booking-command-ref { font-size: 1.08rem; font-weight: 700; color: #0f172a; }
    .booking-command-pill { display: inline-flex; align-items: center; font-size: .74rem; font-weight: 700; padding: .22rem .52rem; border-radius: 999px; border: 1px solid rgba(148,163,184,.4); background: #f8fafc; color: #334155; }
    .booking-command-meta { color: #475569; font-size: .9rem; margin-top: .35rem; }
    .booking-command-amounts { color: #0f172a; font-size: .9rem; font-weight: 600; margin-top: .22rem; }
    .booking-quick-actions { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .55rem; margin-top: .9rem; }
    .booking-quick-action { border: 1px solid rgba(148,163,184,.3); border-radius: .55rem; background: #fff; padding: .5rem; }
    .booking-quick-action .btn { width: 100%; }
    .booking-quick-action-reason { font-size: .72rem; color: #64748b; margin-top: .32rem; line-height: 1.35; }
    .booking-tabs-wrap {
        margin-bottom: 1rem;
        position: sticky;
        top: 0;
        z-index: 1020;
        background: var(--tblr-body-bg, #fff);
        padding-top: .35rem;
        padding-bottom: .35rem;
        margin-left: -.25rem;
        margin-right: -.25rem;
        padding-left: .25rem;
        padding-right: .25rem;
    }
    .booking-tabs-wrap .nav-link { font-size: .82rem; font-weight: 600; border-radius: 999px; padding: .38rem .72rem; color: #475569; }
    .booking-tabs-wrap .nav-link.active { background: #e0edff; color: #1d4ed8; border-color: #93c5fd; }
    .booking-tab-hidden { display: none !important; }
    .booking-pipeline-jump { cursor: pointer; }
    .overview-summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .55rem .9rem; }
    .overview-kv { display: flex; justify-content: space-between; gap: .75rem; font-size: .86rem; border-bottom: 1px dashed rgba(148,163,184,.3); padding-bottom: .22rem; }
    .overview-kv:last-child { border-bottom: 0; }
    .overview-kv .label { color: #64748b; font-weight: 600; }
    .overview-kv .value { color: #0f172a; font-weight: 700; text-align: right; }
    #sabre-capability-posture-panel .overview-kv .label,
    #sabre-capability-posture-panel .overview-kv .value { overflow-wrap: anywhere; word-break: break-word; }
    #sabre-capability-posture-panel .overview-kv .value { min-width: 0; flex: 1 1 42%; }
    #sabre-continuity-diagnostic-panel .overview-kv .label,
    #sabre-continuity-diagnostic-panel .overview-kv .value { overflow-wrap: anywhere; word-break: break-word; }
    #sabre-continuity-diagnostic-panel .overview-kv .value { min-width: 0; flex: 1 1 42%; }
    #sabre-continuity-diagnostic-panel .continuity-field-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr)); gap: 0.35rem 0.75rem; }
    #sabre-continuity-diagnostic-panel .continuity-field-chip { font-size: 0.75rem; line-height: 1.3; }
    .lifecycle-track { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .35rem; margin-top: .6rem; }
    .lifecycle-step { text-align: center; font-size: .72rem; padding: .28rem .2rem; border-radius: 999px; border: 1px solid #cbd5e1; color: #64748b; background: #f8fafc; }
    .lifecycle-step.is-done { border-color: #93c5fd; color: #1d4ed8; background: #e0edff; }
    .passenger-item { border: 1px solid rgba(148,163,184,.28); border-radius: .6rem; padding: .7rem .75rem; margin-bottom: .65rem; }
    .passenger-item:last-child { margin-bottom: 0; }
    .passenger-head { display: flex; flex-wrap: wrap; gap: .35rem .45rem; align-items: center; margin-bottom: .45rem; }
    .passenger-name { font-size: .92rem; font-weight: 700; color: #0f172a; }
    .passenger-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .35rem .9rem; }
    .passenger-kv { display: flex; justify-content: space-between; gap: .7rem; border-bottom: 1px dashed rgba(148,163,184,.25); padding-bottom: .15rem; font-size: .82rem; }
    .passenger-kv .label { color: #64748b; font-weight: 600; }
    .passenger-kv .value { color: #0f172a; font-weight: 600; text-align: right; word-break: break-word; }
    .timeline-entry { border-left: 2px solid var(--tblr-primary, #206bc4); padding-left: 1rem; margin-bottom: 1rem; }
    .audit-row { font-size: .8125rem; border-bottom: 1px dashed rgba(98,105,118,.15); padding: .35rem 0; }
    @media (max-width: 767px) {
        .booking-quick-actions { grid-template-columns: 1fr; }
        .overview-summary-grid { grid-template-columns: 1fr; }
        .lifecycle-track { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .passenger-grid { grid-template-columns: 1fr; }
        .booking-detail .card-body .btn,
        .booking-detail .card-body .jp-control,
        .booking-detail .card-body .jp-control {
            width: 100%;
        }
        .booking-detail .card-body .btn-sm {
            padding-top: .45rem;
            padding-bottom: .45rem;
        }
        .booking-detail .badge {
            white-space: normal;
        }
    }
</style>
@endpush

@section('page-header')
    <div class="jp-between ota-admin-page-header">
        <div class="col">
            <div class="page-pretitle"><a href="{{ $listUrl }}" class="text-secondary">Bookings</a></div>
            <h1 class="jp-page-title">
                {{ $booking->booking_reference ?: 'Draft #'.$booking->id }}
                @if($p === 'admin')
                    <x-dashboard.status-badge :status="$booking->status" class="ms-2" />
                @else
                    <span class="badge bg-azure text-capitalize ms-2">{{ str_replace('_', ' ', $booking->status->value) }}</span>
                @endif
            </h1>
            <div class="text-secondary mt-1">Payment: <strong class="text-capitalize">{{ str_replace('_', ' ', $booking->payment_status ?? 'unpaid') }}</strong>
                @if($booking->assignedStaff)
                   {{ display_sep_dot() }}Assigned: <strong>{{ $booking->assignedStaff->name }}</strong>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('content')
    @include('dashboard.admin.bookings.partials.detail-body')
@endsection

@push('scripts')
<script>
    (function () {
        var VALID_TABS = ['overview', 'passengers', 'payments', 'supplier', 'ticketing', 'documents', 'refunds', 'communication', 'audit'];

        var tabsRoot = document.querySelector('[data-booking-tabs]');
        var container = document.querySelector('[data-booking-tab-container]');
        if (!tabsRoot || !container) return;

        var buttons = Array.prototype.slice.call(tabsRoot.querySelectorAll('[data-tab-target]'));
        var sections = Array.prototype.slice.call(container.querySelectorAll('[data-tab-section]'));

        function scrollToHash() {
            var h = window.location.hash;
            if (!h || h.length < 2) return;
            try {
                var el = document.querySelector(h);
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (e) {}
        }

        function activateTab(tab, opts) {
            opts = opts || {};
            var target = tab || 'overview';
            if (VALID_TABS.indexOf(target) === -1) target = 'overview';
            buttons.forEach(function (btn) {
                btn.classList.toggle('active', btn.getAttribute('data-tab-target') === target);
            });
            sections.forEach(function (sec) {
                sec.classList.toggle('booking-tab-hidden', sec.getAttribute('data-tab-section') !== target);
            });
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', target);
                window.history.replaceState({}, '', url.toString());
            } catch (e) {}
            if (opts.scrollToHash) {
                requestAnimationFrame(function () {
                    scrollToHash();
                });
            }
        }

        function resolveInitialTab() {
            try {
                var url = new URL(window.location.href);
                var q = (url.searchParams.get('tab') || '').trim();
                if (q && VALID_TABS.indexOf(q) !== -1) return q;
            } catch (e) {}
            try {
                var h = (window.location.hash || '').replace(/^#/, '');
                if (!h) return 'overview';
                var byId = document.getElementById(h);
                if (byId) {
                    var sec = byId.closest('[data-tab-section]');
                    if (sec) {
                        var t = sec.getAttribute('data-tab-section');
                        if (t && VALID_TABS.indexOf(t) !== -1) return t;
                    }
                }
                if (VALID_TABS.indexOf(h) !== -1) return h;
            } catch (e2) {}
            return 'overview';
        }

        tabsRoot.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-tab-target]');
            if (!btn) return;
            activateTab(btn.getAttribute('data-tab-target'), { scrollToHash: false });
        });

        Array.prototype.forEach.call(document.querySelectorAll('[data-booking-tab-jump]'), function (btn) {
            btn.addEventListener('click', function () {
                var t = btn.getAttribute('data-booking-tab-jump');
                if (t) activateTab(t, { scrollToHash: false });
            });
        });

        activateTab(resolveInitialTab(), { scrollToHash: true });
    })();

    document.querySelectorAll('.ota-admin-supplier-action-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            var label = btn.textContent;
            btn.textContent = 'Working…';
            window.setTimeout(function () {
                if (btn.disabled) {
                    btn.disabled = false;
                    btn.textContent = label;
                }
            }, 120000);
        });
    });
</script>
@endpush

