@extends(client_layout('dashboard', 'admin'))

@section('title', $booking->reference)

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.group-bookings.index') }}">Group bookings</a></div>
            <h1 class="jp-page-title">{{ $booking->reference }}</h1>
        </div>
    </div>
@endsection

@section('content')
    @if ($userRestriction && $userRestriction->isBlocked())
        <div class="jp-alert jp-alert--warn">Customer is blocked from new group bookings ({{ $userRestriction->unpaid_release_count }} unpaid releases).</div>
    @elseif ($userRestriction && $userRestriction->unpaid_release_count > 0)
        <div class="jp-alert jp-alert--info">Customer has {{ $userRestriction->unpaid_release_count }} unpaid release(s).</div>
    @endif

    @if ($booking->status === \App\Enums\GroupBookingStatus::SupplierReleaseFailed)
        <div class="jp-alert jp-alert--danger">Supplier release failed — local seats remain held until supplier release is confirmed.</div>
        @php($panel = $supplierReleasePanel ?? $booking->supplierReleaseReconciliationPanel())
        <div class="jp-card">
            <div class="jp-card__head"><h3 class="jp-card__title">Supplier release reconciliation</h3></div>
            <div class="jp-card__body">
                <dl class="row mb-3">
                    <dt class="col-sm-3">Booking reference</dt>
                    <dd class="col-sm-9">{{ $panel['booking_reference'] }}</dd>
                    <dt class="col-sm-3">Supplier</dt>
                    <dd class="col-sm-9">{{ $panel['supplier'] }}</dd>
                    <dt class="col-sm-3">Supplier reservation</dt>
                    <dd class="col-sm-9">{{ $panel['supplier_reservation_id'] }}</dd>
                    <dt class="col-sm-3">Requested seats</dt>
                    <dd class="col-sm-9">{{ $panel['requested_seats'] }}</dd>
                    <dt class="col-sm-3">Failure timestamp</dt>
                    <dd class="col-sm-9">{{ $panel['failure_at'] ?? '—' }}</dd>
                    <dt class="col-sm-3">Error class</dt>
                    <dd class="col-sm-9">{{ $panel['error_class'] ?? '—' }}</dd>
                    <dt class="col-sm-3">Safe status</dt>
                    <dd class="col-sm-9">{{ $panel['safe_status'] ?? '—' }}</dd>
                    <dt class="col-sm-3">Reconciliation state</dt>
                    <dd class="col-sm-9">{{ $panel['reconciliation_state'] ?? '—' }}</dd>
                </dl>
                @if ($panel['cancel_gate_enabled'])
                    <form method="POST" action="{{ route('admin.group-bookings.retry-supplier-release', $booking) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-warning">Retry supplier release</button>
                    </form>
                @else
                    <p class="text-muted mb-2">API cancel gate is OFF. After the supplier reservation is cancelled manually, reconcile locally:</p>
                    <form method="POST" action="{{ route('admin.group-bookings.reconcile-manual-supplier-cancel', $booking) }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="confirmation" value="SUPPLIER_MANUAL_CANCEL_CONFIRMED">
                        <input type="text" name="note" class="jp-control d-inline-block w-auto" placeholder="Note (optional)">
                        <button type="submit" class="jp-btn jp-btn--danger">Confirm manual supplier cancel reconcile</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <div class="jp-card">
        <div class="jp-card__body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">{{ $booking->status?->label() }}</dd>
                <dt class="col-sm-3">Customer</dt>
                <dd class="col-sm-9">{{ $booking->user?->name }} ({{ $booking->user?->email }})</dd>
                <dt class="col-sm-3">Package</dt>
                <dd class="col-sm-9">{{ $booking->inventory?->title }}</dd>
                <dt class="col-sm-3">Sector</dt>
                <dd class="col-sm-9">{{ $booking->inventory?->sector ?? '—' }}</dd>
                <dt class="col-sm-3">Seats</dt>
                <dd class="col-sm-9">{{ $booking->seat_count }}</dd>
                <dt class="col-sm-3">Total</dt>
                <dd class="col-sm-9">{{ number_format((float) $booking->total_amount, 0) }} {{ $booking->currency }}</dd>
                <dt class="col-sm-3">Reservation created</dt>
                <dd class="col-sm-9">{{ $booking->reservation_created_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                <dt class="col-sm-3">Payment deadline</dt>
                <dd class="col-sm-9">{{ $booking->expires_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                <dt class="col-sm-3">Payment submitted</dt>
                <dd class="col-sm-9">{{ $booking->payment_submitted_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                <dt class="col-sm-3">Payment method</dt>
                <dd class="col-sm-9">{{ $booking->payment_method ?? '—' }}</dd>
                <dt class="col-sm-3">Payment reference</dt>
                <dd class="col-sm-9">{{ $booking->payment_reference ?? '—' }}</dd>
                <dt class="col-sm-3">Released at</dt>
                <dd class="col-sm-9">{{ $booking->released_at?->format('Y-m-d H:i') ?? '—' }} {{ $booking->release_reason ? '('.$booking->release_reason.')' : '' }}</dd>
                <dt class="col-sm-3">Supplier reservation</dt>
                <dd class="col-sm-9">{{ $booking->supplier_reservation_id ?? '—' }}</dd>
                @if ($booking->supplier_release_failed_at)
                    <dt class="col-sm-3">Supplier release failed</dt>
                    <dd class="col-sm-9">{{ $booking->supplier_release_failed_at->format('Y-m-d H:i') }} — {{ Str::limit($booking->supplier_release_response ?? '', 120) }}</dd>
                @endif
            </dl>
        </div>
    </div>

    @if ($booking->status === \App\Enums\GroupBookingStatus::ManualPaymentPendingReview)
        <div class="jp-card">
            <div class="jp-card__head"><h3 class="jp-card__title">Manual payment review</h3></div>
            <div class="jp-card__body">
                @if ($booking->payment_proof_path)
                    <p><a href="{{ asset('storage/'.$booking->payment_proof_path) }}" target="_blank" rel="noopener">View payment proof</a></p>
                @endif
                <form method="POST" action="{{ route('admin.group-bookings.verify-payment', $booking) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success">Verify payment</button>
                </form>
                <form method="POST" action="{{ route('admin.group-bookings.reject-payment', $booking) }}" class="d-inline ms-2">
                    @csrf
                    <input type="text" name="rejection_note" class="jp-control d-inline-block w-auto" placeholder="Rejection note (optional)">
                    <button type="submit" class="jp-btn jp-btn--danger">Reject payment</button>
                </form>
            </div>
        </div>
    @endif

    <div class="jp-card">
        <div class="jp-card__head"><h3 class="jp-card__title">Passengers</h3></div>
        <ul class="list-group list-group-flush">
            @foreach ($booking->passengers as $passenger)
                <li class="list-group-item">{{ $passenger->fullName() }} · {{ $passenger->passport_number }}</li>
            @endforeach
        </ul>
    </div>
@endsection
