<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Developer\DuplicateDevCpClientProfileRequest;
use App\Http\Requests\Developer\StoreDevCpClientProfileRequest;
use App\Http\Requests\Developer\UpdateDevCpClientProfileBrandingRequest;
use App\Http\Requests\Developer\UpdateDevCpClientProfileModulesRequest;
use App\Http\Requests\Developer\UpdateDevCpClientProfileRequest;
use App\Http\Requests\Developer\UpdateDevCpClientProfileSuppliersRequest;
use App\Http\Requests\Developer\UpdateDevCpClientProfileThemeRequest;
use App\Models\ClientProfile;
use App\Models\DeveloperUser;
use App\Services\Client\ClientThemeRegistry;
use App\Services\Client\RuntimeThemeManager;
use App\Services\Client\RuntimeViewResolver;
use App\Services\Developer\DevCpClientProfileManagerService;
use App\Services\Platform\PlatformAuditLogger;
use App\Support\Client\ClientProfileConfigReader;
use App\Support\Client\ClientProfileExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Dev CP client profile list, create/edit tabs, export, and duplicate (MC-3).
 */
class DevCpClientProfilesController extends Controller
{
    public function __construct(
        protected DevCpClientProfileManagerService $profiles,
        protected ClientProfileExporter $exporter,
        protected PlatformAuditLogger $auditLogger,
        protected ClientThemeRegistry $themeRegistry,
        protected RuntimeThemeManager $runtimeThemeManager,
        protected RuntimeViewResolver $runtimeViewResolver,
    ) {}

    public function index(Request $request): View
    {
        $profiles = ClientProfile::query()
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('developer.clients.index', [
            'profiles' => $profiles,
        ]);
    }

    public function create(): View
    {
        return view('developer.clients.create');
    }

    public function store(StoreDevCpClientProfileRequest $request): RedirectResponse
    {
        $developer = $this->resolveDeveloper($request);

        $profile = $this->profiles->createProfile(
            $request->validated(),
            $developer,
            $request,
        );

        return redirect()
            ->route('dev.cp.clients.edit', $profile)
            ->with('status', 'Client profile "'.$profile->name.'" created.');
    }

    public function edit(ClientProfile $clientProfile): View
    {
        return view('developer.clients.edit', [
            'profile' => $clientProfile,
        ]);
    }

    public function update(UpdateDevCpClientProfileRequest $request, ClientProfile $clientProfile): RedirectResponse
    {
        $developer = $this->resolveDeveloper($request);

        $this->profiles->updateProfile(
            $clientProfile,
            $request->validated(),
            $developer,
            $request,
        );

        return redirect()
            ->route('dev.cp.clients.edit', $clientProfile)
            ->with('status', 'Client profile "'.$clientProfile->name.'" updated.');
    }

    public function branding(ClientProfile $clientProfile): View
    {
        $clientProfile->loadMissing('branding');

        return view('developer.clients.branding', [
            'profile' => $clientProfile,
            'branding' => $clientProfile->branding,
        ]);
    }

    public function updateBranding(UpdateDevCpClientProfileBrandingRequest $request, ClientProfile $clientProfile): RedirectResponse
    {
        $developer = $this->resolveDeveloper($request);

        $this->profiles->updateBranding(
            $clientProfile,
            $request->validated(),
            $developer,
            $request,
        );

        return redirect()
            ->route('dev.cp.clients.branding', $clientProfile)
            ->with('status', 'Branding updated for "'.$clientProfile->name.'".');
    }

    public function modules(ClientProfile $clientProfile): View
    {
        return view('developer.clients.modules', [
            'profile' => $clientProfile,
            'modules' => $this->profiles->modulesStateForProfile($clientProfile),
            'moduleKeys' => ClientProfileConfigReader::MODULE_KEYS,
        ]);
    }

    public function updateModules(UpdateDevCpClientProfileModulesRequest $request, ClientProfile $clientProfile): RedirectResponse
    {
        $developer = $this->resolveDeveloper($request);

        $this->profiles->updateModules(
            $clientProfile,
            $request->validated('modules', []),
            $developer,
            $request,
        );

        return redirect()
            ->route('dev.cp.clients.modules', $clientProfile)
            ->with('status', 'Modules updated for "'.$clientProfile->name.'".');
    }

    public function suppliers(ClientProfile $clientProfile): View
    {
        return view('developer.clients.suppliers', [
            'profile' => $clientProfile,
            'suppliers' => $this->profiles->suppliersStateForProfile($clientProfile),
        ]);
    }

    public function updateSuppliers(UpdateDevCpClientProfileSuppliersRequest $request, ClientProfile $clientProfile): RedirectResponse
    {
        $developer = $this->resolveDeveloper($request);

        $this->profiles->updateSuppliers(
            $clientProfile,
            $request->validated('suppliers', []),
            $developer,
            $request,
        );

        return redirect()
            ->route('dev.cp.clients.suppliers', $clientProfile)
            ->with('status', 'Suppliers updated for "'.$clientProfile->name.'".');
    }

    public function theme(ClientProfile $clientProfile): View
    {
        $themeSummary = $this->runtimeThemeManager->summary($clientProfile);
        $viewResolutionSummary = $this->runtimeViewResolver->summary(null, $clientProfile);

        /** @var list<array{area: string, name: string, label: string}> $layoutSamples */
        $layoutSamples = config('client_view_paths.layout_audit_samples', []);
        $layoutResolutionSummary = [];
        foreach ($layoutSamples as $sample) {
            $layoutResolutionSummary[] = array_merge(
                ['label' => $sample['label']],
                $this->runtimeViewResolver->resolveLayoutSample($sample['name'], $sample['area'], $clientProfile),
            );
        }

        $registeredThemes = [
            'frontend' => $this->themeRegistry->active('frontend'),
            'admin' => $this->themeRegistry->active('admin'),
            'staff' => $this->themeRegistry->active('staff'),
        ];

        return view('developer.clients.theme', [
            'profile' => $clientProfile,
            'availableThemes' => $registeredThemes,
            'themeSummary' => $themeSummary,
            'viewResolutionSummary' => $viewResolutionSummary,
            'layoutResolutionSummary' => $layoutResolutionSummary,
            'uiRuntimeEngine' => [
                'asset_profile' => $clientProfile->asset_profile,
                'view_resolver_status' => 'Active — opt-in via client_view()',
                'layout_resolver_status' => 'Active — opt-in via client_layout()',
                'registered_not_active' => $this->registeredButNotActiveThemes($themeSummary, $registeredThemes),
            ],
        ]);
    }

    /**
     * @param  array{
     *     areas: array<string, array{selected: string|null, resolved: string, used_fallback: bool}>
     * }  $themeSummary
     * @param  array<string, list<array{key: string, name: string, status: string}>>  $registeredThemes
     * @return list<string>
     */
    private function registeredButNotActiveThemes(array $themeSummary, array $registeredThemes): array
    {
        $inactive = [];

        foreach (['frontend', 'admin', 'staff'] as $area) {
            $resolved = $themeSummary['areas'][$area]['resolved'] ?? '';
            foreach ($registeredThemes[$area] ?? [] as $theme) {
                if (($theme['key'] ?? '') !== $resolved && ($theme['status'] ?? '') === 'active') {
                    $inactive[] = sprintf('%s/%s', $area, $theme['key']);
                }
            }
        }

        return $inactive;
    }

    public function updateTheme(UpdateDevCpClientProfileThemeRequest $request, ClientProfile $clientProfile): RedirectResponse
    {
        $developer = $this->resolveDeveloper($request);

        $this->profiles->updateTheme(
            $clientProfile,
            $request->validated(),
            $developer,
            $request,
        );

        return redirect()
            ->route('dev.cp.clients.theme', $clientProfile)
            ->with('status', 'Theme settings updated for "'.$clientProfile->name.'".');
    }

    public function export(Request $request, ClientProfile $clientProfile): RedirectResponse
    {
        $developer = $this->resolveDeveloper($request);

        try {
            $result = $this->exporter->export(
                $clientProfile->slug,
                fromDb: true,
                includeAssets: false,
                force: true,
            );
        } catch (RuntimeException $e) {
            return redirect()
                ->route('dev.cp.clients.index')
                ->withErrors(['export' => $e->getMessage()]);
        } catch (Throwable $e) {
            return redirect()
                ->route('dev.cp.clients.index')
                ->withErrors(['export' => 'Export failed. Check application logs.']);
        }

        $this->auditLogger->record(
            'dev_cp.client_profile.exported',
            $clientProfile,
            $developer,
            null,
            $request,
            ['slug' => $clientProfile->slug, 'client_dir' => $result['client_dir']],
        );

        return redirect()
            ->route('dev.cp.clients.index')
            ->with('status', 'Exported "'.$clientProfile->slug.'" to '.$result['client_dir']);
    }

    public function duplicate(DuplicateDevCpClientProfileRequest $request, ClientProfile $clientProfile): RedirectResponse
    {
        $developer = $this->resolveDeveloper($request);

        $duplicate = $this->profiles->duplicateProfile(
            $clientProfile,
            $request->string('new_name')->toString(),
            $request->string('new_slug')->toString(),
            $request->boolean('copy_credentials'),
            $developer,
            $request,
        );

        return redirect()
            ->route('dev.cp.clients.edit', $duplicate)
            ->with('status', 'Duplicated "'.$clientProfile->name.'" as "'.$duplicate->name.'".');
    }

    private function resolveDeveloper(Request $request): DeveloperUser
    {
        $userId = $request->session()->get('dev_cp_user_id');
        abort_if($userId === null, 403);

        $developer = DeveloperUser::query()->find($userId);
        abort_if($developer === null || ! $developer->is_active, 403);

        return $developer;
    }
}
