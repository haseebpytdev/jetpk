@extends('layouts.developer')

@section('title', 'Sabre Status')

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">Sabre status</h1>
    <p class="text-secondary mb-0">Read-only deployment snapshot — no credentials or live supplier calls.</p>
@endsection

@section('content')
    @if (!empty($snapshot['warnings']))
        <div class="alert alert-warning mb-4">
            <strong>Attention</strong>
            <ul class="mb-0 mt-2">
                @foreach ($snapshot['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row row-cards mb-4">
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-secondary small">sabre_gds</div>
            <div>{{ ($snapshot['sabre_gds_enabled'] ?? false) ? 'On' : 'Off' }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-secondary small">sabre_ndc</div>
            <div>{{ ($snapshot['sabre_ndc_enabled'] ?? false) ? 'On' : 'Off' }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-secondary small">Booking config</div>
            <div>{{ ($snapshot['booking_enabled'] ?? false) ? 'Enabled' : 'Disabled' }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-secondary small">Ticketing config</div>
            <div>{{ ($snapshot['ticketing_enabled'] ?? false) ? 'Enabled' : 'Disabled' }}</div>
        </div></div></div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Primary active connection</h3></div>
        <div class="card-body">
            @php($primary = $snapshot['primary_connection'] ?? null)
            @if (is_array($primary))
                <dl class="row mb-0">
                    <dt class="col-sm-3">Connection ID</dt><dd class="col-sm-9">{{ $primary['id'] ?? '—' }}</dd>
                    <dt class="col-sm-3">Name</dt><dd class="col-sm-9">{{ $primary['name'] ?? '—' }}</dd>
                    <dt class="col-sm-3">Environment</dt><dd class="col-sm-9">{{ $primary['environment'] ?? '—' }}</dd>
                    <dt class="col-sm-3">Base host</dt><dd class="col-sm-9">{{ $primary['base_host'] ?? '—' }}</dd>
                    <dt class="col-sm-3">Auth keys present</dt><dd class="col-sm-9">{{ ($primary['credential_keys_present'] ?? false) ? 'yes' : 'no' }}</dd>
                    <dt class="col-sm-3">Token config present</dt><dd class="col-sm-9">{{ ($primary['token_config_present'] ?? $primary['credential_keys_present'] ?? false) ? 'yes' : 'no' }}</dd>
                </dl>
            @else
                <p class="text-secondary mb-0">No active Sabre supplier connection.</p>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Config flags</h3></div>
        <div class="card-body">
            @php($flags = $snapshot['config_flags'] ?? [])
            @if ($flags === [])
                <p class="text-secondary mb-0">Not available.</p>
            @else
                <div class="row">
                    @foreach ($flags as $key => $value)
                        <div class="col-md-4 mb-2">
                            <span class="text-secondary small">{{ $key }}</span>
                            <div>{{ $value ? 'enabled' : 'disabled' }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Provider mutation policy</h3></div>
        <div class="table-responsive">
            <table class="table table-sm card-table">
                <thead><tr><th>Capability</th><th>Status</th><th>Live call allowed</th><th>Production allowed</th></tr></thead>
                <tbody>
                    @forelse ($snapshot['mutation_policy'] ?? [] as $row)
                        <tr>
                            <td>{{ $row['label'] ?? $row['key'] ?? '—' }}</td>
                            <td>{{ $row['status'] ?? '—' }}</td>
                            <td>{{ ($row['live_supplier_call_allowed'] ?? false) ? 'yes' : 'no' }}</td>
                            <td>{{ ($row['production_allowed'] ?? false) ? 'yes' : 'no' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-secondary">Not available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Controlled PNR lane (F9/F9B)</h3></div>
        <div class="card-body">
            @php($lane = $snapshot['controlled_pnr_lane'] ?? [])
            <dl class="row mb-0">
                <dt class="col-sm-4">Lane exists</dt>
                <dd class="col-sm-8">{{ ($lane['lane_exists'] ?? false) ? 'yes' : 'no' }}</dd>
                <dt class="col-sm-4">Context command (F9B)</dt>
                <dd class="col-sm-8"><code>{{ $lane['context_command'] ?? '—' }}</code></dd>
                <dt class="col-sm-4">Readiness command</dt>
                <dd class="col-sm-8"><code>{{ $lane['readiness_command'] ?? '—' }}</code></dd>
                <dt class="col-sm-4">Create command</dt>
                <dd class="col-sm-8"><code>{{ $lane['create_command'] ?? '—' }}</code></dd>
                <dt class="col-sm-4">Requires explicit confirmation</dt>
                <dd class="col-sm-8">{{ ($lane['requires_explicit_confirmation'] ?? false) ? 'yes' : 'no' }}</dd>
                <dt class="col-sm-4">Public auto-PNR</dt>
                <dd class="col-sm-8">{{ ($lane['public_auto_pnr_enabled'] ?? false) ? 'enabled' : 'disabled' }}</dd>
                <dt class="col-sm-4">Ticketing</dt>
                <dd class="col-sm-8">{{ ($lane['ticketing_enabled'] ?? false) ? 'enabled' : 'disabled' }}</dd>
                <dt class="col-sm-4">Cancellation</dt>
                <dd class="col-sm-8">{{ ($lane['cancellation_enabled'] ?? false) ? 'enabled' : 'disabled' }}</dd>
                <dt class="col-sm-4">Booking live call</dt>
                <dd class="col-sm-8">{{ ($lane['booking_live_call_enabled'] ?? false) ? 'enabled' : 'disabled' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Route readiness</h3></div>
        <div class="card-body">
            @php($routes = $snapshot['route_readiness'] ?? [])
            <dl class="row mb-0">
                <dt class="col-sm-4">Admin supplier-booking</dt>
                <dd class="col-sm-8">{{ ($routes['admin_supplier_booking_registered'] ?? false) ? 'registered' : 'missing' }}</dd>
                <dt class="col-sm-4">Staff supplier-booking</dt>
                <dd class="col-sm-8">{{ ($routes['staff_supplier_booking_registered'] ?? false) ? 'registered' : 'missing' }}</dd>
                <dt class="col-sm-4">Admin prepare-supplier-pnr-context</dt>
                <dd class="col-sm-8">{{ ($routes['admin_prepare_supplier_pnr_context_registered'] ?? false) ? 'registered' : 'missing' }}</dd>
                <dt class="col-sm-4">Staff prepare-supplier-pnr-context</dt>
                <dd class="col-sm-8">{{ ($routes['staff_prepare_supplier_pnr_context_registered'] ?? false) ? 'registered' : 'missing' }}</dd>
                <dt class="col-sm-4">Admin sync-pnr-itinerary</dt>
                <dd class="col-sm-8">{{ ($routes['admin_sync_pnr_itinerary_registered'] ?? false) ? 'registered' : 'missing' }}</dd>
                <dt class="col-sm-4">Staff sync-pnr-itinerary</dt>
                <dd class="col-sm-8">{{ ($routes['staff_sync_pnr_itinerary_registered'] ?? false) ? 'registered' : 'missing' }}</dd>
                <dt class="col-sm-4">Live supplier call attempted</dt>
                <dd class="col-sm-8">{{ ($snapshot['live_supplier_call_attempted'] ?? false) ? 'true' : 'false' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title mb-0">Sabre connections</h3></div>
        <div class="table-responsive">
            <table class="table table-sm card-table">
                <thead><tr><th>ID</th><th>Agency</th><th>Name</th><th>Active</th><th>Status</th><th>Env</th><th>Base host</th><th>Auth keys</th></tr></thead>
                <tbody>
                    @forelse ($snapshot['connections'] ?? [] as $conn)
                        <tr>
                            <td>{{ $conn['id'] ?? '—' }}</td>
                            <td>{{ $conn['agency_id'] ?? '—' }}</td>
                            <td>{{ $conn['name'] ?? '—' }}</td>
                            <td>{{ ($conn['is_active'] ?? false) ? 'yes' : 'no' }}</td>
                            <td>{{ $conn['status'] ?? '—' }}</td>
                            <td>{{ $conn['environment'] ?? '—' }}</td>
                            <td>{{ $conn['base_host'] ?? '—' }}</td>
                            <td>{{ ($conn['credential_keys_present'] ?? false) ? 'yes' : 'no' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-secondary">No Sabre connections.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Recent booking attempt failures</h3></div>
        <div class="table-responsive">
            <table class="table table-sm card-table">
                <thead><tr><th>Time</th><th>Booking</th><th>Code</th><th>Summary</th></tr></thead>
                <tbody>
                    @forelse ($snapshot['recent_failures'] ?? [] as $row)
                        <tr>
                            <td>{{ $row['created_at'] ?? '—' }}</td>
                            <td>{{ $row['booking_id'] ?? '—' }}</td>
                            <td>{{ $row['error_code'] ?? '—' }}</td>
                            <td class="small">{{ $row['safe_summary'] ?? $row['error_message'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-secondary">No recent failures.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
