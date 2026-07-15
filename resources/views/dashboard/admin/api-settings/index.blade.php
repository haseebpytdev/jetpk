@extends(client_layout('dashboard', 'admin'))

@section('title', 'API Settings')

@php
    use App\Support\Suppliers\SabreSupplierChannelConfig;
@endphp

@section('page-header')
    <div class="jp-between ota-admin-page-header">
        <div class="col">
            <div class="page-pretitle">Integrations</div>
            <h1 class="jp-page-title">Supplier API settings</h1>
        </div>
        <div class="col-auto ms-auto">
            <a href="{{ route('admin.api-settings.create') }}" class="jp-btn jp-btn--primary">
                <i class="ti ti-plus me-1"></i>Add connection
            </a>
        </div>
    </div>
@endsection

@section('content')
    <div class="ota-page-content-wide" data-admin-premium-layout>
    @if (session('status') === 'supplier-status-toggled')
        <div class="jp-alert jp-alert--success mb-3">Supplier connection status updated.</div>
    @elseif (session('status') === 'supplier-connection-updated')
        <div class="jp-alert jp-alert--success mb-3">Supplier connection saved.</div>
    @elseif (session('status') === 'supplier-connection-created')
        <div class="jp-alert jp-alert--success mb-3">Supplier connection created.</div>
    @elseif (session('status') === 'supplier-connection-deleted')
        <div class="jp-alert jp-alert--success mb-3">Supplier connection deleted.</div>
    @elseif (session('status') === 'supplier-test-ran')
        <div class="jp-alert jp-alert--info mb-3">Readiness check completed.@if (session('test_result.status')) Last status: {{ session('test_result.status') }}.@endif</div>
    @elseif (session('status'))
        <div class="jp-alert jp-alert--info mb-3">{{ session('status') }}</div>
    @endif
    <div class="jp-alert jp-alert--warn mb-3">
        Credentials are encrypted and never displayed after saving. Add Duffel test access token from Duffel dashboard and keep environment sandbox/test.
    </div>
    @if (!($activeRealSupplierExists ?? false))
        <div class="jp-alert jp-alert--info mb-3">
            No active supplier is connected. Flight search may use fallback provider if enabled.
        </div>
    @endif

    @php($k = $kpis ?? [])
    <div class="row row-cards mb-3 ota-admin-kpi-card">
        <div class="col-sm-6 col-lg-3"><div class="card card-sm ota-kpi-card"><div class="jp-card__body"><div class="text-secondary">Total suppliers</div><div class="h2 mb-0">{{ number_format((int) ($k['total'] ?? 0)) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm ota-kpi-card ota-kpi-accent-emerald"><div class="jp-card__body"><div class="text-secondary">Active suppliers</div><div class="h2 mb-0">{{ number_format((int) ($k['active'] ?? 0)) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm ota-kpi-card ota-kpi-accent-amber"><div class="jp-card__body"><div class="text-secondary">Sandbox</div><div class="h2 mb-0">{{ number_format((int) ($k['sandbox'] ?? 0)) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm ota-kpi-card ota-kpi-accent-violet"><div class="jp-card__body"><div class="text-secondary">Live</div><div class="h2 mb-0">{{ number_format((int) ($k['live'] ?? 0)) }}</div></div></div></div>
    </div>

    <div class="row row-cards mb-3 ota-admin-kpi-card">
        @forelse ($connections as $connection)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 ota-supplier-connection-card {{ $connection->status->value !== 'active' ? 'ota-supplier-connection-card--inactive' : '' }}" data-supplier-card>
                    <div class="jp-card__body">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <div>
                                <div class="text-secondary small">Provider</div>
                                <div class="fw-semibold text-capitalize">{{ str_replace('_', ' ', $connection->provider->value) }}</div>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-2">
                                <span class="badge {{ $connection->status->value === 'active' ? 'bg-success' : ($connection->status->value === 'error' ? 'bg-danger' : 'bg-secondary') }}">
                                    {{ $connection->status->value === 'active' ? 'Active' : 'Inactive' }}
                                </span>
                                <form method="POST" action="{{ route('admin.api-settings.toggle-status', $connection) }}" class="ota-supplier-enable-form">
                                    @csrf
                                    @method('PATCH')
                                    <div class="d-flex flex-column align-items-end gap-1">
                                        <label class="form-check form-switch mb-0 ota-supplier-enable-switch">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="enabled"
                                                value="1"
                                                @checked($connection->status->value === 'active')
                                                onchange="this.form.submit()"
                                            >
                                            <span class="form-check-label small">Enabled</span>
                                        </label>
                                        @if ($connection->provider->value === 'sabre')
                                            @php($sabreChannels = SabreSupplierChannelConfig::fromConnection($connection))
                                            <button
                                                type="button"
                                                class="jp-btn jp-btn--sm jp-btn--ghost py-0 px-2"
                                                data-sabre-advanced-api-config-open
                                                data-connection-id="{{ $connection->id }}"
                                            >
                                                Advanced API Config
                                            </button>
                                            @if (! $sabreChannels->gdsEnabled && ! $sabreChannels->ndcEnabled)
                                                <span class="badge bg-warning-lt text-warning small">Channels off</span>
                                            @endif
                                        @endif
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="mb-2 fw-semibold">{{ $connection->name }}</div>
                        @php($envSlug = $connection->environment->value)
                        <div class="small text-secondary mb-3">
                            Environment:
                            <span class="text-dark">
                                @if ($envSlug === 'sandbox')
                                    Sandbox
                                @elseif ($envSlug === 'live')
                                    Live
                                @else
                                    Training
                                @endif
                            </span>
                            @if ($envSlug === 'sandbox')
                                <span class="badge bg-azure-lt text-azure ms-1">Sandbox</span>
                            @elseif ($envSlug === 'live')
                                <span class="badge bg-green-lt text-green ms-1">Live</span>
                            @else
                                <span class="badge bg-secondary-lt text-secondary ms-1">Training</span>
                            @endif
                        </div>
                        <dl class="row small mb-2">
                            <dt class="col-6 text-secondary">Credentials</dt>
                            <dd class="col-6 mb-1 text-end">
                                @if ($connection->provider->value === 'duffel')
                                    @if (! empty($connection->credentials['access_token'] ?? null))
                                        <span class="text-success">Access token configured</span>
                                    @else
                                        <span class="text-danger">Access token missing</span>
                                    @endif
                                @else
                                    <span class="text-secondary">See edit screen</span>
                                @endif
                            </dd>
                            @if ($connection->provider->value === 'duffel')
                                <dt class="col-6 text-secondary">API version</dt>
                                <dd class="col-6 mb-1 text-end">{{ $connection->credentials['api_version'] ?? 'v2' }}</dd>
                            @endif
                            <dt class="col-6 text-secondary">Last readiness</dt>
                            <dd class="col-6 mb-1 text-end">{{ $connection->last_tested_at?->format('Y-m-d H:i') ?? display_unknown() }}</dd>
                            <dt class="col-6 text-secondary">Last readiness status</dt>
                            <dd class="col-6 mb-1 text-end">{{ $connection->last_test_status ?? display_unknown() }}</dd>
                            <dt class="col-6 text-secondary">Last search</dt>
                            <dd class="col-6 mb-1 text-end">{{ $connection->latestSearchDiagnostic?->status === 'success' ? $connection->latestSearchDiagnostic?->created_at?->format('Y-m-d H:i') : display_unknown() }}</dd>
                            <dt class="col-6 text-secondary">Last order</dt>
                            <dd class="col-6 mb-1 text-end">{{ $connection->latestOrderDiagnostic?->status === 'success' ? $connection->latestOrderDiagnostic?->created_at?->format('Y-m-d H:i') : display_unknown() }}</dd>
                        </dl>
                        @if ($connection->last_error || filled($connection->latestReadinessDiagnostic?->safe_message))
                            <details class="mb-3">
                                <summary class="small cursor-pointer text-secondary">Diagnostics</summary>
                                <div class="small text-secondary mt-2 mb-0">{{ $connection->last_error ?? $connection->latestReadinessDiagnostic?->safe_message }}</div>
                            </details>
                        @endif
                        <div class="d-flex flex-wrap gap-2 mt-auto pt-2 border-top">
                            <a href="{{ route('admin.api-settings.edit', $connection) }}" class="jp-btn jp-btn--sm jp-btn--outline">Edit</a>
                            <form method="POST" action="{{ route('admin.api-settings.test', $connection) }}" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="jp-btn jp-btn--sm jp-btn--ghost">Run readiness check</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="jp-card"><div class="card-body text-center text-secondary py-4">
                    No supplier connections yet. Add your supplier connections to start searching fares.
                </div></div>
            </div>
        @endforelse
    </div>
    @if ($connections->hasPages())
        <div class="mb-4">{{ $connections->links() }}</div>
    @endif

    @if (! $hasRows)
        <div class="card mt-3">
            <div class="jp-card__head"><h3 class="jp-card__title">Recommended setup</h3></div>
            <div class="jp-card__body">
                <div class="row g-2">
                    @foreach ($fallbackSuppliers as $supplier)
                        <div class="col-md-6">
                            <div class="border rounded p-2">
                                <div class="fw-semibold">{{ $supplier['name'] ?? '' }}</div>
                                <div class="small text-secondary">{{ $supplier['notes'] ?? '' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
    </div>

    @foreach ($connections as $connection)
        @if ($connection->provider->value === 'sabre')
            @php($sabreChannels = SabreSupplierChannelConfig::fromConnection($connection))
            <div
                id="ota-sabre-advanced-api-modal-{{ $connection->id }}"
                class="ota-confirm-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="ota-sabre-advanced-api-modal-title-{{ $connection->id }}"
                hidden
            >
                <div class="ota-confirm-modal__backdrop" data-close-sabre-advanced-api></div>
                <div class="ota-confirm-modal__panel" role="document">
                    <h4 id="ota-sabre-advanced-api-modal-title-{{ $connection->id }}" class="ota-confirm-modal__title">Advanced API Config</h4>
                    <p class="ota-confirm-modal__message mb-2">
                        Control which Sabre content channels are active for this API connection.
                    </p>
                    <form method="POST" action="{{ route('admin.api-settings.update', $connection) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="provider" value="sabre">
                        <input type="hidden" name="name" value="{{ $connection->name }}">
                        <input type="hidden" name="environment" value="{{ $connection->environment?->value }}">
                        <input type="hidden" name="status" value="{{ $connection->status?->value }}">
                        <input type="hidden" name="base_url" value="{{ $connection->base_url }}">
                        <div class="d-flex flex-column gap-2">
                            <input type="hidden" name="sabre_gds_enabled" value="0">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="sabre_gds_enabled" value="1" @checked($sabreChannels->gdsEnabled)>
                                <span class="form-check-label">Enable Sabre GDS</span>
                            </label>
                            <input type="hidden" name="sabre_ndc_enabled" value="0">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="sabre_ndc_enabled" value="1" @checked($sabreChannels->ndcEnabled)>
                                <span class="form-check-label">Enable Sabre NDC</span>
                            </label>
                        </div>
                        <div class="form-hint mt-2 text-warning @if ($sabreChannels->gdsEnabled || $sabreChannels->ndcEnabled) d-none @endif" data-sabre-channels-off-warning>
                            Both channels are off. Sabre search and booking will be disabled for this connection.
                        </div>
                        <div class="ota-confirm-modal__actions">
                            <button type="submit" class="jp-btn jp-btn--primary">Save</button>
                            <button type="button" class="jp-btn jp-btn--ghost" data-close-sabre-advanced-api>Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach

    <style>
        .ota-confirm-modal[hidden] { display: none !important; }
        .ota-confirm-modal {
            position: fixed;
            inset: 0;
            z-index: 2100;
            display: grid;
            place-items: center;
            padding: 1rem;
        }
        .ota-confirm-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
        }
        .ota-confirm-modal__panel {
            position: relative;
            width: min(100%, 420px);
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
            padding: 1rem 1rem 0.95rem;
        }
        .ota-confirm-modal__title {
            margin: 0 0 0.45rem;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }
        .ota-confirm-modal__message {
            margin: 0;
            font-size: 0.92rem;
            line-height: 1.45;
            color: #334155;
        }
        .ota-confirm-modal__actions {
            margin-top: 0.9rem;
            display: flex;
            gap: 0.55rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
    </style>
    @push('scripts')
        <script>
            (function () {
                var openButtons = document.querySelectorAll('[data-sabre-advanced-api-config-open]');
                openButtons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var id = btn.getAttribute('data-connection-id');
                        var modal = document.getElementById('ota-sabre-advanced-api-modal-' + id);
                        if (!modal) return;
                        modal.hidden = false;
                        document.body.classList.add('overflow-hidden');
                    });
                });
                document.querySelectorAll('[data-close-sabre-advanced-api]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var modal = btn.closest('.ota-confirm-modal');
                        if (!modal) return;
                        modal.hidden = true;
                        document.body.classList.remove('overflow-hidden');
                    });
                });
                document.addEventListener('keydown', function (event) {
                    if (event.key !== 'Escape') return;
                    document.querySelectorAll('.ota-confirm-modal:not([hidden])').forEach(function (modal) {
                        modal.hidden = true;
                        document.body.classList.remove('overflow-hidden');
                    });
                });
                document.querySelectorAll('.ota-confirm-modal form').forEach(function (form) {
                    var gds = form.querySelector('[name="sabre_gds_enabled"]');
                    var ndc = form.querySelector('[name="sabre_ndc_enabled"]');
                    var warning = form.querySelector('[data-sabre-channels-off-warning]');
                    if (!gds || !ndc || !warning) return;
                    function syncWarning() {
                        warning.classList.toggle('d-none', gds.checked || ndc.checked);
                    }
                    gds.addEventListener('change', syncWarning);
                    ndc.addEventListener('change', syncWarning);
                });
            })();
        </script>
    @endpush
@endsection

