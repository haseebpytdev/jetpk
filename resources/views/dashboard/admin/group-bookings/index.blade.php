@extends(client_layout('dashboard', 'admin'))

@section('title', 'Group bookings')

@section('page-header')
    <div class="jp-between">
        <div class="col"><h1 class="jp-page-title">Group bookings</h1></div>
        <div class="col-auto">
            <a href="{{ route('admin.group-bookings.restrictions') }}" class="jp-btn jp-btn--ghost">Restricted users</a>
        </div>
    </div>
@endsection

@section('content')
    <form method="GET" class="jp-card">
        <div class="card-body jp-form-grid jp-form-grid--filter">
            <div class="col-md-4">
                <label class="jp-label">Search</label>
                <input type="text" name="q" class="jp-control" value="{{ $filters['q'] ?? '' }}" placeholder="Reference or customer">
            </div>
            <div class="col-md-3">
                <label class="jp-label">Status</label>
                <select name="status" class="jp-control">
                    <option value="">All</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="jp-btn jp-btn--primary">Filter</button>
            </div>
        </div>
    </form>

    <div class="jp-card">
        <div class="table-responsive">
            <table class="jp-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Customer</th>
                        <th>Package</th>
                        <th>Seats</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        <tr>
                            <td>{{ $booking->reference }}</td>
                            <td>{{ $booking->user?->name }}</td>
                            <td>{{ $booking->inventory?->title }}</td>
                            <td>{{ $booking->seat_count }}</td>
                            <td>{{ number_format((float) $booking->total_amount, 0) }} {{ $booking->currency }}</td>
                            <td><span class="badge bg-secondary-lt">{{ $booking->status?->label() }}</span></td>
                            <td><a href="{{ route('admin.group-bookings.show', $booking) }}" class="jp-btn jp-btn--sm jp-btn--outline">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No group bookings yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bookings->hasPages())
            <div class="card-footer">{{ $bookings->links() }}</div>
        @endif
    </div>
@endsection
