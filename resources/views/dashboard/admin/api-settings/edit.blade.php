@extends(client_layout('dashboard', 'admin'))

@section('title', 'Edit Supplier Connection')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">Integrations</div>
            <h1 class="jp-page-title">Edit supplier connection</h1>
        </div>
    </div>
@endsection

@section('content')
    @php
        $deleteAction = route('admin.api-settings.destroy', $connection);
    @endphp
    <div class="jp-card">
        <div class="jp-card__head"><h3 class="jp-card__title mb-0">Supplier diagnostics</h3></div>
        <div class="jp-card__body">
            <div class="row g-3 small">
                <div class="col-md-4"><strong>Provider:</strong> <span class="text-capitalize">{{ str_replace('_', ' ', $connection->provider?->value ?? display_unknown()) }}</span></div>
                <div class="col-md-4"><strong>Environment:</strong> <span class="text-capitalize">{{ $connection->environment?->value ?? display_unknown() }}</span></div>
                <div class="col-md-4"><strong>Status:</strong> <span class="text-capitalize">{{ $connection->status?->value ?? display_unknown() }}</span></div>
                <div class="col-md-4"><strong>Last readiness status:</strong> {{ $connection->last_test_status ?? display_unknown() }}</div>
                <div class="col-md-4"><strong>Last tested at:</strong> {{ $connection->last_tested_at?->format('Y-m-d H:i') ?? display_unknown() }}</div>
                <div class="col-md-4"><strong>Last error:</strong> {{ $connection->last_error ?? $connection->latestReadinessDiagnostic?->safe_message ?? display_unknown() }}</div>
                <div class="col-md-4"><strong>Last successful search:</strong> {{ $connection->latestSearchDiagnostic?->status === 'success' ? $connection->latestSearchDiagnostic?->created_at?->format('Y-m-d H:i') : display_unknown() }}</div>
                <div class="col-md-4"><strong>Last successful order:</strong> {{ $connection->latestOrderDiagnostic?->status === 'success' ? $connection->latestOrderDiagnostic?->created_at?->format('Y-m-d H:i') : display_unknown() }}</div>
            </div>
        </div>
    </div>

    @include('dashboard.admin.api-settings.form')
@endsection

