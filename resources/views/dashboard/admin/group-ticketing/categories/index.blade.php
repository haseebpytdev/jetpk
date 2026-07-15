@extends(client_layout('dashboard', 'admin'))

@section('title', 'API Categories')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.group-ticketing.index') }}">Group Ticketing</a></div>
            <h1 class="jp-page-title">API Categories</h1>
        </div>
    </div>
@endsection

@section('content')
    @if (session('info'))
        <div class="jp-alert jp-alert--info">{{ session('info') }}</div>
    @endif

    <div class="alert alert-secondary mb-3">
        Categories are created automatically when group inventory syncs from the API. They cannot be added or deleted manually.
    </div>

    <div class="jp-card">
        <div class="table-responsive">
            <table class="jp-table ota-admin-table">
                <thead>
                    <tr>
                        <th>API name</th>
                        <th>Slug</th>
                        <th>Homepage title</th>
                        <th>Inventory</th>
                        <th>Active inventory</th>
                        <th>Last synced</th>
                        <th>Public tile</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category['name'] }}</td>
                            <td><code>{{ $category['slug'] }}</code></td>
                            <td>{{ $category['homepage_title'] }}</td>
                            <td>{{ $category['inventory_count'] }}</td>
                            <td>{{ $category['active_inventory_count'] }}</td>
                            <td>{{ $category['last_synced_at'] ?? '—' }}</td>
                            <td>
                                @if ($category['has_public_tile'])
                                    <span class="badge bg-success-lt">Visible</span>
                                @else
                                    <span class="badge bg-secondary-lt">Hidden</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-secondary">No categories yet. Run inventory sync when Al-Haider is enabled.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
