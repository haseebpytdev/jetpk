@extends('layouts.developer')

@section('title', 'Group Ticketing')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">Group ticketing status</h1>
    <p class="text-secondary mb-0">Inventory freshness, booking pipeline, and scheduled tasks.</p>
@endsection

@section('content')
    <div class="row row-cards mb-4">
        <div class="col-md-4"><div class="card"><div class="card-body">
            <div class="text-secondary small">Inventory rows</div>
            <div class="h3 mb-0">{{ $snapshot['inventory_count'] ?? 0 }}</div>
        </div></div></div>
        <div class="col-md-8"><div class="card"><div class="card-body">
            <div class="text-secondary small">Last inventory sync</div>
            <div>{{ $snapshot['last_inventory_sync'] ?? 'Unknown' }}</div>
        </div></div></div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Booking status counts</h3></div>
        <ul class="list-group list-group-flush">
            @foreach ($snapshot['status_counts'] ?? [] as $status => $count)
                <li class="list-group-item d-flex justify-content-between">
                    <code>{{ $status }}</code><span>{{ $count }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Recent issues</h3></div>
        <div class="table-responsive">
            <table class="table table-sm card-table">
                <thead><tr><th>Reference</th><th>Status</th><th>Reason</th><th>Updated</th></tr></thead>
                <tbody>
                    @forelse ($snapshot['recent_issues'] ?? [] as $row)
                        <tr>
                            <td>{{ $row['reference'] ?? '—' }}</td>
                            <td>{{ $row['status'] ?? '—' }}</td>
                            <td>{{ $row['release_reason'] ?? '—' }}</td>
                            <td>{{ $row['updated_at'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-secondary">No recent issues.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
