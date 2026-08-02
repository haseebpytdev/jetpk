<?php

namespace App\Http\Middleware;

use App\Enums\AccountType;
use App\Support\AgentPortal\AgentPortalAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgencyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->account_type === AccountType::PlatformAdmin || $user->account_type === AccountType::Customer) {
            return $next($request);
        }

        if ($user->current_agency_id !== null) {
            if ($user->isAgentPortalUser()) {
                AgentPortalAccess::assertUsable($user);
            }

            return $next($request);
        }

        $agency = $user->agencies()->orderBy('agencies.id')->first();

        if ($agency === null) {
            abort(403, 'No agency context assigned.');
        }

        $user->forceFill(['current_agency_id' => $agency->id])->save();

        if ($user->isAgentPortalUser()) {
            AgentPortalAccess::assertUsable($user);
        }

        return $next($request);
    }
}
