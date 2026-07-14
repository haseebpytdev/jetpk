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
use App\Support\Suppliers\IatiSupplierConnectionNormalizer;
use App\Support\Suppliers\PiaNdcSupplierConnectionNormalizer;
use App\Support\Suppliers\SabreSupplierConnectionNormalizer;
use App\Support\Suppliers\SupplierCredentialFormPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupplierConnectionController extends Controller
{
    public function __construct(
        protected SupplierConnectionService $service,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SupplierConnection::class);

        $query = $this->scopedQuery($request->user())
            ->withStoredCredentials();
        $connections = (clone $query)->orderBy('provider')->paginate(20);

        $kpiBase = $this->scopedQuery($request->user())
            ->withStoredCredentials();
        $kpis = [
            'total' => (clone $kpiBase)->count(),
            'active' => (clone $kpiBase)->where('status', SupplierConnectionStatus::Active)->count(),
            'sandbox' => (clone $kpiBase)->where('environment', SupplierEnvironment::Sandbox)->count(),
            'live' => (clone $kpiBase)->where('environment', SupplierEnvironment::Live)->count(),
        ];
        $activeRealSupplierExists = (clone $kpiBase)
            ->where('status', SupplierConnectionStatus::Active)
            ->exists();

        return view(client_view('api-settings.index', 'admin'), [
            'connections' => $connections,
            'kpis' => $kpis,
            'hasRows' => $connections->count() > 0,
            'fallbackSuppliers' => config('ota-suppliers.suppliers', []),
            'activeRealSupplierExists' => $activeRealSupplierExists,
        ]);
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

    public function store(StoreSupplierConnectionRequest $request): RedirectResponse
    {
        Gate::authorize('create', SupplierConnection::class);
        $agency = $request->user()->currentAgency;
        abort_if($agency === null, 403, 'No agency context assigned.');

        $this->service->storeConnection($agency, $this->payload($request));

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

    public function update(UpdateSupplierConnectionRequest $request, SupplierConnection $supplierConnection): RedirectResponse
    {
        Gate::authorize('update', $supplierConnection);
        $this->service->updateConnection($supplierConnection, $this->payload($request, $supplierConnection));

        return redirect()->route('admin.api-settings')->with('status', 'supplier-connection-updated');
    }

    public function destroy(SupplierConnection $supplierConnection): RedirectResponse
    {
        Gate::authorize('delete', $supplierConnection);
        $supplierConnection->delete();

        return redirect()->route('admin.api-settings')->with('status', 'supplier-connection-deleted');
    }

    public function test(Request $request, SupplierConnection $supplierConnection): RedirectResponse
    {
        Gate::authorize('update', $supplierConnection);
        $result = $this->service->testConnection($supplierConnection, $request->user());

        return back()->with('status', 'supplier-test-ran')->with('test_result', $result);
    }

    public function toggleStatus(Request $request, SupplierConnection $supplierConnection): RedirectResponse
    {
        Gate::authorize('update', $supplierConnection);
        $newStatus = $supplierConnection->status === SupplierConnectionStatus::Active
            ? SupplierConnectionStatus::Inactive
            : SupplierConnectionStatus::Active;

        $this->service->updateConnection($supplierConnection, [
            'status' => $newStatus,
            'is_active' => $newStatus === SupplierConnectionStatus::Active,
        ]);

        return back()->with('status', 'supplier-status-toggled');
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

        return AirBlueSupplierConnectionNormalizer::normalizePayload(
            PiaNdcSupplierConnectionNormalizer::normalizePayload(
                IatiSupplierConnectionNormalizer::normalizePayload(
                    SabreSupplierConnectionNormalizer::normalizePayload($payload, $existing),
                    $existing
                ),
                $existing
            ),
            $existing
        );
    }
}
