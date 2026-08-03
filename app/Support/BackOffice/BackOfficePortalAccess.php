<?php

namespace App\Support\BackOffice;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-authoritative Admin / Platform Staff dashboard session usability checks.
 */
final class BackOfficePortalAccess
{
    /**
     * @return array{ok: true, denial_reason: null}|array{ok: false, denial_reason: string, message: string}
     */
    public static function evaluate(User $user): array
    {
        if ($user->isCustomer() || $user->isAgentPortalUser()) {
            return self::deny('permission_required', 'Back-office access is required.');
        }

        if (! $user->isPlatformAdmin() && ! $user->isStaff()) {
            return self::deny('invalid_platform_role', 'This account cannot access the back-office dashboard.');
        }

        if ($user->status === UserAccountStatus::Suspended) {
            return self::deny('account_inactive', 'Your account is suspended.');
        }

        if ($user->status === UserAccountStatus::Inactive) {
            return self::deny('account_inactive', 'Your account is inactive.');
        }

        return ['ok' => true, 'denial_reason' => null];
    }

    public static function assertUsable(User $user): void
    {
        $result = self::evaluate($user);
        if (($result['ok'] ?? false) === true) {
            return;
        }

        $payload = [
            'ok' => false,
            'code' => $result['denial_reason'] ?? 'permission_required',
            'message' => $result['message'] ?? 'Back-office access denied.',
        ];

        if (request()->wantsJson() || request()->query('format') === 'json') {
            throw new HttpResponseException(response()->json($payload, Response::HTTP_FORBIDDEN));
        }

        abort(Response::HTTP_FORBIDDEN, $payload['message']);
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
