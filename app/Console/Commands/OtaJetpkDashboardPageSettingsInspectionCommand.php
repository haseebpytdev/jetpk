<?php

namespace App\Console\Commands;

use App\Models\ClientProfile;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\JetpkPortalUiIdentityClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/**
 * Read-only JetPK dashboard + page settings routing/theme inspection for repair planning.
 */
class OtaJetpkDashboardPageSettingsInspectionCommand extends Command
{
    protected $signature = 'ota:jetpk-dashboard-page-settings-inspection {--client=jetpk : Client slug}';

    protected $description = 'Read-only JetPK dashboard route matrix, page settings URLs, view resolution, and master-link leakage';

    /** @var list<array{portal:string,uri:string,route:string,methods:string}> */
    private array $dashboardRoutes = [
        ['portal' => 'admin', 'route' => 'admin.dashboard', 'uri' => '/admin'],
        ['portal' => 'admin', 'route' => 'admin.bookings', 'uri' => '/admin/bookings'],
        ['portal' => 'admin', 'route' => 'admin.bookings.show', 'uri' => '/admin/bookings/{booking}'],
        ['portal' => 'admin', 'route' => 'admin.settings.index', 'uri' => '/admin/settings'],
        ['portal' => 'admin', 'route' => 'admin.settings.branding.edit', 'uri' => '/admin/settings/branding'],
        ['portal' => 'admin', 'route' => 'admin.settings.communications.index', 'uri' => '/admin/settings/communications'],
        ['portal' => 'admin', 'route' => 'admin.api-settings', 'uri' => '/admin/api-settings'],
        ['portal' => 'admin', 'route' => 'admin.page-settings.index', 'uri' => '/admin/page-settings'],
        ['portal' => 'admin', 'route' => 'admin.page-settings.edit', 'uri' => '/admin/page-settings/{pageKey}'],
        ['portal' => 'admin', 'route' => 'admin.page-settings.publish', 'uri' => '/admin/page-settings/{pageKey}/publish'],
        ['portal' => 'admin', 'route' => 'admin.page-settings.preview.begin', 'uri' => '/admin/page-settings/{pageKey}/preview'],
        ['portal' => 'admin', 'route' => 'admin.page-settings.palette', 'uri' => '/admin/page-settings/palette'],
        ['portal' => 'admin', 'route' => 'admin.group-ticketing.index', 'uri' => '/admin/group-ticketing'],
        ['portal' => 'admin', 'route' => 'admin.users.index', 'uri' => '/admin/users'],
        ['portal' => 'admin', 'route' => 'admin.agencies.index', 'uri' => '/admin/agencies'],
        ['portal' => 'admin', 'route' => 'admin.markups', 'uri' => '/admin/markups'],
        ['portal' => 'admin', 'route' => 'admin.reports.supplier-diagnostics', 'uri' => '/admin/reports/supplier-diagnostics'],
        ['portal' => 'admin', 'route' => 'admin.support.tickets.index', 'uri' => '/admin/support-tickets'],
        ['portal' => 'admin', 'route' => 'admin.agent-applications.index', 'uri' => '/admin/agent-applications'],
        ['portal' => 'staff', 'route' => 'staff.dashboard', 'uri' => '/staff'],
        ['portal' => 'staff', 'route' => 'staff.bookings.index', 'uri' => '/staff/bookings'],
        ['portal' => 'staff', 'route' => 'staff.bookings.show', 'uri' => '/staff/bookings/{booking}'],
        ['portal' => 'agent', 'route' => 'agent.dashboard', 'uri' => '/agent'],
        ['portal' => 'agent', 'route' => 'agent.bookings.index', 'uri' => '/agent/bookings'],
        ['portal' => 'agent', 'route' => 'agent.bookings.create', 'uri' => '/agent/bookings/create'],
        ['portal' => 'agent', 'route' => 'agent.bookings.show', 'uri' => '/agent/bookings/{booking}'],
        ['portal' => 'customer', 'route' => 'customer.dashboard', 'uri' => '/customer'],
        ['portal' => 'customer', 'route' => 'customer.bookings.index', 'uri' => '/customer/bookings'],
        ['portal' => 'customer', 'route' => 'customer.bookings.show', 'uri' => '/customer/bookings/{booking}'],
    ];

    public function handle(
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
        RuntimeViewResolver $viewResolver,
    ): int {
        $slug = trim((string) $this->option('client'));
        $profile = $profileResolver->resolveBySlug($slug);
        if ($profile === null) {
            $this->error("Client profile not found: {$slug}");

            return self::FAILURE;
        }

        $clientContext->set($profile);

        $this->line('Classification: READ-ONLY JetPK dashboard + page settings inspection.');
        $this->line('db_write_attempted=false');
        $this->newLine();
        $this->info('Context');
        $this->table(['key', 'value'], [
            ['client_slug', $slug],
            ['profile_id', (string) $profile->id],
            ['admin_theme', (string) ($profile->admin_theme ?? '')],
            ['frontend_theme', (string) ($profile->frontend_theme ?? '')],
            ['is_client_preview()', is_client_preview() ? 'true' : 'false'],
            ['current_client_slug()', (string) (current_client_slug() ?? '')],
        ]);

        $this->newLine();
        $this->info('Dashboard route matrix (unprefixed + client_route + parity)');
        $routeRows = [];
        foreach ($this->dashboardRoutes as $row) {
            $routeName = $row['route'];
            $exists = Route::has($routeName);
            $parityName = 'client.parity.'.$routeName;
            $parityExists = Route::has($parityName);
            $unprefixed = $exists ? $this->safeRoute($routeName, $this->sampleParams($routeName)) : 'missing';
            $prefixed = $this->safeClientRoute($routeName, $this->sampleParams($routeName), $slug);
            $routeRows[] = [
                $row['portal'],
                $routeName,
                $exists ? 'yes' : 'no',
                $parityExists ? 'yes' : 'no',
                $unprefixed,
                $prefixed,
            ];
        }
        $this->table(['portal', 'route', 'registered', 'parity_get', 'unprefixed_path', 'client_route_path'], $routeRows);

        $this->newLine();
        $this->info('Page settings route inventory');
        $pageSettingRoutes = [
            'admin.page-settings.index',
            'admin.page-settings.palette',
            'admin.page-settings.edit',
            'admin.page-settings.update',
            'admin.page-settings.publish',
            'admin.page-settings.preview.begin',
            'admin.page-settings.assets.store',
            'admin.page-settings.assets.destroy',
        ];
        $psRows = [];
        foreach ($pageSettingRoutes as $name) {
            $route = Route::getRoutes()->getByName($name);
            $methods = $route ? implode('|', $route->methods()) : 'missing';
            $action = $route ? (string) ($route->getActionName() ?? '') : '';
            $psRows[] = [
                $name,
                $methods,
                $action !== '' ? class_basename($action) : '—',
                Route::has($name) ? $this->safeRoute($name, $name === 'admin.page-settings.edit' ? ['pageKey' => ClientPageKeys::HOME] : []) : 'missing',
                $this->safeClientRoute($name, $name === 'admin.page-settings.edit' ? ['pageKey' => ClientPageKeys::HOME] : [], $slug),
            ];
        }
        $this->table(['route', 'methods', 'handler', 'route() path', 'client_route() path'], $psRows);

        $this->newLine();
        $this->info('Page settings generated URL matrix');
        $urlRows = [];
        foreach (ClientPageKeys::all() as $pageKey) {
            $urlRows[] = [
                $pageKey,
                ClientPageKeys::labels()[$pageKey] ?? $pageKey,
                client_route('admin.page-settings.edit', ['pageKey' => $pageKey], $slug),
                client_route(ClientPageKeys::previewRoutes()[$pageKey] ?? 'home', [], $slug),
            ];
        }
        $this->table(['page_key', 'label', 'edit_url', 'preview_public_url'], $urlRows);

        $this->newLine();
        $this->info('View resolution matrix (page-settings)');
        $logicalPages = ['page-settings.index', 'page-settings.edit', 'page-settings.palette'];
        $viewRows = [];
        foreach ($logicalPages as $logical) {
            $theme = $viewResolver->themeViewName($logical, 'admin', $profile);
            $legacy = $viewResolver->legacyViewName($logical, 'admin');
            $resolved = $viewResolver->view($logical, 'admin', $profile);
            $viewRows[] = [
                $logical,
                $theme,
                View::exists($theme) ? 'yes' : 'no',
                $legacy,
                View::exists($legacy) ? 'yes' : 'no',
                $resolved,
            ];
        }
        $this->table(['logical', 'theme_view', 'theme_exists', 'legacy_view', 'legacy_exists', 'resolved'], $viewRows);

        $this->newLine();
        $this->info('Master leakage / wrong-link checks');
        $masterIndex = $this->safeRoute('admin.page-settings.index');
        $clientIndex = $this->safeClientRoute('admin.page-settings.index', [], $slug);
        $leakRows = [
            ['admin.page-settings.index route() vs client_route()', $masterIndex, $clientIndex, $masterIndex !== $clientIndex ? 'prefixed_ok' : 'same_path_warn'],
            ['page-settings requires CurrentClientContext', 'ClientPageSettingsController::requireProfile()', 'abort 404 if null', $clientContext->get() !== null ? 'context_ok' : 'context_missing'],
            ['AdminSettingsHub page-settings card', 'is_client_preview() gate', is_client_preview() ? 'visible' : 'hidden_without_preview', is_client_preview() ? 'ok' : 'master_settings_hub_leak_risk'],
            ['parity GET /{slug}/admin/page-settings', Route::has('client.parity.admin.page-settings.index') ? 'registered' : 'missing', 'mutating parity routes', $this->mutatingParityStatus()],
        ];
        $this->table(['check', 'detail_a', 'detail_b', 'status'], $leakRows);

        $this->newLine();
        $this->info('Page not found root causes (read-only diagnosis)');
        $causes = [
            '404 Client profile not available' => 'CurrentClientContext empty when hitting /admin/page-settings without /{clientSlug} preview/parity context.',
            '404 invalid pageKey' => 'edit() abort_unless(ClientPageKeys::isValid) — unknown page key in URL.',
            'Master shell on page-settings' => 'client_view() fell back to legacy dashboard shell when admin theme view missing or context not set before controller.',
            'Settings hub shows master cards' => 'AdminSettingsHubController always renders master agency settings; JetPK page-settings card only when is_client_preview().',
        ];
        foreach ($causes as $symptom => $cause) {
            $this->line("  • {$symptom}: {$cause}");
        }

        $this->newLine();
        $this->info('Recommended next repair phase (planning only)');
        $this->line('  1. Register authenticated client-prefixed admin page-settings routes under /{clientSlug}/admin/page-settings/* with preview.client middleware.');
        $this->line('  2. Ensure ResolvePreviewClient / PersistClientPreviewContext run before ClientPageSettingsController on JetPK admin URLs.');
        $this->line('  3. Replace master route() links in JetPK admin chrome with client_route() when current_client_slug() is set.');
        $this->line('  4. Gate sidebar “Page settings” on active client context.');
        $this->line('  5. Seed client_page_settings rows for jetpk profile if table empty (separate approved migration/data pass).');

        return self::SUCCESS;
    }

    private function mutatingParityStatus(): string
    {
        $required = [
            'client.parity.admin.page-settings.update',
            'client.parity.admin.page-settings.publish',
            'client.parity.admin.page-settings.preview.begin',
            'client.parity.admin.page-settings.assets.store',
            'client.parity.admin.page-settings.assets.destroy',
        ];

        foreach ($required as $name) {
            if (! Route::has($name)) {
                return 'missing_'.$name;
            }
        }

        return 'ok';
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function safeRoute(string $name, array $params = []): string
    {
        if (! Route::has($name)) {
            return 'missing';
        }

        try {
            return route($name, $params, false);
        } catch (\Throwable) {
            return 'error';
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function safeClientRoute(string $name, array $params, string $slug): string
    {
        if (! Route::has($name)) {
            return 'missing';
        }

        try {
            return client_route($name, $params, $slug);
        } catch (\Throwable) {
            return 'error';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleParams(string $routeName): array
    {
        return match (true) {
            str_contains($routeName, 'bookings.show') => ['booking' => 1],
            str_contains($routeName, 'page-settings.edit'),
            str_contains($routeName, 'page-settings.publish'),
            str_contains($routeName, 'page-settings.preview') => ['pageKey' => ClientPageKeys::HOME],
            default => [],
        };
    }
}
