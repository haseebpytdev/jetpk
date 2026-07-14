@extends(client_layout('dashboard', 'admin'))

@php
    $p = $portal ?? 'admin';
    $statusUrl = $p === 'staff' ? client_route('staff.bookings.status', $booking) : client_route('admin.bookings.status', $booking);
    $noteUrl = $p === 'staff' ? client_route('staff.bookings.notes', $booking) : client_route('admin.bookings.notes', $booking);
    $assignUrl = $p === 'admin' ? client_route('admin.bookings.assign-staff', $booking) : null;
    $listUrl = $p === 'staff' ? client_route('staff.bookings.index') : client_route('admin.bookings');
    $docConfirmationUrl = $p === 'staff' ? client_route('staff.bookings.documents.confirmation', $booking) : client_route('admin.bookings.documents.confirmation', $booking);
    $docInvoiceUrl = $p === 'staff' ? client_route('staff.bookings.documents.invoice', $booking) : client_route('admin.bookings.documents.invoice', $booking);
    $docItineraryUrl = $p === 'staff' ? client_route('staff.bookings.documents.ticket-itinerary', $booking) : client_route('admin.bookings.documents.ticket-itinerary', $booking);
    $docDownloadRoute = $p === 'staff' ? 'staff.bookings.documents.download' : 'admin.bookings.documents.download';
    $docReceiptRoute = $p === 'staff' ? 'staff.bookings.payments.documents.receipt' : 'admin.bookings.payments.documents.receipt';
    $cancelStoreUrl = $p === 'staff' ? client_route('staff.bookings.cancellations.store', $booking) : client_route('admin.bookings.cancellations.store', $booking);
    $refundStoreUrl = $p === 'staff' ? client_route('staff.bookings.refunds.store', $booking) : client_route('admin.bookings.refunds.store', $booking);
    $communicationSendUrl = $p === 'admin' ? client_route('admin.bookings.communication.send', $booking) : null;
    $syncPnrItineraryRoute = $p === 'staff' ? client_route('staff.bookings.sync-pnr-itinerary', $booking) : client_route('admin.bookings.sync-pnr-itinerary', $booking);
    $prepareSupplierContextRoute = $p === 'staff' ? client_route('staff.bookings.prepare-supplier-pnr-context', $booking) : client_route('admin.bookings.prepare-supplier-pnr-context', $booking);
@endphp

@section('title', 'Booking '.$booking->booking_reference ?: '#'.$booking->id)

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-backlink"><a href="{{ $listUrl }}">← Bookings</a></p>
            <h1>{{ $booking->booking_reference ?: 'Draft #'.$booking->id }}</h1>
            <p>
                Payment: <strong>{{ ucfirst(str_replace('_', ' ', $booking->payment_status ?? 'unpaid')) }}</strong>
                @if($booking->assignedStaff)
                    · Assigned: <strong>{{ $booking->assignedStaff->name }}</strong>
                @endif
            </p>
        </div>
        @if($p === 'admin')
            <x-dashboard.status-badge :status="$booking->status" />
        @endif
    </div>
@endsection

@section('content')
    <div class="jp-booking-unified" data-jp-booking-unified>
        @include('dashboard.admin.bookings.partials.detail-body')
    </div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

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

    document.querySelectorAll('[data-booking-tab-jump]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = btn.getAttribute('data-booking-tab-jump');
            if (!target) return;
            var section = document.querySelector('[data-tab-section="' + target + '"]');
            if (section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
})();
</script>
@endpush
