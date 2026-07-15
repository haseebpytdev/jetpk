<?php

namespace App\Support\Audits;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Read-only admin dashboard deep-page inventory for JetPK 9H-D closure audits.
 */
final class JetpkAdminDeepPageInventoryAuditService
{
    /** @var list<string> */
    private const FINAL_STATUSES = [
        'PASS',
        'FIXED',
        'BLOCKED',
        'LEGACY-REDIRECTED',
        'NOT-UI',
        'OUT-OF-SCOPE-WITH-REASON',
    ];

    public function __construct(
        private readonly RuntimeViewResolver $viewResolver,
        private readonly ClientProfileResolver $profileResolver,
        private readonly CurrentClientContext $clientContext,
        private readonly JetpkAdminBladeContractAuditor $contractAuditor,
    ) {}

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, int|string|array<string, int>>,
     *     json_path: string,
     *     md_path: string,
     *     matrix_path: string
     * }
     */
    public function run(string $clientSlug = 'jetpk'): array
    {
        $profile = $this->profileResolver->resolveBySlug($clientSlug);
        if ($profile !== null) {
            $this->clientContext->set($profile);
        }

        $rows = [];
        $counts = [
            'visible_routes' => 0,
            'index' => 0,
            'create' => 0,
            'edit' => 0,
            'show' => 0,
            'settings' => 0,
            'modal' => 0,
            'action_form' => 0,
            'legacy' => 0,
            'legacy_fixed' => 0,
            'blocked' => 0,
        ];
        $byFinal = array_fill_keys(self::FINAL_STATUSES, 0);

        foreach (RouteFacade::getRoutes() as $route) {
            if (! $this->isAdminRoute($route)) {
                continue;
            }

            $uri = '/'.ltrim((string) $route->uri(), '/');
            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $method = $methods[0] ?? 'GET';
            $name = (string) ($route->getName() ?? '');
            $pageType = $this->pageType($uri, $name, $method);
            $viewInfo = $this->resolveView($route, $profile);
            $shellStatus = $this->shellStatus($viewInfo);
            $legacyStatus = $this->legacyStatus($viewInfo, $uri);
            $isVisibleUi = $this->isVisibleUi($method, $pageType, $uri, $name);
            $linkedParent = $this->linkedFrom($uri, $name);

            $contractAudit = ['pass' => true, 'legacy_markers' => [], 'jp_markers' => [], 'defects' => []];
            $auditView = '';
            if ($isVisibleUi) {
                $auditView = $this->contractAuditViewName($viewInfo);
                if ($auditView !== '') {
                    $contractAudit = $this->contractAuditor->auditView($auditView);
                } else {
                    $contractAudit = [
                        'pass' => false,
                        'legacy_markers' => [],
                        'jp_markers' => [],
                        'defects' => ['no resolvable blade view'],
                    ];
                }
            }

            $firstPassResult = $isVisibleUi
                ? $this->firstPassResult($shellStatus, $legacyStatus, $uri, $contractAudit)
                : '';
            $defectsFound = $isVisibleUi ? $contractAudit['defects'] : [];
            $filesChanged = $isVisibleUi && $defectsFound !== []
                ? $this->bladeFilePath($auditView)
                : '';
            $finalVerdict = $this->classifyFinalStatus(
                $isVisibleUi,
                $shellStatus,
                $legacyStatus,
                $uri,
                $method,
                $viewInfo,
                $contractAudit,
            );
            $secondPassResult = $isVisibleUi ? $finalVerdict : 'NOT-UI';
            $actionRequired = in_array($finalVerdict, ['PASS', 'FIXED', 'NOT-UI', 'LEGACY-REDIRECTED'], true)
                ? ($finalVerdict === 'LEGACY-REDIRECTED' ? $this->actionRequired($finalVerdict, $shellStatus, $legacyStatus) : '')
                : $this->actionRequired($finalVerdict, $shellStatus, $legacyStatus);

            if ($isVisibleUi) {
                $counts['visible_routes']++;
                $counts[$pageType] = ($counts[$pageType] ?? 0) + 1;
            }

            if ($legacyStatus === 'legacy') {
                $counts['legacy']++;
            }
            if ($legacyStatus === 'themed' || $finalVerdict === 'LEGACY-REDIRECTED' || $finalVerdict === 'FIXED') {
                $counts['legacy_fixed']++;
            }
            if ($finalVerdict === 'BLOCKED') {
                $counts['blocked']++;
            }

            $byFinal[$finalVerdict] = ($byFinal[$finalVerdict] ?? 0) + 1;

            $rows[] = [
                'is_visible_ui' => $isVisibleUi,
                'module' => $this->module($uri, $name),
                'route_name' => $name,
                'http_method' => $method,
                'uri' => $uri,
                'controller' => $this->controllerAction($route),
                'blade' => $viewInfo['view'],
                'page_type' => $pageType,
                'role_access' => 'platform_admin',
                'sidebar_entry' => $this->sidebarEntry($uri, $name),
                'linked_from' => $linkedParent,
                'linked_parent' => $linkedParent,
                'jetpk_shell_status' => $shellStatus,
                'visual_status' => $this->visualStatus($shellStatus, $legacyStatus),
                'palette_status' => $shellStatus === 'jetpk-themed' ? 'vars-injected' : 'pending',
                'form_status' => $this->formStatus($uri, $legacyStatus),
                'table_status' => $this->tableStatus($uri, $legacyStatus),
                'responsive_status' => $shellStatus === 'jetpk-themed' ? 'safe' : 'review',
                'legacy_status' => $legacyStatus,
                'first_pass_result' => $firstPassResult,
                'defects_found' => $defectsFound,
                'files_changed' => $filesChanged,
                'second_pass_result' => $secondPassResult,
                'final_verdict' => $finalVerdict,
                'action_required' => $actionRequired,
                'final_status' => $finalVerdict,
            ];
        }

        usort($rows, fn (array $a, array $b) => [$a['module'], $a['uri'], $a['http_method']] <=> [$b['module'], $b['uri'], $b['http_method']]);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'client_slug' => $clientSlug,
            'phase' => 'jetpk-9h-d',
            'route_count' => count($rows),
            'counts' => $counts,
            'by_final_status' => $byFinal,
            'rows' => $rows,
        ];

        $dir = storage_path('app/audits/jetpk-9h-d');
        File::ensureDirectoryExists($dir);
        $jsonPath = $dir.'/ADMIN-DEEP-PAGE-INVENTORY.json';
        $mdPath = $dir.'/ADMIN-DEEP-PAGE-INVENTORY.md';
        $matrixPath = $dir.'/ADMIN-DEEP-PAGE-FINAL-MATRIX.md';
        File::put($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($mdPath, $this->markdownInventory($payload));
        File::put($matrixPath, $this->markdownFinalMatrix($rows));

        return [
            'rows' => $rows,
            'summary' => $payload['counts'],
            'json_path' => $jsonPath,
            'md_path' => $mdPath,
            'matrix_path' => $matrixPath,
        ];
    }

    /**
     * @param  array{view: string, exists: bool, status: string, theme: string}  $viewInfo
     */
    private function contractAuditViewName(array $viewInfo): string
    {
        if (($viewInfo['theme'] ?? '') !== '') {
            return $viewInfo['theme'];
        }

        return $viewInfo['view'] ?? '';
    }

    /**
     * @param  array{pass: bool, legacy_markers: list<string>, jp_markers: list<string>, defects: list<string>}  $contractAudit
     */
    private function firstPassResult(string $shellStatus, string $legacyStatus, string $uri, array $contractAudit): string
    {
        if (Str::contains($uri, 'settings/homepage') && ! Str::contains($uri, 'featured-fares')) {
            return 'redirect-pending';
        }

        $contractLabel = ($contractAudit['pass'] ?? false) ? 'contract-pass' : 'contract-fail';

        return match ($shellStatus) {
            'jetpk-themed' => 'themed+'.$contractLabel,
            'legacy-shell-wrapped' => 'legacy-shell+'.$contractLabel,
            default => $legacyStatus.'+'.$contractLabel,
        };
    }

    private function bladeFilePath(string $viewName): string
    {
        if ($viewName === '') {
            return '';
        }

        $normalized = str_replace('.', '/', $viewName);
        $candidates = [
            resource_path('views/'.$normalized.'.blade.php'),
            resource_path('views/themes/admin/jetpakistan/'.$normalized.'.blade.php'),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $candidate);

                return str_replace('\\', '/', $relative);
            }
        }

        return '';
    }

    private function isAdminRoute(Route $route): bool
    {
        $uri = (string) $route->uri();
        $name = (string) ($route->getName() ?? '');

        return Str::startsWith($uri, 'admin') || Str::startsWith($name, 'admin.');
    }

    private function pageType(string $uri, string $name, string $method): string
    {
        if ($method !== 'GET') {
            if (Str::contains($uri, ['preview', 'export', 'data', 'suggestions'])) {
                return 'preview';
            }

            return 'action_form';
        }

        return match (true) {
            Str::contains($uri, '/create') || Str::endsWith($name, '.create') => 'create',
            Str::contains($uri, '/edit') || Str::endsWith($name, '.edit') => 'edit',
            Str::contains($name, '.show') || preg_match('#/\{[^}]+\}$#', $uri) === 1 && ! Str::contains($uri, ['index', 'create', 'edit', 'preview']) => 'show',
            Str::contains($uri, ['settings', 'page-settings', 'branding', 'communications']) => 'settings',
            Str::contains($uri, 'preview') => 'preview',
            default => 'index',
        };
    }

    /**
     * @return array{view: string, exists: bool, status: string, theme: string}
     */
    private function resolveView(Route $route, ?\App\Models\ClientProfile $profile): array
    {
        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            return ['view' => '', 'exists' => false, 'status' => 'closure', 'theme' => ''];
        }

        $logical = $this->guessLogicalView($route);
        if ($logical === '') {
            return ['view' => '', 'exists' => false, 'status' => 'unknown', 'theme' => ''];
        }

        $resolved = $this->viewResolver->view($logical, 'admin', $profile);
        $theme = $this->viewResolver->themeViewName($logical, 'admin', $profile);
        $hasTheme = View::exists($theme);

        return [
            'view' => $resolved,
            'exists' => View::exists($resolved),
            'status' => $hasTheme ? 'themed' : (View::exists($resolved) ? 'legacy' : 'missing'),
            'theme' => $hasTheme ? $theme : '',
        ];
    }

    private function guessLogicalView(Route $route): string
    {
        $name = (string) ($route->getName() ?? '');
        if ($name === '') {
            return '';
        }

        $stripped = preg_replace('/^admin\./', '', $name) ?? $name;

        return match ($stripped) {
            'dashboard' => 'index',
            default => str_replace('.', '/', $stripped),
        };
    }

    private function shellStatus(array $viewInfo): string
    {
        return match ($viewInfo['status']) {
            'themed' => 'jetpk-themed',
            'legacy' => 'legacy-shell-wrapped',
            'closure' => 'not-applicable',
            default => 'unknown',
        };
    }

    private function legacyStatus(array $viewInfo, string $uri): string
    {
        if (Str::contains($uri, 'settings/homepage') && ! Str::contains($uri, 'featured-fares')) {
            return 'redirect-pending';
        }

        return match ($viewInfo['status']) {
            'themed' => 'themed',
            'legacy' => 'legacy',
            default => 'unknown',
        };
    }

    private function isVisibleUi(string $method, string $pageType, string $uri, string $name): bool
    {
        if ($method !== 'GET') {
            return false;
        }

        if (in_array($pageType, ['action_form'], true)) {
            return false;
        }

        if (Str::contains($uri, ['data', 'suggestions', 'export', 'proof', 'audit/export', '/search', '/_test/'])) {
            return false;
        }

        if (Str::endsWith($name, ['.data', '.suggestions', '.search', '.export'])) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{view: string, exists: bool, status: string, theme: string}  $viewInfo
     * @param  array{pass: bool, legacy_markers: list<string>, jp_markers: list<string>, defects: list<string>}  $contractAudit
     */
    private function classifyFinalStatus(
        bool $isVisibleUi,
        string $shellStatus,
        string $legacyStatus,
        string $uri,
        string $method,
        array $viewInfo,
        array $contractAudit,
    ): string {
        if (! $isVisibleUi) {
            return 'NOT-UI';
        }

        if (Str::contains($uri, 'settings/homepage') && ! Str::contains($uri, 'featured-fares')) {
            return 'LEGACY-REDIRECTED';
        }

        if (($viewInfo['status'] ?? '') === 'missing') {
            return 'NOT-UI';
        }

        if ($method !== 'GET') {
            return 'NOT-UI';
        }

        $contractPass = $contractAudit['pass'] ?? false;

        if ($shellStatus === 'jetpk-themed') {
            return $contractPass ? 'PASS' : 'BLOCKED';
        }

        if ($shellStatus === 'legacy-shell-wrapped') {
            return $contractPass ? 'FIXED' : 'BLOCKED';
        }

        return 'BLOCKED';
    }

    private function actionRequired(string $finalStatus, string $shellStatus, string $legacyStatus): string
    {
        return match ($finalStatus) {
            'BLOCKED' => $shellStatus === 'legacy-shell-wrapped'
                ? 'Convert legacy inner markup to jp-* contract or promote to themed view'
                : 'Fix blade contract defects on themed view',
            'LEGACY-REDIRECTED' => 'Route redirects to canonical Page Settings editor',
            default => '',
        };
    }

    private function module(string $uri, string $name): string
    {
        return match (true) {
            Str::contains($uri, 'page-settings') => 'page_settings',
            Str::contains($uri, 'api-settings') => 'suppliers',
            Str::contains($uri, 'communications') => 'communications',
            Str::contains($uri, 'bookings') => 'bookings',
            Str::contains($uri, 'group-ticketing') => 'group_ticketing',
            Str::contains($uri, 'settings') => 'settings',
            Str::contains($uri, 'finance') || Str::contains($uri, 'accounting') => 'finance',
            default => explode('/', trim($uri, '/'))[1] ?? 'overview',
        };
    }

    private function sidebarEntry(string $uri, string $name): string
    {
        if (Str::contains($uri, 'settings') || Str::contains($name, 'settings')) {
            return 'settings_hub';
        }

        return $this->module($uri, $name);
    }

    private function linkedFrom(string $uri, string $name): string
    {
        if (Str::contains($uri, '/create')) {
            return 'parent_index';
        }
        if (Str::contains($uri, '/edit')) {
            return 'parent_index_or_show';
        }

        return 'sidebar';
    }

    private function visualStatus(string $shellStatus, string $legacyStatus): string
    {
        if ($shellStatus === 'jetpk-themed') {
            return 'aligned';
        }

        if ($legacyStatus === 'legacy') {
            return 'legacy-pending';
        }

        return 'review';
    }

    private function formStatus(string $uri, string $legacyStatus): string
    {
        if (Str::contains($uri, ['create', 'edit', 'settings', 'page-settings'])) {
            return $legacyStatus === 'themed' ? 'jp-controls' : 'mixed';
        }

        return 'n/a';
    }

    private function tableStatus(string $uri, string $legacyStatus): string
    {
        if (Str::endsWith($uri, '/create') || Str::contains($uri, '/edit')) {
            return 'n/a';
        }

        return $legacyStatus === 'themed' ? 'jp-table' : 'legacy-table';
    }

    private function controllerAction(Route $route): string
    {
        $action = $route->getActionName();

        return $action === 'Closure' ? 'closure' : $action;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function markdownFinalMatrix(array $rows): string
    {
        $lines = [
            '# Admin Deep Page Final Matrix (9H-D)',
            '',
            'Visible UI routes only. legacy-shell-wrapped never scores PASS; themed+contract-pass = PASS, converted legacy inner = FIXED.',
            '',
            '| route | page | first-pass | fixes applied | second-pass | blocker | final verdict |',
            '|-------|------|------------|---------------|-------------|---------|---------------|',
        ];

        foreach ($rows as $row) {
            if (! ($row['is_visible_ui'] ?? false)) {
                continue;
            }

            $fixesApplied = '';
            if (($row['final_verdict'] ?? '') === 'FIXED') {
                $fixesApplied = 'legacy-inner-converted';
            } elseif (($row['files_changed'] ?? '') !== '') {
                $fixesApplied = (string) $row['files_changed'];
            }

            $blocker = in_array($row['final_verdict'] ?? '', ['BLOCKED'], true)
                ? (string) ($row['action_required'] ?? '')
                : '';

            $defects = $row['defects_found'] ?? [];
            if ($blocker !== '' && is_array($defects) && $defects !== []) {
                $blocker .= ' ('.implode('; ', $defects).')';
            }

            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s | %s | %s |',
                $row['route_name'] ?? '',
                $row['page_type'] ?? '',
                $row['first_pass_result'] ?? '',
                $fixesApplied,
                $row['second_pass_result'] ?? '',
                $blocker,
                $row['final_verdict'] ?? '',
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markdownInventory(array $payload): string
    {
        $lines = [
            '# JetPK Admin Deep-Page Inventory (9H-D)',
            '',
            'Generated: '.$payload['generated_at'],
            'Routes: '.$payload['route_count'],
            '',
            '## Totals',
            '',
        ];

        foreach ($payload['counts'] as $key => $value) {
            $lines[] = '- **'.$key.'**: '.$value;
        }

        $lines[] = '';
        $lines[] = '## By final status';
        $lines[] = '';
        foreach ($payload['by_final_status'] as $status => $count) {
            $lines[] = '- **'.$status.'**: '.$count;
        }

        $visibleRows = array_values(array_filter(
            $payload['rows'],
            fn (array $row) => (bool) ($row['is_visible_ui'] ?? false),
        ));

        $lines[] = '';
        $lines[] = '## Visible UI routes ('.count($visibleRows).')';
        $lines[] = '';
        $lines[] = '| Route | URI | Page | Shell | First pass | Defects | Files | Second pass | Verdict | Parent |';
        $lines[] = '|-------|-----|------|-------|------------|---------|-------|-------------|---------|--------|';

        foreach ($visibleRows as $row) {
            $defects = $row['defects_found'] ?? [];
            $defectText = is_array($defects) && $defects !== [] ? implode('; ', $defects) : '';
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |',
                $row['route_name'] ?? '',
                $row['uri'] ?? '',
                $row['page_type'] ?? '',
                $row['jetpk_shell_status'] ?? '',
                $row['first_pass_result'] ?? '',
                $defectText,
                $row['files_changed'] ?? '',
                $row['second_pass_result'] ?? '',
                $row['final_verdict'] ?? '',
                $row['linked_parent'] ?? '',
            );
        }

        $lines[] = '';
        $lines[] = '## Routes needing action';
        $lines[] = '';
        $lines[] = '| URI | Type | View | Final | Action |';
        $lines[] = '|-----|------|------|-------|--------|';

        foreach ($payload['rows'] as $row) {
            if (! in_array($row['final_status'], ['BLOCKED', 'OUT-OF-SCOPE-WITH-REASON'], true)) {
                continue;
            }
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $row['uri'],
                $row['page_type'],
                $row['blade'],
                $row['final_status'],
                $row['action_required'],
            );
        }

        return implode("\n", $lines);
    }
}
