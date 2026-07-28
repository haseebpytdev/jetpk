<?php

namespace App\Support\Auth;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Auth\LoginOtpService;
use App\Services\Client\ClientRedirectResolver;
use App\Support\Agents\AgentPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Authoritative session bootstrap payload for the JetPakistan Next.js frontend.
 */
final class PublicSessionBootstrapService
{
    public function __construct(
        private readonly ClientRedirectResolver $clientRedirectResolver,
        private readonly LoginOtpService $loginOtpService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        if ($this->loginOtpService->hasPending($request)) {
            return [
                'authenticated' => false,
                'requires_otp' => true,
                'otp_challenge' => [
                    'masked_email' => $this->loginOtpService->maskedEmail($request),
                    'resend_available_in' => $this->loginOtpService->resendAvailableIn($request),
                ],
            ];
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return [
                'authenticated' => false,
            ];
        }

        return $this->forAuthenticatedUser($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function forAuthenticatedUser(User $user): array
    {
        $accountType = $user->account_type?->value;
        $dashboardUrl = PublicAuthRedirectAllowlist::sanitize(
            $this->clientRedirectResolver->dashboardPathForUser($user),
            '/',
        );

        return [
            'authenticated' => true,
            'user' => [
                'id' => (string) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'account_type' => $accountType,
            ],
            'role' => $accountType,
            'permissions' => $this->resolvePermissions($user),
            'dashboard_url' => $dashboardUrl,
            'requires_otp' => false,
            'requires_password_change' => (bool) ($user->must_change_password ?? false),
            'requires_email_verification' => $user->isCustomer() && ! $user->hasVerifiedEmail(),
            'account_status' => $user->status?->value ?? 'active',
        ];
    }

    /**
     * @return list<string>
     */
    private function resolvePermissions(User $user): array
    {
        if ($user->isStaff()) {
            return $user->staffPermissions();
        }

        if ($user->isAgent() || $user->isAgentStaff()) {
            return array_values(array_filter(
                AgentPermission::all(),
                fn (string $permission): bool => $user->hasAgentPermission($permission),
            ));
        }

        return [];
    }
}
