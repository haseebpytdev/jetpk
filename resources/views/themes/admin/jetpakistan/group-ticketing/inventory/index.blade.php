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
            <button type="submit" class="jp-btn jp-btn--sm">Sync Al-Haider</button>
        </form>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

<div class="jp-card" style="margin-bottom: 20px;" data-testid="admin-manual-local-create">
    <h2 style="margin: 0 0 8px;">Create manual / local QA group</h2>
    <p class="jp-cell-sub" style="margin-bottom: 12px;">
        QA_GROUP_SOURCE=MANUAL_LOCAL · no Al-Haider binding · hidden from public search unless email is in OTA_GROUP_QA_VIEWER_EMAILS.
        Defaults inactive (unpublished).
    </p>
    <form method="POST" action="{{ client_route('admin.group-ticketing.inventory.manual-local.store') }}" class="jp-form-grid">
        @csrf
        <div class="jp-filterbar" style="flex-wrap: wrap; gap: 12px;">
            <div class="jp-filterbar__field">
                <label class="jp-label" for="ml-title">Title</label>
                <input id="ml-title" name="title" class="jp-input" required maxlength="200" value="{{ old('title', 'QA GROUP A — B2C') }}" placeholder="QA GROUP A — B2C">
            </div>
            <div class="jp-filterbar__field">
                <label class="jp-label" for="ml-sector">Sector</label>
                <input id="ml-sector" name="sector" class="jp-input" required pattern="[A-Za-z]{3}-[A-Za-z]{3}" value="{{ old('sector', 'LHE-DXB') }}" placeholder="LHE-DXB">
            </div>
            <div class="jp-filterbar__field">
                <label class="jp-label" for="ml-airline">Airline</label>
                <input id="ml-airline" name="airline_name" class="jp-input" value="{{ old('airline_name', 'QA LOCAL') }}">
            </div>
            <div class="jp-filterbar__field">
                <label class="jp-label" for="ml-depart">Departure</label>
                <input id="ml-depart" type="date" name="departure_date" class="jp-input" required value="{{ old('departure_date') }}">
            </div>
            <div class="jp-filterbar__field">
                <label class="jp-label" for="ml-seats">Seats</label>
                <input id="ml-seats" type="number" name="total_seats" class="jp-input" min="1" max="20" required value="{{ old('total_seats', 5) }}">
            </div>
            <div class="jp-filterbar__field">
                <label class="jp-label" for="ml-price">Price / seat</label>
                <input id="ml-price" type="number" name="price" class="jp-input" min="1" step="1" required value="{{ old('price', 50000) }}">
            </div>
            <div class="jp-filterbar__field">
                <label class="jp-label" for="ml-audience">Audience tag</label>
                <select id="ml-audience" name="audience" class="jp-input">
                    <option value="b2c" @selected(old('audience', 'b2c') === 'b2c')>B2C / Customer</option>
                    <option value="b2b" @selected(old('audience') === 'b2b')>B2B / Agent</option>
                    <option value="boundary" @selected(old('audience') === 'boundary')>Boundary / sold-out fixture</option>
                </select>
            </div>
            <div class="jp-filterbar__field" style="align-self: end;">
                <label class="jp-label"><input type="checkbox" name="is_active" value="1" @checked(old('is_active'))> Publish for QA viewers</label>
            </div>
            <div class="jp-filterbar__actions" style="align-self: end;">
                <button type="submit" class="jp-btn jp-btn--sm" data-testid="admin-manual-local-submit">Create QA group</button>
            </div>
        </div>
    </form>
</div>

<form method="GET" class="jp-filterbar" style="margin-bottom: 16px;">
    <div class="jp-filterbar__field" style="flex: 1;">
        <label class="jp-label" for="inventory-search">Search</label>
        <input type="text" id="inventory-search" name="q" class="jp-input" value="{{ $filters['q'] ?? '' }}" placeholder="Search title, sector, ID">
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="inventory-source">Source</label>
        <select id="inventory-source" name="source" class="jp-input">
            <option value="">All</option>
            <option value="manual_local" @selected(($filters['source'] ?? '') === 'manual_local')>Manual / local QA</option>
        </select>
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
                <th>Source</th>
                <th>Category</th>
                <th>Sector</th>
                <th>Date</th>
                <th class="num">Seats</th>
                <th class="num">Price</th>
                <th>Active</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($inventories as $inv)
                <tr>
                    <td data-label="Title">
                        {{ $inv->title }}
                        <span class="jp-cell-sub">{{ $inv->public_id }}</span>
                    </td>
                    <td data-label="Source">{{ $inv->supplier }}</td>
                    <td data-label="Category">{{ $inv->category?->name ?? '—' }}</td>
                    <td data-label="Sector">{{ $inv->sector }}</td>
                    <td data-label="Date">{{ $inv->departure_date?->format('Y-m-d') }}</td>
                    <td data-label="Seats" class="num">{{ $inv->availableSeats() }} / {{ $inv->total_seats }}</td>
                    <td data-label="Price" class="num">{{ number_format((float) $inv->price, 0) }} {{ $inv->currency }}</td>
                    <td data-label="Active">{{ $inv->is_active ? 'Yes' : 'No' }}</td>
                    <td data-label="Actions">
                        @if ($inv->isManualLocal())
                            <form method="POST" action="{{ client_route('admin.group-ticketing.inventory.manual-local.update', $inv) }}" style="display:inline;">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $inv->is_active ? 0 : 1 }}">
                                <button type="submit" class="jp-btn jp-btn--sm">{{ $inv->is_active ? 'Unpublish' : 'Publish QA' }}</button>
                            </form>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><x-themes.admin.jetpakistan.components.empty-state title="No inventory" message="Run sync when Al-Haider is enabled, or create a manual/local QA group above." /></td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($inventories->hasPages())
        <div class="jp-pagination">{{ $inventories->links() }}</div>
    @endif
</div>
@endsection
