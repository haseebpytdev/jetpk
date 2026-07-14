@extends(client_layout('dashboard', 'admin'))

@section('title', 'Agent commission detail')

@section('page-header')
    <h1 class="jp-page-title">Commission ledger - {{ $agent->user?->name ?? $agent->code }}</h1>
@endsection

@section('content')
    @if (session('status'))
        <div class="jp-alert jp-alert--success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="mb-3"><strong>Current balance:</strong> Rs {{ number_format($balance, 2) }}</div>
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="jp-card"><div class="jp-card__body">
                <h4 class="mb-2">Adjustment</h4>
                <form method="post" action="{{ route('admin.commissions.adjustments.store', $agent) }}">
                    @csrf
                    <input class="jp-control mb-2" type="number" step="0.01" name="amount" placeholder="Amount" required>
                    <input class="jp-control mb-2" type="text" name="description" placeholder="Description">
                    <button class="jp-btn jp-btn--outline w-100" type="submit">Record adjustment</button>
                </form>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="jp-card"><div class="jp-card__body">
                <h4 class="mb-2">Payout</h4>
                <form method="post" action="{{ route('admin.commissions.payouts.store', $agent) }}">
                    @csrf
                    <input class="jp-control mb-2" type="number" step="0.01" name="amount" placeholder="Amount" required>
                    <input class="jp-control mb-2" type="text" name="description" placeholder="Description">
                    <button class="jp-btn jp-btn--outline w-100" type="submit">Record payout</button>
                </form>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="jp-card"><div class="jp-card__body">
                <h4 class="mb-2">Statement</h4>
                <form method="post" action="{{ route('admin.commissions.statements.store', $agent) }}">
                    @csrf
                    <input class="jp-control mb-2" type="date" name="period_start">
                    <input class="jp-control mb-2" type="date" name="period_end">
                    <button class="jp-btn jp-btn--outline w-100" type="submit">Generate statement</button>
                </form>
            </div></div>
        </div>
    </div>

    <div class="jp-card">
        <div class="jp-card__head"><h3 class="jp-card__title mb-0">Entries</h3></div>
        <div class="table-responsive">
            <table class="table card-jp-table">
                <thead>
                <tr><th>Date</th><th>Type</th><th>Status</th><th>Booking</th><th>Amount</th><th>Action</th></tr>
                </thead>
                <tbody>
                @forelse($agent->commissionEntries->sortByDesc('created_at') as $entry)
                    <tr>
                        <td>{{ $entry->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-capitalize">{{ $entry->type->value }}</td>
                        <td class="text-capitalize">{{ $entry->status->value }}</td>
                        <td>{{ $entry->booking?->booking_reference ?? 'N/A' }}</td>
                        <td>Rs {{ number_format((float) $entry->commission_amount, 2) }}</td>
                        <td>
                            @if($entry->status->value === 'pending')
                                <form method="post" action="{{ route('admin.commissions.entries.approve', $entry) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success" type="submit">Approve</button></form>
                                <form method="post" action="{{ route('admin.commissions.entries.reject', $entry) }}" class="d-inline">@csrf<input type="hidden" name="reason" value="Rejected by admin"><button class="btn btn-sm btn-outline-danger" type="submit">Reject</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-secondary">No entries found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="jp-card">
        <div class="jp-card__head"><h3 class="jp-card__title mb-0">Statements</h3></div>
        <div class="table-responsive">
            <table class="table card-jp-table">
                <thead><tr><th>Statement</th><th>Period</th><th>Status</th><th>Closing balance</th></tr></thead>
                <tbody>
                @forelse($agent->commissionStatements->sortByDesc('created_at') as $statement)
                    <tr>
                        <td>{{ $statement->statement_number ?? 'N/A' }}</td>
                        <td>{{ $statement->period_start?->format('Y-m-d') ?? 'N/A' }} - {{ $statement->period_end?->format('Y-m-d') ?? 'N/A' }}</td>
                        <td class="text-capitalize">{{ $statement->status->value }}</td>
                        <td>Rs {{ number_format((float) $statement->closing_balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-secondary">No statements yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

