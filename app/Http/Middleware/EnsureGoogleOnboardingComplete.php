<?php

namespace App\Http\Middleware;

use App\Support\Auth\GoogleOnboarding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGoogleOnboardingComplete
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->isCustomer()) {
            return $next($request);
        }

        if (! GoogleOnboarding::sessionRequiresCompletion($request)) {
            return $next($request);
        }

        if ($request->routeIs('auth.google.complete-profile', 'auth.google.complete-profile.store', 'logout')) {
            return $next($request);
        }

        return redirect()->route('auth.google.complete-profile');
    }
}
