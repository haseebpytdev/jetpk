<?php

namespace App\Console\Commands;

use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientPublicWebrootPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * Read-only JetPK dedicated-server package manifest audit — views, assets, runtime, routes, leakage scan.
 */
class OtaJetpkDedicatedPackageAuditCommand extends Command
{
    protected $signature = 'ota:jetpk-dedicated-package-audit
                            {--client=jetpk : Client slug}
                            {--manifest=docs/deployment/jetpk-dedicated-server-manifest.md : Manifest doc path for reference}';

    protected $description = 'Read-only JetPK package audit — file checklist, public webroot mapping, route URLs, Master leakage scan';

    /** @var list<string> */
    private array $requiredViewDirs = [
        'resources/views/themes/frontend/jetpakistan',
        'resources/views/themes/admin/jetpakistan',
        'resources/views/themes/staff/jetpakistan',
        'resources/views/themes/agent/jetpakistan',
        'resources/views/themes/customer/jetpakistan',
        'resources/views/components/themes/admin/jetpakistan',
        'resources/views/components/jp',
    ];

    /** @var list<string> */
    private array $requiredPublicDirs = [
        'public/themes/frontend/jetpakistan',
        'public/themes/admin/jetpakistan',
        'public/client-assets/jetpk-assets',
    ];

    /** @var list<string> */
    private array $requiredRuntimeClasses = [
        'App\Services\Client\ClientPrefixedRouteRegistrar',
        'App\Services\Client\ClientPageSettingsParityRouteRegistrar',
        'App\Services\Client\RuntimeViewResolver',
        'App\Services\Client\RuntimeThemeManager',
        'App\Services\Client\ClientPageContentResolver',
        'App\Services\Client\ClientPageAssetService',
        'App\Services\Client\ClientProfileResolver',
        'App\Services\Client\CurrentClientContext',
        'App\Http\Middleware\ResolvePreviewClient',
        'App\Http\Middleware\PersistClientPreviewContext',
        'App\Http\Controllers\Admin\ClientPageSettingsController',
    ];

    /** @var list<string> */
    private array $pageSettingsParityRoutes = [
        'client.parity.admin.page-settings.index',
        'client.parity.admin.page-settings.edit',
        'client.parity.admin.page-settings.update',
        'client.parity.admin.page-settings.publish',
        'client.parity.admin.page-settings.preview.begin',
        'client.parity.admin.page-settings.assets.store',
        'client.parity.admin.page-settings.assets.destroy',
    ];

    /** @var list<string> */
    private array $forbiddenThemePatterns = [
        'parwaaz',
        'yoursdomain',
        'tournest',
        'ota-public.css',
        'haseeb-master',
        'adminlte',
        'tabler.min.css',
    ];

    public function handle(ClientProfileResolver $profileResolver, CurrentClientContext $clientContext): int
    {
        $slug = trim((string) $this->option('client'));
        $profile = $profileResolver->resolveBySlug($slug);

        $this->line('Classification: READ-ONLY JetPK dedicated package audit.');
        $this->line('db_write_attempted=false');
        $this->line('live_supplier_call_attempted=false');
        $this->newLine();

        if ($profile === null) {
            $this->error("Client profile not found: {$slug}");

            return self::FAILURE;
        }

        $clientContext->set($profile);
        $assetProfile = (string) ($profile->asset_profile ?: $slug);
        $failCount = 0;
        $warnCount = 0;

        $this->info('Deployment modes');
        $this->table(['mode', 'example URLs', 'when'], [
            ['A — shared preview', "/{$slug}/home, /{$slug}/admin/page-settings", 'Current Hostinger Master server'],
            ['B — dedicated root', '/, /admin/page-settings, /groups/search', 'OTA_CLIENT_SLUG=jetpk on jetpakistan.com'],
        ]);
        $rootReady = $profileResolver->defaultDeploymentSlug() === $slug
            ? 'yes_with_OTA_CLIENT_SLUG_already_set'
            : 'needs_OTA_CLIENT_SLUG=jetpk_on_target';
        $this->line("Root mode runnable today: {$rootReady} (no extra root-mode code required — ResolvePreviewClient redirects /{$slug}/* → /* when slug is default deployment slug).");
        $this->newLine();

        $this->info('JetPK view directories');
        $viewRows = [];
        foreach ($this->requiredViewDirs as $dir) {
            $path = base_path($dir);
            $exists = File::isDirectory($path);
            $count = $exists ? count(File::allFiles($path)) : 0;
            if (! $exists) {
                $failCount++;
            }
            $viewRows[] = [$dir, $exists ? 'present' : 'MISSING', (string) $count];
        }
        $this->table(['path', 'status', 'file_count'], $viewRows);

        $this->newLine();
        $this->info('Public assets (Laravel public/ — may differ from live public_html)');
        $configuredWebroot = (string) config('ota_client.public_webroot_path', '');
        $webroot = ClientPublicWebrootPath::resolve();
        $this->line('Configured OTA_PUBLIC_WEBROOT_PATH: '.($configuredWebroot !== '' ? $configuredWebroot : '(not set)'));
        $this->line('Resolved public webroot for checks: '.$webroot);
        $this->newLine();

        $assetRows = [];
        foreach ($this->requiredPublicDirs as $dir) {
            $laravelPath = public_path(str_replace('public/', '', $dir));
            $relative = str_replace('public/', '', $dir);
            $livePath = rtrim($webroot, '/\\').'/'.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $laravelOk = File::isDirectory($laravelPath);
            $liveOk = File::isDirectory($livePath);
            $laravelCount = $laravelOk ? count(File::allFiles($laravelPath)) : 0;
            $liveCount = $liveOk ? count(File::allFiles($livePath)) : 0;

            if (! $laravelOk && ! $liveOk) {
                $failCount++;
                $status = 'MISSING_BOTH';
            } elseif (! $laravelOk && $liveOk) {
                $warnCount++;
                $status = 'live_only_copy_required';
            } elseif ($laravelOk && ! $liveOk) {
                $warnCount++;
                $status = 'laravel_only_upload_webroot';
            } else {
                $status = 'ok';
            }

            $assetRows[] = [$relative, $status, (string) $laravelCount, (string) $liveCount];
        }
        $this->table(['relative_path', 'status', 'laravel_public_files', 'live_webroot_files'], $assetRows);

        $logoPath = public_path('client-assets/'.$assetProfile.'/logo/logo.svg');
        $faviconPath = public_path('client-assets/'.$assetProfile.'/favicon/favicon.ico');
        foreach ([['logo', $logoPath], ['favicon', $faviconPath]] as [$label, $path]) {
            if (! File::exists($path)) {
                $warnCount++;
                $this->warn("Missing client {$label}: {$path} (copy from live public_html/client-assets/{$assetProfile}/)");
            }
        }

        $this->newLine();
        $this->info('Shared runtime classes');
        $classRows = [];
        foreach ($this->requiredRuntimeClasses as $class) {
            $exists = class_exists($class);
            if (! $exists) {
                $failCount++;
            }
            $classRows[] = [$class, $exists ? 'present' : 'MISSING'];
        }
        $this->table(['class', 'status'], $classRows);

        $this->newLine();
        $this->info('Page Settings parity routes + client_route()');
        $routeRows = [];
        foreach ($this->pageSettingsParityRoutes as $name) {
            $ok = Route::has($name);
            if (! $ok) {
                $failCount++;
            }
            $routeRows[] = [$name, $ok ? 'registered' : 'MISSING'];
        }
        $this->table(['route', 'status'], $routeRows);

        $urlChecks = [
            ['admin.page-settings.index', [], "/{$slug}/admin/page-settings"],
            ['admin.page-settings.publish', ['pageKey' => ClientPageKeys::HOME], "/{$slug}/admin/page-settings/home/publish"],
            ['admin.page-settings.preview.begin', ['pageKey' => ClientPageKeys::HOME], "/{$slug}/admin/page-settings/home/preview"],
        ];
        foreach ($urlChecks as [$routeName, $params, $expected]) {
            $actual = client_route($routeName, $params, $slug);
            if ($actual !== $expected) {
                $failCount++;
                $this->error("client_route mismatch {$routeName}: expected {$expected}, got {$actual}");
            } else {
                $this->line("  OK {$routeName} → {$actual}");
            }
        }

        $this->newLine();
        $this->info('Master/other-client leakage scan (JetPK theme files)');
        $leakRows = [];
        foreach ($this->scanJetpkThemeFiles() as $file => $hits) {
            if ($hits !== []) {
                $warnCount++;
                $leakRows[] = [str_replace(base_path(DIRECTORY_SEPARATOR), '', $file), implode(', ', $hits)];
            }
        }
        if ($leakRows === []) {
            $this->line('  No forbidden Master/Parwaaz path references in JetPK theme blades.');
        } else {
            $this->table(['file', 'patterns'], $leakRows);
        }

        $this->newLine();
        $this->info('Data readiness notes (warnings only — not package blockers)');
        $dataNotes = [
            ['seeded_users', 'Reuse same users exported from Master Client — bobsif not required as new user'],
            ['client_page_settings', $this->pageSettingsCount($profile).' rows — seed/export required before client handoff if empty'],
            ['supplier_connections', 'Configure post-deploy in Admin Supplier/API Settings — disabled/empty in audit is OK'],
            ['public_assets', 'Copy from live public_html if logo/favicon missing in ota_app/public'],
            ['smtp_oauth', 'Configure in .env after deployment — not in package'],
            ['email_templates', 'Separate future phase — not in 7J package'],
        ];
        $this->table(['item', 'note'], $dataNotes);

        $this->newLine();
        $this->info('Deploy package folder');
        $packageReadme = base_path('deploy_packages/jetpk_dedicated/README_JETPK_DEPLOYMENT.md');
        $this->line(File::exists($packageReadme) ? '  deploy_packages/jetpk_dedicated/ — present (manifest-based)' : '  deploy_packages/jetpk_dedicated/ — MISSING');

        $this->newLine();
        $this->info('Manifest reference');
        $manifest = base_path((string) $this->option('manifest'));
        $this->line(File::exists($manifest) ? "  {$manifest} — present" : "  {$manifest} — MISSING");

        $this->newLine();
        $this->info('Package readiness summary');
        $this->table(['metric', 'value'], [
            ['fail_count', (string) $failCount],
            ['warn_count', (string) $warnCount],
            ['safe_to_deploy_package', $failCount === 0 ? ($warnCount === 0 ? 'yes' : 'yes_with_warnings') : 'no'],
            ['manifest_doc', (string) $this->option('manifest')],
            ['env_template', '.env.example.jetpk'],
        ]);

        if ($warnCount > 0) {
            $this->warn('Warnings only (not blockers): public webroot copy, page settings seed before handoff, suppliers/SMTP/OAuth post-deploy.');
        }

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function pageSettingsCount(ClientProfile $profile): string
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('client_page_settings')) {
            return 'n/a';
        }

        return (string) ClientPageSetting::query()->where('client_profile_id', $profile->id)->count();
    }

    /**
     * @return array<string, list<string>>
     */
    private function scanJetpkThemeFiles(): array
    {
        $results = [];
        $roots = [
            resource_path('views/themes/frontend/jetpakistan'),
            resource_path('views/themes/admin/jetpakistan'),
            resource_path('views/themes/staff/jetpakistan'),
            resource_path('views/themes/agent/jetpakistan'),
            resource_path('views/themes/customer/jetpakistan'),
        ];

        foreach ($roots as $root) {
            if (! File::isDirectory($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                if ($file->getExtension() !== 'php' && ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $contents = strtolower(File::get($file->getPathname()));
                $hits = [];
                foreach ($this->forbiddenThemePatterns as $pattern) {
                    if (str_contains($contents, strtolower($pattern))) {
                        $hits[] = $pattern;
                    }
                }

                if ($hits !== []) {
                    $results[$file->getPathname()] = $hits;
                }
            }
        }

        return $results;
    }
}
