@extends('layouts.developer')

@section('title', 'System Health')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">System health</h1>
    <p class="text-secondary mb-0">Database, failed jobs, scheduler checklist, and recent errors (redacted).</p>
@endsection

@section('content')
    @php $db = $snapshot['database'] ?? []; @endphp
    <div class="row row-cards mb-4">
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <div class="text-secondary small">Environment</div>
                <div>{{ $snapshot['app_env'] ?? '—' }}</div>
                <div class="text-secondary small mt-2">APP_DEBUG</div>
                <div>{{ ($snapshot['app_debug'] ?? false) ? 'true' : 'false' }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <div class="text-secondary small">Database</div>
                <div>{{ ($db['ok'] ?? false) ? 'Healthy' : 'Failed' }}</div>
                @if ($db['ok'] ?? false)
                    <div class="small text-secondary mt-2">
                        Agencies: {{ $db['agencies'] ?? 0 }} · Users: {{ $db['users'] ?? 0 }} · Bookings: {{ $db['bookings'] ?? '—' }}
                    </div>
                @endif
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <div class="text-secondary small">Failed jobs</div>
                <div class="h3 mb-0">{{ $snapshot['failed_jobs']['count'] ?? 0 }}</div>
            </div></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Scheduler checklist</h3></div>
        <ul class="list-group list-group-flush">
            @foreach ($snapshot['scheduler'] ?? [] as $item)
                <li class="list-group-item d-flex justify-content-between">
                    <code>{{ $item['command'] }}</code>
                    <span class="text-secondary">{{ $item['schedule'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    @if (! empty($snapshot['recent_errors']))
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0">Recent log errors (truncated)</h3></div>
            <pre class="card-body small mb-0" style="max-height:320px;overflow:auto;">@foreach ($snapshot['recent_errors'] as $line){{ $line }}
@endforeach</pre>
        </div>
    @endif
@endsection
