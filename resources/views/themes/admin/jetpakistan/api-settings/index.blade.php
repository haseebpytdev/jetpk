@extends(client_layout('dashboard', 'admin'))

@section('title', 'API Settings')

@php
    use App\Support\Suppliers\SabreSupplierChannelConfig;
@endphp

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Supplier API settings</h1>
            <p>Integrations, credentials, and supplier readiness.</p>
        </div>
        <a href="{{ client_route('admin.api-settings.create') }}" class="jp-btn jp-btn--sm">Add connection</a>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

<div class="jp-alert jp-alert--warn">
    Credentials are encrypted and never displayed after saving. Add Duffel test access token from Duffel dashboard and keep environment sandbox/test.
</div>
@if (!($activeRealSupplierExists ?? false))
    <div class="jp-alert jp-alert--info">
        No active supplier is connected. Flight search may use fallback provider if enabled.
    </div>
@endif

@php($k = $kpis ?? [])
<div class="jp-kpis jp-kpis--4">
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($k['total'] ?? 0)) }}</div><div class="jp-kpi__l">Total suppliers</div></div>
    <div class="jp-kpi t-blue"><div class="jp-kpi__v">{{ number_format((int) ($k['active'] ?? 0)) }}</div><div class="jp-kpi__l">Active suppliers</div></div>
    <div class="jp-kpi t-amber"><div class="jp-kpi__v">{{ number_format((int) ($k['sandbox'] ?? 0)) }}</div><div class="jp-kpi__l">Sandbox</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($k['live'] ?? 0)) }}</div><div class="jp-kpi__l">Live</div></div>
</div>

@forelse ($connections as $connection)
    <div class="jp-card {{ $connection->status->value !== 'active' ? 'is-muted' : '' }}">
        <div class="jp-card__head">
            <div>
                <h2 class="jp-card__title text-capitalize">{{ str_replace('_', ' ', $connection->provider->value) }} — {{ $connection->name }}</h2>
                <p class="jp-cell-sub">
                    Environment:
                    @if ($connection->environment->value === 'sandbox')
                        Sandbox
                    @elseif ($connection->environment->value === 'live')
                        Live
                    @else
                        Training
                    @endif
                </p>
            </div>
            <span class="jp-badge-pill {{ $connection->status->value === 'active' ? 'jp-badge-pill--green' : '' }}">
                {{ $connection->status->value === 'active' ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <form method="POST" action="{{ client_route('admin.api-settings.toggle-status', $connection) }}" style="margin-bottom: 12px;">
            @csrf
            @method('PATCH')
            <label style="display: inline-flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="enabled" value="1" @checked($connection->status->value === 'active') onchange="this.form.submit()">
                <span>Enabled</span>
            </label>
            @if ($connection->provider->value === 'sabre')
                @php($sabreChannels = SabreSupplierChannelConfig::fromConnection($connection))
                <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-sabre-advanced-api-config-open data-connection-id="{{ $connection->id }}">Advanced API Config</button>
                @if (! $sabreChannels->gdsEnabled && ! $sabreChannels->ndcEnabled)
                    <span class="jp-badge-pill jp-badge-pill--amber">Channels off</span>
                @endif
            @endif
        </form>

        <dl style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; font-size: 0.875rem; margin: 0 0 12px;">
            <div><span class="jp-cell-sub">Credentials</span><br>
                @if ($connection->provider->value === 'duffel')
                    @if (! empty($connection->credentials['access_token'] ?? null))
                        <span class="jp-badge-pill jp-badge-pill--green">Access token configured</span>
                    @else
                        <span class="jp-badge-pill jp-badge-pill--danger">Access token missing</span>
                    @endif
                @else
                    See edit screen
                @endif
            </div>
            @if ($connection->provider->value === 'duffel')
                <div><span class="jp-cell-sub">API version</span><br>{{ $connection->credentials['api_version'] ?? 'v2' }}</div>
            @endif
            <div><span class="jp-cell-sub">Last readiness</span><br>{{ $connection->last_tested_at?->format('Y-m-d H:i') ?? display_unknown() }}</div>
            <div><span class="jp-cell-sub">Last readiness status</span><br>{{ $connection->last_test_status ?? display_unknown() }}</div>
            <div><span class="jp-cell-sub">Last search</span><br>{{ $connection->latestSearchDiagnostic?->status === 'success' ? $connection->latestSearchDiagnostic?->created_at?->format('Y-m-d H:i') : display_unknown() }}</div>
            <div><span class="jp-cell-sub">Last order</span><br>{{ $connection->latestOrderDiagnostic?->status === 'success' ? $connection->latestOrderDiagnostic?->created_at?->format('Y-m-d H:i') : display_unknown() }}</div>
        </dl>

        @if ($connection->last_error || filled($connection->latestReadinessDiagnostic?->safe_message))
            <details style="margin-bottom: 12px;">
                <summary class="jp-cell-sub" style="cursor: pointer;">Diagnostics</summary>
                <p class="jp-cell-sub" style="margin-top: 8px;">{{ $connection->last_error ?? $connection->latestReadinessDiagnostic?->safe_message }}</p>
            </details>
        @endif

        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <a href="{{ client_route('admin.api-settings.edit', $connection) }}" class="jp-btn jp-btn--sm jp-btn--outline">Edit</a>
            <form method="POST" action="{{ client_route('admin.api-settings.test', $connection) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="jp-btn jp-btn--sm jp-btn--ghost">Run readiness check</button>
            </form>
        </div>
    </div>
@empty
    <div class="jp-card">
        <x-themes.admin.jetpakistan.components.empty-state title="No supplier connections" message="Add your supplier connections to start searching fares." />
    </div>
@endforelse

@if ($connections->hasPages())
    <div class="jp-pagination">{{ $connections->links() }}</div>
@endif

@if (! $hasRows)
    <div class="jp-card">
        <div class="jp-card__head"><h2 class="jp-card__title">Recommended setup</h2></div>
        @foreach ($fallbackSuppliers as $supplier)
            <div style="padding: 8px 0; border-top: 1px solid var(--line-soft);">
                <strong>{{ $supplier['name'] ?? '' }}</strong>
                <p class="jp-cell-sub">{{ $supplier['notes'] ?? '' }}</p>
            </div>
        @endforeach
    </div>
@endif

@foreach ($connections as $connection)
    @if ($connection->provider->value === 'sabre')
        @php($sabreChannels = SabreSupplierChannelConfig::fromConnection($connection))
        <div id="ota-sabre-advanced-api-modal-{{ $connection->id }}" class="ota-confirm-modal" role="dialog" aria-modal="true" hidden>
            <div class="ota-confirm-modal__backdrop" data-close-sabre-advanced-api></div>
            <div class="ota-confirm-modal__panel" role="document">
                <h4 class="ota-confirm-modal__title">Advanced API Config</h4>
                <p class="ota-confirm-modal__message">Control which Sabre content channels are active for this API connection.</p>
                <form method="POST" action="{{ client_route('admin.api-settings.update', $connection) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="provider" value="sabre">
                    <input type="hidden" name="name" value="{{ $connection->name }}">
                    <input type="hidden" name="environment" value="{{ $connection->environment?->value }}">
                    <input type="hidden" name="status" value="{{ $connection->status?->value }}">
                    <input type="hidden" name="base_url" value="{{ $connection->base_url }}">
                    <input type="hidden" name="sabre_gds_enabled" value="0">
                    <label><input type="checkbox" name="sabre_gds_enabled" value="1" @checked($sabreChannels->gdsEnabled)> Enable Sabre GDS</label><br>
                    <input type="hidden" name="sabre_ndc_enabled" value="0">
                    <label><input type="checkbox" name="sabre_ndc_enabled" value="1" @checked($sabreChannels->ndcEnabled)> Enable Sabre NDC</label>
                    <div class="ota-confirm-modal__actions">
                        <button type="submit" class="jp-btn jp-btn--sm">Save</button>
                        <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" data-close-sabre-advanced-api>Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach

<style>
    .ota-confirm-modal[hidden] { display: none !important; }
    .ota-confirm-modal { position: fixed; inset: 0; z-index: 2100; display: grid; place-items: center; padding: 1rem; }
    .ota-confirm-modal__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); }
    .ota-confirm-modal__panel { position: relative; width: min(100%, 420px); background: #fff; border-radius: 12px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25); padding: 1rem; }
    .ota-confirm-modal__title { margin: 0 0 0.45rem; font-size: 1rem; font-weight: 700; }
    .ota-confirm-modal__message { margin: 0 0 0.75rem; font-size: 0.92rem; color: #334155; }
    .ota-confirm-modal__actions { margin-top: 0.9rem; display: flex; gap: 0.55rem; justify-content: flex-end; flex-wrap: wrap; }
</style>
@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-sabre-advanced-api-config-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = document.getElementById('ota-sabre-advanced-api-modal-' + btn.getAttribute('data-connection-id'));
            if (modal) { modal.hidden = false; document.body.classList.add('overflow-hidden'); }
        });
    });
    document.querySelectorAll('[data-close-sabre-advanced-api]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = btn.closest('.ota-confirm-modal');
            if (modal) { modal.hidden = true; document.body.classList.remove('overflow-hidden'); }
        });
    });
})();
</script>
@endpush
@endsection
