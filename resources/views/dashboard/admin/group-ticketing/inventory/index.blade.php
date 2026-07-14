@extends(client_layout('dashboard', 'admin'))

@section('title', 'Group inventory')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.group-ticketing.index') }}">Group Ticketing</a></div>
            <h1 class="jp-page-title">Inventory</h1>
            @if ($lastSyncAt)
                <p class="text-secondary mb-0 small">Last sync: {{ $lastSyncAt->format('Y-m-d H:i') }} · {{ number_format($activeInventoryCount) }} active package(s)</p>
            @else
                <p class="text-secondary mb-0 small">No sync recorded yet</p>
            @endif
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
            <form method="POST" action="{{ route('admin.group-ticketing.inventory.sync') }}">
                @csrf
                <button type="submit" class="jp-btn jp-btn--primary btn-sm">Sync now</button>
            </form>
        </div>
    </div>
@endsection

@section('content')
    @if (session('status'))
        <div class="jp-alert jp-alert--success">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="jp-alert jp-alert--warn">{{ session('warning') }}</div>
    @endif

    <form method="GET" class="card mb-3 ota-admin-filter-bar">
        <div class="card-body py-2">
            <input type="text" name="q" class="jp-control" value="{{ $filters['q'] ?? '' }}" placeholder="Search title, sector, ID">
        </div>
    </form>

    <div class="jp-card">
        <div class="table-responsive">
            <table class="jp-table table-sm ota-admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Sector</th>
                        <th>Date</th>
                        <th>Seats</th>
                        <th>Price</th>
                        <th>Active</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inventories as $inv)
                        <tr>
                            <td>{{ $inv->title }}<br><small class="text-secondary">{{ $inv->public_id }}</small></td>
                            <td>{{ $inv->category?->name ?? '—' }}</td>
                            <td>{{ $inv->sector }}</td>
                            <td>{{ $inv->departure_date?->format('Y-m-d') }}</td>
                            <td>{{ $inv->availableSeats() }} / {{ $inv->total_seats }}</td>
                            <td>{{ number_format((float) $inv->price, 0) }} {{ $inv->currency }}</td>
                            <td>{{ $inv->is_active ? 'Yes' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No inventory. Run sync when Al-Haider is enabled.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($inventories->hasPages())
            <div class="card-footer">{{ $inventories->links() }}</div>
        @endif
    </div>
@endsection
