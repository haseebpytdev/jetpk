@extends(client_layout('dashboard', 'admin'))

@section('title', 'Group Ticketing')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Group Ticketing</h1>
            <p>Homepage tiles and inventory are driven by synced group packages.</p>
        </div>
    </div>
@endsection

@section('content')
<div class="jp-alert jp-alert--info">
    Categories and tiles are generated automatically from live inventory. Customize tile images and titles on the Homepage Tiles page.
</div>

<div class="jp-kpis jp-kpis--4">
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format($activeInventoryCount) }}</div><div class="jp-kpi__l">Active packages</div></div>
    <div class="jp-kpi t-blue"><div class="jp-kpi__v">{{ number_format($categoryCount) }}</div><div class="jp-kpi__l">API categories</div></div>
    <div class="jp-kpi">
        <div class="jp-kpi__v">
            @if ($lastSyncAt)
                <span style="font-size: 1rem;">{{ $lastSyncAt->format('Y-m-d H:i') }}</span>
            @else
                —
            @endif
        </div>
        <div class="jp-kpi__l">Last inventory sync</div>
    </div>
</div>

<div class="jp-settings-grid">
    <a href="{{ client_route('admin.group-ticketing.tiles.index') }}" class="jp-settings-card">
        <h3 class="jp-settings-card__title">Homepage Tiles</h3>
        <p class="jp-settings-card__desc">Upload images and customize titles for homepage group category tiles.</p>
    </a>
    <a href="{{ client_route('admin.group-ticketing.inventory.index') }}" class="jp-settings-card">
        <h3 class="jp-settings-card__title">Inventory</h3>
        <p class="jp-settings-card__desc">View synced packages and run a manual sync when needed.</p>
    </a>
    <a href="{{ client_route('admin.group-ticketing.categories.index') }}" class="jp-settings-card">
        <h3 class="jp-settings-card__title">API Categories</h3>
        <p class="jp-settings-card__desc">Read-only list of categories derived from synced inventory.</p>
    </a>
</div>
@endsection
