<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierConnectionRequest;
use App\Http\Requests\Admin\UpdateSupplierConnectionRequest;
use App\Models\SupplierConnection;
use App\Services\Suppliers\SupplierConnectionService;
use App\Support\Suppliers\AlHaiderSupplierConnectionNormalizer;
use App\Support\Suppliers\AirBlueSupplierConnectionNormalizer;
use App\Support\Suppliers\IatiSupplierConnectionNormalizer;
use App\Support\Suppliers\OneApiSupplierConnectionNormalizer;
use App\Support\Suppliers\PiaNdcSupplierConnectionNormalizer;
use App\Support\Suppliers\SabreCapabilityTruth;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use App\Support\Suppliers\SabreSupplierConnectionNormalizer;
use App\Support\Suppliers\SupplierCredentialFormPresenter;
use App\Support\Suppliers\SupplierProviderFieldCatalog;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupplierConnectionController extends Controller
{
    use RespondsWithBackOfficeJson;
    public function __construct(
        protected SupplierConnectionService $service,
    ) {}

    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        Gate::authorize('viewAny', SupplierConnection::class);

        $query = $this->scopedQuery($request->user())
            ->withStoredCredentials();
        $connections = (clone $query)->orderBy('provider')->paginate(20);

        if ($this->wantsBackOfficeJson($request)) {
            $existingProviders = (clone $query)->pluck('provider')->map(fn ($p) => $p->value ?? (string) $p)->all();

            return $this->backOfficeJson([
                'ok' => true,
                'connections' => collect($connections->items())->map(fn ($row) => $this->presentConnection($row))->all(),
                'providers' => $this->providerCatalog(),
                'providerCards' => $this->providerCards($existingProviders),
            ]);
        }

        // Legacy HTML entry: Integrations Hub is the authoritative configuration surface.
        // JSON/API consumers of this action remain unchanged above.
        return redirect()->to('/admin/dashboard/integrations');
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', SupplierConnection::class);

        $agency = $request->user()->currentAgency;
        $preselectedProvider = $request->query('provider');
        if (! is_string($preselectedProvider) || ! in_array($preselectedProvider, array_column(SupplierProvider::cases(), 'value'), true)) {
            $preselectedProvider = null;
        }

        $existingProviders = $this->scopedQuery($request->user())->pluck('provider')->map(fn ($p) => $p->value ?? (string) $p)->all();

        return view(client_view('api-settings.create', 'admin'), [
            'connection' => new SupplierConnection,
            'providers' => SupplierProvider::cases(),
            'environments' => SupplierEnvironment::cases(),
            'statuses' => SupplierConnectionStatus::cases(),
            'maskedCredentials' => [],
            'sabreMaskedSummary' => [],
            'providerCredentialConfig' => config('supplier_credentials.providers', []),
            'preselectedProvider' => $preselectedProvider,
            'showProviderPicker' => $preselectedProvider === null,
            'providerCards' => $this->providerCards($existingProviders),
            'defaultIatiConnectionName' => IatiSupplierConnectionNormalizer::defaultConnectionName($agency?->name),
            'defaultPiaNdcConnectionName' => PiaNdcSupplierConnectionNormalizer::defaultConnectionName($agency?->name),
            'defaultAirBlueConnectionName' => AirBlueSupplierConnectionNormalizer::defaultConnectionName($agency?->name),
            'credentialFieldStatesByProvider' => SupplierCredentialFormPresenter::buildFieldStatesByProvider(false),
            'action' => route('admin.api-settings.store'),
            'method' => 'POST',
        ]);
    }

    /**
     * @param  list<string>  $configuredProviders
     * @return list<array{key: string, label: string, channel: string, description: string, configured: bool}>
     */
    private function providerCards(array $configuredProviders): array
    {
        $catalog = [
            ['key' => 'sabre', 'label' => 'Sabre', 'channel' => 'GDS / NDC', 'description' => 'Sabre GDS and NDC channels with CERT/LIVE environments.', 'icon' => 'SB', 'capabilities' => ['GDS', 'NDC', 'PNR'], 'readiness' => 'Recommended'],
            ['key' => 'pia_ndc', 'label' => 'PIA NDC', 'channel' => 'NDC', 'description' => 'Pakistan International Airlines NDC direct connect.', 'icon' => 'PK', 'capabilities' => ['NDC', 'Direct'], 'readiness' => 'Live ready'],
            ['key' => 'airblue', 'label' => 'AirBlue / Zapways', 'channel' => 'API', 'description' => 'AirBlue Zapways/Crane inventory channel.', 'icon' => 'AB', 'capabilities' => ['API', 'LCC'], 'readiness' => 'Sandbox'],
            ['key' => 'iati', 'label' => 'IATI', 'channel' => 'API', 'description' => 'IATI consolidated inventory and booking API.', 'icon' => 'IA', 'capabilities' => ['API', 'Search'], 'readiness' => 'Sandbox'],
            ['key' => 'duffel', 'label' => 'Duffel', 'channel' => 'API', 'description' => 'Duffel NDC aggregator for global content.', 'icon' => 'DF', 'capabilities' => ['NDC', 'Global'], 'readiness' => 'Sandbox'],
            ['key' => 'airline_direct', 'label' => 'Airline Direct', 'channel' => 'Direct', 'description' => 'Direct airline API or portal integration.', 'icon' => 'AD', 'capabilities' => ['Direct'], 'readiness' => 'Custom'],
            ['key' => 'airsial', 'label' => 'AirSial', 'channel' => 'Direct', 'description' => 'AirSial direct inventory and booking channel.', 'icon' => 'AS', 'capabilities' => ['Direct', 'LCC'], 'readiness' => 'Live ready'],
            ['key' => 'al_haider', 'label' => 'Al-Haider', 'channel' => 'Group', 'description' => 'Al-Haider Umrah group ticketing and package inventory.', 'icon' => 'AH', 'capabilities' => ['Group', 'Umrah'], 'readiness' => 'Group'],
            ['key' => 'generic', 'label' => 'Generic', 'channel' => 'Other', 'description' => 'Generic supplier connection for custom integrations.', 'icon' => 'GX', 'capabilities' => ['Custom'], 'readiness' => 'Advanced'],
        ];

        return array_map(static function (array $row) use ($configuredProviders): array {
            $row['configured'] = in_array($row['key'], $configuredProviders, true);

            return $row;
        }, $catalog);
    }

    public function store(StoreSupplierConnectionRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', SupplierConnection::class);
        $agency = $request->user()->currentAgency;
        abort_if($agency === null, 403, 'No agency context assigned.');

        $connection = $this->service->storeConnection($agency, $this->payload($request));

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson(['ok' => true, 'connection' => $this->presentConnection($connection)]);
        }

        return redirect()->route('admin.api-settings')->with('status', 'supplier-connection-created');
    }

    public function edit(SupplierConnection $supplierConnection): View
    {
        Gate::authorize('view', $supplierConnection);

        $supplierConnection->loadMissing(['latestReadinessDiagnostic', 'latestSearchDiagnostic', 'latestOrderDiagnostic']);

        return view(client_view('api-settings.edit', 'admin'), [
            'connection' => $supplierConnection,
            'providers' => SupplierProvider::cases(),
            'environments' => SupplierEnvironment::cases(),
            'statuses' => SupplierConnectionStatus::cases(),
            'maskedCredentials' => $supplierConnection->provider === SupplierProvider::Sabre
                ? []
                : $supplierConnection->maskedCredentials(),
            'sabreMaskedSummary' => $supplierConnection->provider === SupplierProvider::Sabre
                ? SabreSupplierConnectionNormalizer::maskedSummary($supplierConnection)
                : [],
            'providerCredentialConfig' => config('supplier_credentials.providers', []),
            'preselectedProvider' => null,
            'defaultIatiConnectionName' => IatiSupplierConnectionNormalizer::defaultConnectionName($supplierConnection->agency?->name),
            'defaultPiaNdcConnectionName' => PiaNdcSupplierConnectionNormalizer::defaultConnectionName($supplierConnection->agency?->name),
            'defaultAirBlueConnectionName' => AirBlueSupplierConnectionNormalizer::defaultConnectionName($supplierConnection->agency?->name),
            'credentialFieldStatesByProvider' => SupplierCredentialFormPresenter::buildFieldStatesByProvider(
                true,
                is_array($supplierConnection->credentials) ? $supplierConnection->credentials : [],
                is_array(old('credentials')) ? old('credentials') : [],
                $supplierConnection->provider?->value,
            ),
            'action' => route('admin.api-settings.update', $supplierConnection),
            'method' => 'PATCH',
        ]);
    }

    public function update(UpdateSupplierConnectionRequest $request, SupplierConnection $supplierConnection): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $supplierConnection);
        $this->service->updateConnection($supplierConnection, $this->payload($request, $supplierConnection));

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson(['ok' => true, 'connection' => $this->presentConnection($supplierConnection->fresh() ?? $supplierConnection)]);
        }

        return redirect()->route('admin.api-settings')->with('status', 'supplier-connection-updated');
    }

    public function destroy(Request $request, SupplierConnection $supplierConnection): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $supplierConnection);
        $supplierConnection->delete();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson(['ok' => true]);
        }

        return redirect()->route('admin.api-settings')->with('status', 'supplier-connection-deleted');
    }

    public function test(Request $request, SupplierConnection $supplierConnection): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $supplierConnection);
        $result = $this->service->testConnection($supplierConnection, $request->user());

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'test' => is_array($result) ? $this->sanitizeTestResult($result) : ['status' => 'completed'],
                'connection' => $this->presentConnection($supplierConnection->fresh() ?? $supplierConnection),
            ]);
        }

        return back()->with('status', 'supplier-test-ran')->with('test_result', $result);
    }

    public function toggleStatus(Request $request, SupplierConnection $supplierConnection): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $supplierConnection);
        $newStatus = $supplierConnection->status === SupplierConnectionStatus::Active
            ? SupplierConnectionStatus::Inactive
            : SupplierConnectionStatus::Active;

        $this->service->updateConnection($supplierConnection, [
            'status' => $newStatus,
            'is_active' => $newStatus === SupplierConnectionStatus::Active,
        ]);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson(['ok' => true, 'connection' => $this->presentConnection($supplierConnection->fresh() ?? $supplierConnection)]);
        }

        return back()->with('status', 'supplier-status-toggled');
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentConnection(SupplierConnection $connection): array
    {
        $provider = $connection->provider instanceof SupplierProvider
            ? $connection->provider->value
            : (string) $connection->provider;

        return [
            'id' => (string) $connection->id,
            'name' => (string) ($connection->display_name ?: $connection->name),
            'provider' => $provider,
            'environment' => $connection->environment?->value ?? '',
            'status' => $connection->status?->value ?? '',
            'enabled' => (bool) $connection->is_active,
            'channel' => $provider === SupplierProvider::Sabre->value ? 'gds' : $provider,
            'credentialsConfigured' => is_array($connection->credentials) && $connection->credentials !== [],
            'maskedCredentials' => $connection->maskedCredentials(),
            'lastTestedAt' => $connection->last_tested_at?->toIso8601String(),
            'lastTestStatus' => $connection->last_test_status,
            'lastFailure' => $this->sanitizeFailure((string) ($connection->last_error ?? '')),
            'sabreGdsSupported' => $provider === SupplierProvider::Sabre->value
                ? SabreCapabilityTruth::gdsSupported()
                : null,
            'sabreGdsEnabled' => $provider === SupplierProvider::Sabre->value
                ? SabreSupplierChannelConfig::gdsEnabled($connection)
                : null,
            'sabreNdcSupported' => $provider === SupplierProvider::Sabre->value
                ? SabreCapabilityTruth::ndcSupported()
                : null,
            'sabreNdcEnabled' => $provider === SupplierProvider::Sabre->value && SabreCapabilityTruth::ndcSupported()
                ? SabreSupplierChannelConfig::ndcEnabled($connection)
                : false,
            'registryState' => \App\Support\Suppliers\SupplierRegistry::stateForConnection($connection),
            'registryLabel' => \App\Support\Suppliers\SupplierRegistry::businessLabel(
                \App\Support\Suppliers\SupplierRegistry::stateForConnection($connection)
            ),
            'baseUrl' => filled($connection->base_url) ? (string) $connection->base_url : null,
            'baseUrlOverridable' => in_array($provider, [SupplierProvider::PiaNdc->value, SupplierProvider::Airblue->value], true),
            'credentialFields' => $this->credentialFieldsFor($provider),
            'timeouts' => is_array($connection->settings) ? ($connection->settings['timeouts'] ?? null) : null,
            'advanced' => $this->presentAdvanced($connection, $provider),
            'audit' => $this->presentAudit($connection),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function providerCatalog(): array
    {
        $catalog = [];
        foreach (SupplierProvider::cases() as $provider) {
            $fields = SupplierProviderFieldCatalog::fieldsFor($provider->value);
            $catalog[] = [
                'key' => $provider->value,
                'label' => $provider->name,
                'installed' => \App\Support\Suppliers\SupplierRegistry::adapterInstalled($provider),
                'baseUrlOverridable' => in_array($provider->value, [
                    SupplierProvider::PiaNdc->value,
                    SupplierProvider::Airblue->value,
                    SupplierProvider::AlHaider->value,
                ], true),
                'credentialFields' => $fields,
                'advancedFields' => array_values(array_filter(
                    $fields,
                    static fn (array $field): bool => in_array($field['group'] ?? '', ['advanced', 'channel'], true),
                )),
                'state' => \App\Support\Suppliers\SupplierRegistry::stateForUnprovisioned($provider),
            ];
        }

        return $catalog;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function credentialFieldsFor(string $provider): array
    {
        return SupplierProviderFieldCatalog::fieldsFor($provider);
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentAdvanced(SupplierConnection $connection, string $provider): array
    {
        $fields = array_values(array_filter(
            SupplierProviderFieldCatalog::fieldsFor($provider),
            static fn (array $field): bool => in_array($field['group'] ?? '', ['advanced', 'channel'], true),
        ));
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $values = [];
        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $raw = $credentials[$key] ?? ($field['default'] ?? null);
            if ($raw === null || $raw === '') {
                continue;
            }
            $values[$key] = (string) $raw;
        }
        $settings = is_array($connection->settings) ? $connection->settings : [];
        $readOnly = [];
        if ($provider === SupplierProvider::Sabre->value) {
            $readOnly[] = [
                'key' => 'sabre_cancellation_gates',
                'label' => 'Sabre cancellation gates',
                'value' => 'Preserved internal policy — not editable here',
            ];
        }
        foreach ($settings as $key => $value) {
            if (! is_string($key) || in_array($key, ['advanced_base_url_override', 'timeouts'], true)) {
                continue;
            }
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $readOnly[] = [
                'key' => $key,
                'label' => str_replace('_', ' ', $key),
                'value' => is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value,
            ];
        }

        return [
            'fields' => $fields,
            'values' => $values,
            'timeouts' => $settings['timeouts'] ?? null,
            'timeoutsUserConfigurable' => false,
            'baseUrlOverridable' => in_array($provider, [SupplierProvider::PiaNdc->value, SupplierProvider::Airblue->value], true),
            'readOnly' => $readOnly,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentAudit(SupplierConnection $connection): array
    {
        $history = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
            $history = AuditLog::query()
                ->where('auditable_type', SupplierConnection::class)
                ->where('auditable_id', $connection->id)
                ->latest('id')
                ->limit(40)
                ->get()
                ->map(function (AuditLog $log): array {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'createdAt' => $log->created_at?->toIso8601String(),
                        'actorId' => $log->user_id,
                        'properties' => $this->redactAuditProperties($log->properties),
                    ];
                })
                ->all();
        }

        return [
            'history' => $history,
            'lastTestedAt' => $connection->last_tested_at?->toIso8601String(),
            'lastTestStatus' => $connection->last_test_status,
            'lastFailure' => $this->sanitizeFailure((string) ($connection->last_error ?? '')),
            'updatedAt' => $connection->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  mixed  $properties
     * @return mixed
     */
    protected function redactAuditProperties(mixed $properties): mixed
    {
        if (! is_array($properties)) {
            return $properties;
        }
        $blocked = ['password', 'secret', 'token', 'access_token', 'client_key', 'api_key', 'auth_code', 'credentials'];
        $out = [];
        foreach ($properties as $key => $value) {
            $name = strtolower((string) $key);
            if (in_array($name, $blocked, true) || str_contains($name, 'password') || str_contains($name, 'secret') || str_contains($name, 'token')) {
                $out[$key] = '[redacted]';

                continue;
            }
            $out[$key] = is_array($value) ? $this->redactAuditProperties($value) : $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function sanitizeTestResult(array $result): array
    {
        unset($result['credentials'], $result['password'], $result['token'], $result['secret']);

        return [
            'ok' => (bool) ($result['ok'] ?? $result['success'] ?? true),
            'message' => (string) ($result['message'] ?? $result['status'] ?? 'Test completed'),
        ];
    }

    protected function sanitizeFailure(string $error): ?string
    {
        $error = trim($error);
        if ($error === '') {
            return null;
        }

        $sanitized = preg_replace('/\b(pcc|lniata|password|token|secret|api[_-]?key)\b/i', '[redacted]', $error);

        return mb_substr((string) $sanitized, 0, 200);
    }

    protected function scopedQuery($user): Builder
    {
        $query = SupplierConnection::query()
            ->with([
                'latestReadinessDiagnostic',
                'latestSearchDiagnostic',
                'latestOrderDiagnostic',
            ]);
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Request $request, ?SupplierConnection $existing = null): array
    {
        $provider = $request->string('provider')->toString();
        $credentials = $request->input('credentials', []);
        if (! is_array($credentials)) {
            $credentials = [];
        }
        $providerFields = (array) config('supplier_credentials.providers.'.$provider.'.fields', []);
        $allowedKeys = array_keys($providerFields);
        $normalizedCredentials = [];

        $providerChanged = $existing !== null && $existing->provider->value !== $provider;
        $baseCredentials = $providerChanged ? [] : (($existing?->credentials && is_array($existing->credentials)) ? $existing->credentials : []);

        foreach ($allowedKeys as $key) {
            $raw = $credentials[$key] ?? null;
            $value = trim((string) $raw);
            if (SupplierCredentialFormPresenter::isMaskedPlaceholder($value)) {
                $value = '';
            }
            if ($value !== '') {
                $normalizedCredentials[$key] = $value;
            } elseif ($existing === null) {
                $default = $providerFields[$key]['default'] ?? null;
                if (is_string($default) && $default !== '') {
                    $normalizedCredentials[$key] = $default;
                }
            }
        }

        $credentials = array_merge($baseCredentials, $normalizedCredentials);

        $settings = is_array($existing?->settings) ? $existing->settings : [];
        if ($request->exists('settings_json')) {
            $settingsRaw = trim((string) $request->input('settings_json', ''));
            if ($settingsRaw === '') {
                $settings = [];
            } else {
                $decoded = json_decode($settingsRaw, true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }
        } elseif ($request->exists('settings') && is_array($request->input('settings'))) {
            $settings = $request->input('settings');
        }

        $meta = is_array($existing?->meta) ? $existing->meta : [];
        if ($request->exists('meta') && is_array($request->input('meta'))) {
            $meta = $request->input('meta');
        }

        $baseUrl = $existing?->base_url;
        if ($request->exists('base_url')) {
            $baseUrl = $request->string('base_url')->toString() ?: null;
        }

        $status = $request->input(
            'status',
            $existing?->status?->value ?? SupplierConnectionStatus::Inactive->value
        );

        $payload = [
            'provider' => $provider,
            'name' => $request->string('name')->toString(),
            'display_name' => $request->string('name')->toString(),
            'environment' => $request->string('environment')->toString(),
            'status' => $status,
            'base_url' => $baseUrl,
            'credentials' => $credentials,
            'settings' => $settings,
            'meta' => $meta,
            'is_active' => $status === SupplierConnectionStatus::Active->value,
            'advanced_base_url_override' => $existing !== null && ! $request->exists('advanced_base_url_override')
                ? true
                : $request->boolean('advanced_base_url_override'),
        ];

        if ($provider === SupplierProvider::Sabre->value) {
            if ($request->exists('sabre_gds_enabled')) {
                $payload['sabre_gds_enabled'] = $request->boolean('sabre_gds_enabled');
            }
            if ($request->exists('sabre_ndc_enabled')) {
                $payload['sabre_ndc_enabled'] = $request->boolean('sabre_ndc_enabled');
            }
        }

        return AlHaiderSupplierConnectionNormalizer::normalizePayload(
            OneApiSupplierConnectionNormalizer::normalizePayload(
                AirBlueSupplierConnectionNormalizer::normalizePayload(
                    PiaNdcSupplierConnectionNormalizer::normalizePayload(
                        IatiSupplierConnectionNormalizer::normalizePayload(
                            SabreSupplierConnectionNormalizer::normalizePayload($payload, $existing),
                            $existing
                        ),
                        $existing
                    ),
                    $existing
                ),
                $existing
            ),
            $existing
        );
    }
}
