<?php

namespace App\Support\Audits;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Client\JetpkPortalUiIdentityClassifier;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Read-only authenticated dashboard route inventory for JetPK closure audits.
 */
final class JetpkDashboardRouteAuditService
{
    /** @var list<string> */
    private const AUTH_MIDDLEWARE_MARKERS = [
        'auth',
        'auth:',
        'verified',
        'can:',
        'role:',
        'permission:',
    ];

    /** @var list<string> */
    private const PORTAL_PREFIXES = [
        'admin' => 'admin',
        'staff' => 'staff',
        'agent' => 'agent',
        'customer' => 'customer',
        'developer' => 'devcp',
    ];

    /** @var list<string> */
    private const FORBIDDEN_BRANDS = [
        'Parwaaz',
        'YD Travel',
        'YoursDomain',
        'haseeb-master',
    ];

    public function __construct(
        private readonly RuntimeViewResolver $viewResolver,
        private readonly ClientProfileResolver $profileResolver,
        private readonly CurrentClientContext $clientContext,
    ) {}

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, int|string>,
     *     json_path: string,
     *     md_path: string
     * }
     */
    public function run(string $clientSlug = 'jetpk'): array
    {
        $profile = $this->profileResolver->resolveBySlug($clientSlug);
        if ($profile !== null) {
            $this->clientContext->set($profile);
        }

        $rows = [];
        $byRole = [];
        $byModule = [];
        $fail = 0;
        $warn = 0;
        $blockedQa = 0;

        foreach (RouteFacade::getRoutes() as $route) {
            if (! $this->isAuthenticatedGet($route)) {
                continue;
            }

            $uri = '/'.ltrim((string) $route->uri(), '/');
            $role = $this->resolveRole($uri, $route);
            $module = $this->resolveModule($uri, $role);
            $viewInfo = $this->resolveView($route, $role, $profile);
            $layout = $this->resolveLayout($role, $profile);
            $cssAssets = $this->cssAssetsForRole($role);
            $qaMode = $this->qaSafety($uri, $route);
            $issues = $this->detectIssues($viewInfo, $layout, $role, $uri);

            $severity = 'pass';
            if ($issues !== []) {
                $hasFail = collect($issues)->contains(fn (array $i) => ($i['severity'] ?? '') === 'fail');
                $hasWarn = collect($issues)->contains(fn (array $i) => ($i['severity'] ?? '') === 'warn');
                $severity = $hasFail ? 'fail' : ($hasWarn ? 'warn' : 'info');
                if ($hasFail) {
                    $fail++;
                } elseif ($hasWarn) {
                    $warn++;
                }
            }

            if ($qaMode['blocked']) {
                $blockedQa++;
            }

            $byRole[$role] = ($byRole[$role] ?? 0) + 1;
            $byModule[$module] = ($byModule[$module] ?? 0) + 1;

            $rows[] = [
                'method' => 'GET',
                'route_name' => $route->getName() ?? '',
                'uri' => $uri,
                'middleware' => implode('|', $route->gatherMiddleware()),
                'controller' => $this->controllerAction($route),
                'view' => $viewInfo['view'],
                'view_exists' => $viewInfo['exists'],
                'view_status' => $viewInfo['status'],
                'layout' => $layout,
                'role' => $role,
                'module' => $module,
                'permissions' => $this->permissionHints($route),
                'css_assets' => $cssAssets,
                'qa_read_only' => $qaMode['safe'],
                'qa_block_reason' => $qaMode['reason'],
                'severity' => $severity,
                'issues' => $issues,
                'fix_status' => $severity === 'pass' ? 'ok' : 'pending',
                'screenshot_path' => '',
                'verification' => $severity === 'pass' ? 'pass' : 'open',
            ];
        }

        usort($rows, fn (array $a, array $b) => [$a['role'], $a['module'], $a['uri']] <=> [$b['role'], $b['module'], $b['uri']]);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'client_slug' => $clientSlug,
            'route_count' => count($rows),
            'summary' => [
                'pass' => count($rows) - $fail - $warn,
                'warn' => $warn,
                'fail' => $fail,
                'blocked_visual_qa' => $blockedQa,
                'by_role' => $byRole,
                'by_module' => $byModule,
            ],
            'rows' => $rows,
        ];

        $dir = storage_path('app/audits/jetpk-dashboard-route-audit');
        File::ensureDirectoryExists($dir);
        $jsonPath = $dir.'/inventory.json';
        $mdPath = $dir.'/inventory.md';
        File::put($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($mdPath, $this->markdownReport($payload));

        return [
            'rows' => $rows,
            'summary' => $payload['summary'],
            'json_path' => $jsonPath,
            'md_path' => $mdPath,
        ];
    }

    private function isAuthenticatedGet(Route $route): bool
    {
        $methods = $route->methods();
        if (! in_array('GET', $methods, true) && ! in_array('HEAD', $methods, true)) {
            return false;
        }

        $middleware = implode('|', $route->gatherMiddleware());
        if (! Str::contains($middleware, self::AUTH_MIDDLEWARE_MARKERS)) {
            return false;
        }

        $uri = (string) $route->uri();
        if (Str::contains($uri, ['_ignition', 'livewire', 'sanctum', 'telescope'])) {
            return false;
        }

        foreach (array_keys(self::PORTAL_PREFIXES) as $prefix) {
            if (Str::startsWith($uri, $prefix)) {
                return true;
            }
        }

        return Str::contains($uri, ['profile', 'password', 'two-factor', 'account', 'forced-password']);
    }

    private function resolveRole(string $uri, Route $route): string
    {
        foreach (self::PORTAL_PREFIXES as $prefix => $role) {
            if (Str::startsWith(ltrim($uri, '/'), $prefix)) {
                return $role;
            }
        }

        $name = (string) ($route->getName() ?? '');
        if (Str::startsWith($name, 'developer.')) {
            return 'devcp';
        }

        return 'shared';
    }

    private function resolveModule(string $uri, string $role): string
    {
        $path = trim($uri, '/');
        $parts = explode('/', $path);
        $tail = $parts[1] ?? ($parts[0] ?? 'dashboard');

        return match (true) {
            Str::contains($path, 'bookings') => 'bookings',
            Str::contains($path, 'api-settings') || Str::contains($path, 'supplier') => 'suppliers',
            Str::contains($path, 'page-settings') => 'page_settings',
            Str::contains($path, 'group-ticketing') => 'group_ticketing',
            Str::contains($path, 'reports') || Str::contains($path, 'finance') || Str::contains($path, 'accounting') => 'finance',
            Str::contains($path, 'support') => 'service',
            Str::contains($path, 'users') || Str::contains($path, 'roles') || Str::contains($path, 'settings') => 'administration',
            Str::contains($path, 'profile') || Str::contains($path, 'password') => 'account_security',
            $role === 'devcp' => 'devcp',
            default => $tail !== '' ? $tail : 'overview',
        };
    }

    /**
     * @return array{view: string, exists: bool, status: string}
     */
    private function resolveView(Route $route, string $role, ?\App\Models\ClientProfile $profile): array
    {
        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            return ['view' => '', 'exists' => false, 'status' => 'closure'];
        }

        $logical = $this->guessLogicalView($route);
        $area = match ($role) {
            'staff' => 'staff',
            'agent' => 'agent',
            'customer' => 'customer',
            default => 'admin',
        };

        if ($logical === '') {
            return ['view' => '', 'exists' => false, 'status' => 'unknown'];
        }

        $resolved = $this->viewResolver->view($logical, $area, $profile);
        $theme = $this->viewResolver->themeViewName($logical, $area, $profile);
        $hasTheme = View::exists($theme);
        $status = $hasTheme ? 'themed' : (View::exists($resolved) ? 'shell-wrapped' : 'missing');

        return [
            'view' => $resolved,
            'exists' => View::exists($resolved),
            'status' => $status,
        ];
    }

    private function guessLogicalView(Route $route): string
    {
        $name = (string) ($route->getName() ?? '');
        if ($name === '') {
            return '';
        }

        $stripped = preg_replace('/^(admin|staff|agent|customer|developer)\./', '', $name) ?? $name;

        return match ($stripped) {
            'dashboard' => 'index',
            default => str_replace('.', '/', $stripped),
        };
    }

    private function resolveLayout(string $role, ?\App\Models\ClientProfile $profile): string
    {
        return match ($role) {
            'admin', 'staff' => 'themes.admin.jetpakistan.layouts.dashboard',
            'agent' => 'themes.agent.jetpakistan.layouts.agent-portal',
            'customer' => 'themes.customer.jetpakistan.layouts.customer-account',
            'devcp' => 'layouts.developer',
            default => 'shared/account',
        };
    }

    /** @return list<string> */
    private function cssAssetsForRole(string $role): array
    {
        return match ($role) {
            'admin', 'staff' => [
                'themes/frontend/jetpakistan/css/tokens.css',
                'themes/admin/jetpakistan/css/dashboard.css',
            ],
            'agent', 'customer' => [
                'themes/frontend/jetpakistan/css/tokens.css',
                'themes/frontend/jetpakistan/css/portal.css',
            ],
            'devcp' => ['css/devcp.css'],
            default => ['themes/frontend/jetpakistan/css/tokens.css'],
        };
    }

    /**
     * @return array{safe: bool, blocked: bool, reason: string}
     */
    private function qaSafety(string $uri, Route $route): array
    {
        $lower = strtolower($uri.'|'.($route->getName() ?? ''));
        $blockedPatterns = [
            '/delete',
            '/destroy',
            'test-connection',
            '/publish',
            '/send',
            '/charge',
            '/cancel',
            '/refund',
            '/sync',
            '/probe',
        ];
        foreach ($blockedPatterns as $needle) {
            if (Str::contains($lower, $needle)) {
                return ['safe' => false, 'blocked' => true, 'reason' => "Mutating or side-effect route ({$needle})"];
            }
        }

        if (Str::contains($lower, ['create', 'edit']) && Str::contains($lower, 'api-settings')) {
            return ['safe' => true, 'blocked' => false, 'reason' => 'Form render only — do not submit'];
        }

        return ['safe' => true, 'blocked' => false, 'reason' => ''];
    }

    /**
     * @return list<array{severity: string, issue: string, root_cause: string}>
     */
    private function detectIssues(array $viewInfo, string $layout, string $role, string $uri): array
    {
        $issues = [];

        if (! $viewInfo['exists'] && $viewInfo['status'] !== 'closure') {
            $issues[] = [
                'severity' => 'info',
                'classification' => 'heuristic-unverified',
                'issue' => 'Resolved view could not be verified from route name',
                'root_cause' => 'Route-to-view mapping heuristic — verify manually or extend catalog',
            ];
        }

        if ($viewInfo['status'] === 'shell-wrapped' && in_array($role, ['admin', 'staff'], true)) {
            $issues[] = [
                'severity' => 'info',
                'classification' => 'compat-shell',
                'issue' => 'Legacy shell-wrapped page',
                'root_cause' => 'Compat layer — themed shell wraps legacy module body',
            ];
        }

        if (in_array($role, ['admin', 'staff'], true) && $layout !== 'themes.admin.jetpakistan.layouts.dashboard') {
            $issues[] = [
                'severity' => 'fail',
                'issue' => 'Wrong dashboard shell',
                'root_cause' => 'Expected JetPK admin dashboard layout',
            ];
        }

        if (Str::contains($uri, 'api-settings/create')) {
            $themeCreate = resource_path('views/themes/admin/jetpakistan/api-settings/create.blade.php');
            if (! is_file($themeCreate)) {
                $issues[] = [
                    'severity' => 'warn',
                    'issue' => 'Supplier create uses legacy form chrome',
                    'root_cause' => 'Missing themed create view',
                ];
            }
        }

        return $issues;
    }

  /**
     * @return list<string>
     */
    private function permissionHints(Route $route): array
    {
        $hints = [];
        foreach ($route->gatherMiddleware() as $mw) {
            if (Str::startsWith($mw, 'can:') || Str::startsWith($mw, 'permission:')) {
                $hints[] = $mw;
            }
        }

        return $hints;
    }

    private function controllerAction(Route $route): string
    {
        $action = $route->getActionName();
        if ($action === 'Closure') {
            return 'closure';
        }

        return $action;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markdownReport(array $payload): string
    {
        $lines = [
            '# JetPK Dashboard Route Inventory',
            '',
            'Generated: '.$payload['generated_at'],
            'Routes: '.$payload['route_count'],
            '',
            '## Summary',
            '',
        ];

        foreach ($payload['summary']['by_role'] ?? [] as $role => $count) {
            $lines[] = "- **{$role}**: {$count}";
        }

        $lines[] = '';
        $lines[] = '| Severity | Count |';
        $lines[] = '|----------|-------|';
        $lines[] = '| pass | '.($payload['summary']['pass'] ?? 0).' |';
        $lines[] = '| warn | '.($payload['summary']['warn'] ?? 0).' |';
        $lines[] = '| fail | '.($payload['summary']['fail'] ?? 0).' |';
        $lines[] = '';

        foreach ($payload['rows'] as $row) {
            if (($row['severity'] ?? 'pass') === 'pass') {
                continue;
            }
            $lines[] = '### '.$row['uri'].' ('.$row['role'].')';
            foreach ($row['issues'] as $issue) {
                $lines[] = '- **'.$issue['severity'].'**: '.$issue['issue'].' — '.$issue['root_cause'];
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
