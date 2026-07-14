@extends(client_layout('dashboard', 'staff'))

@php
    $portal = 'staff';
    $p = 'staff';
    $statusUrl = client_route('staff.bookings.status', $booking);
    $noteUrl = client_route('staff.bookings.notes', $booking);
    $assignUrl = null;
    $listUrl = client_route('staff.bookings.index');
    $docConfirmationUrl = client_route('staff.bookings.documents.confirmation', $booking);
    $docInvoiceUrl = client_route('staff.bookings.documents.invoice', $booking);
    $docItineraryUrl = client_route('staff.bookings.documents.ticket-itinerary', $booking);
    $docDownloadRoute = 'staff.bookings.documents.download';
    $docReceiptRoute = 'staff.bookings.payments.documents.receipt';
    $cancelStoreUrl = client_route('staff.bookings.cancellations.store', $booking);
    $refundStoreUrl = client_route('staff.bookings.refunds.store', $booking);
    $communicationSendUrl = null;
    $syncPnrItineraryRoute = client_route('staff.bookings.sync-pnr-itinerary', $booking);
    $prepareSupplierContextRoute = client_route('staff.bookings.prepare-supplier-pnr-context', $booking);
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
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

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
            if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();
</script>
@endpush
