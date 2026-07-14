@extends(client_layout('dashboard', 'admin'))

@section('title', 'System Health')

@section('page-header')
    <h1 class="jp-page-title">System Health</h1>
@endsection

@section('content')
    <div class="jp-card">
        <div class="jp-card__head"><h3 class="jp-card__title mb-0">Diagnostics</h3></div>
        <div class="jp-card__body">
            <div class="jp-form-grid">
                @foreach($checks as $label => $value)
                    <div class="jp-card jp-card--compact">
                        <div class="jp-muted">{{ str_replace('_', ' ', $label) }}</div>
                        <div>{{ is_bool($value) ? ($value ? 'OK' : 'FAIL') : $value }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="jp-card">
        <div class="jp-card__head"><h3 class="jp-card__title mb-0">Recent Admin Activity</h3></div>
        <div class="jp-card__body">
            @forelse($recentAdminActivity as $log)
                <div class="small border-bottom py-2">
                    <strong>{{ $log->action }}</strong> · {{ $log->created_at?->format('Y-m-d H:i') }}
                </div>
            @empty
                <div class="ota-text-meta text-secondary">No audit activity found.</div>
            @endforelse
        </div>
    </div>
@endsection
