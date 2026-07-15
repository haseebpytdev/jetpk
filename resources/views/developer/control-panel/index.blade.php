@extends('layouts.developer')

@section('title', 'Developer Control Panel')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">Overview</h1>
    <p class="text-secondary mb-0">Deployment-owner control panel for this OTA install — not client admin settings.</p>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row row-cards mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body">
                <div class="text-secondary small">Agencies (Admin Panel)</div>
                <div class="h2 mb-0">{{ $stats['agencies'] ?? 0 }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body">
                <div class="text-secondary small">Platform admins</div>
                <div class="h2 mb-0">{{ $stats['platform_admins'] ?? 0 }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body">
                <div class="text-secondary small">Modules enabled</div>
                <div class="h2 mb-0">{{ $moduleEnabled }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body">
                <div class="text-secondary small">Security events (24h)</div>
                <div class="h2 mb-0">{{ $stats['security_events_24h'] ?? 0 }}</div>
            </div></div>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="card-title">Deployment module access</h3>
                    <p class="text-secondary small mb-3">Global module presets and planned on/off states for this deployment.</p>
                    <a href="{{ route('dev.cp.modules.index') }}" class="btn btn-primary">Open modules</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="card-title">Platform Admin handoff</h3>
                    <p class="text-secondary small mb-3">Create or reset Platform Admin credentials for the client. Agencies and other users are managed in the OTA Admin Panel.</p>
                    <a href="{{ route('dev.cp.users.index') }}" class="btn btn-outline-primary">Manage platform admins</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="card-title">System health</h3>
                    <p class="text-secondary small mb-3">DB, failed jobs, scheduler, and recent errors.</p>
                    <a href="{{ route('dev.cp.health') }}" class="btn btn-outline-primary">View health</a>
                </div>
            </div>
        </div>
    </div>

    @if ($recentEvents->isNotEmpty())
        <div class="card mt-4">
            <div class="card-header"><h3 class="card-title mb-0">Recent security events</h3></div>
            <div class="table-responsive">
                <table class="table table-sm card-table mb-0">
                    <thead><tr><th>Time</th><th>Type</th><th>Outcome</th></tr></thead>
                    <tbody>
                        @foreach ($recentEvents as $event)
                            <tr>
                                <td>{{ $event->created_at?->diffForHumans() }}</td>
                                <td><code>{{ $event->event_type }}</code></td>
                                <td>{{ $event->outcome }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <a href="{{ route('dev.cp.security-events.index') }}">View all security events</a>
            </div>
        </div>
    @endif
@endsection
