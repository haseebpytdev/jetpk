@extends(client_layout('dashboard', 'admin'))

@section('title', 'API Settings')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">Integrations</div>
            <h1 class="jp-page-title">API Settings</h1>
            <div class="text-secondary mt-1">
                Supplier matrix from <code>config/ota-suppliers.php</code>. Outbound calls and credential storage are disabled on this screen.
            </div>
        </div>
    </div>
@endsection

@section('content')
    @php
        $suppliers = $suppliers ?? [];
        $integrationNotice = $integrationNotice ?? '';
    @endphp

    <div class="jp-alert jp-alert--info mb-4" role="alert">
        <div class="d-flex">
            <div><i class="ti ti-info-circle me-2"></i></div>
            <div>
                <strong>Policy.</strong>
                {{ $integrationNotice }}
            </div>
        </div>
    </div>

    {{-- Dynamic: encrypted vault, rotation, per-tenant overrides, health checks --}}
    <div class="row row-cards">
        @foreach ($suppliers as $key => $supplier)
            @php
                $status = $supplier['status'] ?? 'not_configured';
                $env = $supplier['environment'] ?? 'demo';
                $statusClass = match ($status) {
                    'connected' => 'bg-success',
                    'demo' => 'bg-primary',
                    default => 'bg-secondary',
                };
                $statusLabel = match ($status) {
                    'connected' => 'Connected',
                    'demo' => 'Demo',
                    default => 'Not configured',
                };
                $envClass = match ($env) {
                    'live' => 'bg-danger',
                    'sandbox' => 'bg-warning',
                    default => 'bg-info',
                };
                $envLabel = ucfirst($env);
            @endphp
            <div class="col-md-6 col-lg-6">
                <div class="card h-100 shadow-sm" data-supplier-card>
                    <div class="jp-card__body">
                        <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                            <div>
                                <h3 class="jp-card__title mb-1">{{ $supplier['name'] ?? $key }}</h3>
                                <div class="text-secondary small text-uppercase mb-2">{{ $supplier['type'] ?? '' }}</div>
                            </div>
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                <span class="badge {{ $envClass }}">{{ $envLabel }}</span>
                            </div>
                        </div>

                        <p class="text-secondary small mt-2 mb-3 text-break">{{ $supplier['notes'] ?? '' }}</p>

                        <div class="mb-3">
                            <div class="text-secondary small text-uppercase fw-semibold mb-2">Required credentials</div>
                            @if (!empty($supplier['required_credentials']) && is_array($supplier['required_credentials']))
                                <ul class="list-unstyled mb-0 small">
                                    @foreach ($supplier['required_credentials'] as $item)
                                        <li class="d-flex gap-2 py-1 border-bottom border-secondary border-opacity-25">
                                            <span class="text-primary"><i class="ti ti-check"></i></span>
                                            <span>{{ $item }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </div>

                        <button type="button" class="jp-btn jp-btn--primary btn-sm btn-planned-action w-100" disabled aria-disabled="true">
                            Configure @include('components.planned-hint')
                        </button>
                        <span class="text-secondary small ms-2 d-block d-md-inline mt-1 mt-md-0">Vault not connected in this build.</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

