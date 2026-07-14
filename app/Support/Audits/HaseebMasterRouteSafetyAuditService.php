<?php

namespace App\Support\Audits;

use App\Services\Client\ClientProfileResolver;
use App\Support\Client\ReservedClientPreviewSlugs;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Read-only MC-5C route safety audit for the default haseeb-master deployment.
 *
 * No supplier HTTP, no DB writes, no mutating POST.
 */
final class HaseebMasterRouteSafetyAuditService
{
    /**
     * @return list<array{name: string, method: string, uri: string, status: string, notes: string}>
     */
    public function run(string $clientSlug): array
    {
        $rows = [];

        foreach (HaseebMasterRouteSafetyCatalog::requiredProductionRoutes() as $check) {
            $rows[] = $this->auditNamedRoute(
                $check['route'],
                $check['method'],
                $check['expected_uri'],
                $check['notes'],
                $clientSlug,
            );
        }

        foreach (HaseebMasterRouteSafetyCatalog::requiredPreviewRoutes() as $check) {
            $rows[] = $this->auditNamedRoute(
                $check['route'],
                $check['method'],
                $check['expected_uri'],
                $check['notes'],
                $clientSlug,
                allowParametricUri: true,
            );
        }

        foreach (HaseebMasterRouteSafetyCatalog::defaultSlugChecks($clientSlug) as $check) {
            $rows[] = $this->auditDefaultSlugBehavior($check, $clientSlug);
        }

        foreach (HaseebMasterRouteSafetyCatalog::productionRouteMatchChecks() as $check) {
            $rows[] = $this->auditProductionRouteMatch($check);
        }

        foreach (HaseebMasterRouteSafetyCatalog::reservedSlugCollisionChecks() as $check) {
            $rows[] = $this->auditReservedSlugPath($check);
        }

        foreach (HaseebMasterRouteSafetyCatalog::staticAssetPathChecks() as $check) {
            $rows[] = $this->auditStaticAssetPath($check);
        }

        foreach (HaseebMasterRouteSafetyCatalog::dashboardRouteInventoryChecks() as $check) {
            $rows[] = $this->auditRouteInventory($check);
        }

        $rows[] = $this->auditDefaultDeploymentSlug($clientSlug);

        return $rows;
    }

    /**
     * @return array{name: string, method: string, uri: string, status: string, notes: string}
     */
    private function auditNamedRoute(
        string $routeName,
        string $method,
        string $expectedUri,
        string $notes,
        string $clientSlug,
        bool $allowParametricUri = false,
    ): array {
        if (! RouteFacade::has($routeName)) {
            return $this->row($routeName, $method, $expectedUri, 'missing', $notes.' — route not registered');
        }

        $route = RouteFacade::getRoutes()->getByName($routeName);
        if (! $route instanceof Route) {
            return $this->row($routeName, $method, $expectedUri, 'missing', $notes.' — route name not resolved');
        }

        $actualUri = '/'.$route->uri();
        if ($actualUri === '//') {
            $actualUri = '/';
        }

        if (! $allowParametricUri && $actualUri !== $expectedUri) {
            return $this->row(
                $routeName,
                $method,
                $actualUri,
                'collision-risk',
                $notes.' — expected URI '.$expectedUri,
            );
        }

        if ($this->uriStartsWithClientPrefix($actualUri, $clientSlug)) {
            return $this->row(
                $routeName,
                $method,
                $actualUri,
                'collision-risk',
                $notes.' — production route uses default client prefix',
            );
        }

        return $this->row($routeName, $method, $actualUri, 'OK', $notes);
    }

    /**
     * @param  array{route: string, method: string, uri: string, expected_target?: string, parity_route?: string, notes: string}  $check
     * @return array{name: string, method: string, uri: string, status: string, notes: string}
     */
    private function auditDefaultSlugBehavior(array $check, string $clientSlug): array
    {
        return $this->auditDefaultSlugRedirect($check, $clientSlug);
    }

    /**
     * @param  array{route: string, method: string, uri: string, parity_route: string, notes: string}  $check
     * @return array{name: string, method: string, uri: string, status: string, notes: string}
     */
    private function auditDefaultSlugParity(array $check, string $clientSlug): array
    {
        $resolver = app(ClientProfileResolver::class);
        if (! $resolver->isDefaultDeploymentSlug($clientSlug)) {
            return $this->row(
                $check['route'],
                $check['method'],
                $check['uri'],
                'OK',
                $check['notes'].' — skipped parity probe for non-default --client slug',
            );
        }

        if ($resolver->resolveDefault() === null) {
            return $this->row(
                $check['route'],
                $check['method'],
                $check['uri'],
                'OK',
                $check['notes'].' — skipped parity HTTP probe (no active default ClientProfile in DB)',
            );
        }

        if (! isset($check['parity_route'])) {
            return $this->row(
                $check['route'],
                $check['method'],
                $check['uri'],
                'OK',
                $check['notes'].' — legacy redirect check skipped in parity mode',
            );
        }

        try {
            $status = $this->dispatchGetStatus($check['uri']);
            $location = $this->dispatchGetRedirectTarget($check['uri']);
        } catch (Throwable $e) {
            return $this->row(
                $check['route'],
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — dispatch failed: '.$e->getMessage(),
            );
        }

        if ($check['route'] === 'client.preview.root') {
            if ($status !== 302 || $location === null) {
                return $this->row(
                    $check['route'],
                    $check['method'],
                    $check['uri'],
                    'collision-risk',
                    $check['notes'].' — expected redirect to prefixed home, got HTTP '.$status,
                );
            }

            $expectedHome = $this->normalizePath('/'.$clientSlug.'/home');
            if ($this->normalizePath($location) !== $expectedHome) {
                return $this->row(
                    $check['route'],
                    $check['method'],
                    $check['uri'],
                    'collision-risk',
                    $check['notes'].' — redirect target '.$this->normalizePath($location).' expected '.$expectedHome,
                );
            }

            return $this->row($check['route'], $check['method'], $check['uri'], 'OK', $check['notes'].' → '.$expectedHome);
        }

        if ($status === 302 && $location !== null && $this->normalizePath($location) === $this->normalizePath('/login')) {
            if ($check['parity_route'] === 'client.parity.admin.dashboard') {
                return $this->row(
                    $check['parity_route'],
                    $check['method'],
                    $check['uri'],
                    'OK',
                    $check['notes'].' — auth redirect to login (expected when guest)',
                );
            }
        }

        if ($status >= 500) {
            return $this->row(
                $check['parity_route'],
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — server error HTTP '.$status,
            );
        }

        if ($status === 404) {
            return $this->row(
                $check['parity_route'],
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — parity route not found (404)',
            );
        }

        if (! in_array($status, [200, 302, 301, 303, 307, 308], true)) {
            return $this->row(
                $check['parity_route'],
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — unexpected HTTP '.$status,
            );
        }

        if ($status === 302 && $location !== null && str_starts_with($this->normalizePath($location), '/login')) {
            return $this->row(
                $check['parity_route'],
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — unexpected redirect to production login',
            );
        }

        return $this->row(
            $check['parity_route'],
            $check['method'],
            $check['uri'],
            'OK',
            $check['notes'].' — parity route responded HTTP '.$status,
        );
    }

    /**
     * @param  array{route: string, method: string, uri: string, expected_target: string, notes: string}  $check
     * @return array{name: string, method: string, uri: string, status: string, notes: string}
     */
    private function auditDefaultSlugRedirect(array $check, string $clientSlug): array
    {
        $resolver = app(ClientProfileResolver::class);
        if (! $resolver->isDefaultDeploymentSlug($clientSlug)) {
            return $this->row(
                $check['route'],
                $check['method'],
                $check['uri'],
                'OK',
                $check['notes'].' — skipped redirect probe for non-default --client slug',
            );
        }

        try {
            $status = $this->dispatchGetStatus($check['uri']);
            $location = $this->dispatchGetRedirectTarget($check['uri']);
        } catch (Throwable $e) {
            return $this->row(
                $check['route'],
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — dispatch failed: '.$e->getMessage(),
            );
        }

        if ($status !== 302 || $location === null) {
            return $this->row(
                $check['route'],
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — expected 302 redirect, got HTTP '.$status,
            );
        }

        $normalizedLocation = $this->normalizePath($location);
        $normalizedTarget = $this->normalizePath($check['expected_target']);

        if ($normalizedLocation !== $normalizedTarget) {
            return $this->row(
                $check['route'],
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — redirect target '.$normalizedLocation.' expected '.$normalizedTarget,
            );
        }

        return $this->row($check['route'], $check['method'], $check['uri'], 'OK', $check['notes'].' → '.$normalizedTarget);
    }

    /**
     * @param  array{route: string, method: string, uri: string, expected_route_prefix: string, notes: string}  $check
     * @return array{name: string, method: string, uri: string, status: string, notes: string}
     */
    private function auditProductionRouteMatch(array $check): array
    {
        try {
            $matched = RouteFacade::getRoutes()->match(Request::create($check['uri'], $check['method']));
        } catch (NotFoundHttpException) {
            return $this->row(
                $check['route'],
                $check['method'],
                $check['uri'],
                'missing',
                $check['notes'].' — no route matched',
            );
        }

        $matchedName = (string) $matched->getName();
        if (str_starts_with($matchedName, 'client.preview.')) {
            return $this->row(
                $check['route'],
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — matched preview route '.$matchedName,
            );
        }

        $expectedPrefix = $check['expected_route_prefix'];
        if ($expectedPrefix !== '' && ! str_starts_with($matchedName, $expectedPrefix) && $matchedName !== $expectedPrefix) {
            return $this->row(
                $matchedName !== '' ? $matchedName : $check['route'],
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — matched '.$matchedName,
            );
        }

        return $this->row(
            $matchedName !== '' ? $matchedName : $check['route'],
            $check['method'],
            $check['uri'],
            'OK',
            $check['notes'].' — matched '.$matchedName,
        );
    }

    /**
     * @param  array{route: string, method: string, uri: string, notes: string}  $check
     * @return array{name: string, method: string, uri: string, status: string, notes: string}
     */
    private function auditReservedSlugPath(array $check): array
    {
        try {
            $status = $this->dispatchGetStatus($check['uri']);
        } catch (Throwable $e) {
            return $this->row(
                '-',
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — dispatch failed: '.$e->getMessage(),
            );
        }

        if ($status === 404) {
            return $this->row('-', $check['method'], $check['uri'], 'OK', $check['notes'].' — HTTP 404 (not a preview client path)');
        }

        if ($status >= 500) {
            return $this->row(
                '-',
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — HTTP '.$status.' (500-risk)',
            );
        }

        return $this->row(
            '-',
            $check['method'],
            $check['uri'],
            'collision-risk',
            $check['notes'].' — reserved path responded HTTP '.$status.' instead of 404',
        );
    }

    /**
     * @param  array{route: string, method: string, uri: string, notes: string}  $check
     * @return array{name: string, method: string, uri: string, status: string, notes: string}
     */
    private function auditStaticAssetPath(array $check): array
    {
        $firstSegment = trim(explode('/', trim($check['uri'], '/'))[0] ?? '', '/');
        if ($firstSegment === '') {
            return $this->row('-', $check['method'], $check['uri'], 'collision-risk', $check['notes'].' — empty path segment');
        }

        if (! ReservedClientPreviewSlugs::isReserved($firstSegment)) {
            return $this->row(
                '-',
                $check['method'],
                $check['uri'],
                'collision-risk',
                $check['notes'].' — first segment `'.$firstSegment.'` is not in reserved slug list',
            );
        }

        try {
            $matched = RouteFacade::getRoutes()->match(Request::create($check['uri'], $check['method']));
            $matchedName = (string) $matched->getName();
        } catch (NotFoundHttpException) {
            return $this->row('-', $check['method'], $check['uri'], 'OK', $check['notes'].' — no Laravel route (static/web-server path)');
        }

        if (str_starts_with($matchedName, 'client.preview.')) {
            return $this->row('-', $check['method'], $check['uri'], 'collision-risk', $check['notes'].' — static path captured by preview route');
        }

        return $this->row('-', $check['method'], $check['uri'], 'OK', $check['notes'].' — Laravel route '.$matchedName.' (not preview)');
    }

    /**
     * @param  array{route: string, method: string, uri: string, prefix: string, minimum: int, notes: string}  $check
     * @return array{name: string, method: string, uri: string, status: string, notes: string}
     */
    private function auditRouteInventory(array $check): array
    {
        $count = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => str_starts_with((string) $route->getName(), $check['prefix']))
            ->count();

        if ($count < $check['minimum']) {
            return $this->row(
                $check['route'],
                $check['method'],
                $check['uri'],
                'missing',
                $check['notes'].' — only '.$count.' routes with prefix '.$check['prefix'],
            );
        }

        return $this->row(
            $check['route'],
            $check['method'],
            $check['uri'],
            'OK',
            $check['notes'].' — '.$count.' named routes',
        );
    }

    /**
     * @return array{name: string, method: string, uri: string, status: string, notes: string}
     */
    private function auditDefaultDeploymentSlug(string $clientSlug): array
    {
        $resolver = app(ClientProfileResolver::class);
        $defaultSlug = $resolver->defaultDeploymentSlug();

        if ($clientSlug !== HaseebMasterRouteSafetyCatalog::DEFAULT_CLIENT_SLUG) {
            return $this->row(
                'ota_client.slug',
                '-',
                '-',
                'OK',
                'Audit client '.$clientSlug.' (default deployment slug is '.$defaultSlug.')',
            );
        }

        if ($defaultSlug !== HaseebMasterRouteSafetyCatalog::DEFAULT_CLIENT_SLUG) {
            return $this->row(
                'ota_client.slug',
                '-',
                '-',
                'collision-risk',
                'Configured default slug is '.$defaultSlug.'; audit expects haseeb-master',
            );
        }

        return $this->row(
            'ota_client.slug',
            '-',
            '-',
            'OK',
            'Default deployment slug resolves to haseeb-master',
        );
    }

    private function uriStartsWithClientPrefix(string $uri, string $clientSlug): bool
    {
        $prefix = '/'.$clientSlug;

        return $uri === $prefix || str_starts_with($uri, $prefix.'/');
    }

    private function dispatchGetStatus(string $uri): int
    {
        /** @var Kernel $kernel */
        $kernel = app(Kernel::class);

        $request = Request::create($uri, 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_HOST' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ]);

        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $kernel->terminate($request, $response);

        return $status;
    }

    private function dispatchGetRedirectTarget(string $uri): ?string
    {
        /** @var Kernel $kernel */
        $kernel = app(Kernel::class);

        $request = Request::create($uri, 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_HOST' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ]);

        $response = $kernel->handle($request);
        $target = $response->isRedirect() ? $response->headers->get('Location') : null;
        $kernel->terminate($request, $response);

        return is_string($target) ? $target : null;
    }

    private function normalizePath(string $path): string
    {
        if (str_contains($path, '://')) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : '/';
        }

        $path = '/'.ltrim($path, '/');

        return rtrim($path, '/') ?: '/';
    }

    /**
     * @return array{name: string, method: string, uri: string, status: string, notes: string}
     */
    private function row(string $name, string $method, string $uri, string $status, string $notes): array
    {
        return [
            'name' => $name,
            'method' => $method,
            'uri' => $uri,
            'status' => $status,
            'notes' => $notes,
        ];
    }
}
