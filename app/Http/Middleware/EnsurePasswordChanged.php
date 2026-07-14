<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect authenticated users who must change password before accessing dashboards.
 */
class EnsurePasswordChanged
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        if (! ($user->must_change_password ?? false)) {
            return $next($request);
        }

        if ($request->routeIs(
            'password.force',
            'password.force.store',
            'password.update',
            'logout',
            'verification.*',
            'password.confirm',
            'password.confirm.store',
        )) {
            return $next($request);
        }

        return redirect()->route('password.force');
    }
}
