@php
    $activeRoute = request()->route()?->getName();
    $branding = $brandingResolver->all();
    $themes = $themeResolver->all();
@endphp

<nav class="preview-nav" aria-label="Preview portals">
    @foreach ([
        'client.preview.home' => 'Home',
        'client.preview.login' => 'Login',
        'client.preview.admin' => 'Admin',
        'client.preview.staff' => 'Staff',
        'client.preview.agent' => 'Agent',
    ] as $routeName => $label)
        <a href="{{ route($routeName, ['clientSlug' => $profile->slug]) }}"
           @class(['is-active' => $activeRoute === $routeName])>
            {{ $label }}
        </a>
    @endforeach
</nav>

<div class="preview-card">
    <h2>Client context</h2>
    <table class="preview-table">
        <tbody>
            <tr>
                <th>Name</th>
                <td>{{ $profile->name }}</td>
            </tr>
            <tr>
                <th>Slug</th>
                <td><code>{{ $profile->slug }}</code></td>
            </tr>
            <tr>
                <th>Environment</th>
                <td>{{ $profile->environment }}</td>
            </tr>
            <tr>
                <th>Preview active</th>
                <td>{{ $context->isPreview() ? 'Yes' : 'No' }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Resolved branding</h2>
    <table class="preview-table">
        <tbody>
            <tr>
                <th>Company</th>
                <td>{{ $branding['company_name'] }}</td>
            </tr>
            @if ($branding['logo_url'])
                <tr>
                    <th>Logo</th>
                    <td>
                        <code>{{ $branding['logo_url'] }}</code>
                        <div class="preview-brand-asset">
                            <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['company_name'] }} logo" loading="lazy">
                        </div>
                    </td>
                </tr>
            @endif
            @if ($branding['favicon_url'])
                <tr>
                    <th>Favicon</th>
                    <td>
                        <code>{{ $branding['favicon_url'] }}</code>
                        <div class="preview-brand-asset">
                            <img src="{{ $branding['favicon_url'] }}" alt="{{ $branding['company_name'] }} favicon" width="32" height="32" loading="lazy">
                        </div>
                    </td>
                </tr>
            @endif
            <tr>
                <th>Primary color</th>
                <td>
                    <span class="preview-swatch" style="background: {{ $branding['primary_color'] }}"></span>
                    <code>{{ $branding['primary_color'] }}</code>
                </td>
            </tr>
            <tr>
                <th>Secondary color</th>
                <td>
                    <span class="preview-swatch" style="background: {{ $branding['secondary_color'] }}"></span>
                    <code>{{ $branding['secondary_color'] }}</code>
                </td>
            </tr>
            <tr>
                <th>Accent color</th>
                <td>
                    <span class="preview-swatch" style="background: {{ $branding['accent_color'] }}"></span>
                    <code>{{ $branding['accent_color'] }}</code>
                </td>
            </tr>
            <tr>
                <th>Phone</th>
                <td>{{ $branding['phone'] !== '' ? $branding['phone'] : '—' }}</td>
            </tr>
            <tr>
                <th>Email</th>
                <td>{{ $branding['email'] !== '' ? $branding['email'] : '—' }}</td>
            </tr>
            <tr>
                <th>Address</th>
                <td>{{ $branding['address'] !== '' ? $branding['address'] : '—' }}</td>
            </tr>
            <tr>
                <th>Footer text</th>
                <td>{{ $branding['footer_text'] !== '' ? $branding['footer_text'] : '—' }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Resolved themes</h2>
    <table class="preview-table">
        <tbody>
            <tr>
                <th>Frontend theme</th>
                <td>
                    <code>{{ $themes['frontend_theme'] }}</code>
                    @if ($themes['frontend_theme_exists'])
                        <span class="flag-true">(on disk)</span>
                    @else
                        <span class="flag-false">(not on disk)</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Admin theme</th>
                <td>
                    <code>{{ $themes['admin_theme'] }}</code>
                    @if ($themes['admin_theme_exists'])
                        <span class="flag-true">(on disk)</span>
                    @else
                        <span class="flag-false">(not on disk)</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Staff theme</th>
                <td>
                    <code>{{ $themes['staff_theme'] }}</code>
                    @if ($themes['staff_theme_exists'])
                        <span class="flag-true">(on disk)</span>
                    @else
                        <span class="flag-false">(not on disk)</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Asset profile</th>
                <td><code>{{ $themes['asset_profile'] }}</code></td>
            </tr>
            <tr>
                <th>Frontend theme URL</th>
                <td><code>{{ $themes['frontend_theme_url'] }}</code></td>
            </tr>
            <tr>
                <th>Admin theme URL</th>
                <td><code>{{ $themes['admin_theme_url'] }}</code></td>
            </tr>
            <tr>
                <th>Staff theme URL</th>
                <td><code>{{ $themes['staff_theme_url'] }}</code></td>
            </tr>
        </tbody>
    </table>

    <h2>Resolved assets</h2>
    <table class="preview-table">
        <tbody>
            <tr>
                <th>Client asset base URL</th>
                <td><code>{{ $assetResolver->clientAssetUrl('') }}</code></td>
            </tr>
            @if ($assetResolver->logoUrl())
                <tr>
                    <th>Logo URL</th>
                    <td><code>{{ $assetResolver->logoUrl() }}</code></td>
                </tr>
            @endif
            @if ($assetResolver->faviconUrl())
                <tr>
                    <th>Favicon URL</th>
                    <td><code>{{ $assetResolver->faviconUrl() }}</code></td>
                </tr>
            @endif
        </tbody>
    </table>

    <h2>Module flags</h2>
    <table class="preview-table">
        <tbody>
            @forelse ($context->modules() as $moduleKey => $enabled)
                <tr>
                    <th><code>{{ $moduleKey }}</code></th>
                    <td @class(['flag-true' => $enabled, 'flag-false' => ! $enabled])>
                        {{ $enabled ? 'enabled' : 'disabled' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No module rows configured.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Supplier flags</h2>
    <table class="preview-table">
        <tbody>
            @forelse ($context->suppliers() as $supplier)
                <tr>
                    <th><code>{{ $supplier->supplier_key }}</code></th>
                    <td @class(['flag-true' => $supplier->enabled, 'flag-false' => ! $supplier->enabled])>
                        {{ $supplier->enabled ? 'enabled' : 'disabled' }}
                        @if ($supplier->mode)
                            ({{ $supplier->mode }})
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No supplier rows configured.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
