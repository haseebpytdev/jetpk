<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isStaff()) {
            abort(403);
        }

        if (! $user->hasStaffPermission($permission)) {
            abort(403);
        }

        return $next($request);
    }
}
