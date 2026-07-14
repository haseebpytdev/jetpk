<?php

namespace App\Services\Client;

use App\Support\Audits\ClientRouteParityClassifier;
use App\Support\Audits\ClientRouteParityRouteFilter;
use App\Support\Client\ReservedClientPreviewSlugs;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

/**
 * MC-7B dynamic client-prefixed GET/HEAD route parity registrar.
 *
 * Mirrors safe production routes under /{clientSlug}/{originalUri} using the
 * MC-7A classifier as source of truth. Does not register mutating or
 * high-risk routes.
 */
final class ClientPrefixedRouteRegistrar
{
    private const RISK_ORDER = ['low' => 0, 'medium' => 1, 'high' => 2];

    /**
     * @var array<string, true>
     */
    private array $registeredKeys = [];

    /**
     * @var array<string, int>
     */
    private array $byClassification = [];

    /**
     * @var array<string, int>
     */
    private array $byPortal = [];

    private int $collisionCount = 0;

    private int $excludedHighRiskCount = 0;

    public function __construct(
        private readonly ClientRouteParityClassifier $classifier = new ClientRouteParityClassifier,
        private readonly ClientRouteParityRouteFilter $routeFilter = new ClientRouteParityRouteFilter,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     total_registered: int,
     *     by_classification: array<string, int>,
     *     by_portal: array<string, int>,
     *     excluded_high_risk_count: int,
     *     collision_count: int
     * }
     */
    public function register(): array
    {
        if (! config('client_route_parity.enabled', true)) {
            return $this->emptyStats(false);
        }

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }

            if (! $this->routeFilter->isWebRoute($route)) {
                continue;
            }

            $this->registerRouteParity($route);
        }

        return [
            'enabled' => true,
            'total_registered' => count($this->registeredKeys),
            'by_classification' => $this->byClassification,
            'by_portal' => $this->byPortal,
            'excluded_high_risk_count' => $this->excludedHighRiskCount,
            'collision_count' => $this->collisionCount,
        ];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     total_registered: int,
     *     by_classification: array<string, int>,
     *     by_portal: array<string, int>,
     *     excluded_high_risk_count: int,
     *     collision_count: int
     * }
     */
    public function statsFromRegistry(): array
    {
        if (! config('client_route_parity.enabled', true)) {
            return $this->emptyStats(false);
        }

        $byClassification = [];
        $byPortal = [];
        $total = 0;

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }

            $name = (string) $route->getName();
            if (! str_starts_with($name, 'client.parity.')) {
                continue;
            }

            $total++;
            $classification = (string) ($route->getAction()['client_parity_classification'] ?? 'unknown');
            $portal = (string) ($route->getAction()['client_parity_portal'] ?? 'Other');
            $byClassification[$classification] = ($byClassification[$classification] ?? 0) + 1;
            $byPortal[$portal] = ($byPortal[$portal] ?? 0) + 1;
        }

        return [
            'enabled' => true,
            'total_registered' => $total,
            'by_classification' => $byClassification,
            'by_portal' => $byPortal,
            'excluded_high_risk_count' => $this->countExcludedHighRiskFromScan(),
            'collision_count' => 0,
        ];
    }

    private function registerRouteParity(Route $sourceRoute): void
    {
        $routeName = (string) $sourceRoute->getName();
        $uri = $sourceRoute->uri();
        $action = $this->routeFilter->resolveAction($sourceRoute);
        $middleware = $sourceRoute->gatherMiddleware();
        $methods = array_values(array_intersect(
            array_map('strtoupper', $sourceRoute->methods()),
            array_map('strtoupper', config('client_route_parity.allowed_methods', ['GET', 'HEAD'])),
        ));

        if ($methods === []) {
            return;
        }

        if ($this->isExcludedRouteName($routeName)) {
            return;
        }

        if ($this->isExcludedUri($uri)) {
            return;
        }

        if (str_starts_with($routeName, 'client.parity.')) {
            return;
        }

        foreach ($methods as $method) {
            if ($method === 'HEAD') {
                continue;
            }

            $classification = $this->classifier->classify(
                $routeName,
                $method,
                $uri,
                $action,
                $middleware,
            );

            if ($classification['should_have_client_prefix'] !== 'yes') {
                continue;
            }

            if (! $this->riskAllowed($classification['risk_level'])) {
                if ($classification['risk_level'] === 'high') {
                    $this->excludedHighRiskCount++;
                }

                continue;
            }

            if (! in_array($classification['classification'], config('client_route_parity.allowed_classifications', []), true)) {
                continue;
            }

            if ($routeName === 'home' && ltrim($uri, '/') === '') {
                $this->registerHomeAliasOnly($sourceRoute, $classification['classification']);

                continue;
            }

            $this->registerParityRoute(
                $sourceRoute,
                $method,
                $uri,
                $routeName,
                $classification['classification'],
            );
        }
    }

    private function registerParityRoute(
        Route $sourceRoute,
        string $method,
        string $uri,
        string $originalName,
        string $classification,
    ): void {
        $parityName = $this->parityRouteName($originalName, $uri, $method, $sourceRoute);
        $uriSuffix = $this->normalizeUriSuffix($uri);
        $collisionKey = strtoupper($method).'|'.$uriSuffix;

        if (isset($this->registeredKeys[$collisionKey]) || RouteFacade::has($parityName)) {
            $this->collisionCount++;

            return;
        }

        $middleware = $this->parityMiddleware($sourceRoute->gatherMiddleware());
        $action = $sourceRoute->getAction();
        unset($action['as'], $action['prefix'], $action['middleware']);
        $action['client_parity_classification'] = $classification;
        $action['client_parity_portal'] = $this->resolvePortal($originalName, $classification);

        $registrar = RouteFacade::middleware($middleware)
            ->prefix('{clientSlug}')
            ->where(array_merge(
                ['clientSlug' => ReservedClientPreviewSlugs::routeParameterConstraint()],
                $sourceRoute->wheres,
            ));

        $registered = match (strtoupper($method)) {
            'GET' => $registrar->get($uriSuffix, $action),
            'HEAD' => $registrar->match(['HEAD'], $uriSuffix, $action),
            default => null,
        };

        if ($registered === null) {
            return;
        }

        $registered->name($parityName);
        $this->registeredKeys[$collisionKey] = true;
        $this->byClassification[$classification] = ($this->byClassification[$classification] ?? 0) + 1;
        $portal = $action['client_parity_portal'];
        $this->byPortal[$portal] = ($this->byPortal[$portal] ?? 0) + 1;
    }

    private function registerHomeAliasOnly(Route $sourceRoute, string $classification): void
    {
        $middleware = $this->parityMiddleware($sourceRoute->gatherMiddleware());
        $action = $sourceRoute->getAction();
        unset($action['as'], $action['prefix'], $action['middleware']);
        $action['client_parity_classification'] = $classification;
        $action['client_parity_portal'] = $this->resolvePortal('home', $classification);

        $this->registerHomeAlias(
            $sourceRoute,
            $middleware,
            $action,
            $classification,
            $action['client_parity_portal'],
        );
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function registerHomeAlias(
        Route $sourceRoute,
        array $middleware,
        array $action,
        string $classification,
        string $portal,
    ): void {
        $aliasKey = 'GET|home';
        if (isset($this->registeredKeys[$aliasKey])) {
            return;
        }

        $aliasName = 'client.parity.home.alias';
        if (RouteFacade::has($aliasName)) {
            $this->collisionCount++;

            return;
        }

        RouteFacade::middleware($middleware)
            ->prefix('{clientSlug}')
            ->where(array_merge(
                ['clientSlug' => ReservedClientPreviewSlugs::routeParameterConstraint()],
                $sourceRoute->wheres,
            ))
            ->get('home', $action)
            ->name($aliasName);

        $this->registeredKeys[$aliasKey] = true;
        $this->byClassification[$classification] = ($this->byClassification[$classification] ?? 0) + 1;
        $this->byPortal[$portal] = ($this->byPortal[$portal] ?? 0) + 1;
    }

    /**
     * @param  list<string>  $originalMiddleware
     * @return list<string>
     */
    private function parityMiddleware(array $originalMiddleware): array
    {
        $filtered = array_values(array_filter(
            $originalMiddleware,
            static fn (string $middleware): bool => $middleware !== 'web',
        ));

        return array_values(array_unique(array_merge(['web', 'preview.client', 'preview.client.persist'], $filtered)));
    }

    private function parityRouteName(string $originalName, string $uri, string $method, Route $sourceRoute): string
    {
        if ($originalName !== '') {
            return 'client.parity.'.$originalName;
        }

        $hash = md5($uri.'|'.strtoupper($method).'|'.$this->routeFilter->resolveAction($sourceRoute));

        return 'client.parity.generated.'.$hash;
    }

    private function normalizeUriSuffix(string $uri): string
    {
        return ltrim($uri, '/');
    }

    private function isExcludedRouteName(string $routeName): bool
    {
        if ($routeName === '') {
            return false;
        }

        foreach (config('client_route_parity.excluded_route_names', []) as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    private function isExcludedUri(string $uri): bool
    {
        $normalized = strtolower(ltrim($uri, '/'));

        foreach (config('client_route_parity.excluded_uri_prefixes', []) as $prefix) {
            $prefixLower = strtolower(ltrim($prefix, '/'));
            if ($normalized === $prefixLower || str_starts_with($normalized, $prefixLower.'/')) {
                return true;
            }
        }

        return false;
    }

    private function riskAllowed(string $riskLevel): bool
    {
        $maxRisk = (string) config('client_route_parity.max_risk', 'low');
        $maxOrder = self::RISK_ORDER[$maxRisk] ?? 0;
        $riskOrder = self::RISK_ORDER[$riskLevel] ?? 99;

        return $riskOrder <= $maxOrder;
    }

    private function resolvePortal(string $routeName, string $classification): string
    {
        if (str_starts_with($routeName, 'admin.')) {
            return 'Admin';
        }

        if (str_starts_with($routeName, 'agent.')) {
            return 'Agent';
        }

        if (str_starts_with($routeName, 'staff.')) {
            return 'Staff';
        }

        if (str_starts_with($routeName, 'customer.') || $routeName === 'dashboard') {
            return 'Customer';
        }

        return match ($classification) {
            'auth_page' => 'Auth',
            'group_ticketing' => 'Groups',
            'booking_flow' => 'Booking',
            'admin_dashboard' => 'Admin',
            'agent_dashboard' => 'Agent',
            'staff_dashboard' => 'Staff',
            'customer_dashboard' => 'Customer',
            default => 'Public',
        };
    }

    private function countExcludedHighRiskFromScan(): int
    {
        $count = 0;

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! $route instanceof Route || ! $this->routeFilter->isWebRoute($route)) {
                continue;
            }

            $routeName = (string) $route->getName();
            if ($this->isExcludedRouteName($routeName) || str_starts_with($routeName, 'client.parity.')) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $classification = $this->classifier->classify(
                    $routeName,
                    $method,
                    $route->uri(),
                    $this->routeFilter->resolveAction($route),
                    $route->gatherMiddleware(),
                );

                if ($classification['should_have_client_prefix'] === 'yes'
                    && $classification['risk_level'] === 'high') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     total_registered: int,
     *     by_classification: array<string, int>,
     *     by_portal: array<string, int>,
     *     excluded_high_risk_count: int,
     *     collision_count: int
     * }
     */
    private function emptyStats(bool $enabled): array
    {
        return [
            'enabled' => $enabled,
            'total_registered' => 0,
            'by_classification' => [],
            'by_portal' => [],
            'excluded_high_risk_count' => 0,
            'collision_count' => 0,
        ];
    }
}
