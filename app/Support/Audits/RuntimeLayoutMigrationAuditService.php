<?php

namespace App\Support\Audits;

use App\Services\Client\ClientProfileResolver;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * MC-9A–9E read-only audit — counts runtime layout migration status in Blade views.
 */
final class RuntimeLayoutMigrationAuditService
{
    private const MIGRATED_FRONTEND = "@extends(client_layout('frontend', 'frontend'))";

    private const MIGRATED_AUTH = "@extends(client_layout('auth', 'frontend'))";

    private const MIGRATED_ADMIN = "@extends(client_layout('dashboard', 'admin'))";

    private const MIGRATED_STAFF = "@extends(client_layout('dashboard', 'staff'))";

    private const MIGRATED_AGENT = "@extends(client_layout('agent-portal', 'agent'))";

    private const MIGRATED_CUSTOMER_ACCOUNT = "@extends(client_layout('customer-account', 'customer'))";

    private const MIGRATED_CUSTOMER_DASHBOARD = "@extends(client_layout('dashboard', 'customer'))";

    /**
     * @return array{
     *     counts: array{
     *         frontend_migrated: int,
     *         auth_migrated: int,
     *         admin_migrated: int,
     *         staff_migrated: int,
     *         agent_migrated: int,
     *         customer_migrated: int,
     *         remaining_frontend: int,
     *         remaining_auth: int,
     *         remaining_admin_dashboard: int,
     *         remaining_staff_dashboard: int,
     *         remaining_agent_portal: int,
     *         remaining_customer_account: int,
     *         remaining_customer_dashboard: int,
     *         deferred_untouched: int,
     *         deferred_client_layout_violations: int
     *     },
     *     safety: array{
     *         remaining_staff_dashboard: int,
     *         remaining_agent_portal: int,
     *         remaining_customer_legacy: int,
     *         profile_edit_dashboard_migrated: bool,
     *         profile_edit_agent_migrated: bool,
     *         profile_edit_frontend_migrated: bool,
     *         dev_cp_has_layout_extends: bool,
     *         supplier_has_client_layout: bool,
     *         module_gate_has_client_layout: bool,
     *         passed: bool,
     *         failures: list<string>
     *     },
     *     http_checks: list<array{name: string, expected: string, actual: string, ok: bool}>
     * }
     */
    public function run(string $clientSlug): array
    {
        $viewsRoot = resource_path('views');
        $scope = config('client_view_paths.mc9_migrated_layout_scope', []);

        $frontendPaths = (array) ($scope['frontend_paths'] ?? ['resources/views/frontend']);
        $authPaths = (array) ($scope['auth_paths'] ?? ['resources/views/auth', 'resources/views/frontend/agent-registration']);
        $adminPaths = (array) ($scope['admin_paths'] ?? ['resources/views/dashboard/admin']);
        $staffPaths = (array) ($scope['staff_paths'] ?? ['resources/views/dashboard/staff']);
        $agentPaths = (array) ($scope['agent_paths'] ?? ['resources/views/dashboard/agent']);
        $customerPaths = (array) ($scope['customer_paths'] ?? ['resources/views/dashboard/customer']);
        $deferredPaths = (array) ($scope['deferred_paths'] ?? []);

        $frontendFiles = $this->collectBladeFiles($viewsRoot, $frontendPaths);
        $authFiles = $this->collectBladeFiles($viewsRoot, $authPaths);
        $adminFiles = $this->collectBladeFiles($viewsRoot, $adminPaths);
        $staffFiles = $this->collectBladeFiles($viewsRoot, $staffPaths);
        $agentFiles = $this->collectBladeFiles($viewsRoot, $agentPaths);
        $customerFiles = $this->collectBladeFiles($viewsRoot, $customerPaths);
        $deferredFiles = $this->collectBladeFiles($viewsRoot, $deferredPaths);

        $frontendMigrated = $this->countMatching($frontendFiles, self::MIGRATED_FRONTEND);
        $frontendMigrated += $this->countMatching(
            $this->collectBladeFiles($viewsRoot, ['resources/views/auth']),
            self::MIGRATED_FRONTEND,
        );
        $authMigrated = $this->countMatching($authFiles, self::MIGRATED_AUTH);
        $adminMigrated = $this->countMatching($adminFiles, self::MIGRATED_ADMIN);
        $staffMigrated = $this->countMatching($staffFiles, self::MIGRATED_STAFF);
        $agentMigrated = $this->countMatching($agentFiles, self::MIGRATED_AGENT);
        $customerMigrated = $this->countMatching($customerFiles, self::MIGRATED_CUSTOMER_ACCOUNT)
            + $this->countMatching($customerFiles, self::MIGRATED_CUSTOMER_DASHBOARD);

        $remainingFrontend = $this->countLegacyExtends($frontendFiles, 'layouts.frontend');
        $remainingFrontend += $this->countLegacyExtends(
            $this->collectBladeFiles($viewsRoot, ['resources/views/auth']),
            'layouts.frontend',
        );
        $remainingAuth = $this->countLegacyExtends($authFiles, 'layouts.auth');
        $remainingAdmin = $this->countLegacyExtends($adminFiles, 'layouts.dashboard');
        $remainingStaff = $this->countLegacyExtends($staffFiles, 'layouts.dashboard');
        $remainingAgent = $this->countLegacyExtends($agentFiles, 'layouts.agent-portal');
        $remainingCustomerAccount = $this->countLegacyExtends($customerFiles, 'layouts.customer-account');
        $remainingCustomerDashboard = $this->countLegacyExtends($customerFiles, 'layouts.dashboard');

        $deferredViolations = $this->countContaining($deferredFiles, 'client_layout(');
        $deferredUntouched = count($deferredFiles) - $deferredViolations;

        $profileEditDashboardMigrated = $this->fileContains(
            $viewsRoot.DIRECTORY_SEPARATOR.'profile'.DIRECTORY_SEPARATOR.'edit-dashboard.blade.php',
            'client_layout(',
        );
        $profileEditAgentMigrated = $this->fileContains(
            $viewsRoot.DIRECTORY_SEPARATOR.'profile'.DIRECTORY_SEPARATOR.'edit-agent.blade.php',
            'client_layout(',
        );
        $profileEditFrontendMigrated = $this->fileContains(
            $viewsRoot.DIRECTORY_SEPARATOR.'profile'.DIRECTORY_SEPARATOR.'edit-frontend.blade.php',
            'client_layout(',
        );

        $devCpHasLayoutExtends = $this->pathHasExtendsClientLayout($viewsRoot, 'developer');
        $supplierHasClientLayout = $this->pathHasClientLayoutInCodebase(base_path('app/Services/Suppliers'));
        $moduleGateHasClientLayout = $this->pathHasClientLayoutInCodebase(base_path('app/Support/Platform'));

        $failures = [];

        if ($remainingStaff > 0) {
            $failures[] = sprintf('%d staff view(s) still extend layouts.dashboard directly', $remainingStaff);
        }
        if ($remainingAgent > 0) {
            $failures[] = sprintf('%d agent view(s) still extend layouts.agent-portal directly', $remainingAgent);
        }
        if ($remainingCustomerAccount > 0 || $remainingCustomerDashboard > 0) {
            $failures[] = sprintf(
                '%d customer view(s) still extend legacy layouts directly',
                $remainingCustomerAccount + $remainingCustomerDashboard,
            );
        }
        if ($profileEditDashboardMigrated) {
            $failures[] = 'profile/edit-dashboard.blade.php must remain on legacy layout';
        }
        if ($profileEditAgentMigrated) {
            $failures[] = 'profile/edit-agent.blade.php must remain on legacy layout';
        }
        if ($profileEditFrontendMigrated) {
            $failures[] = 'profile/edit-frontend.blade.php must remain on legacy layout';
        }
        if ($deferredViolations > 0) {
            $failures[] = sprintf('%d deferred view(s) incorrectly use client_layout()', $deferredViolations);
        }
        if ($devCpHasLayoutExtends) {
            $failures[] = 'Developer CP views must not @extends(client_layout(...))';
        }
        if ($supplierHasClientLayout) {
            $failures[] = 'Supplier files must not reference client_layout()';
        }
        if ($moduleGateHasClientLayout) {
            $failures[] = 'Module gate files must not reference client_layout()';
        }

        $httpChecks = app(ClientProfileResolver::class)->isDefaultDeploymentSlug($clientSlug)
            ? $this->defaultSlugHttpChecks($clientSlug)
            : $this->prefixedClientHttpChecks($clientSlug);

        foreach ($httpChecks as $check) {
            if (! $check['ok']) {
                $failures[] = $check['name'].' failed';
            }
        }

        return [
            'counts' => [
                'frontend_migrated' => $frontendMigrated,
                'auth_migrated' => $authMigrated,
                'admin_migrated' => $adminMigrated,
                'staff_migrated' => $staffMigrated,
                'agent_migrated' => $agentMigrated,
                'customer_migrated' => $customerMigrated,
                'remaining_frontend' => $remainingFrontend,
                'remaining_auth' => $remainingAuth,
                'remaining_admin_dashboard' => $remainingAdmin,
                'remaining_staff_dashboard' => $remainingStaff,
                'remaining_agent_portal' => $remainingAgent,
                'remaining_customer_account' => $remainingCustomerAccount,
                'remaining_customer_dashboard' => $remainingCustomerDashboard,
                'deferred_untouched' => max(0, $deferredUntouched),
                'deferred_client_layout_violations' => $deferredViolations,
            ],
            'safety' => [
                'remaining_staff_dashboard' => $remainingStaff,
                'remaining_agent_portal' => $remainingAgent,
                'remaining_customer_legacy' => $remainingCustomerAccount + $remainingCustomerDashboard,
                'profile_edit_dashboard_migrated' => $profileEditDashboardMigrated,
                'profile_edit_agent_migrated' => $profileEditAgentMigrated,
                'profile_edit_frontend_migrated' => $profileEditFrontendMigrated,
                'dev_cp_has_layout_extends' => $devCpHasLayoutExtends,
                'supplier_has_client_layout' => $supplierHasClientLayout,
                'module_gate_has_client_layout' => $moduleGateHasClientLayout,
                'passed' => $failures === [],
                'failures' => $failures,
            ],
            'http_checks' => $httpChecks,
        ];
    }

    /**
     * @param  list<string>  $relativePaths
     * @return list<string>
     */
    private function collectBladeFiles(string $viewsRoot, array $relativePaths): array
    {
        $files = [];

        foreach ($relativePaths as $relativePath) {
            $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
            $relativePath = preg_replace('#^resources'.preg_quote(DIRECTORY_SEPARATOR, '#').'views'.preg_quote(DIRECTORY_SEPARATOR, '#').'#', '', $relativePath) ?? $relativePath;

            $fullPath = $viewsRoot.DIRECTORY_SEPARATOR.$relativePath;

            if (is_file($fullPath) && str_ends_with($fullPath, '.blade.php')) {
                $files[] = $fullPath;

                continue;
            }

            if (! is_dir($fullPath)) {
                continue;
            }

            foreach (File::allFiles($fullPath) as $file) {
                if (str_ends_with($file->getPathname(), '.blade.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param  list<string>  $files
     */
    private function countMatching(array $files, string $needle): int
    {
        $count = 0;

        foreach ($files as $file) {
            if ($this->fileContains($file, $needle)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<string>  $files
     */
    private function countLegacyExtends(array $files, string $layoutName): int
    {
        $count = 0;

        foreach ($files as $file) {
            $contents = $this->readFile($file);
            if ($contents === null) {
                continue;
            }

            if (preg_match("/@extends\\(['\"]".preg_quote($layoutName, '/')."['\"]\\)/", $contents) === 1) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<string>  $files
     */
    private function countContaining(array $files, string $needle): int
    {
        return $this->countMatching($files, $needle);
    }

    private function pathHasExtendsClientLayout(string $viewsRoot, string $relativePath): bool
    {
        $fullPath = $viewsRoot.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

        if (! is_dir($fullPath)) {
            return false;
        }

        foreach (File::allFiles($fullPath) as $file) {
            if (! str_ends_with($file->getPathname(), '.blade.php')) {
                continue;
            }

            $contents = $this->readFile($file->getPathname());
            if ($contents !== null && preg_match('/@extends\\(\\s*client_layout\\(/', $contents) === 1) {
                return true;
            }
        }

        return false;
    }

    private function pathHasClientLayoutInCodebase(string $basePath): bool
    {
        if (! is_dir($basePath)) {
            return false;
        }

        foreach (File::allFiles($basePath) as $file) {
            if (! str_ends_with($file->getPathname(), '.php')) {
                continue;
            }

            if ($this->fileContains($file->getPathname(), 'client_layout(')) {
                return true;
            }
        }

        return false;
    }

    private function fileContains(string $file, string $needle): bool
    {
        $contents = $this->readFile($file);

        return $contents !== null && str_contains($contents, $needle);
    }

    private function readFile(string $file): ?string
    {
        if (! is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        return is_string($contents) ? $contents : null;
    }

    /**
     * @return list<array{name: string, expected: string, actual: string, ok: bool}>
     */
    private function defaultSlugHttpChecks(string $clientSlug): array
    {
        return [
            $this->checkHttpStatus('root /', '200', '/'),
            $this->checkCanonicalAliasRedirect("/{$clientSlug}/home", '/'),
            $this->checkHttpStatus('/login', '200', '/login'),
            $this->checkCanonicalAliasRedirect("/{$clientSlug}/login", '/login'),
            $this->checkGuestRedirect('/admin', '/login'),
            $this->checkCanonicalAliasRedirect("/{$clientSlug}/admin", '/admin'),
            $this->checkGuestRedirect('/staff', '/login'),
            $this->checkCanonicalAliasRedirect("/{$clientSlug}/staff", '/staff'),
            $this->checkGuestRedirect('/agent', '/login'),
            $this->checkCanonicalAliasRedirect("/{$clientSlug}/agent", '/agent'),
            $this->checkGuestRedirect('/customer', '/login'),
            $this->checkCanonicalAliasRedirect("/{$clientSlug}/customer", '/customer'),
        ];
    }

    /**
     * @return list<array{name: string, expected: string, actual: string, ok: bool}>
     */
    private function prefixedClientHttpChecks(string $clientSlug): array
    {
        return [
            $this->checkHttpStatus('root /', '200', '/'),
            $this->checkHttpStatus("{$clientSlug}/home", '200', "/{$clientSlug}/home"),
            $this->checkHttpStatus('/login', '200', '/login'),
            $this->checkHttpStatus("{$clientSlug}/login", '200', "/{$clientSlug}/login"),
            $this->checkGuestRedirect('/admin', '/login'),
            $this->checkGuestRedirect("/{$clientSlug}/admin", "/{$clientSlug}/login"),
            $this->checkGuestRedirect('/staff', '/login'),
            $this->checkGuestRedirect("/{$clientSlug}/staff", "/{$clientSlug}/login"),
            $this->checkGuestRedirect('/agent', '/login'),
            $this->checkGuestRedirect("/{$clientSlug}/agent", "/{$clientSlug}/login"),
            $this->checkGuestRedirect('/customer', '/login'),
            $this->checkGuestRedirect("/{$clientSlug}/customer", "/{$clientSlug}/login"),
        ];
    }

    /**
     * @return array{name: string, expected: string, actual: string, ok: bool}
     */
    private function checkCanonicalAliasRedirect(string $path, string $expectedTarget): array
    {
        $response = $this->httpGet($path);
        $location = (string) ($response->headers->get('Location') ?? '');
        $actualPath = $this->redirectPathFromLocation($location);

        $normalizedExpected = $this->normalizeAuditPath($expectedTarget);
        $normalizedActual = $this->normalizeAuditPath($actualPath);

        return [
            'name' => "{$path} canonical alias redirect",
            'expected' => "redirect {$normalizedExpected}",
            'actual' => $normalizedActual,
            'ok' => $response->getStatusCode() === 302
                && $response->isRedirect()
                && $normalizedActual === $normalizedExpected,
        ];
    }

    private function redirectPathFromLocation(string $location): string
    {
        if ($location === '') {
            return '';
        }

        if (str_contains($location, '://')) {
            $parsedPath = parse_url($location, PHP_URL_PATH);

            return is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : '/';
        }

        return $location;
    }

    private function normalizeAuditPath(string $path): string
    {
        if (str_contains($path, '://')) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : '/';
        }

        return rtrim('/'.ltrim($path, '/'), '/') ?: '/';
    }

    /**
     * @return array{name: string, expected: string, actual: string, ok: bool}
     */
    private function checkHttpStatus(string $name, string $expected, string $path): array
    {
        $response = $this->httpGet($path);
        $actual = (string) $response->getStatusCode();

        return [
            'name' => $name,
            'expected' => $expected,
            'actual' => $actual,
            'ok' => $expected === $actual,
        ];
    }

    /**
     * @return array{name: string, expected: string, actual: string, ok: bool}
     */
    private function checkGuestRedirect(string $path, string $expectedLoginPath): array
    {
        $response = $this->httpGet($path);
        $location = $response->headers->get('Location') ?? '';
        $actual = $location !== '' ? (string) (parse_url($location, PHP_URL_PATH) ?? $location) : (string) $response->getStatusCode();

        return [
            'name' => "{$path} guest redirect",
            'expected' => "redirect {$expectedLoginPath}",
            'actual' => $actual,
            'ok' => $response->isRedirect()
                && is_string($actual)
                && (str_ends_with($actual, $expectedLoginPath) || $actual === $expectedLoginPath),
        ];
    }

    private function httpGet(string $path): Response
    {
        $kernel = app(Kernel::class);
        $request = Request::create($path, 'GET');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
