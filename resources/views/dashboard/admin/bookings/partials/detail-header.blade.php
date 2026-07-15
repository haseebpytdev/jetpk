<div class="card mb-3 booking-command-header">
    <div class="jp-card__body">
        <div class="booking-command-top">
            <span class="booking-command-ref">{{ $bookingRef }}</span>
            <span class="booking-command-pill">{{ ucwords($operationalStatus['label']) }}</span>
            <span class="booking-command-pill">Payment: {{ ucwords($pipelinePayment) }}</span>
            <span class="booking-command-pill">PNR: {{ ucwords($pipelineSupplier) }}</span>
            <span class="booking-command-pill">Ticketing: {{ ucwords($pipelineTicket) }}</span>
        </div>
        <div class="booking-command-meta">
            {{ $booking->route ?? display_unknown() }}{{ display_sep_dot() }}{{ $booking->airline ?? display_unknown() }}{{ display_sep_dot() }}{{ $travelDateLabel }}{{ display_sep_dot() }}{{ $paxCount }} passenger{{ $paxCount === 1 ? '' : 's' }}
        </div>
        <div class="booking-command-amounts">
            Total: Rs {{ number_format($totalDue, 0) }}{{ display_sep_dot() }}Balance: Rs {{ number_format($balanceAmount, 0) }}
        </div>
        <div class="booking-command-meta">
            Paid: Rs {{ number_format($paidAmount, 0) }}{{ display_sep_dot() }}Lead passenger: {{ $leadPaxName }}{{ display_sep_dot() }}Contact: {{ $contactLine }}{{ display_sep_dot() }}Assigned: {{ $booking->assignedStaff?->name ?? 'Unassigned' }}
        </div>

        <div class="booking-quick-actions ota-booking-action-row">
            <div class="booking-quick-action">
                <a href="{{ $overviewPrimaryActionUrl }}" class="jp-btn jp-btn--primary btn-sm">{{ $overviewPrimaryActionLabel }}</a>
            </div>
            <div class="booking-quick-action">
                <form method="post" action="{{ $docInvoiceUrl }}">
                    @csrf
                    <button type="submit" class="jp-btn jp-btn--outline btn-sm" @disabled(! $canGenerateInvoiceAction || ! in_array('generate_invoice', $stateEnabled, true))>Generate invoice</button>
                </form>
            </div>
            <div class="booking-quick-action">
                <a href="{{ $bookingShowUrl }}?tab=payments#payments" class="jp-btn jp-btn--outline btn-sm">Review payment</a>
            </div>
            @if ($p === 'admin' && $assignUrl)
                <div class="booking-quick-action booking-quick-action--assign">
                    @if ($assignableStaff->isEmpty())
                        <button type="button" class="jp-btn jp-btn--ghost btn-sm w-100" disabled title="No staff in this agency">Assign staff</button>
                    @else
                        <form method="post" action="{{ $assignUrl }}" class="ota-assign-staff-inline">
                            @csrf
                            @method('PATCH')
                            <div class="input-group input-group-sm">
                                <select name="staff_user_id" class="jp-control" aria-label="Assign staff">
                                    <option value="">Unassign</option>
                                    @foreach ($assignableStaff as $su)
                                        <option value="{{ $su->id }}" @selected($booking->assigned_staff_id === $su->id)>{{ $su->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="jp-btn jp-btn--ghost">Assign</button>
                            </div>
                        </form>
                    @endif
                </div>
            @endif
            <div class="booking-quick-action">
                <a href="{{ $bookingShowUrl }}?tab=communication#add-note-panel" class="jp-btn jp-btn--outline btn-sm">Add note</a>
            </div>
        </div>
    </div>
</div>
<div class="card mb-3 ota-admin-booking-pipeline" data-booking-pipeline-bar>
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center gap-3 justify-content-between">
            <div class="fw-semibold text-secondary small text-uppercase mb-0">Booking status</div>
            <div class="d-flex flex-wrap gap-2 justify-content-end" role="group" aria-label="Jump to booking tab">
                <button type="button" class="badge bg-blue-lt text-blue border-0 booking-pipeline-jump" data-booking-tab-jump="overview">Booking{{ display_sep_dot() }}{{ $pipelineBooking }}</button>
                <button type="button" class="badge bg-azure-lt text-azure border-0 booking-pipeline-jump" data-booking-tab-jump="payments">Payment{{ display_sep_dot() }}{{ $pipelinePayment }}</button>
                <button type="button" class="badge bg-purple-lt text-purple border-0 booking-pipeline-jump" data-booking-tab-jump="supplier">Supplier / PNR{{ display_sep_dot() }}{{ $pipelineSupplier }}</button>
                <button type="button" class="badge bg-teal-lt text-teal border-0 booking-pipeline-jump" data-booking-tab-jump="ticketing">Ticketing{{ display_sep_dot() }}{{ $pipelineTicket }}</button>
            </div>
        </div>
    </div>
</div>
