@php
    use App\Support\Suppliers\AirBlueSupplierConnectionNormalizer;
    use App\Support\Suppliers\IatiSupplierConnectionNormalizer;
    use App\Support\Suppliers\PiaNdcSupplierConnectionNormalizer;
    use App\Support\Suppliers\SabreSupplierChannelConfig;
    use App\Support\Suppliers\SabreSupplierConnectionNormalizer;
    use App\Support\Suppliers\SupplierCredentialFormPresenter;

    $providerCredentialConfig = $providerCredentialConfig ?? config('supplier_credentials.providers', []);
    $credentialFieldStatesByProvider = $credentialFieldStatesByProvider ?? [];
    $oldCredentials = is_array(old('credentials')) ? old('credentials') : [];
    $isEdit = $connection->exists;
    $preselectedProvider = $preselectedProvider ?? null;
    $fallbackProvider = $preselectedProvider ?? ($isEdit ? ($connection->provider?->value ?? 'duffel') : '');
    $resolvedProvider = old('provider', $connection->provider?->value ?? $fallbackProvider);
    $selectedProvider = $resolvedProvider;
    $selectedProviderFields = (array) data_get($providerCredentialConfig, $selectedProvider.'.fields', []);
    $providerLabel = ucfirst(str_replace('_', ' ', $connection->provider?->value ?? 'Provider'));
    $isSabre = $selectedProvider === 'sabre';
    $isIati = $selectedProvider === 'iati';
    $isPiaNdc = $selectedProvider === 'pia_ndc';
    $isAirblue = $selectedProvider === 'airblue';
    $isOneApi = $selectedProvider === 'one_api';
    $lockProvider = ($isIati && ($isEdit || $preselectedProvider === 'iati'))
        || ($isPiaNdc && ($isEdit || $preselectedProvider === 'pia_ndc'))
        || ($isAirblue && ($isEdit || $preselectedProvider === 'airblue'));
    $defaultIatiConnectionName = $defaultIatiConnectionName ?? IatiSupplierConnectionNormalizer::defaultConnectionName(null);
    $defaultPiaNdcConnectionName = $defaultPiaNdcConnectionName ?? PiaNdcSupplierConnectionNormalizer::defaultConnectionName(null);
    $defaultAirBlueConnectionName = $defaultAirBlueConnectionName ?? AirBlueSupplierConnectionNormalizer::defaultConnectionName(null);
    $defaultConnectionName = old('name', $connection->name ?: ($isIati && ! $isEdit ? $defaultIatiConnectionName : ($isPiaNdc && ! $isEdit ? $defaultPiaNdcConnectionName : ($isAirblue && ! $isEdit ? $defaultAirBlueConnectionName : ''))));
    $sabreEnv = old('environment', $connection->environment?->value ?? 'sandbox');
    if ($isSabre && $sabreEnv === 'demo') {
        $sabreEnv = 'sandbox';
    }
    $iatiEnv = IatiSupplierConnectionNormalizer::normalizeEnvironment(old('environment', $connection->environment?->value ?? 'sandbox'));
    $piaNdcEnv = PiaNdcSupplierConnectionNormalizer::normalizeEnvironment(old('environment', $connection->environment?->value ?? 'sandbox'));
    $airblueEnv = AirBlueSupplierConnectionNormalizer::normalizeEnvironment(old('environment', $connection->environment?->value ?? 'sandbox'));
    $airblueChannel = old('credentials.api_channel', data_get($connection->credentials, 'api_channel', 'crane_ndc'));
    $iatiBaseUrl = IatiSupplierConnectionNormalizer::flightBaseUrlForEnvironment($iatiEnv);
    $sabreBaseUrl = SabreSupplierConnectionNormalizer::baseUrlForEnvironment($sabreEnv);
    $sabreMasked = $sabreMaskedSummary ?? [];
    $settingsJsonDefault = old('settings_json', $connection->settings ? json_encode($connection->settings, JSON_PRETTY_PRINT) : '{}');
    $statusValue = old('status', $connection->status?->value ?? 'inactive');
    $baseUrlOverride = (bool) old('advanced_base_url_override', false);
    $sabreChannelConfig = $isSabre ? SabreSupplierChannelConfig::fromConnection($connection) : null;
    $sabreGdsEnabled = (bool) old('sabre_gds_enabled', $sabreChannelConfig?->gdsEnabled ?? true);
    $sabreNdcEnabled = (bool) old('sabre_ndc_enabled', $sabreChannelConfig?->ndcEnabled ?? false);
    $hasProviderSelected = $selectedProvider !== '';
@endphp
<form method="POST" action="{{ $action }}" class="jp-supplier-form jp-form-shell" data-supplier-connection-form>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="jp-form-section">
        <div class="jp-form-section__head">
            <h2 class="jp-form-section__title">Connection</h2>
            <p class="jp-form-section__hint">Select the supplier, name this connection, and set operational status.</p>
        </div>
        <div class="jp-form-grid jp-form-grid--2">
            <div class="jp-field">
                <label class="jp-label" for="supplier-provider">Provider</label>
                <select id="supplier-provider" name="provider" class="jp-control" required data-provider-select @disabled($lockProvider)>
                    @if (! $isEdit && ! $preselectedProvider)
                        <option value="" @selected($selectedProvider === '')>Select a provider…</option>
                    @endif
                    @foreach ($providers as $provider)
                        <option value="{{ $provider->value }}" @selected($resolvedProvider === $provider->value)>
                            {{ strtoupper(match ($provider->value) { 'iati' => 'IATI', 'pia_ndc' => 'PIA NDC', 'airblue' => 'AirBlue', 'one_api' => 'One API', default => str_replace('_', ' ', $provider->value) }) }}
                        </option>
                    @endforeach
                </select>
                @if ($lockProvider && $isIati)<input type="hidden" name="provider" value="iati">@endif
                @if ($lockProvider && $isPiaNdc)<input type="hidden" name="provider" value="pia_ndc">@endif
                @if ($lockProvider && $isAirblue)<input type="hidden" name="provider" value="airblue">@endif
            </div>
            <div class="jp-field">
                <label class="jp-label" for="supplier-name">Connection name</label>
                <input id="supplier-name" type="text" name="name" class="jp-control" value="{{ $defaultConnectionName }}" required data-connection-name>
            </div>
            <div class="jp-field">
                <label class="jp-label" for="supplier-status">Status</label>
                <select id="supplier-status" name="status" class="jp-control" data-connection-status>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($statusValue === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="jp-form-section {{ $hasProviderSelected ? '' : 'jp-is-hidden' }}" data-supplier-provider-config>
        <div class="jp-form-section__head">
            <h2 class="jp-form-section__title">Provider configuration</h2>
            <p class="jp-form-section__hint">Environment and endpoint for the selected supplier only.</p>
        </div>
        @include('dashboard.admin.api-settings.partials.supplier-panels.iati')
        @include('dashboard.admin.api-settings.partials.supplier-panels.pia_ndc')
        @include('dashboard.admin.api-settings.partials.supplier-panels.airblue')
        @include('dashboard.admin.api-settings.partials.supplier-panels.one_api')
        @include('dashboard.admin.api-settings.partials.supplier-panels.sabre')
        @include('dashboard.admin.api-settings.partials.supplier-panels.generic')
    </div>

    <div class="jp-form-section jp-form-section--secure {{ $hasProviderSelected ? '' : 'jp-is-hidden' }}" data-supplier-credentials-section>
        <div class="jp-form-section__head">
            <h2 class="jp-form-section__title">Credentials</h2>
            <p class="jp-form-section__hint">Encrypted at rest. Leave secrets blank when editing to keep existing values.</p>
        </div>
        <p class="form-hint jp-is-hidden" data-duffel-help style="margin-bottom:12px;">
            Duffel uses a single access token. Use a test token beginning with <code>duffel_test_</code> for sandbox.
        </p>
        @if (! empty($maskedCredentials))
            <div class="alert alert-secondary py-2" style="margin-bottom:12px;">
                <div class="small text-secondary mb-1">Saved credentials (masked):</div>
                @foreach ($maskedCredentials as $key => $masked)
                    <div><code>{{ $key }}</code>: {{ $masked }}</div>
                @endforeach
            </div>
        @endif
        @if (! empty($sabreMasked))
            <div class="alert alert-secondary py-2 {{ $isSabre ? '' : 'jp-is-hidden' }}" data-sabre-masked-summary style="margin-bottom:12px;">
                <div class="small text-secondary mb-1">Saved Sabre credentials (masked):</div>
                @foreach ($sabreMasked as $label => $masked)
                    <div>{{ $label }}: {{ $masked }}</div>
                @endforeach
            </div>
        @endif
        <div data-credentials-container>
            @foreach ($selectedProviderFields as $credentialKey => $fieldMeta)
                @php
                    $fieldStates = $credentialFieldStatesByProvider[$selectedProvider] ?? [];
                    $fieldState = $fieldStates[$credentialKey] ?? SupplierCredentialFormPresenter::buildFieldStates(
                        $selectedProvider,
                        is_array($connection->credentials) ? $connection->credentials : [],
                        $isEdit,
                        $oldCredentials,
                    )[$credentialKey] ?? [
                        'has_saved' => false,
                        'masked_label' => null,
                        'prefill_value' => (string) old('credentials.'.$credentialKey, ''),
                        'placeholder' => (string) ($fieldMeta['placeholder'] ?? ''),
                        'preserve_hint' => false,
                        'is_sensitive' => SupplierCredentialFormPresenter::isSensitive($credentialKey, $fieldMeta),
                    ];
                    $inputValue = (string) ($fieldState['prefill_value'] ?? '');
                    $inputPlaceholder = (string) ($fieldMeta['placeholder'] ?? '');
                    if (($fieldState['has_saved'] ?? false) && (($fieldState['is_sensitive'] ?? false) || SupplierCredentialFormPresenter::isSemiSensitive($credentialKey))) {
                        $inputPlaceholder = (string) ($fieldState['placeholder'] ?? 'Saved — leave blank to keep existing value.');
                    }
                    $editOptional = $isEdit && (
                        ($selectedProvider === 'duffel' && $credentialKey === 'access_token')
                        || ($selectedProvider === 'sabre' && in_array($credentialKey, ['sign_in', 'password'], true))
                        || ($selectedProvider === 'iati' && $credentialKey === 'secret')
                        || ($selectedProvider === 'pia_ndc' && in_array($credentialKey, ['username', 'password', 'agency_id', 'agency_name', 'owner_code', 'mco_invoice_number', 'payment_type'], true))
                        || ($selectedProvider === 'airblue' && in_array($credentialKey, ['username', 'password', 'agency_id', 'agency_name', 'owner_code', 'agent_password', 'client_id', 'client_key', 'agent_type', 'agent_id'], true))
                        || (($fieldState['has_saved'] ?? false) && ! empty($fieldMeta['required']))
                    );
                    if ($selectedProvider === 'airblue' && $credentialKey === 'api_channel') { continue; }
                    if ($selectedProvider === 'airblue' && ! empty($fieldMeta['channel']) && $fieldMeta['channel'] !== $airblueChannel) { continue; }
                @endphp
                <div class="jp-field{{ ($fieldMeta['type'] ?? 'text') === 'password' || in_array($credentialKey, ['access_token', 'secret', 'password', 'client_secret'], true) ? ' jp-secret-field' : '' }}" data-credential-field="{{ $credentialKey }}">
                    <label class="jp-label jp-label">
                        {{ $fieldMeta['label'] ?? str_replace('_', ' ', ucfirst($credentialKey)) }}
                        @if (! empty($fieldState['has_saved']) && ! empty($fieldState['masked_label']))
                            <span class="badge bg-secondary-lt">Saved: {{ $fieldState['masked_label'] }}</span>
                        @elseif (! empty($fieldState['has_saved']))
                            <span class="badge bg-secondary-lt">Saved</span>
                        @endif
                    </label>
                    <input
                        type="{{ $fieldMeta['type'] ?? 'text' }}"
                        name="credentials[{{ $credentialKey }}]"
                        class="jp-control jp-control"
                        value="{{ $inputValue }}"
                        placeholder="{{ $inputPlaceholder }}"
                        autocomplete="off"
                        @if (!empty($fieldMeta['required']) && ! $editOptional) required @endif
                    >
                    @if (! empty($fieldState['preserve_hint']))
                        <p class="form-hint">Saved value exists. Leave blank to keep it, or enter a new value.</p>
                    @elseif (!empty($fieldMeta['help']))
                        <p class="form-hint">{{ $fieldMeta['help'] }}</p>
                    @elseif($selectedProvider === 'duffel' && $credentialKey === 'access_token')
                        <p class="form-hint">Leave blank to keep existing token.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="{{ $hasProviderSelected ? '' : 'jp-is-hidden' }}" data-supplier-advanced-wrap>
        @include('dashboard.admin.api-settings.partials.supplier-advanced')
    </div>

    <div class="jp-action-bar">
        <a href="{{ client_route('admin.api-settings') }}" class="jp-btn jp-btn--ghost">Cancel</a>
        <div class="jp-action-bar__primary">
            @if ($isEdit && isset($deleteAction))
                <button type="button" class="jp-btn jp-btn--danger" data-open-delete-confirm aria-haspopup="dialog" aria-controls="ota-delete-connection-modal">Delete connection</button>
            @endif
            <button type="submit" class="jp-btn jp-btn--primary">Save connection</button>
        </div>
    </div>
</form>

@include('dashboard.admin.api-settings.partials.supplier-delete-modal')

@once
    @push('scripts')
        <script src="{{ rtrim(client_theme()->adminThemeUrl(), '/') }}/js/supplier-form.js?v=2"></script>
        <script>
            window.JetPkSupplierFormBoot = {
                providerCredentialConfig: @json($providerCredentialConfig),
                credentialFieldStatesByProvider: @json($credentialFieldStatesByProvider),
                oldCredentials: @json($oldCredentials),
                isEdit: @json($isEdit),
                defaultIatiName: @json($defaultIatiConnectionName),
                defaultPiaNdcName: @json($defaultPiaNdcConnectionName ?? 'PIA NDC / OTA'),
                defaultAirBlueName: @json($defaultAirBlueConnectionName ?? 'AirBlue / OTA'),
                airblueNdcUrl: @json(config('suppliers.airblue.default_ndc_base_url')),
                airblueOtaCertUrl: @json(config('suppliers.airblue.default_ota_qa_base_url')),
                airblueOtaLiveUrl: @json(config('suppliers.airblue.default_ota_base_url')),
                sabreCertUrl: @json(SabreSupplierConnectionNormalizer::CERT_BASE_URL),
                sabreLiveUrl: @json(SabreSupplierConnectionNormalizer::LIVE_BASE_URL),
                iatiCertUrl: @json(IatiSupplierConnectionNormalizer::CERT_FLIGHT_BASE),
                iatiLiveUrl: @json(IatiSupplierConnectionNormalizer::LIVE_FLIGHT_BASE),
            };
            if (typeof JetPkSupplierForm !== 'undefined') {
                JetPkSupplierForm.init(window.JetPkSupplierFormBoot);
            }
        </script>
    @endpush
@endonce
