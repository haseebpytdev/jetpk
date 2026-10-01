<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierConnectionRequest;
use App\Http\Requests\Admin\UpdateSupplierConnectionRequest;
use App\Models\AuditLog;
use App\Models\SupplierConnection;
use App\Services\Suppliers\SupplierConnectionService;
use App\Support\Integrations\PlatformIntegrationStatusPresenter;
use App\Support\Suppliers\AirBlueSupplierConnectionNormalizer;
use App\Support\Suppliers\AlHaiderSupplierConnectionNormalizer;
use App\Support\Suppliers\AmeerEMillatSupplierConnectionNormalizer;
use App\Support\Suppliers\IatiSupplierConnectionNormalizer;
use App\Support\Suppliers\OneApiSupplierConnectionNormalizer;
use App\Support\Suppliers\PiaNdcSupplierConnectionNormalizer;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use App\Support\Suppliers\SabreSupplierConnectionNormalizer;
use App\Support\Suppliers\SupplierCredentialFormPresenter;
use App\Support\Suppliers\SupplierIntegrationCatalog;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
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

        if ($this->wantsBackOfficeJson($request)) {
            $connections = (clone $query)->orderBy('provider')->orderBy('name')->limit(200)->get();
            $existingProviders = $connections->pluck('provider')->map(fn ($p) => $p->value ?? (string) $p)->all();

            return $this->backOfficeJson([
                'ok' => true,
                'connections' => $connections->map(fn ($row) => $this->presentConnection($row))->values()->all(),
                'providers' => $this->providerCatalog(),
                'providerCards' => $this->providerCards($existingProviders),
                'platformIntegrations' => PlatformIntegrationStatusPresenter::present(),
            ]);
        }

        return redirect()->to('/admin/dashboard/api-connections');
    }

    public function create(Request $request): RedirectResponse
    {
        Gate::authorize('create', SupplierConnection::class);

        $provider = $request->query('provider');
        $query = is_string($provider) && $provider !== '' ? ('?provider='.urlencode($provider)) : '';

        return redirect()->to('/admin/dashboard/api-connections'.$query);
    }

    /**
     * @param  list<string>  $configuredProviders
     * @return list<array<string, mixed>>
     */
    private function providerCards(array $configuredProviders): array
    {
        return array_map(static function (array $row) use ($configuredProviders): array {
            return [
                'key' => $row['key'],
                'label' => $row['label'],
                'channel' => $row['channel'],
                'description' => $row['description'],
                'icon' => $row['icon'] ?? null,
                'capabilities' => $row['capabilities'] ?? [],
                'readiness' => $row['readiness'] ?? null,
                'kind' => $row['kind'] ?? 'supplier',
                'implementation_state' => $row['implementation_state'] ?? null,
                'installed' => (bool) ($row['installed'] ?? false),
                'configured' => in_array($row['key'], $configuredProviders, true),
            ];
        }, SupplierIntegrationCatalog::definitions());
    }

    public function store(StoreSupplierConnectionRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', SupplierConnection::class);
        $agency = $request->user()->currentAgency;
        abort_if($agency === null, 403, 'No agency context assigned.');

        $connection = $this->service->storeConnection($agency, $this->payload($request));

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'connection' => $this->presentConnection($connection),
            ]);
        }

        return redirect()->to('/admin/dashboard/api-connections')->with('status', 'supplier-connection-created');
    }

    public function edit(SupplierConnection $supplierConnection): RedirectResponse
    {
        Gate::authorize('view', $supplierConnection);

        return redirect()->to('/admin/dashboard/api-connections?manage='.$supplierConnection->id);
    }

    public function update(UpdateSupplierConnectionRequest $request, SupplierConnection $supplierConnection): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $supplierConnection);
        $this->service->updateConnection($supplierConnection, $this->payload($request, $supplierConnection));

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'connection' => $this->presentConnection($supplierConnection->fresh() ?? $supplierConnection),
            ]);
        }

        return redirect()->to('/admin/dashboard/api-connections')->with('status', 'supplier-connection-updated');
    }

    public function destroy(Request $request, SupplierConnection $supplierConnection): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $supplierConnection);
        $supplierConnection->delete();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson(['ok' => true]);
        }

        return redirect()->to('/admin/dashboard/api-connections')->with('status', 'supplier-connection-deleted');
    }

    public function test(Request $request, SupplierConnection $supplierConnection): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $supplierConnection);
        $result = $this->service->testConnection($supplierConnection, $request->user());
        $sanitized = $this->sanitizeTestResult(is_array($result) ? $result : ['ok' => true, 'message' => 'Check completed']);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'test' => $sanitized,
                'connection' => $this->presentConnection($supplierConnection->fresh() ?? $supplierConnection),
            ]);
        }

        return redirect()->to('/admin/dashboard/api-connections')->with('status', 'supplier-test-ran')->with('test_result', $sanitized);
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
            return $this->backOfficeJson([
                'ok' => true,
                'connection' => $this->presentConnection($supplierConnection->fresh() ?? $supplierConnection),
            ]);
        }

        return redirect()->to('/admin/dashboard/api-connections')->with('status', 'supplier-status-toggled');
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentConnection(SupplierConnection $connection): array
    {
        $provider = $connection->providerKey();
        $isSabre = $provider === SupplierProvider::Sabre->value;
        $definition = SupplierIntegrationCatalog::definitionFor($provider);
        $checkType = SupplierIntegrationCatalog::checkTypeFor($provider);
        $baseUrlOverridable = SupplierIntegrationCatalog::baseUrlOverridable($provider);

        return [
            'id' => (string) $connection->id,
            'name' => (string) ($connection->display_name ?: $connection->name),
            'provider' => $provider,
            'environment' => $connection->environment?->value ?? '',
            'status' => $connection->status?->value ?? '',
            'enabled' => (bool) $connection->is_active,
            'channel' => $isSabre ? 'gds' : $provider,
            'retired' => $connection->isRetiredProvider(),
            'credentialsConfigured' => is_array($connection->credentials) && $connection->credentials !== [],
            'maskedCredentials' => $connection->maskedCredentials(),
            'lastTestedAt' => $connection->last_tested_at?->toIso8601String(),
            'lastTestStatus' => $connection->last_test_status,
            'lastFailure' => $this->sanitizeFailure((string) ($connection->last_error ?? '')),
            'checkType' => $checkType,
            'checkLabel' => $checkType === 'connectivity_probe' || $checkType === 'auth_probe'
                ? 'Test connection'
                : 'Validate configuration',
            'sabreGdsSupported' => $isSabre ? true : null,
            'sabreGdsEnabled' => $isSabre ? SabreSupplierChannelConfig::gdsEnabled($connection) : null,
            'sabreNdcSupported' => $isSabre ? true : null,
            'sabreNdcEnabled' => $isSabre ? SabreSupplierChannelConfig::ndcEnabled($connection) : false,
            'registryLabel' => $isSabre ? SabreSupplierChannelConfig::connectionAdminLabel($connection) : ($definition['label'] ?? null),
            'baseUrl' => filled($connection->base_url) ? (string) $connection->base_url : null,
            'baseUrlOverridable' => $baseUrlOverridable,
            'credentialFields' => $this->credentialFieldsFor($provider),
            'timeouts' => is_array($connection->settings) ? ($connection->settings['timeouts'] ?? null) : null,
            'advanced' => [
                'fields' => $this->advancedFieldsFor($connection),
                'values' => $this->advancedValuesFor($connection),
                'timeouts' => is_array($connection->settings) ? ($connection->settings['timeouts'] ?? null) : null,
                'timeoutsUserConfigurable' => false,
                'baseUrlOverridable' => $baseUrlOverridable,
                'readOnly' => [],
            ],
            'audit' => [
                'history' => $this->auditHistoryFor($connection),
                'lastTestedAt' => $connection->last_tested_at?->toIso8601String(),
                'lastTestStatus' => $connection->last_test_status,
                'lastFailure' => $this->sanitizeFailure((string) ($connection->last_error ?? '')),
                'updatedAt' => $connection->updated_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function providerCatalog(): array
    {
        $catalog = [];
        foreach (SupplierIntegrationCatalog::definitions() as $definition) {
            $key = (string) $definition['key'];
            $fields = $this->credentialFieldsFor($key);
            $catalog[] = [
                'key' => $key,
                'label' => (string) $definition['label'],
                'installed' => (bool) ($definition['installed'] ?? false),
                'kind' => (string) ($definition['kind'] ?? 'supplier'),
                'channel' => (string) ($definition['channel'] ?? ''),
                'implementation_state' => (string) ($definition['implementation_state'] ?? ''),
                'configuration_source' => (string) ($definition['configuration_source'] ?? 'supplier_connection'),
                'supports_create' => (bool) ($definition['supports_create'] ?? false),
                'supports_update' => (bool) ($definition['supports_update'] ?? false),
                'supports_delete' => (bool) ($definition['supports_delete'] ?? false),
                'supports_enable_disable' => (bool) ($definition['supports_enable_disable'] ?? false),
                'check_type' => (string) ($definition['check_type'] ?? 'configuration_validation'),
                'readiness' => (string) ($definition['readiness'] ?? ''),
                'baseUrlOverridable' => SupplierIntegrationCatalog::baseUrlOverridable($key),
                'credentialFields' => $fields,
                'advancedFields' => [],
                'capabilities' => $definition['capabilities'] ?? [],
                'state' => (string) ($definition['implementation_state'] ?? 'available'),
            ];
        }

        return $catalog;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function credentialFieldsFor(string $provider): array
    {
        $configured = (array) config('supplier_credentials.providers.'.$provider.'.fields', []);
        $fields = [];
        foreach ($configured as $key => $meta) {
            if (! is_array($meta)) {
                continue;
            }
            $fields[] = [
                'key' => (string) $key,
                'label' => (string) ($meta['label'] ?? $key),
                'type' => (string) ($meta['type'] ?? 'text'),
                'required' => (bool) ($meta['required'] ?? false),
                'placeholder' => (string) ($meta['placeholder'] ?? ''),
                'help' => (string) ($meta['help'] ?? ''),
                'default' => $meta['default'] ?? null,
                'group' => (string) ($meta['group'] ?? 'credentials'),
                'channel' => isset($meta['channel']) ? (string) $meta['channel'] : null,
                'options' => is_array($meta['options'] ?? null)
                    ? collect($meta['options'])->map(fn ($label, $value): array => [
                        'value' => (string) $value,
                        'label' => (string) $label,
                    ])->values()->all()
                    : null,
            ];
        }

        return $fields;
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
            'message' => (string) ($result['message'] ?? $result['status'] ?? 'Check completed'),
            'check_type' => (string) ($result['check_type'] ?? 'configuration_validation'),
            'last_test_status' => isset($result['last_test_status']) ? (string) $result['last_test_status'] : null,
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

    /**
     * @return list<array<string, mixed>>
     */
    protected function auditHistoryFor(SupplierConnection $connection): array
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->where('auditable_type', SupplierConnection::class)
            ->where('auditable_id', $connection->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => (string) $log->id,
                'action' => (string) $log->action,
                'actor' => (string) ($log->user?->name ?? 'System'),
                'at' => $log->created_at?->toIso8601String(),
                'environment' => $connection->environment?->value ?? '',
                'changes' => $this->sanitizeAuditProperties(is_array($log->properties) ? $log->properties : []),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    protected function sanitizeAuditProperties(array $properties): array
    {
        $secretPattern = '/password|secret|token|api[_-]?key|client_secret|credentials/i';
        $sanitize = static function ($value) use (&$sanitize, $secretPattern) {
            if (is_array($value)) {
                $clean = [];
                foreach ($value as $key => $item) {
                    if (preg_match($secretPattern, (string) $key)) {
                        $clean[$key] = '[redacted]';
                        continue;
                    }
                    $clean[$key] = $sanitize($item);
                }

                return $clean;
            }

            return $value;
        };

        return $sanitize($properties);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function advancedFieldsFor(SupplierConnection $connection): array
    {
        $settings = is_array($connection->settings) ? $connection->settings : [];
        $fields = [];
        foreach ($settings as $key => $value) {
            if (is_array($value) || preg_match('/password|secret|token|api[_-]?key|credentials/i', (string) $key)) {
                continue;
            }
            $fields[] = [
                'key' => (string) $key,
                'label' => ucwords(str_replace('_', ' ', (string) $key)),
                'type' => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'text'),
            ];
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    protected function advancedValuesFor(SupplierConnection $connection): array
    {
        $settings = is_array($connection->settings) ? $connection->settings : [];
        $values = [];
        foreach ($settings as $key => $value) {
            if (is_array($value) || preg_match('/password|secret|token|api[_-]?key|credentials/i', (string) $key)) {
                continue;
            }
            $values[(string) $key] = $value;
        }

        return $values;
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

        $providerChanged = $existing !== null && $existing->providerKey() !== $provider;
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

        $settings = [];
        $settingsRaw = trim((string) $request->input('settings_json', ''));
        if ($settingsRaw !== '') {
            $decoded = json_decode($settingsRaw, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }

        $meta = $request->input('meta', []);
        if (! is_array($meta)) {
            $meta = [];
        }

        $status = $request->input('status', SupplierConnectionStatus::Inactive->value);

        $payload = [
            'provider' => $provider,
            'name' => $request->string('name')->toString(),
            'display_name' => $request->string('name')->toString(),
            'environment' => $request->string('environment')->toString(),
            'status' => $status,
            'base_url' => $request->string('base_url')->toString() ?: null,
            'credentials' => $credentials,
            'settings' => $settings,
            'meta' => $meta,
            'is_active' => $status === SupplierConnectionStatus::Active->value,
            'advanced_base_url_override' => $request->boolean('advanced_base_url_override'),
        ];

        if ($provider === SupplierProvider::Sabre->value) {
            $payload['sabre_gds_enabled'] = $request->boolean('sabre_gds_enabled', true);
            $payload['sabre_ndc_enabled'] = $request->boolean('sabre_ndc_enabled', false);
        }

        return AmeerEMillatSupplierConnectionNormalizer::normalizePayload(
            AlHaiderSupplierConnectionNormalizer::normalizePayload(
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
            ),
            $existing
        );
    }
}
