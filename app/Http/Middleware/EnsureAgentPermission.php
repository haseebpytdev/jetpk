<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgentPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAgentPortalUser()) {
            abort(403);
        }

        if (! $user->hasAgentPermission($permission)) {
            abort(403);
        }

        return $next($request);
    }
}
