@extends('layouts.developer')

@section('title', 'Theme — '.$profile->name)

@section('page-header')
    <h1 class="ota-dev-cp-page-title h2 mb-1">{{ $profile->name }} — Theme</h1>
    <p class="text-secondary mb-0">Theme and asset profile for <code>{{ $profile->slug }}</code>. Preview routing is not wired yet.</p>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('developer.clients.partials.tabs', ['profile' => $profile, 'activeTab' => 'theme'])

    <div class="card mb-3 border-primary">
        <div class="card-header bg-primary text-white">UI Runtime Engine (MC-8D)</div>
        <div class="card-body">
            <p class="text-secondary small mb-3">
                The runtime engine resolves theme views and layouts at request time. Production legacy views remain the safe fallback until pages opt in with <code>client_view()</code> and <code>client_layout()</code>.
            </p>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <h3 class="h6">Active themes (this profile)</h3>
                    <dl class="small mb-0">
                        <dt>Frontend theme</dt>
                        <dd><code>{{ $themeSummary['areas']['frontend']['resolved'] ?? '—' }}</code></dd>
                        <dt>Admin theme</dt>
                        <dd><code>{{ $themeSummary['areas']['admin']['resolved'] ?? '—' }}</code></dd>
                        <dt>Staff theme</dt>
                        <dd><code>{{ $themeSummary['areas']['staff']['resolved'] ?? '—' }}</code></dd>
                        <dt>Asset profile</dt>
                        <dd><code>{{ $uiRuntimeEngine['asset_profile'] ?? '—' }}</code></dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <h3 class="h6">Resolver status</h3>
                    <dl class="small mb-0">
                        <dt>View resolver</dt>
                        <dd>{{ $uiRuntimeEngine['view_resolver_status'] ?? '—' }}</dd>
                        <dt>Layout resolver</dt>
                        <dd>{{ $uiRuntimeEngine['layout_resolver_status'] ?? '—' }}</dd>
                        <dt>Layout fallback</dt>
                        <dd>Production <code>layouts.*</code> used when theme layout file is missing</dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <h3 class="h6">What is live vs registered</h3>
                    <dl class="small mb-0">
                        <dt>Live (resolved)</dt>
                        <dd>
                            frontend <code>{{ $themeSummary['areas']['frontend']['resolved'] ?? '—' }}</code>,
                            admin <code>{{ $themeSummary['areas']['admin']['resolved'] ?? '—' }}</code>,
                            staff <code>{{ $themeSummary['areas']['staff']['resolved'] ?? '—' }}</code>
                        </dd>
                        <dt>Registered but not active</dt>
                        <dd>
                            @if (($uiRuntimeEngine['registered_not_active'] ?? []) === [])
                                <span class="text-secondary">None</span>
                            @else
                                @foreach ($uiRuntimeEngine['registered_not_active'] as $inactiveTheme)
                                    <code>{{ $inactiveTheme }}</code>@if (! $loop->last), @endif
                                @endforeach
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            <h3 class="h6">Theme view roots</h3>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Theme root</th>
                            <th>Fallback root</th>
                            <th>Theme root exists</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($viewResolutionSummary as $areaSummary)
                            <tr>
                                <td><code>{{ $areaSummary['area'] }}</code></td>
                                <td><code>{{ $areaSummary['theme_view_root'] }}</code></td>
                                <td><code>{{ $areaSummary['fallback_root'] }}</code></td>
                                <td>{{ ! empty($areaSummary['theme_root_exists']) ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h3 class="h6">Layout resolution (sample)</h3>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Layout</th>
                            <th>Resolved layout</th>
                            <th>Fallback used</th>
                            <th>Theme layout exists</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($layoutResolutionSummary as $layoutSummary)
                            <tr>
                                <td><code>{{ $layoutSummary['area'] }}</code></td>
                                <td>{{ $layoutSummary['label'] }}</td>
                                <td><code>{{ $layoutSummary['resolved_layout_name'] }}</code></td>
                                <td>{{ ! empty($layoutSummary['fallback_used']) ? 'Yes' : 'No' }}</td>
                                <td>{{ ! empty($layoutSummary['theme_layout_exists']) ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h3 class="h6">Developer instructions</h3>
            <ul class="small mb-0">
                <li><strong>Theme</strong> controls which view/layout folder is preferred under <code>resources/views/themes/{area}/{theme}/</code>.</li>
                <li><strong>Asset profile</strong> controls the client asset folder under <code>public/client-assets/{clientSlug}</code>.</li>
                <li><strong>Fallback</strong> — production layout/view is used if the theme file is missing. Do not delete legacy layouts.</li>
                <li>Add new client UI under <code>resources/views/themes/{area}/{theme}/</code>.</li>
                <li>Add theme assets under <code>public/themes/{area}/{theme}</code>.</li>
                <li>Add client-specific assets under <code>public/client-assets/{clientSlug}</code>.</li>
                <li>Use <code>client_view()</code> for page views, <code>client_layout()</code> for layouts, <code>client_route()</code> / <code>client_url()</code> for links.</li>
                <li>Verify with <code>php artisan ota:ui-runtime-audit --client={{ $profile->slug }}</code>.</li>
            </ul>
        </div>
    </div>

    @if (($themeSummary['warnings'] ?? []) !== [])
        <div class="alert alert-warning">
            <strong>Theme fallback / missing warnings</strong>
            <ul class="mb-0 mt-2">
                @foreach ($themeSummary['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header">Runtime theme registry (MC-8A)</div>
        <div class="card-body">
            <p class="text-secondary small mb-3">
                Selected values come from this profile. Resolved values are registry-validated; invalid or empty selections fall back to the area default.
            </p>

            <div class="row g-3">
                @foreach (['frontend' => 'Frontend', 'admin' => 'Admin', 'staff' => 'Staff'] as $areaKey => $areaLabel)
                    @php
                        $areaSummary = $themeSummary['areas'][$areaKey] ?? [];
                        $themes = $availableThemes[$areaKey] ?? [];
                    @endphp
                    <div class="col-lg-4">
                        <h3 class="h6">{{ $areaLabel }} themes</h3>
                        <table class="table table-sm table-bordered mb-2">
                            <thead>
                                <tr>
                                    <th>Key</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($themes as $theme)
                                    <tr>
                                        <td><code>{{ $theme['key'] }}</code></td>
                                        <td>{{ $theme['name'] }}</td>
                                        <td>{{ $theme['status'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-secondary">No registered themes.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        <dl class="small mb-0">
                            <dt>Selected</dt>
                            <dd><code>{{ $areaSummary['selected'] ?? '(empty)' }}</code></dd>
                            <dt>Resolved</dt>
                            <dd><code>{{ $areaSummary['resolved'] ?? '—' }}</code></dd>
                            <dt>Asset base</dt>
                            <dd><code>{{ $areaSummary['asset_base'] ?? '—' }}</code></dd>
                            <dt>Fallback used</dt>
                            <dd>{{ ! empty($areaSummary['used_fallback']) ? 'Yes' : 'No' }}</dd>
                        </dl>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">View resolution summary (MC-8B)</div>
        <div class="card-body">
            <p class="text-secondary small mb-3">
                Theme-specific views resolve under <code>resources/views/themes/{area}/{theme}/</code> when present; otherwise production legacy view names are used. Layouts resolve the same way via <code>client_layout()</code>.
            </p>

            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Resolved theme</th>
                            <th>Theme view root</th>
                            <th>Fallback root</th>
                            <th>Theme root exists</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($viewResolutionSummary as $areaSummary)
                            <tr>
                                <td><code>{{ $areaSummary['area'] }}</code></td>
                                <td><code>{{ $areaSummary['resolved_theme'] }}</code></td>
                                <td><code>{{ $areaSummary['theme_view_root'] }}</code></td>
                                <td><code>{{ $areaSummary['fallback_root'] }}</code></td>
                                <td>{{ ! empty($areaSummary['theme_root_exists']) ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-secondary small mb-0 mt-3">
                {{ $viewResolutionSummary['frontend']['note'] ?? 'MC-8D resolver active.' }}
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('dev.cp.clients.theme.update', $profile) }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-md-6">
                    <label class="form-label" for="active_frontend_theme">Frontend theme</label>
                    <input type="text" name="active_frontend_theme" id="active_frontend_theme" class="form-control @error('active_frontend_theme') is-invalid @enderror"
                           value="{{ old('active_frontend_theme', $profile->active_frontend_theme) }}" required maxlength="64"
                           placeholder="e.g. v1-classic, v2-modern">
                    @error('active_frontend_theme')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="active_admin_theme">Admin theme</label>
                    <input type="text" name="active_admin_theme" id="active_admin_theme" class="form-control @error('active_admin_theme') is-invalid @enderror"
                           value="{{ old('active_admin_theme', $profile->active_admin_theme) }}" maxlength="64">
                    @error('active_admin_theme')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="active_staff_theme">Staff theme</label>
                    <input type="text" name="active_staff_theme" id="active_staff_theme" class="form-control @error('active_staff_theme') is-invalid @enderror"
                           value="{{ old('active_staff_theme', $profile->active_staff_theme) }}" maxlength="64">
                    @error('active_staff_theme')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="asset_profile">Asset profile</label>
                    <input type="text" name="asset_profile" id="asset_profile" class="form-control @error('asset_profile') is-invalid @enderror"
                           value="{{ old('asset_profile', $profile->asset_profile) }}" required maxlength="255">
                    @error('asset_profile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="preview_path">Preview path</label>
                    <input type="text" name="preview_path" id="preview_path" class="form-control @error('preview_path') is-invalid @enderror"
                           value="{{ old('preview_path', $profile->preview_path) }}" maxlength="255"
                           placeholder="Reserved — preview routing not implemented">
                    @error('preview_path')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Slug</label>
                    <input type="text" class="form-control" value="{{ $profile->slug }}" readonly disabled>
                </div>
                @if ($profile->is_master_profile)
                    <div class="col-12">
                        <label class="form-check">
                            <input type="checkbox" name="confirm_master_edit" value="1" class="form-check-input @error('confirm_master_edit') is-invalid @enderror"
                                   @checked(old('confirm_master_edit') === '1')>
                            <span class="form-check-label">I confirm editing the master deployment profile</span>
                            @error('confirm_master_edit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </label>
                    </div>
                @endif
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Save theme</button>
                </div>
            </form>
        </div>
    </div>
@endsection
