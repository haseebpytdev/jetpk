@extends('layouts.developer')

@section('title', 'UI Versions')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">UI version channels</h1>
    <p class="text-secondary mb-0">Read-only defaults, active versions, preview rules, and critical view status.</p>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1"><strong>Preview query param (admin/staff):</strong> <code>{{ $snapshot['preview_query_param'] ?? 'ui' }}</code></p>
            <p class="mb-0 text-secondary small">
                Site channel also supports <code>/v1</code> and <code>/v2</code> path prefixes when preview is enabled.
                Public assets root: <code>{{ $snapshot['public_asset_root_reminder'] ?? '—' }}</code>
            </p>
        </div>
    </div>

    @foreach ($snapshot['channels'] ?? [] as $channelKey => $channel)
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0 text-capitalize">{{ $channelKey }} channel</h3>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="text-secondary small">Default</div>
                        <div><code>{{ $channel['default'] ?? 'v1' }}</code></div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-secondary small">Fallback</div>
                        <div><code>{{ $channel['fallback'] ?? 'v1' }}</code></div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-secondary small">Preview</div>
                        <div>{{ ($channel['preview_enabled'] ?? false) ? 'Enabled' : 'Disabled' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-secondary small">Active versions</div>
                        <div><code>{{ implode(', ', $channel['active_versions'] ?? []) }}</code></div>
                    </div>
                </div>

                @if (! empty($channel['route_prefix_versions']))
                    <p class="small text-secondary mb-3">
                        Route prefixes: <code>/{{ implode('</code>, <code>/', $channel['route_prefix_versions']) }}</code>
                    </p>
                @endif

                <h4 class="h5">Critical v1 views</h4>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Logical path</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($channel['critical_views'] ?? [] as $view)
                                <tr>
                                    <td><code>{{ $view['path'] ?? '' }}</code></td>
                                    <td>{{ ($view['exists'] ?? false) ? 'Present' : 'Missing' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (! empty($channel['overlay_views']))
                    <h4 class="h5 mt-3">v2+ overlays</h4>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr>
                                    <th>Overlay</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($channel['overlay_views'] as $overlay)
                                    <tr>
                                        <td><code>{{ $overlay['path'] ?? '' }}</code></td>
                                        <td>{{ ($overlay['exists'] ?? false) ? 'Present' : 'Fallback to v1' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endsection
