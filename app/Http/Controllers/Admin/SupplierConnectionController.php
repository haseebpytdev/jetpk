<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierConnectionRequest;
use App\Http\Requests\Admin\UpdateSupplierConnectionRequest;
use App\Models\SupplierConnection;
use App\Services\Suppliers\SupplierConnectionService;
use App\Support\Suppliers\AirBlueSupplierConnectionNormalizer;
use App\Support\Suppliers\AlHaiderSupplierConnectionNormalizer;
use App\Support\Suppliers\AmeerEMillatSupplierConnectionNormalizer;
use App\Support\Suppliers\IatiSupplierConnectionNormalizer;
use App\Support\Suppliers\OneApiSupplierConnectionNormalizer;
use App\Support\Suppliers\PiaNdcSupplierConnectionNormalizer;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use App\Support\Suppliers\SabreSupplierConnectionNormalizer;
use App\Support\Suppliers\SupplierCredentialFormPresenter;
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
     * @return list<array{key: string, label: string, channel: string, description: string, configured: bool}>
     */
    private function providerCards(array $configuredProviders): array
    {
        $catalog = [
            ['key' => 'sabre', 'label' => 'Sabre', 'channel' => 'GDS / NDC', 'description' => 'Sabre GDS and NDC channels with CERT/LIVE environments.', 'icon' => 'SB', 'capabilities' => ['GDS', 'NDC', 'PNR'], 'readiness' => 'Recommended'],
            ['key' => 'pia_ndc', 'label' => 'PIA NDC', 'channel' => 'NDC', 'description' => 'Pakistan International Airlines NDC direct connect.', 'icon' => 'PK', 'capabilities' => ['NDC', 'Direct'], 'readiness' => 'Live ready'],
            ['key' => 'airblue', 'label' => 'AirBlue / Zapways', 'channel' => 'API', 'description' => 'AirBlue Zapways inventory channel.', 'icon' => 'AB', 'capabilities' => ['API', 'LCC'], 'readiness' => 'Sandbox'],
            ['key' => 'iati', 'label' => 'IATI', 'channel' => 'API', 'description' => 'IATI consolidated inventory and booking API.', 'icon' => 'IA', 'capabilities' => ['API', 'Search'], 'readiness' => 'Sandbox'],
            ['key' => 'duffel', 'label' => 'Duffel', 'channel' => 'API', 'description' => 'Duffel NDC aggregator for global content.', 'icon' => 'DF', 'capabilities' => ['NDC', 'Global'], 'readiness' => 'Sandbox'],
            ['key' => 'airline_direct', 'label' => 'Airline Direct', 'channel' => 'Direct', 'description' => 'Direct airline API or portal integration.', 'icon' => 'AD', 'capabilities' => ['Direct'], 'readiness' => 'Custom'],
            ['key' => 'airsial', 'label' => 'AirSial', 'channel' => 'Direct', 'description' => 'AirSial direct inventory and booking channel.', 'icon' => 'AS', 'capabilities' => ['Direct', 'LCC'], 'readiness' => 'Live ready'],
            ['key' => 'al_haider', 'label' => 'Al-Haider', 'channel' => 'Group', 'description' => 'Al-Haider Umrah group ticketing and package inventory.', 'icon' => 'AH', 'capabilities' => ['Group', 'Umrah'], 'readiness' => 'Group'],
            ['key' => 'ameer_e_millat', 'label' => 'Ameer-e-Millat', 'channel' => 'Group', 'description' => 'Ameer-e-Millat group flight inventory and post-payment booking.', 'icon' => 'AM', 'capabilities' => ['Group', 'Live Inventory', 'Booking'], 'readiness' => 'Group'],
            ['key' => 'one_api', 'label' => 'One API', 'channel' => 'API', 'description' => 'One API consolidated channel (Air Arabia / FlyJinnah family where configured).', 'icon' => 'OA', 'capabilities' => ['API', 'LCC'], 'readiness' => 'Live ready'],
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
        $sanitized = $this->sanitizeTestResult(is_array($result) ? $result : ['ok' => true, 'message' => 'Test completed']);

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
        $provider = $connection->provider instanceof SupplierProvider
            ? $connection->provider->value
            : (string) $connection->provider;
        $isSabre = $provider === SupplierProvider::Sabre->value;

        return [
            'id' => (string) $connection->id,
            'name' => (string) ($connection->display_name ?: $connection->name),
            'provider' => $provider,
            'environment' => $connection->environment?->value ?? '',
            'status' => $connection->status?->value ?? '',
            'enabled' => (bool) $connection->is_active,
            'channel' => $isSabre ? 'gds' : $provider,
            'credentialsConfigured' => is_array($connection->credentials) && $connection->credentials !== [],
            'maskedCredentials' => $connection->maskedCredentials(),
            'lastTestedAt' => $connection->last_tested_at?->toIso8601String(),
            'lastTestStatus' => $connection->last_test_status,
            'lastFailure' => $this->sanitizeFailure((string) ($connection->last_error ?? '')),
            'sabreGdsSupported' => $isSabre ? true : null,
            'sabreGdsEnabled' => $isSabre ? SabreSupplierChannelConfig::gdsEnabled($connection) : null,
            'sabreNdcSupported' => $isSabre ? true : null,
            'sabreNdcEnabled' => $isSabre ? SabreSupplierChannelConfig::ndcEnabled($connection) : false,
            'registryLabel' => $isSabre ? SabreSupplierChannelConfig::connectionAdminLabel($connection) : null,
            'baseUrl' => filled($connection->base_url) ? (string) $connection->base_url : null,
            'baseUrlOverridable' => in_array($provider, [
                SupplierProvider::PiaNdc->value,
                SupplierProvider::Airblue->value,
                SupplierProvider::AlHaider->value,
            ], true),
            'credentialFields' => $this->credentialFieldsFor($provider),
            'timeouts' => is_array($connection->settings) ? ($connection->settings['timeouts'] ?? null) : null,
            'advanced' => [
                'fields' => [],
                'values' => [],
                'timeouts' => is_array($connection->settings) ? ($connection->settings['timeouts'] ?? null) : null,
                'timeoutsUserConfigurable' => false,
                'baseUrlOverridable' => in_array($provider, [
                    SupplierProvider::PiaNdc->value,
                    SupplierProvider::Airblue->value,
                ], true),
                'readOnly' => [],
            ],
            'audit' => [
                'history' => [],
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
        foreach (SupplierProvider::cases() as $provider) {
            $fields = $this->credentialFieldsFor($provider->value);
            $catalog[] = [
                'key' => $provider->value,
                'label' => $provider->name,
                'installed' => true,
                'baseUrlOverridable' => in_array($provider->value, [
                    SupplierProvider::PiaNdc->value,
                    SupplierProvider::Airblue->value,
                    SupplierProvider::AlHaider->value,
                ], true),
                'credentialFields' => $fields,
                'advancedFields' => [],
                'state' => 'available',
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
