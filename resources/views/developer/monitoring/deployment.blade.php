@extends('layouts.developer')

@section('title', 'Deployment Status')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">Deployment / rollback status</h1>
    <p class="text-secondary mb-0">Deploy marker and recent module setting changes.</p>
@endsection

@section('content')
    @php $marker = $snapshot['deploy_marker'] ?? []; @endphp
    <div class="card mb-4">
        <div class="card-body">
            <h3 class="card-title">Deploy marker</h3>
            @if ($marker['present'] ?? false)
                <dl class="row mb-0">
                    <dt class="col-sm-3">Version</dt><dd class="col-sm-9">{{ $marker['version'] ?? '—' }}</dd>
                    <dt class="col-sm-3">Deployed at</dt><dd class="col-sm-9">{{ $marker['deployed_at'] ?? '—' }}</dd>
                    <dt class="col-sm-3">Git SHA</dt><dd class="col-sm-9"><code>{{ $marker['git_sha'] ?? '—' }}</code></dd>
                </dl>
            @else
                <p class="text-secondary mb-0">{{ $marker['message'] ?? 'Unknown' }}</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Recent module changes</h3></div>
        <div class="table-responsive">
            <table class="table table-sm card-table">
                <thead><tr><th>Time</th><th>Module</th><th>Change</th><th>Source</th><th>By</th></tr></thead>
                <tbody>
                    @forelse ($snapshot['recent_module_changes'] ?? [] as $change)
                        <tr>
                            <td>{{ $change['created_at'] ?? '—' }}</td>
                            <td><code>{{ $change['module_key'] }}</code></td>
                            <td>{{ ($change['old_enabled'] ?? false) ? 'on' : 'off' }} → {{ ($change['new_enabled'] ?? false) ? 'on' : 'off' }}</td>
                            <td>{{ $change['source'] }}{{ $change['preset_key'] ? ' ('.$change['preset_key'].')' : '' }}</td>
                            <td>{{ $change['developer'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary">No module changes recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
