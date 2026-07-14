@extends(client_layout('dashboard', 'admin'))

@section('title', 'API Categories')

@section('page-header')
    <div class="jp-between">
        <div>
            <p class="jp-cell-sub"><a href="{{ client_route('admin.group-ticketing.index') }}">Group Ticketing</a></p>
            <h1>API Categories</h1>
            <p>Categories derived automatically from synced inventory.</p>
        </div>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

<div class="jp-alert jp-alert--info">
    Categories are created automatically when group inventory syncs from the API. They cannot be added or deleted manually.
</div>

<div class="jp-dtable-wrap">
    <table class="jp-dtable">
        <thead>
            <tr>
                <th>API name</th>
                <th>Slug</th>
                <th>Homepage title</th>
                <th class="num">Inventory</th>
                <th class="num">Active inventory</th>
                <th>Last synced</th>
                <th>Public tile</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td data-label="API name">{{ $category['name'] }}</td>
                    <td data-label="Slug"><code>{{ $category['slug'] }}</code></td>
                    <td data-label="Homepage title">{{ $category['homepage_title'] }}</td>
                    <td data-label="Inventory" class="num">{{ $category['inventory_count'] }}</td>
                    <td data-label="Active inventory" class="num">{{ $category['active_inventory_count'] }}</td>
                    <td data-label="Last synced">{{ $category['last_synced_at'] ?? '—' }}</td>
                    <td data-label="Public tile">
                        <span class="jp-badge-pill {{ $category['has_public_tile'] ? 'jp-badge-pill--green' : '' }}">
                            {{ $category['has_public_tile'] ? 'Visible' : 'Hidden' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-themes.admin.jetpakistan.components.empty-state title="No categories yet" message="Run inventory sync when Al-Haider is enabled." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
