<?php

namespace App\Console\Commands;

use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Models\ClientProfileBranding;
use App\Models\ClientProfileSupplier;
use App\Models\ClientThemePalette;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Client\ClientPageKeys;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/**
 * Read-only JetPK dedicated-server deployment readiness report (routes, views, assets, data).
 */
class OtaJetpkDedicatedServerReadinessCommand extends Command
{
    protected $signature = 'ota:jetpk-dedicated-server-readiness {--client=jetpk : Client slug}';

    protected $description = 'Read-only JetPK dedicated-server readiness — routes, page settings, themes, assets, data, root-mode blockers';

    /** @var list<string> */
    private array $pageSettingsMutatingParity = [
        'client.parity.admin.page-settings.update',
        'client.parity.admin.page-settings.publish',
        'client.parity.admin.page-settings.preview.begin',
        'client.parity.admin.page-settings.assets.store',
        'client.parity.admin.page-settings.assets.destroy',
        'client.parity.admin.page-settings.palette.generate',
        'client.parity.admin.page-settings.palette.apply',
    ];

    /** @var list<string> */
    private array $pageSettingsGetParity = [
        'client.parity.admin.page-settings.index',
        'client.parity.admin.page-settings.palette',
        'client.parity.admin.page-settings.edit',
    ];

    public function handle(
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
        RuntimeViewResolver $viewResolver,
    ): int {
        $slug = trim((string) $this->option('client'));
        $profile = $profileResolver->resolveBySlug($slug);

        $this->line('Classification: READ-ONLY JetPK dedicated-server readiness.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        if ($profile === null) {
            $this->error("Client profile not found: {$slug}");

            return self::FAILURE;
        }

        $clientContext->set($profile);

        $this->info('Context');
        $this->table(['key', 'value'], [
            ['client_slug', $slug],
            ['profile_id', (string) $profile->id],
            ['OTA_CLIENT_SLUG env', (string) config('ota_client.slug', '')],
            ['default_deployment_slug', $profileResolver->defaultDeploymentSlug()],
            ['client_route_parity.enabled', config('client_route_parity.enabled', true) ? 'true' : 'false'],
        ]);

        $this->newLine();
        $this->info('Route readiness');
        $routeRows = [];
        foreach (array_merge($this->pageSettingsGetParity, $this->pageSettingsMutatingParity) as $name) {
            $routeRows[] = [$name, Route::has($name) ? 'registered' : 'missing'];
        }
        $this->table(['parity_route', 'status'], $routeRows);

        $urlRows = [
            ['index', client_route('admin.page-settings.index', [], $slug)],
            ['edit home', client_route('admin.page-settings.edit', ['pageKey' => 'home'], $slug)],
            ['publish home', client_route('admin.page-settings.publish', ['pageKey' => 'home'], $slug)],
            ['preview home', client_route('admin.page-settings.preview.begin', ['pageKey' => 'home'], $slug)],
            ['update home', client_route('admin.page-settings.update', ['pageKey' => 'home'], $slug)],
        ];
        $this->table(['action', 'client_route_path'], $urlRows);

        $this->newLine();
        $this->info('Page settings / view readiness');
        $viewRows = [];
        foreach (['page-settings.index', 'page-settings.edit', 'page-settings.palette'] as $logical) {
            $resolved = $viewResolver->view($logical, 'admin', $profile);
            $viewRows[] = [$logical, $resolved, View::exists($resolved) ? 'yes' : 'no'];
        }
        $this->table(['logical', 'resolved_view', 'exists'], $viewRows);

        $this->newLine();
        $this->info('Theme / asset path readiness');
        $assetProfile = (string) ($profile->asset_profile ?: $slug);
        $paths = [
            'frontend theme views' => resource_path('views/themes/frontend/jetpakistan'),
            'admin theme views' => resource_path('views/themes/admin/jetpakistan'),
            'staff theme views' => resource_path('views/themes/staff/jetpakistan'),
            'agent theme views' => resource_path('views/themes/agent/jetpakistan'),
            'customer theme views' => resource_path('views/themes/customer/jetpakistan'),
            'public frontend theme' => public_path('themes/frontend/jetpakistan'),
            'public admin theme' => public_path('themes/admin/jetpakistan'),
            'client logo dir' => public_path('client-assets/'.$assetProfile.'/logo'),
            'client favicon dir' => public_path('client-assets/'.$assetProfile.'/favicon'),
        ];
        $pathRows = [];
        foreach ($paths as $label => $path) {
            $pathRows[] = [$label, $path, File::isDirectory($path) ? 'present' : 'missing'];
        }
        $this->table(['asset_area', 'path', 'status'], $pathRows);

        $this->newLine();
        $this->info('Dedicated root-mode readiness (Mode B — not switched live)');
        $blockers = [];
        if ($profileResolver->defaultDeploymentSlug() !== $slug) {
            $blockers[] = 'OTA_CLIENT_SLUG must be set to '.$slug.' on dedicated server for root routes without /'.$slug.' prefix.';
        }
        if (config('client_route_parity.enabled', true)) {
            $blockers[] = 'CLIENT_ROUTE_PARITY_ENABLED=true keeps /{slug} parity on shared master; dedicated server should set OTA_CLIENT_SLUG='.$slug.' so default slug redirects unprefixed.';
        }
        $blockers[] = 'OTA_SINGLE_CLIENT_MODE / OTA_SINGLE_CLIENT_ROOT env flags are not implemented yet — use OTA_CLIENT_SLUG='.$slug.' + parity default-slug redirect.';
        $blockers[] = 'OAuth/social callback URLs must be re-registered for dedicated domain (no /jetpk prefix).';
        $blockers[] = 'Mail from-name/logo should use JetPK branding row on dedicated server.';
        foreach ($blockers as $blocker) {
            $this->line('  • '.$blocker);
        }
        $this->line('  can_run_at_root_today: '.($profileResolver->defaultDeploymentSlug() === $slug ? 'partial_yes_with_OTA_CLIENT_SLUG' : 'no_needs_OTA_CLIENT_SLUG=jetpk'));

        $this->newLine();
        $this->info('Data readiness (masked)');
        $this->table(['table', 'count_or_status'], $this->dataRows($profile, $slug));

        $this->newLine();
        $this->info('Deployment file checklist (high level)');
        foreach ($this->fileChecklist() as $line) {
            $this->line('  • '.$line);
        }

        $missingRoutes = collect(array_merge($this->pageSettingsGetParity, $this->pageSettingsMutatingParity))
            ->reject(fn (string $name): bool => Route::has($name));

        if ($missingRoutes->isNotEmpty()) {
            $this->error('Missing parity routes: '.$missingRoutes->implode(', '));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function dataRows(ClientProfile $profile, string $slug): array
    {
        $rows = [
            ['client_profiles (jetpk)', $profile->is_active ? 'active id='.$profile->id : 'inactive'],
            ['branding row', $profile->branding instanceof ClientProfileBranding ? 'present' : 'missing'],
            ['frontend_theme', (string) ($profile->active_frontend_theme ?? '')],
            ['admin_theme', (string) ($profile->active_admin_theme ?? '')],
        ];

        if (Schema::hasTable('client_profile_suppliers')) {
            $suppliers = ClientProfileSupplier::query()->where('client_profile_id', $profile->id)->get();
            foreach ($suppliers as $supplier) {
                $conn = SupplierConnection::query()->find($supplier->supplier_connection_id);
                $rows[] = [
                    'supplier '.$supplier->supplier_key,
                    ($supplier->enabled ? 'enabled' : 'disabled').' conn#'.($conn?->id ?? '?').' env='.($this->mask((string) ($conn?->environment ?? ''))),
                ];
            }
        }

        if (Schema::hasTable('client_page_settings')) {
            $rows[] = ['client_page_settings', (string) ClientPageSetting::query()->where('client_profile_id', $profile->id)->count()];
        }
        if (Schema::hasTable('client_page_assets')) {
            $rows[] = ['client_page_assets', (string) ClientPageAsset::query()->where('client_profile_id', $profile->id)->count()];
        }
        if (Schema::hasTable('client_theme_palettes')) {
            $rows[] = ['client_theme_palettes', (string) ClientThemePalette::query()->where('client_profile_id', $profile->id)->count()];
        }

        $admin = User::query()->where('email', 'bobsif@jetpakistan.com')->first();
        $rows[] = [
            'platform admin users',
            $admin ? 'bobsif present id='.$admin->id : 'reuse Master Client seeded users (not a blocker)',
        ];

        $rows[] = ['login_otp_required', config('ota_client.auth.require_login_otp', false) ? 'yes' : 'no'];

        return $rows;
    }

    private function mask(string $value): string
    {
        if ($value === '') {
            return '(empty)';
        }

        if (strlen($value) <= 4) {
            return '****';
        }

        return substr($value, 0, 2).'***'.substr($value, -2);
    }

    /**
     * @return list<string>
     */
    private function fileChecklist(): array
    {
        return [
            'JetPK-only: resources/views/themes/**/jetpakistan/**',
            'JetPK-only: public/themes/**/jetpakistan/**',
            'JetPK-only: resources/views/components/themes/admin/jetpakistan/**',
            'Storage: public/client-assets/jetpk-assets/** (logo, favicon, page-builder uploads)',
            'Shared core: app/Services/Client/*, app/Support/Client/*, client middleware, ClientPrefixedRouteRegistrar, ClientPageSettingsParityRouteRegistrar',
            'Shared core: config/client_*.php, config/ota_client.php',
            'Exclude from JetPK-only package: routes/dev/cp, Master-only themes (v1-classic dashboards), other client themes',
            'Secrets: .env, supplier credentials in DB — never export raw; use SupplierConnection UI on target server',
        ];
    }
}
