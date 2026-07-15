<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Developer\ApplyDevCpDeploymentPackageRequest;
use App\Http\Requests\Developer\ApplyPlatformModulePresetRequest;
use App\Http\Requests\Developer\UpdatePlatformModuleSettingsRequest;
use App\Models\DeveloperUser;
use App\Models\PlatformPackage;
use App\Services\Developer\DevCpDeploymentPackageService;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Services\Platform\PlatformPackageService;
use App\Support\Platform\PlatformModule;
use App\Support\Platform\PlatformModuleDependencyValidation;
use App\Support\Platform\PlatformModuleGate;
use App\Support\Platform\PlatformModuleRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Platform module registry and planned deployment states (Developer CP).
 */
class PlatformModuleControlController extends Controller
{
    public function __construct(
        protected PlatformModuleSettingsService $moduleSettings,
        protected PlatformPackageService $packages,
        protected DevCpDeploymentPackageService $deploymentPackage,
    ) {}

    public function index(Request $request): View
    {
        $agencyId = null;
        $currentStates = $this->moduleSettings->states();
        $presented = [];

        foreach (PlatformModuleRegistry::all() as $module) {
            $presented[$module->key] = $this->presentModule($module, $agencyId);
        }

        $productModes = [];
        $allModes = PlatformModuleRegistry::recommendedProductModes();
        foreach (PlatformModuleRegistry::featuredDeploymentPresetKeys() as $modeKey) {
            if (! isset($allModes[$modeKey])) {
                continue;
            }
            $mode = $allModes[$modeKey];
            $validation = PlatformModuleRegistry::validateDependencies($mode['modules']);
            $preview = PlatformModuleRegistry::presetApplyPreview($modeKey, $currentStates);
            $copy = self::deploymentModeCopy()[$modeKey] ?? ['enables' => '', 'disables' => ''];
            $productModes[$modeKey] = [
                'label' => $mode['label'],
                'description' => $mode['description'],
                'enables' => $copy['enables'],
                'disables' => $copy['disables'],
                'valid' => $validation->isValid(),
                'violation_count' => count($validation->violations()),
                'enable_count' => count($preview['enable']),
                'disable_count' => count($preview['disable']),
            ];
        }

        $moduleGroups = [];
        foreach (PlatformModuleRegistry::deploymentUiGroups() as $groupKey => $group) {
            $moduleGroups[$groupKey] = [
                'key' => $groupKey,
                'title' => $group['title'],
                'description' => $group['description'],
                'default_open' => $group['default_open'],
                'modules' => $this->modulesForUiGroup($group, $presented),
            ];
        }

        $disabledModules = [];
        foreach ($presented as $module) {
            if ($module['protected'] || $module['effective_enabled']) {
                continue;
            }
            $disabledModules[] = $module;
        }

        $stats = $this->buildDeploymentStats($presented);

        $currentPackageKey = $this->deploymentPackage->currentPackageKey();
        $deploymentPackages = [];
        foreach ($this->packages->activePackages() as $package) {
            $deploymentPackages[] = [
                'id' => $package->id,
                'key' => $package->key,
                'label' => $package->label,
                'description' => $package->description,
                'preset_key' => $this->packages->presetKeyForPackage($package),
                'is_current' => $currentPackageKey === $package->key,
            ];
        }

        return view('developer.platform-modules.index', [
            'moduleGroups' => $moduleGroups,
            'productModes' => $productModes,
            'disabledModules' => $disabledModules,
            'deploymentStats' => $stats,
            'registryValidation' => PlatformModuleRegistry::validateDependencies($currentStates),
            'moduleCount' => count($presented),
            'deploymentPackages' => $deploymentPackages,
            'currentDeploymentPackageKey' => $currentPackageKey,
        ]);
    }

    public function update(UpdatePlatformModuleSettingsRequest $request): RedirectResponse
    {
        $actor = $this->resolveDeveloperUser($request);
        $modules = [];
        foreach ($request->input('modules', []) as $key => $value) {
            $modules[(string) $key] = (bool) $value;
        }

        $validation = $this->moduleSettings->applyChanges(
            changes: $modules,
            actor: $actor,
            request: $request,
            source: 'manual',
        );

        return $this->redirectWithValidationResult($validation, 'Deployment module settings saved.');
    }

    public function applyPreset(ApplyPlatformModulePresetRequest $request): RedirectResponse
    {
        $actor = $this->resolveDeveloperUser($request);
        $presetKey = $request->string('preset_key')->toString();
        $modes = PlatformModuleRegistry::recommendedProductModes();
        $label = $modes[$presetKey]['label'] ?? $presetKey;
        $beforeStates = $this->moduleSettings->states();
        $preview = PlatformModuleRegistry::presetApplyPreview($presetKey, $beforeStates);

        $validation = $this->moduleSettings->applyPreset(
            presetKey: $presetKey,
            actor: $actor,
            request: $request,
        );

        $message = sprintf(
            'Preset "%s" applied. %d module(s) planned off, %d planned on.',
            $label,
            count($preview['disable']),
            count($preview['enable']),
        );

        return $this->redirectWithValidationResult($validation, $message);
    }

    public function applyPackage(ApplyDevCpDeploymentPackageRequest $request): RedirectResponse
    {
        $actor = $this->resolveDeveloperUser($request);
        $package = PlatformPackage::query()
            ->where('is_active', true)
            ->findOrFail((int) $request->input('package_id'));

        $presetKey = $this->packages->presetKeyForPackage($package);
        if ($presetKey === null) {
            return redirect()
                ->route('dev.cp.modules.index')
                ->withErrors(['package_id' => 'This package has no linked deployment preset.']);
        }

        $validation = $this->moduleSettings->applyPreset(
            presetKey: $presetKey,
            actor: $actor,
            request: $request,
        );

        $this->deploymentPackage->markApplied($package->key);

        $message = sprintf(
            'Deployment package "%s" applied to this OTA install.',
            $package->label,
        );

        return $this->redirectWithValidationResult($validation, $message);
    }

    public function reset(Request $request): RedirectResponse
    {
        $actor = $this->resolveDeveloperUser($request);
        $this->moduleSettings->resetToDefaults($actor, $request);

        return redirect()
            ->route('dev.cp.modules.index')
            ->with('status', 'All module overrides removed. Registry defaults restored.');
    }

    public function emergencyReset(Request $request): RedirectResponse
    {
        $actor = $this->resolveDeveloperUser($request);
        $this->moduleSettings->allEnabledEmergencyReset($actor, $request);

        return redirect()
            ->route('dev.cp.modules.index')
            ->with('status', 'Emergency reset complete. All modules use registry defaults.');
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     default_enabled: bool,
     *     db_override_label: string,
     *     effective_enabled: bool,
     *     effective_display: string,
     *     risk: string,
     *     protected: bool,
     *     locked: bool,
     *     requires_all: list<string>,
     *     requires_any: list<string>,
     *     dependents: list<string>,
     *     notes: list<string>,
     *     db_notes: string|null,
     *     config_hints: list<string>,
     *     env_snapshot: list<array{label: string, value: string}>,
     *     related_routes: list<string>,
     *     toggle_disabled: bool,
     *     route_middleware: bool,
     *     backend_service: bool,
     *     enforcement_summary: string,
     *     provider_scope: string|null,
     *     area_label: string,
     *     status_pill: array{label: string, tone: string},
     *     section: string
     * }
     */
    private function presentModule(PlatformModule $module, ?int $agencyId): array
    {
        $deps = PlatformModuleRegistry::dependenciesFor($module->key);
        $dbState = $this->moduleSettings->effectiveStateFor($module->key);
        $status = PlatformModuleGate::effectiveStatus($module->key, $agencyId);

        $dbOverrideLabel = match (true) {
            ! $dbState['db_row_exists'] => 'Default',
            $dbState['db_override'] === true => 'Enabled',
            default => 'Disabled',
        };

        $sections = PlatformModuleRegistry::sections();

        return [
            'key' => $module->key,
            'label' => $module->label,
            'description' => $module->description,
            'area_label' => $sections[$module->section] ?? $module->section,
            'status_pill' => $this->statusPillFor($module, $dbState, $status),
            'default_enabled' => $module->defaultEnabled,
            'db_override_label' => $dbOverrideLabel,
            'effective_enabled' => $dbState['effective_enabled'],
            'effective_display' => $status['display'],
            'nav_visible' => $status['visible'],
            'nav_hidden' => $status['nav_hidden'],
            'risk' => $module->risk,
            'protected' => $module->protected,
            'locked' => $dbState['locked'],
            'requires_all' => $deps['requiresAll'],
            'requires_any' => $deps['requiresAny'],
            'dependents' => PlatformModuleRegistry::dependentsOf($module->key),
            'notes' => $module->notes,
            'db_notes' => $dbState['notes'],
            'config_hints' => $module->configHints,
            'env_snapshot' => $status['env_snapshot'],
            'related_routes' => $module->relatedRoutes,
            'toggle_disabled' => $module->protected || $dbState['locked'],
            'route_middleware' => $status['route_middleware'] ?? PlatformModuleGate::hasRouteMiddleware($module->key),
            'backend_service' => $status['backend_service'] ?? PlatformModuleGate::hasBackendServiceEnforcement($module->key),
            'enforcement_summary' => $status['enforcement_summary'] ?? PlatformModuleGate::enforcementSummary($module->key),
            'provider_scope' => $status['provider_scope'] ?? PlatformModuleGate::providerScopeNote($module->key),
            'section' => $module->section,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $presented
     * @return array{
     *     total: int,
     *     enabled: int,
     *     disabled: int,
     *     protected: int,
     *     backend_enforced: int
     * }
     */
    private function buildDeploymentStats(array $presented): array
    {
        $stats = [
            'total' => count($presented),
            'enabled' => 0,
            'disabled' => 0,
            'protected' => 0,
            'backend_enforced' => 0,
        ];

        foreach ($presented as $module) {
            if ($module['protected']) {
                $stats['protected']++;
            }
            if ($module['effective_enabled']) {
                $stats['enabled']++;
            } else {
                $stats['disabled']++;
            }
            if ($module['backend_service']) {
                $stats['backend_enforced']++;
            }
        }

        return $stats;
    }

    /**
     * @param  array{title: string, description: string, registry_sections: list<string>, default_open: bool, protected_only?: bool, exclude_protected?: bool}  $group
     * @param  array<string, array<string, mixed>>  $presented
     * @return list<array<string, mixed>>
     */
    private function modulesForUiGroup(array $group, array $presented): array
    {
        $modules = [];
        $sectionKeys = $group['registry_sections'];
        $protectedOnly = (bool) ($group['protected_only'] ?? false);
        $excludeProtected = (bool) ($group['exclude_protected'] ?? false);

        foreach ($presented as $module) {
            if ($protectedOnly) {
                if ($module['protected']) {
                    $modules[] = $module;
                }

                continue;
            }

            if ($excludeProtected && $module['protected']) {
                continue;
            }

            if ($sectionKeys !== [] && ! in_array($module['section'] ?? '', $sectionKeys, true)) {
                continue;
            }

            if ($sectionKeys === [] && ! $protectedOnly) {
                continue;
            }

            $modules[] = $module;
        }

        usort($modules, fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']));

        return $modules;
    }

    /**
     * @param  array<string, mixed>  $dbState
     * @param  array<string, mixed>  $status
     * @return array{label: string, tone: string}
     */
    private function statusPillFor(PlatformModule $module, array $dbState, array $status): array
    {
        if ($module->protected) {
            return ['label' => 'Protected', 'tone' => 'protected'];
        }

        if (! $dbState['effective_enabled']) {
            return ['label' => 'Disabled', 'tone' => 'disabled'];
        }

        if ($status['backend_service'] ?? false) {
            return ['label' => 'Backend enforced', 'tone' => 'backend'];
        }

        if ($status['route_middleware'] ?? false) {
            return ['label' => 'Route guarded', 'tone' => 'route'];
        }

        if ($status['nav_hidden'] ?? false) {
            return ['label' => 'Nav only', 'tone' => 'nav'];
        }

        return ['label' => 'Active', 'tone' => 'active'];
    }

    /**
     * @return array<string, array{enables: string, disables: string}>
     */
    private static function deploymentModeCopy(): array
    {
        return [
            'b2b_b2c' => [
                'enables' => 'Agent and customer portals, supplier booking, finance, and admin operations.',
                'disables' => 'Nothing — full OTA deployment.',
            ],
            'b2b_only' => [
                'enables' => 'Agent portal, wallet, deposits, admin, and supplier flows.',
                'disables' => 'Public flight search, customer registration, and B2C checkout.',
            ],
            'b2c_only' => [
                'enables' => 'Public site, customer portal, checkout, and shared supplier stack.',
                'disables' => 'Agent portal, wallet, deposits, and agency staff tools.',
            ],
            'public_search_only' => [
                'enables' => 'Marketing site and flight search (browse-only).',
                'disables' => 'Checkout, supplier booking, ticketing, and agent/customer portals.',
            ],
            'no_supplier_booking' => [
                'enables' => 'Search and checkout flows without creating supplier PNRs.',
                'disables' => 'Supplier booking and ticketing automation.',
            ],
            'no_ticketing' => [
                'enables' => 'Supplier booking and holds without automated ticket issuance.',
                'disables' => 'Ticketing module and automated issue flows.',
            ],
            'no_wallet_deposits' => [
                'enables' => 'Agent bookings and commissions without wallet UI.',
                'disables' => 'Agent wallet, deposits, and ledger navigation.',
            ],
            'maintenance_lite' => [
                'enables' => 'Public site shell and admin access for maintenance.',
                'disables' => 'Portals, search, suppliers, finance, and support modules.',
            ],
        ];
    }

    private function resolveDeveloperUser(Request $request): DeveloperUser
    {
        $userId = $request->session()->get('dev_cp_user_id');
        abort_if($userId === null, 403);

        $developer = DeveloperUser::query()->find($userId);
        abort_if($developer === null || ! $developer->is_active, 403);

        return $developer;
    }

    private function redirectWithValidationResult(
        PlatformModuleDependencyValidation $validation,
        string $successMessage,
    ): RedirectResponse {
        if (! $validation->isValid()) {
            return redirect()
                ->route('dev.cp.modules.index')
                ->withErrors(['modules' => $this->formatViolations($validation)])
                ->withInput();
        }

        return redirect()
            ->route('dev.cp.modules.index')
            ->with('status', $successMessage);
    }

    /**
     * @return list<string>
     */
    private function formatViolations(PlatformModuleDependencyValidation $validation): array
    {
        return array_map(
            fn (array $violation): string => $violation['message'],
            $validation->violations()
        );
    }
}
