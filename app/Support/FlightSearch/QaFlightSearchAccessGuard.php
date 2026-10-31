<?php

namespace App\Support\FlightSearch;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Enforces that QA-scoped flight search payloads are only readable by the authorized QA actor.
 */
final class QaFlightSearchAccessGuard
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function assertCanAccess(?array $payload, Request $request, bool $hideExistence = true): void
    {
        if ($payload === null || ! QaStaffAirBlueContext::payloadIsQaScope($payload)) {
            return;
        }

        $user = $request->user();
        if (! $user instanceof User || ! QaStaffAirBlueContext::actorIsAllowed($user)) {
            throw new HttpException($hideExistence ? 404 : 403, $hideExistence ? 'Not found.' : 'Forbidden.');
        }

        $actorId = (int) ($payload['qa_actor_user_id']
            ?? data_get($payload, 'criteria_cache_context.qa_actor_user_id')
            ?? 0);

        // Prefer binding to creating actor when recorded; otherwise any allowed QA role may continue.
        if ($actorId > 0 && (int) $user->id !== $actorId) {
            throw new HttpException($hideExistence ? 404 : 403, $hideExistence ? 'Not found.' : 'Forbidden.');
        }

        if (! QaStaffAirBlueContext::isActive($request)) {
            // Session must still be QA-active for transitions (prevents leaked UUID use after logout).
            throw new HttpException($hideExistence ? 404 : 403, $hideExistence ? 'Not found.' : 'Forbidden.');
        }
    }

    public function assertCanInitiate(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User || ! QaStaffAirBlueContext::actorIsAllowed($user)) {
            throw new HttpException(403, 'AirBlue QA search requires platform admin or staff.');
        }

        return $user;
    }
}
