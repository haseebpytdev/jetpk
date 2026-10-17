<?php

namespace App\Http\Middleware;

use App\Support\GuestBooking\LegacyGuestTokenUsageTelemetry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Emits privacy-safe legacy guest-token route telemetry (OPS-DEF-0011).
 *
 * Attach only to named legacy routes. Classifies outcome from HTTP status only;
 * never reads URI, query, body, headers, session, or route parameters.
 */
class LogLegacyGuestTokenUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $routeName = $request->route()?->getName();
            $category = LegacyGuestTokenUsageTelemetry::categoryForRouteName($routeName);
            if ($category === null) {
                return $response;
            }

            LegacyGuestTokenUsageTelemetry::record(
                $category,
                LegacyGuestTokenUsageTelemetry::outcomeFromStatus($response->getStatusCode()),
                $request->getMethod(),
            );
        } catch (Throwable) {
            // Telemetry must never break the guest booking flow.
        }

        return $response;
    }
}
