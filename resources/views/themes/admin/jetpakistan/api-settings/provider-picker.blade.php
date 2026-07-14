@php
    /** @var list<array{key: string, label: string, channel: string, description: string, configured: bool, icon?: string, capabilities?: list<string>, readiness?: string}> $providers */
    $providers = $providers ?? [];
@endphp
<div class="jp-provider-picker" data-testid="supplier-provider-picker">
    <p class="jp-backlink"><a href="{{ client_route('admin.api-settings') }}">← Back to supplier connections</a></p>
    <div class="jp-provider-grid">
        @foreach ($providers as $provider)
            <article class="jp-provider-card">
                <div class="jp-provider-card__meta">
                    <div class="jp-provider-card__icon" aria-hidden="true">{{ $provider['icon'] ?? strtoupper(substr($provider['label'], 0, 2)) }}</div>
                    @if ($provider['configured'])
                        <span class="jp-status-badge jp-status-badge--success">Configured</span>
                    @else
                        <span class="jp-status-badge">{{ $provider['readiness'] ?? 'Ready' }}</span>
                    @endif
                </div>
                <h3 class="jp-provider-card__title">{{ $provider['label'] }}</h3>
                <p class="jp-provider-card__channel">{{ $provider['channel'] }}</p>
                <div class="jp-provider-card__caps">
                    @foreach ($provider['capabilities'] ?? [] as $cap)
                        <span class="jp-provider-card__cap">{{ $cap }}</span>
                    @endforeach
                </div>
                <p class="jp-provider-card__desc">{{ $provider['description'] }}</p>
                <p class="jp-provider-card__credential">
                    {{ $provider['configured'] ? 'Existing credentials on file' : 'No connection yet — guided setup' }}
                </p>
                <div class="jp-provider-card__actions">
                    <a href="{{ client_route('admin.api-settings.create', ['provider' => $provider['key']]) }}" class="jp-btn jp-btn--sm jp-btn--primary">Select</a>
                </div>
            </article>
        @endforeach
    </div>
</div>
