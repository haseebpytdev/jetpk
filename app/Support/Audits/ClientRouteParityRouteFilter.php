<?php

namespace App\Support\Audits;

use Illuminate\Routing\Route;

/**
 * Shared web-route eligibility checks for MC-7A audit and MC-7B registrar.
 */
final class ClientRouteParityRouteFilter
{
    public function isWebRoute(Route $route): bool
    {
        $uri = $route->uri();
        $routeName = (string) $route->getName();

        if ($uri === '') {
            return $routeName === 'home';
        }

        $action = $route->getActionName();

        if (str_starts_with($action, 'Illuminate\\Routing\\ViewController')) {
            return false;
        }

        return true;
    }

    public function resolveAction(Route $route): string
    {
        $action = $route->getActionName();

        return $action === 'Closure' ? 'Closure' : $action;
    }
}
