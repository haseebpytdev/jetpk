@extends(client_layout('dashboard', 'admin'))

@section('title', 'Group Ticketing')

@section('page-header')
    <div class="jp-between ota-admin-page-header">
        <div class="col">
            <h1 class="jp-page-title">Group Ticketing</h1>
            <p class="text-secondary mb-0">Homepage tiles and inventory are driven by synced group packages.</p>
        </div>
    </div>
@endsection

@section('content')
    <div class="jp-alert jp-alert--info mb-3">
        Categories and tiles are generated automatically from live inventory. Customize tile images and titles on the Homepage Tiles page.
    </div>

    <div class="row row-cards mb-3 ota-admin-kpi-card">
        <div class="col-md-4">
            <div class="jp-card">
                <div class="jp-card__body">
                    <div class="text-secondary small">Active packages</div>
                    <div class="h2 mb-0">{{ number_format($activeInventoryCount) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="jp-card">
                <div class="jp-card__body">
                    <div class="text-secondary small">API categories</div>
                    <div class="h2 mb-0">{{ number_format($categoryCount) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="jp-card">
                <div class="jp-card__body">
                    <div class="text-secondary small">Last inventory sync</div>
                    <div class="h4 mb-0">
                        @if ($lastSyncAt)
                            {{ $lastSyncAt->format('Y-m-d H:i') }}
                        @else
                            <span class="text-secondary">Not synced yet</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-md-4">
            <div class="card ota-admin-compact-card">
                <div class="jp-card__body">
                    <h3 class="jp-card__title">Homepage Tiles</h3>
                    <p class="text-secondary">Upload images and customize titles for homepage group category tiles.</p>
                    <a href="{{ route('admin.group-ticketing.tiles.index') }}" class="jp-btn jp-btn--primary btn-sm">Manage tiles</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card ota-admin-compact-card">
                <div class="jp-card__body">
                    <h3 class="jp-card__title">Inventory</h3>
                    <p class="text-secondary">View synced packages and run a manual sync when needed.</p>
                    <a href="{{ route('admin.group-ticketing.inventory.index') }}" class="jp-btn jp-btn--primary btn-sm">View inventory</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card ota-admin-compact-card">
                <div class="jp-card__body">
                    <h3 class="jp-card__title">API Categories</h3>
                    <p class="text-secondary">Read-only list of categories derived from synced inventory.</p>
                    <a href="{{ route('admin.group-ticketing.categories.index') }}" class="jp-btn jp-btn--outline btn-sm">View categories</a>
                </div>
            </div>
        </div>
    </div>
@endsection
