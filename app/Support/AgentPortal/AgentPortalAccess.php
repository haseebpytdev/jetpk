<?php

namespace App\Support\AgentPortal;

use App\Enums\UserAccountStatus;
use App\Models\AgencyUser;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-authoritative agent portal session usability checks.
 */
final class AgentPortalAccess
{
    /**
     * @return array{ok: true, denial_reason: null}|array{ok: false, denial_reason: string, message: string}
     */
    public static function evaluate(User $user): array
    {
        if (! $user->isAgentPortalUser()) {
            return self::deny('permission_required', 'Agent portal access is required.');
        }

        if ($user->status === UserAccountStatus::Suspended) {
            return self::deny('staff_inactive', 'Your account is suspended.');
        }

        if ($user->status === UserAccountStatus::Inactive) {
            return self::deny('staff_inactive', 'Your account is inactive.');
        }

        $agent = $user->agent();
        if ($agent === null) {
            return self::deny('permission_required', 'No agency business profile is linked to this account.');
        }

        if (! $agent->is_active) {
            return self::deny('agency_inactive', 'This agency account is inactive.');
        }

        $agencyId = (int) $agent->agency_id;
        if ($agencyId <= 0) {
            return self::deny('agency_inactive', 'Agency context is missing.');
        }

        if ($user->current_agency_id !== null && (int) $user->current_agency_id !== $agencyId) {
            return self::deny('permission_required', 'Agency context does not match your business profile.');
        }

        if ($user->isAgentStaff()) {
            $ownerAgentId = (int) data_get($user->meta, 'owner_agent_id', 0);
            if ($ownerAgentId <= 0 || $ownerAgentId !== (int) $agent->id) {
                return self::deny('permission_required', 'Agency membership is no longer valid.');
            }

            $membershipExists = AgencyUser::query()
                ->where('agency_id', $agencyId)
                ->where('user_id', $user->id)
                ->exists();

            if (! $membershipExists) {
                return self::deny('permission_required', 'Agency membership has been removed.');
            }
        }

        return ['ok' => true, 'denial_reason' => null];
    }

    public static function assertUsable(User $user): void
    {
        $result = self::evaluate($user);
        if (($result['ok'] ?? false) === true) {
            return;
        }

        $status = match ($result['denial_reason'] ?? '') {
            'agency_inactive', 'staff_inactive' => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_FORBIDDEN,
        };

        $payload = [
            'ok' => false,
            'code' => $result['denial_reason'] ?? 'permission_required',
            'message' => $result['message'] ?? 'Agent portal access denied.',
        ];

        if (request()->wantsJson() || request()->query('format') === 'json') {
            throw new HttpResponseException(response()->json($payload, $status));
        }

        abort($status, $payload['message']);
    }

    /**
     * @return array{ok: false, denial_reason: string, message: string}
     */
    private static function deny(string $reason, string $message): array
    {
        return [
            'ok' => false,
            'denial_reason' => $reason,
            'message' => $message,
        ];
    }
}
