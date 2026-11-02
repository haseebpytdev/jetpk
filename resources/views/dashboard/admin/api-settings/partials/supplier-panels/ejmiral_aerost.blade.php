@php
    $isEjmiralAerost = ($selectedProvider ?? '') === 'ejmiral_aerost';
@endphp
<div class="jp-provider-panel {{ $isEjmiralAerost ? '' : 'jp-is-hidden' }}" data-provider-panel="ejmiral_aerost">
    <div class="jp-form-grid jp-form-grid--2">
        <div class="jp-field">
            <label class="jp-label" for="ejmiral-aerost-environment">Environment</label>
            <select id="ejmiral-aerost-environment" name="environment" class="jp-control" @disabled(! $isEjmiralAerost) @required($isEjmiralAerost)>
                @foreach ($environments as $environment)
                    <option value="{{ $environment->value }}" @selected(old('environment', $connection->environment?->value ?? 'sandbox') === $environment->value)>
                        {{ ucfirst($environment->value) }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    @php
        $apiHostStatus = app(\App\Services\Suppliers\EjmiralAerost\EjmiralAerostConfigResolver::class)->apiBaseUrlStatus();
        $aerostReadiness = ($isEjmiralAerost && isset($connection) && $connection->exists)
            ? app(\App\Services\Suppliers\EjmiralAerost\EjmiralAerostReadinessService::class)->dimensions($connection)
            : null;
    @endphp

    <div class="jp-card jp-card--muted mt-3 mb-0">
        <h3 class="jp-card__title h6">API Host Status</h3>
        <p class="small mb-2">
            {{ $apiHostStatus === 'CONFIGURED' ? 'Configured' : 'Unresolved' }}
            — system <code>EJMIRAL_AEROST_API_BASE_URL</code> only (not operator-editable).
        </p>
        @if (is_array($aerostReadiness))
            <h3 class="jp-card__title h6">Connection Readiness</h3>
            <ul class="mb-0 small">
                @foreach ($aerostReadiness as $dimension)
                    <li>
                        <strong>{{ $dimension['label'] }}:</strong>
                        {{ ($dimension['ready'] ?? false) ? 'Ready' : 'Blocked' }}
                        — {{ $dimension['detail'] }}
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="form-hint mt-2 mb-0">
            PSA portal (<code>https://sa.uat.ejmiral.com:7777</code>) is onboarding/admin only.
            Do not store PSA credentials or paste bearer tokens here — tokens are issued automatically.
        </p>
    </div>
</div>
