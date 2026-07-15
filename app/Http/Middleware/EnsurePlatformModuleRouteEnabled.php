<?php

namespace App\Http\Middleware;

use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Platform\PlatformModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks direct access to routes when a platform module is planned off (Sprint 8J).
 */
class EnsurePlatformModuleRouteEnabled
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $module = PlatformModuleRegistry::find($moduleKey);
        if ($module === null) {
            abort(404);
        }

        if (app(PlatformModuleEnforcer::class)->routeEnabled($moduleKey)) {
            return $next($request);
        }

        if ($request->expectsJson() || ! $request->isMethodSafe()) {
            return response()->json([
                'message' => 'This module is disabled for this deployment.',
            ], 403);
        }

        $moduleLabel = null;
        if ($request->user() !== null) {
            $moduleLabel = $module->label;
        }

        return response()->view('errors.module-disabled', [
            'moduleLabel' => $moduleLabel,
            'showSupportLink' => app(PlatformModuleEnforcer::class)->routeEnabled('support_system'),
        ], 403);
    }
}
