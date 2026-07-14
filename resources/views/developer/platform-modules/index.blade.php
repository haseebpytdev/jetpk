@extends('layouts.developer')

@section('title', 'Deployment Control Panel')

@section('page-header')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
            <h1 class="ota-dev-cp-page-title h2 mb-1" data-testid="dev-cp-deployment-title">Deployment Control Panel</h1>
            <p class="text-secondary mb-0">
                Choose deployment module access for this OTA install. This controls the current deployment/package, not individual agencies.
            </p>
        </div>
        <span class="badge bg-warning-lt text-warning fs-6">Developer access only</span>
    </div>
@endsection

@section('content')
        <style>
            .ota-dcp-stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.85rem 1rem; }
            .ota-dcp-stat strong { display: block; font-size: 1.35rem; line-height: 1.2; }
            .ota-dcp-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
            .ota-dcp-card + .ota-dcp-card { margin-top: 1rem; }
            .ota-dcp-card__head { padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; }
            .ota-dcp-card__body { padding: 1rem 1.25rem; }
            .ota-dcp-mode-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; height: 100%; display: flex; flex-direction: column; background: #fff; }
            .ota-dcp-mode-card h4 { font-size: 1rem; margin-bottom: 0.35rem; }
            .ota-dcp-mode-meta { font-size: 0.8rem; color: #64748b; margin-top: auto; padding-top: 0.75rem; }
            .ota-dcp-module-row { border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.85rem 1rem; background: #fff; }
            .ota-dcp-module-row + .ota-dcp-module-row { margin-top: 0.65rem; }
            .ota-dcp-pill { font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.55rem; border-radius: 999px; display: inline-block; }
            .ota-dcp-pill--active { background: #dcfce7; color: #166534; }
            .ota-dcp-pill--disabled { background: #f1f5f9; color: #475569; }
            .ota-dcp-pill--protected { background: #fee2e2; color: #991b1b; }
            .ota-dcp-pill--backend { background: #dbeafe; color: #1e40af; }
            .ota-dcp-pill--route { background: #e0e7ff; color: #3730a3; }
            .ota-dcp-pill--nav { background: #fef3c7; color: #92400e; }
            .ota-dcp-save-bar {
                position: sticky;
                bottom: 0;
                z-index: 20;
                background: rgba(241, 245, 249, 0.95);
                backdrop-filter: blur(6px);
                border-top: 1px solid #e2e8f0;
                padding: 0.85rem 0;
                margin-top: 1rem;
            }
            .ota-dcp-disabled-table th { font-size: 0.8rem; }
            @media (max-width: 767.98px) {
                .ota-dcp-disabled-table thead { display: none; }
                .ota-dcp-disabled-table tr { display: block; border-bottom: 1px solid #e2e8f0; padding: 0.65rem 0; }
                .ota-dcp-disabled-table td { display: block; border: 0; padding: 0.15rem 0; }
                .ota-dcp-disabled-table td::before {
                    content: attr(data-label);
                    font-weight: 600;
                    font-size: 0.75rem;
                    color: #64748b;
                    display: block;
                }
            }
        </style>

    @if (session('status'))
        <div class="alert alert-success mb-4">{{ session('status') }}</div>
    @endif

    <div class="alert alert-info mb-4" role="status">
        <strong>Deployment scope.</strong>
        Module and package controls apply globally to this OTA deployment.
        Agencies are managed by Platform Admin inside the OTA Admin Panel.
    </div>

    @if ($currentDeploymentPackageKey)
        <div class="alert alert-secondary mb-4">
            Current deployment package marker: <code>{{ $currentDeploymentPackageKey }}</code>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-4" data-testid="dev-cp-deployment-stats">
        <div class="col-6 col-md-4 col-lg">
            <div class="ota-dcp-stat"><span class="text-secondary small">Total modules</span><strong>{{ $deploymentStats['total'] }}</strong></div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="ota-dcp-stat"><span class="text-secondary small">Enabled / planned on</span><strong>{{ $deploymentStats['enabled'] }}</strong></div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="ota-dcp-stat"><span class="text-secondary small">Disabled / planned off</span><strong>{{ $deploymentStats['disabled'] }}</strong></div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="ota-dcp-stat"><span class="text-secondary small">Protected modules</span><strong>{{ $deploymentStats['protected'] }}</strong></div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="ota-dcp-stat"><span class="text-secondary small">Backend-enforced</span><strong>{{ $deploymentStats['backend_enforced'] }}</strong></div>
        </div>
    </div>

    @if (! $registryValidation->isValid())
        <div class="alert alert-warning mb-4">
            Current planned states have {{ count($registryValidation->violations()) }} dependency issue(s). Fix before saving if errors persist.
        </div>
    @endif

    <div class="ota-dcp-card mb-4" data-testid="dev-cp-deployment-packages">
        <div class="ota-dcp-card__head">
            <h2 class="h4 mb-1">Deployment package assignment</h2>
            <p class="text-secondary small mb-0">Apply a catalog package to this entire deployment (not per agency).</p>
        </div>
        <div class="ota-dcp-card__body">
            @if ($deploymentPackages === [])
                <p class="text-secondary small mb-0">No packages seeded. Run <code>php artisan devcp:seed-default-packages</code> locally or on the server.</p>
            @else
                <div class="row g-3">
                    @foreach ($deploymentPackages as $package)
                        <div class="col-md-6 col-xl-4">
                            <div class="ota-dcp-mode-card">
                                <h4>{{ $package['label'] }} @if ($package['is_current'])<span class="badge bg-primary-lt">Current</span>@endif</h4>
                                <p class="text-secondary small mb-2">{{ $package['description'] }}</p>
                                @if ($package['preset_key'])
                                    <form method="post" action="{{ route('dev.cp.modules.package') }}"
                                          onsubmit="return confirm('Apply deployment package &quot;{{ $package['label'] }}&quot; to this OTA install?');">
                                        @csrf
                                        <input type="hidden" name="package_id" value="{{ $package['id'] }}">
                                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Apply deployment package</button>
                                    </form>
                                @else
                                    <span class="text-secondary small">No linked preset</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="ota-dcp-card mb-4" data-testid="dev-cp-deployment-modes">
        <div class="ota-dcp-card__head">
            <h2 class="h4 mb-1">Deployment Modes</h2>
            <p class="text-secondary small mb-0">Apply a preset to quickly align this deployment. Review the preview counts before confirming.</p>
        </div>
        <div class="ota-dcp-card__body">
            <div class="row g-3">
                @foreach ($productModes as $modeKey => $mode)
                    <div class="col-md-6 col-xl-4">
                        <div class="ota-dcp-mode-card" data-testid="dev-cp-mode-{{ $modeKey }}">
                            <h4>{{ $mode['label'] }}</h4>
                            <p class="text-secondary small mb-2">{{ $mode['description'] }}</p>
                            <p class="small mb-1"><strong class="text-success">Enables:</strong> {{ $mode['enables'] }}</p>
                            <p class="small mb-2"><strong class="text-danger">Disables:</strong> {{ $mode['disables'] }}</p>
                            <span class="badge bg-{{ $mode['valid'] ? 'success' : 'warning' }}-lt text-{{ $mode['valid'] ? 'success' : 'warning' }} mb-2">
                                {{ $mode['valid'] ? 'Dependencies valid' : $mode['violation_count'].' dependency issue(s)' }}
                            </span>
                            <div class="ota-dcp-mode-meta">
                                Turns off <strong>{{ $mode['disable_count'] }}</strong> module(s),
                                turns on <strong>{{ $mode['enable_count'] }}</strong> module(s).
                            </div>
                            <form method="post" action="{{ route('dev.cp.modules.preset') }}" class="mt-2"
                                  onsubmit="return confirm('Apply deployment mode &quot;{{ $mode['label'] }}&quot;? This updates planned module states for this deployment.');">
                                @csrf
                                <input type="hidden" name="preset_key" value="{{ $modeKey }}">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Apply mode</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="ota-dcp-card mb-4" data-testid="dev-cp-disabled-summary">
        <div class="ota-dcp-card__head">
            <h2 class="h4 mb-1">Currently Disabled</h2>
            <p class="text-secondary small mb-0">Planned-off modules and their deployment impact.</p>
        </div>
        <div class="ota-dcp-card__body">
            @if ($disabledModules === [])
                <div class="alert alert-success mb-0">
                    All deployment modules are using default enabled state.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm ota-dcp-disabled-table mb-0">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Area</th>
                                <th>Impact</th>
                                <th>Enforcement</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($disabledModules as $module)
                                <tr>
                                    <td data-label="Module">{{ $module['label'] }}</td>
                                    <td data-label="Area">{{ $module['area_label'] }}</td>
                                    <td data-label="Impact">{{ $module['description'] }}</td>
                                    <td data-label="Enforcement">{{ $module['enforcement_summary'] }}</td>
                                    <td data-label="Action">
                                        <a href="#module-{{ $module['key'] }}" class="btn btn-outline-primary btn-sm">Re-enable</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <form method="post" action="{{ route('dev.cp.modules.update') }}" id="dev-cp-modules-form">
        @csrf

        @foreach ($moduleGroups as $group)
            @if (count($group['modules']) === 0)
                @continue
            @endif
            <details class="ota-dcp-card mb-3" {{ $group['default_open'] ? 'open' : '' }}>
                <summary class="ota-dcp-card__head" style="cursor: pointer; list-style: none;">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h2 class="h5 mb-1">{{ $group['title'] }}</h2>
                            <p class="text-secondary small mb-0">{{ $group['description'] }}</p>
                        </div>
                        <span class="badge bg-secondary-lt">{{ count($group['modules']) }} modules</span>
                    </div>
                </summary>
                <div class="ota-dcp-card__body">
                    @foreach ($group['modules'] as $module)
                        @php
                            $pill = $module['status_pill'];
                            $pillClass = match ($pill['tone']) {
                                'active' => 'ota-dcp-pill--active',
                                'disabled' => 'ota-dcp-pill--disabled',
                                'protected' => 'ota-dcp-pill--protected',
                                'backend' => 'ota-dcp-pill--backend',
                                'route' => 'ota-dcp-pill--route',
                                default => 'ota-dcp-pill--nav',
                            };
                        @endphp
                        <div class="ota-dcp-module-row" id="module-{{ $module['key'] }}" data-testid="dev-cp-module-{{ $module['key'] }}">
                            <div class="row g-2 align-items-start">
                                <div class="col-lg-7">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <span class="fw-semibold">{{ $module['label'] }}</span>
                                        <span class="ota-dcp-pill {{ $pillClass }}">{{ $pill['label'] }}</span>
                                        @if ($module['protected'])
                                            <span class="small text-danger"><i class="ti ti-lock"></i> Protected — cannot be disabled</span>
                                        @endif
                                    </div>
                                    <p class="text-secondary small mb-0">{{ $module['description'] }}</p>
                                </div>
                                <div class="col-lg-5">
                                    <div class="d-flex align-items-center justify-content-lg-end gap-2">
                                        @if ($module['toggle_disabled'])
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" role="switch" checked disabled
                                                       title="{{ $module['protected'] ? 'Protected modules cannot be disabled.' : 'This module is locked.' }}">
                                                <label class="form-check-label small text-muted">On</label>
                                            </div>
                                            @if ($module['protected'])
                                                <input type="hidden" name="modules[{{ $module['key'] }}]" value="1">
                                            @else
                                                <input type="hidden" name="modules[{{ $module['key'] }}]" value="{{ $module['effective_enabled'] ? '1' : '0' }}">
                                            @endif
                                        @else
                                            <input type="hidden" name="modules[{{ $module['key'] }}]" value="{{ $module['effective_enabled'] ? '1' : '0' }}" id="module-input-{{ $module['key'] }}">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                       id="module-switch-{{ $module['key'] }}"
                                                       @checked($module['effective_enabled'])
                                                       onchange="document.getElementById('module-input-{{ $module['key'] }}').value = this.checked ? '1' : '0';">
                                                <label class="form-check-label small" for="module-switch-{{ $module['key'] }}">{{ $module['effective_enabled'] ? 'On' : 'Off' }}</label>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <details class="mt-2">
                                <summary class="small text-primary" style="cursor: pointer;">Advanced details</summary>
                                <div class="small text-muted mt-2">
                                    <div><strong>Module key:</strong> <code>{{ $module['key'] }}</code></div>
                                    <div><strong>Area:</strong> {{ $module['area_label'] }}</div>
                                    <div><strong>Route middleware:</strong> {{ $module['route_middleware'] ? 'Yes' : 'No' }}</div>
                                    <div><strong>Backend service:</strong> {{ $module['backend_service'] ? 'Yes' : 'No' }}</div>
                                    <div><strong>Enforcement:</strong> {{ $module['enforcement_summary'] }}</div>
                                    @if ($module['provider_scope'])
                                        <div><strong>Provider scope:</strong> {{ $module['provider_scope'] }}</div>
                                    @endif
                                    @if ($module['related_routes'] !== [])
                                        <div><strong>Related routes:</strong>
                                            @foreach ($module['related_routes'] as $routeName)
                                                <code class="me-1">{{ $routeName }}</code>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($module['env_snapshot'] !== [])
                                        <div class="mt-1"><strong>Env snapshot:</strong></div>
                                        @foreach ($module['env_snapshot'] as $row)
                                            <div>{{ $row['label'] }}: {{ $row['value'] }}</div>
                                        @endforeach
                                    @endif
                                    <div class="mt-2">
                                        <label class="form-label small mb-1">Planning note</label>
                                        <textarea class="form-control form-control-sm" name="notes[{{ $module['key'] }}]" rows="2"
                                                  placeholder="Optional note">{{ old('notes.'.$module['key'], $module['db_notes']) }}</textarea>
                                    </div>
                                </div>
                            </details>
                        </div>
                    @endforeach
                </div>
            </details>
        @endforeach

        <div class="ota-dcp-save-bar">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="submit" class="btn btn-primary" data-testid="dev-cp-save-modules">Save changes</button>
                <span class="text-secondary small">Saves planned module states for this deployment.</span>
            </div>
        </div>
    </form>

    <div class="ota-dcp-card mt-4">
        <div class="ota-dcp-card__head">
            <h2 class="h5 mb-1">Reset actions</h2>
            <p class="text-secondary small mb-0">Use with care on production deployments.</p>
        </div>
        <div class="ota-dcp-card__body d-flex flex-wrap gap-2">
            <form method="post" action="{{ route('dev.cp.modules.reset') }}"
                  onsubmit="return confirm('Remove all DB overrides and revert every module to registry defaults?');">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">Reset to registry defaults</button>
            </form>
            <form method="post" action="{{ route('dev.cp.modules.emergency-reset') }}"
                  onsubmit="return confirm('Emergency reset: delete all module overrides immediately? Type RESET in the next prompt to continue.') && prompt('Type RESET to confirm emergency reset:') === 'RESET';">
                @csrf
                <button type="submit" class="btn btn-outline-danger">Emergency all-enabled reset</button>
            </form>
        </div>
    </div>
@endsection
