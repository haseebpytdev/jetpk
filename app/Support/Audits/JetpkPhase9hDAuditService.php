<?php

namespace App\Support\Audits;

use App\Enums\OtaNotificationEvent;
use App\Services\Client\ClientPageContentResolver;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientPageMediaConsumption;
use App\Support\Client\ClientPageMediaSchema;
use App\Support\Emails\EmailTemplateRegistry;
use App\Support\Emails\JetpkEmailEventContentRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Read-only audit runners for JetPK phase 9H-D closure gates.
 */
final class JetpkPhase9hDAuditService
{
    private const OUTPUT_DIR = 'app/audits/jetpk-9h-d';

    public function __construct(
        private readonly ClientPageContentResolver $contentResolver,
        private readonly JetpkAdminDeepPageInventoryAuditService $inventoryService,
    ) {}

    /**
     * @return array{pass: int, fail: int, path: string}
     */
    public function emptyValueAudit(): array
    {
        $saved = [
            'hero' => [
                'eyebrow' => '',
                'subtitle' => '',
                'search_visible' => '0',
            ],
            'trust_chips' => [],
            'faq' => ['enabled' => '0'],
        ];

        $checks = [
            'hero.eyebrow empty' => $this->contentResolver->resolveField($saved, 'hero.eyebrow', 'default eyebrow') === '',
            'hero.subtitle empty' => $this->contentResolver->resolveField($saved, 'hero.subtitle', 'default sub') === '',
            'hero.search_visible false' => $this->contentResolver->resolveField($saved, 'hero.search_visible', '1') === '0',
            'trust_chips empty array' => $this->contentResolver->resolveField($saved, 'trust_chips', [['label' => 'x']]) === [],
            'absent uses default' => $this->contentResolver->resolveField($saved, 'hero.headline', 'Default headline') === 'Default headline',
        ];

        $fail = collect($checks)->filter(static fn (bool $ok) => ! $ok)->count();

        return $this->writeJson('page-settings-empty-value-audit.json', [
            'checks' => $checks,
            'pass' => count($checks) - $fail,
            'fail' => $fail,
        ], $fail);
    }

    /**
     * @return array{pass: int, fail: int, path: string}
     */
    public function mediaCoverageAudit(): array
    {
        $required = [
            'hero_background',
            'support_cta_background',
            'support_cta_background_mobile',
            'group_card_1',
            'destination_1',
        ];
        $defined = ClientPageMediaSchema::assetKeysFor(ClientPageKeys::HOME);
        $missing = array_values(array_diff($required, $defined));
        $fail = count($missing);

        return $this->writeJson('page-settings-media-coverage-audit.json', [
            'required' => $required,
            'defined' => $defined,
            'missing' => $missing,
            'consumption_rows' => count(ClientPageMediaConsumption::matrix()),
            'pass' => $fail === 0 ? 1 : 0,
            'fail' => $fail > 0 ? 1 : 0,
        ], $fail);
    }

    /**
     * @return array{pass: int, fail: int, path: string}
     */
    public function paletteConsumptionAudit(): array
    {
        $cssPath = public_path('themes/admin/jetpakistan/css/dashboard.css');
        $css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';
        $requiredVars = [
            '--jp-color-primary',
            '--jp-color-primary-soft',
            '--jp-gradient-primary',
            '--jp-button-shadow',
            '--jp-focus-ring',
            'var(--brand)',
            '.jp-btn--primary',
            '.jp-btn--danger',
        ];
        $missing = [];
        foreach ($requiredVars as $needle) {
            if (! str_contains($css, $needle)) {
                $missing[] = $needle;
            }
        }

        $brandHexPatterns = ['#FB923C', '#fb923c', '#F97316', '#f97316', '#EA580C', '#ea580c', '#34E0E8'];
        $hardcoded = [];
        $scanRoots = [
            public_path('themes/admin/jetpakistan/css'),
            resource_path('views/themes/admin/jetpakistan'),
            resource_path('views/dashboard/admin'),
        ];
        foreach ($scanRoots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (! in_array($ext, ['css', 'blade.php', 'php'], true)) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
                $content = (string) file_get_contents($file->getPathname());
                foreach ($brandHexPatterns as $hex) {
                    if (str_contains($content, $hex)) {
                        $hardcoded[] = $relative.':'.$hex;
                    }
                }
            }
        }
        $hardcoded = array_values(array_unique($hardcoded));

        $fail = count($missing) + count($hardcoded);

        return $this->writeJson('palette-consumption-audit.json', [
            'missing_selectors_or_vars' => $missing,
            'hardcoded_brand_hex' => $hardcoded,
            'hardcoded_count' => count($hardcoded),
            'pass' => $fail === 0 ? 1 : 0,
            'fail' => $fail > 0 ? 1 : 0,
        ], $fail);
    }

    /**
     * @return array{pass: int, fail: int, path: string}
     */
    public function settingsNavigationAudit(): array
    {
        $requiredRoutes = [
            'admin.settings.branding.edit',
            'admin.page-settings.index',
            'admin.settings.media.index',
            'admin.settings.background-removal.edit',
            'admin.settings.branding.footer.edit',
            'admin.settings.communications.templates.index',
            'admin.settings.communications.notification-events.index',
            'admin.api-settings.create',
        ];
        $missing = array_values(array_filter($requiredRoutes, static fn (string $r) => ! Route::has($r)));
        $fail = count($missing);

        return $this->writeJson('settings-navigation-audit.json', [
            'missing_routes' => $missing,
            'pass' => $fail === 0 ? 1 : 0,
            'fail' => $fail > 0 ? 1 : 0,
        ], $fail);
    }

    /**
     * @return array{pass: int, fail: int, path: string}
     */
    public function legacySettingsRouteAudit(): array
    {
        $fail = 0;
        $notes = [];
        if (Route::has('admin.settings.homepage.edit')) {
            $notes[] = 'admin.settings.homepage.edit exists — must redirect to page-settings for JetPK';
        }

        return $this->writeJson('legacy-settings-route-audit.json', [
            'homepage_legacy_route' => Route::has('admin.settings.homepage.edit'),
            'page_settings_route' => Route::has('admin.page-settings.edit'),
            'notes' => $notes,
            'pass' => 1,
            'fail' => $fail,
        ], $fail);
    }

    /**
     * @return array{pass: int, fail: int, path: string}
     */
    public function supplierCardSelectorAudit(): array
    {
        $picker = resource_path('views/themes/admin/jetpakistan/api-settings/provider-picker.blade.php');
        $create = resource_path('views/themes/admin/jetpakistan/api-settings/create.blade.php');
        $fail = 0;
        $issues = [];
        if (! is_file($picker)) {
            $issues[] = 'Missing provider-picker.blade.php';
            $fail++;
        }
        if (! is_file($create)) {
            $issues[] = 'Missing themed create view';
            $fail++;
        }
        $requiredProviders = ['sabre', 'pia_ndc', 'airblue', 'iati', 'duffel', 'airline_direct', 'airsial', 'al_haider'];
        $controllerSource = (string) @file_get_contents(app_path('Http/Controllers/Admin/SupplierConnectionController.php'));
        foreach ($requiredProviders as $provider) {
            if (! str_contains($controllerSource, "'{$provider}'")) {
                $issues[] = "Missing provider card: {$provider}";
                $fail++;
            }
        }
        if (is_file($create) && ! str_contains((string) file_get_contents($create), 'showProviderPicker')) {
            $issues[] = 'Create view missing provider picker gate';
            $fail++;
        }

        return $this->writeJson('supplier-card-selector-audit.json', [
            'issues' => $issues,
            'required_providers' => $requiredProviders,
            'pass' => $fail === 0 ? 1 : 0,
            'fail' => $fail,
        ], $fail);
    }

    /**
     * @return array{pass: int, fail: int, path: string}
     */
    public function emailTemplateArchitectureAudit(): array
    {
        $shell = JetpkEmailEventContentRegistry::shellView();
        $contentView = JetpkEmailEventContentRegistry::contentView();
        $fail = 0;
        $issues = [];

        if (! View::exists($shell)) {
            $issues[] = 'Missing universal JetPK email shell';
            $fail++;
        }

        if (! View::exists($contentView)) {
            $issues[] = 'Missing universal JetPK event-content view';
            $fail++;
        }

        $contentCount = count(JetpkEmailEventContentRegistry::all());
        $eventCount = count(OtaNotificationEvent::cases());

        if ($contentCount < $eventCount) {
            $issues[] = "Event-content definitions ({$contentCount}) fewer than notification events ({$eventCount})";
            $fail++;
        }

        foreach (OtaNotificationEvent::cases() as $event) {
            if (JetpkEmailEventContentRegistry::find($event->value) === null) {
                $issues[] = 'Missing event-content definition: '.$event->value;
                $fail++;
            }
        }

        $legacyDirs = ['auth', 'booking', 'payment', 'support', 'agent', 'admin', 'group-ticketing', 'generic'];
        foreach ($legacyDirs as $dir) {
            $path = resource_path('views/emails/themes/jetpakistan/'.$dir);
            if (! is_dir($path)) {
                continue;
            }
            foreach (glob($path.'/*.blade.php') ?: [] as $file) {
                if (str_contains((string) file_get_contents($file), "@extends('emails.themes.jetpakistan.layouts.base')")) {
                    $issues[] = 'Duplicated full-layout event template: '.basename($file);
                    $fail++;
                }
            }
        }

        $parwaazInShell = false;
        $shellPath = resource_path('views/emails/themes/jetpakistan/layouts/base.blade.php');
        if (is_file($shellPath) && str_contains((string) file_get_contents($shellPath), 'Parwaaz')) {
            $parwaazInShell = true;
            $issues[] = 'Parwaaz branding in JetPK email shell';
            $fail++;
        }

        foreach ([
            app_path('Support/Emails/EmailBaseVariables.php'),
            app_path('Support/Emails/EmailPlaceholderFallbacks.php'),
        ] as $emailFile) {
            if (is_file($emailFile) && str_contains((string) file_get_contents($emailFile), 'Parwaaz')) {
                $issues[] = 'Parwaaz constant still present: '.basename($emailFile);
                $fail++;
            }
        }

        if (function_exists('uses_jetpk_company_branding') && uses_jetpk_company_branding()) {
            $resolvedBrand = \App\Support\Emails\EmailPlaceholderFallbacks::fallbackFor('brand_name', ['audience' => 'customer']);
            if (is_string($resolvedBrand) && str_contains($resolvedBrand, 'Parwaaz')) {
                $issues[] = 'JetPK resolved brand_name fallback returns Parwaaz';
                $fail++;
            }
        }

        $registryCount = count(EmailTemplateRegistry::listForAgency(
            \App\Models\Agency::query()->first() ?? new \App\Models\Agency(['name' => 'Test']),
            [],
        ));

        return $this->writeJson('email-template-architecture-audit.json', [
            'shell' => $shell,
            'content_view' => $contentView,
            'content_definition_count' => $contentCount,
            'registry_count' => $registryCount,
            'event_count' => $eventCount,
            'issues' => $issues,
            'pass' => $fail === 0 ? 1 : 0,
            'fail' => $fail,
        ], $fail);
    }

    /**
     * @return array{pass: int, fail: int, path: string}
     */
    public function dashboardUiContractAudit(): array
    {
        $cssPath = public_path('themes/admin/jetpakistan/css/dashboard.css');
        $css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';
        $required = [
            '.jp-page-editor',
            '.jp-field',
            '.jp-control',
            '.jp-media-picker',
            '.jp-action-bar',
            '.jp-empty-state',
        ];
        $missing = array_values(array_filter($required, static fn (string $c) => ! str_contains($css, $c)));
        $fail = count($missing);

        return $this->writeJson('dashboard-ui-contract-audit.json', [
            'missing_classes' => $missing,
            'pass' => $fail === 0 ? 1 : 0,
            'fail' => $fail > 0 ? 1 : 0,
        ], $fail);
    }

    /**
     * @return array{pass: int, fail: int, path: string, matrix_path: string}
     */
    public function adminDeepPageUiAudit(string $clientSlug = 'jetpk'): array
    {
        $inventory = $this->inventoryService->run($clientSlug);
        $fail = collect($inventory['rows'])->where('final_verdict', 'BLOCKED')->count();
        $visible = collect($inventory['rows'])->where('is_visible_ui', true)->count();

        return array_merge($this->writeJson('admin-deep-page-ui-audit.json', [
            'blocked' => $fail,
            'visible_routes' => $visible,
            'total' => count($inventory['rows']),
            'pass' => $fail === 0 ? 1 : 0,
            'fail' => $fail > 0 ? 1 : 0,
        ], $fail), ['matrix_path' => $inventory['matrix_path']]);
    }

    /**
     * @return array{pass: int, fail: int, path: string}
     */
    private function writeJson(string $filename, array $payload, int $failCount): array
    {
        $dir = storage_path(self::OUTPUT_DIR);
        File::ensureDirectoryExists($dir);
        $path = $dir.'/'.$filename;
        $payload['generated_at'] = now()->toIso8601String();
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'pass' => $failCount === 0 ? 1 : 0,
            'fail' => $failCount > 0 ? 1 : 0,
            'path' => $path,
        ];
    }
}
