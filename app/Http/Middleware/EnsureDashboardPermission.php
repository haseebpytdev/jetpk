<?php

namespace App\Http\Middleware;

use App\Support\Dashboard\DashboardPermissionResolver;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardPermission
{
    /**
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        DashboardPermissionResolver::assertPermission($user, $permission);

        return $next($request);
    }
}
