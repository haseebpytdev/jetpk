@php
    use App\Services\Suppliers\OneApi\Support\OneApiConfigResolver;
    use App\Services\Suppliers\OneApi\Support\OneApiReadinessService;
    use App\Support\Suppliers\OneApiAccountContext;
    use App\Support\Suppliers\OneApiSupplierConnectionNormalizer;

    $oneApiEnv = OneApiSupplierConnectionNormalizer::normalizeEnvironment(old('environment', $connection->environment?->value ?? 'sandbox'));
    $accountContext = is_array(old('settings.account_context'))
        ? old('settings.account_context')
        : (is_array(data_get($connection->settings, 'account_context')) ? data_get($connection->settings, 'account_context') : []);
    $accountStatus = (string) old(
        'settings.account_context_status',
        data_get($connection->settings, 'account_context_status', OneApiAccountContext::STATUS_UNRESOLVED)
    );
    $accountConfirmed = strtoupper($accountStatus) === OneApiAccountContext::STATUS_CONFIRMED;
    $sandboxEndpoints = (array) config('suppliers.one_api.endpoints.sandbox', []);
    $productionEndpoints = (array) config('suppliers.one_api.endpoints.production', []);
    $resolvedEndpoints = ($isOneApi ?? false) && isset($connection)
        ? app(OneApiConfigResolver::class)->resolveEndpointUrls($connection, is_array($connection->credentials) ? $connection->credentials : [])
        : ['rest_auth_url' => '', 'rest_search_url' => '', 'soap_url' => '', 'wsdl_url' => ''];
@endphp
<div class="jp-provider-panel {{ ($selectedProvider ?? '') === 'one_api' ? '' : 'jp-is-hidden' }}" data-provider-panel="one_api">
    <div class="jp-form-grid jp-form-grid--2">
        <div class="jp-field">
            <label class="form-label" for="one-api-environment">Environment</label>
            <select id="one-api-environment" name="environment" class="form-select" required @disabled(! ($isOneApi ?? false))>
                <option value="sandbox" @selected($oneApiEnv === 'sandbox')>Sandbox / UAT</option>
                <option value="live" @selected($oneApiEnv === 'live')>Production</option>
            </select>
            <p class="form-hint">REST/SOAP endpoints are system-managed from configuration for the selected environment.</p>
        </div>
    </div>

    <div class="jp-form-section" style="margin-top:16px;">
        <div class="jp-form-section__head">
            <h3 class="jp-form-section__title h6">Account context</h3>
            <p class="jp-form-section__hint">POS and currency for search requests. Confirm only after vendor-verified values.</p>
        </div>
        <div class="jp-form-grid jp-form-grid--2">
            <div class="jp-field">
                <label class="jp-label" for="one-api-currency">Agent preferred currency</label>
                <input id="one-api-currency" type="text" name="settings[account_context][agent_preferred_currency]" class="jp-control" maxlength="3"
                    value="{{ old('settings.account_context.agent_preferred_currency', $accountContext['agent_preferred_currency'] ?? '') }}" placeholder="e.g. AED" @disabled(! ($isOneApi ?? false))>
            </div>
            <div class="jp-field">
                <label class="jp-label" for="one-api-pos-country">POS country</label>
                <input id="one-api-pos-country" type="text" name="settings[account_context][pos_country]" class="jp-control" maxlength="2"
                    value="{{ old('settings.account_context.pos_country', $accountContext['pos_country'] ?? '') }}" placeholder="e.g. AE" @disabled(! ($isOneApi ?? false))>
            </div>
            <div class="jp-field">
                <label class="jp-label" for="one-api-pos-station">POS station</label>
                <input id="one-api-pos-station" type="text" name="settings[account_context][pos_station]" class="jp-control" maxlength="3"
                    value="{{ old('settings.account_context.pos_station', $accountContext['pos_station'] ?? '') }}" placeholder="e.g. DXB" @disabled(! ($isOneApi ?? false))>
            </div>
            <div class="jp-field jp-field--full">
                <label class="jp-check">
                    <input class="form-check-input" type="checkbox" name="account_context_confirm" value="1" @checked(old('account_context_confirm') || $accountConfirmed) @disabled(! ($isOneApi ?? false))>
                    <span>Confirm account POS/currency context is verified with the vendor</span>
                </label>
                <p class="form-hint mb-0">Status: <strong>{{ $accountConfirmed ? 'Confirmed' : 'Unresolved' }}</strong>. Search stays blocked until confirmed.</p>
            </div>
        </div>
    </div>

    @if(($selectedProvider ?? '') === 'one_api' && isset($connection))
        @php
            $readiness = isset($connection->id)
                ? app(OneApiReadinessService::class)->dimensions($connection)
                : [];
        @endphp
        <div class="jp-card jp-card--muted mb-3" style="margin-top:16px;">
            <h3 class="jp-card__title h6">Connection readiness</h3>
            @if ($readiness !== [])
                <ul class="mb-0 small">
                    @foreach ([OneApiReadinessService::CREDENTIAL_READINESS_KEY, OneApiReadinessService::SEARCH_READINESS_KEY] as $readinessKey)
                        @if(isset($readiness[$readinessKey]))
                            @php $dimension = $readiness[$readinessKey]; @endphp
                            <li>
                                <strong>{{ $dimension['label'] }}:</strong>
                                {{ ($dimension['ready'] ?? false) ? 'Ready' : 'Blocked' }}
                                — {{ $dimension['detail'] }}
                            </li>
                        @endif
                    @endforeach
                </ul>
            @else
                <p class="form-hint mb-0">Save credentials to evaluate readiness.</p>
            @endif
        </div>
    @endif

    <details class="jp-advanced-panel" style="margin-top:8px;">
        <summary>System endpoints (read-only)</summary>
        <div class="jp-advanced-panel__body small">
            <p class="form-hint">Sandbox/UAT defaults are preconfigured. Production URLs remain empty until supplied via environment variables.</p>
            <dl class="mb-0">
                <dt>Sandbox REST auth</dt><dd><code>{{ $sandboxEndpoints['rest_auth_url'] ?? '—' }}</code></dd>
                <dt>Sandbox REST search</dt><dd><code>{{ $sandboxEndpoints['rest_search_url'] ?? '—' }}</code></dd>
                <dt>Sandbox SOAP</dt><dd><code>{{ $sandboxEndpoints['soap_url'] ?? '—' }}</code></dd>
                <dt>Sandbox WSDL</dt><dd><code>{{ $sandboxEndpoints['wsdl_url'] ?? '—' }}</code></dd>
                @if(($resolvedEndpoints['rest_auth_url'] ?? '') !== '')
                    <dt>Resolved for this connection</dt>
                    <dd>Auth: <code>{{ $resolvedEndpoints['rest_auth_url'] }}</code></dd>
                @endif
                @if(($productionEndpoints['rest_auth_url'] ?? '') === '')
                    <dt>Production</dt><dd class="text-secondary">Not configured (awaiting vendor URLs).</dd>
                @endif
            </dl>
        </div>
    </details>
</div>
