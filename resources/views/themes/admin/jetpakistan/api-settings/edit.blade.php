@extends(client_layout('dashboard', 'admin'))

@section('title', 'Edit Supplier Connection')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Edit supplier connection</h1>
            <p>Update credentials and connection settings.</p>
        </div>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

<div class="jp-card">
    <div class="jp-card__head"><h2 class="jp-card__title">Supplier diagnostics</h2></div>
    <dl style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; font-size: 0.875rem; margin: 0;">
        <div><span class="jp-cell-sub">Provider</span><br><span class="text-capitalize">{{ str_replace('_', ' ', $connection->provider?->value ?? display_unknown()) }}</span></div>
        <div><span class="jp-cell-sub">Environment</span><br><span class="text-capitalize">{{ $connection->environment?->value ?? display_unknown() }}</span></div>
        <div><span class="jp-cell-sub">Status</span><br><span class="text-capitalize">{{ $connection->status?->value ?? display_unknown() }}</span></div>
        <div><span class="jp-cell-sub">Last readiness status</span><br>{{ $connection->last_test_status ?? display_unknown() }}</div>
        <div><span class="jp-cell-sub">Last tested at</span><br>{{ $connection->last_tested_at?->format('Y-m-d H:i') ?? display_unknown() }}</div>
        <div><span class="jp-cell-sub">Last error</span><br>{{ $connection->last_error ?? $connection->latestReadinessDiagnostic?->safe_message ?? display_unknown() }}</div>
        <div><span class="jp-cell-sub">Last successful search</span><br>{{ $connection->latestSearchDiagnostic?->status === 'success' ? $connection->latestSearchDiagnostic?->created_at?->format('Y-m-d H:i') : display_unknown() }}</div>
        <div><span class="jp-cell-sub">Last successful order</span><br>{{ $connection->latestOrderDiagnostic?->status === 'success' ? $connection->latestOrderDiagnostic?->created_at?->format('Y-m-d H:i') : display_unknown() }}</div>
    </dl>
</div>

<div class="jp-card jp-module-compat jp-supplier-form-shell">
    @include('dashboard.admin.api-settings.form')
</div>
@endsection
