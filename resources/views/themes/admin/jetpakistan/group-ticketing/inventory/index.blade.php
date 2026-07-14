@extends(client_layout('dashboard', 'admin'))

@section('title', 'Group inventory')

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-cell-sub"><a href="{{ client_route('admin.group-ticketing.index') }}">Group Ticketing</a></p>
            <h1>Inventory</h1>
            <p>
                @if ($lastSyncAt)
                    Last sync: {{ $lastSyncAt->format('Y-m-d H:i') }} · {{ number_format($activeInventoryCount) }} active package(s)
                @else
                    No sync recorded yet
                @endif
            </p>
        </div>
        <form method="POST" action="{{ client_route('admin.group-ticketing.inventory.sync') }}">
            @csrf
            <button type="submit" class="jp-btn jp-btn--sm">Sync now</button>
        </form>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

<form method="GET" class="jp-filterbar" style="margin-bottom: 16px;">
    <div class="jp-filterbar__field" style="flex: 1;">
        <label class="jp-label" for="inventory-search">Search</label>
        <input type="text" id="inventory-search" name="q" class="jp-input" value="{{ $filters['q'] ?? '' }}" placeholder="Search title, sector, ID">
    </div>
    <div class="jp-filterbar__actions">
        <button type="submit" class="jp-btn jp-btn--sm">Search</button>
    </div>
</form>

<div class="jp-dtable-wrap">
    <table class="jp-dtable">
        <thead>
            <tr>
                <th>Title</th>
                <th>Category</th>
                <th>Sector</th>
                <th>Date</th>
                <th class="num">Seats</th>
                <th class="num">Price</th>
                <th>Active</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($inventories as $inv)
                <tr>
                    <td data-label="Title">
                        {{ $inv->title }}
                        <span class="jp-cell-sub">{{ $inv->public_id }}</span>
                    </td>
                    <td data-label="Category">{{ $inv->category?->name ?? '—' }}</td>
                    <td data-label="Sector">{{ $inv->sector }}</td>
                    <td data-label="Date">{{ $inv->departure_date?->format('Y-m-d') }}</td>
                    <td data-label="Seats" class="num">{{ $inv->availableSeats() }} / {{ $inv->total_seats }}</td>
                    <td data-label="Price" class="num">{{ number_format((float) $inv->price, 0) }} {{ $inv->currency }}</td>
                    <td data-label="Active">{{ $inv->is_active ? 'Yes' : 'No' }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-themes.admin.jetpakistan.components.empty-state title="No inventory" message="Run sync when Al-Haider is enabled." /></td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($inventories->hasPages())
        <div class="jp-pagination">{{ $inventories->links() }}</div>
    @endif
</div>
@endsection
