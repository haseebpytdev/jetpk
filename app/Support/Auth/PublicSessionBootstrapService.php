<?php

namespace App\Support\Auth;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
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
        private readonly AuthPostLoginRedirectResolver $postLoginRedirectResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $base = [
            'csrf_ready' => $request->hasSession(),
            'logout' => [
                'method' => 'POST',
                'path' => '/logout',
            ],
        ];

        if ($this->loginOtpService->hasPending($request)) {
            return array_merge($base, [
                'authenticated' => false,
                'requires_otp' => true,
                'otp_challenge' => [
                    'masked_email' => $this->loginOtpService->maskedEmail($request),
                    'resend_available_in' => $this->loginOtpService->resendAvailableIn($request),
                ],
            ]);
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return array_merge($base, [
                'authenticated' => false,
            ]);
        }

        return array_merge($base, $this->forAuthenticatedUser($user, $request));
    }

    /**
     * @return array<string, mixed>
     */
    public function forAuthenticatedUser(User $user, ?Request $request = null): array
    {
        $accountType = $user->account_type?->value;
        $landingRoute = $this->postLoginRedirectResolver->resolvePath($user, $request);
        $dashboardUrl = PublicAuthRedirectAllowlist::sanitize(
            $this->clientRedirectResolver->dashboardPathForUser($user),
            '/',
        );
        $accountStatus = $user->status?->value ?? UserAccountStatus::Active->value;
        $sessionUsable = ! in_array($user->status, [UserAccountStatus::Suspended, UserAccountStatus::Inactive], true);

        return [
            'authenticated' => true,
            'user' => [
                'id' => (string) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'account_type' => $accountType,
            ],
            'role' => $accountType,
            'portal_type' => $this->resolvePortalType($user),
            'agency_id' => $user->current_agency_id !== null ? (string) $user->current_agency_id : null,
            'agency_role' => $this->resolveAgencyRole($user),
            'permissions' => $this->resolvePermissions($user),
            'dashboard_url' => $dashboardUrl,
            'landing_route' => $landingRoute,
            'requires_otp' => false,
            'requires_password_change' => (bool) ($user->must_change_password ?? false),
            'requires_email_verification' => $user->isCustomer() && ! $user->hasVerifiedEmail(),
            'account_status' => $accountStatus,
            'email_verified' => $user->hasVerifiedEmail(),
            'session_usable' => $sessionUsable,
        ];
    }

    private function resolvePortalType(User $user): string
    {
        return match ($user->account_type) {
            AccountType::Customer => 'customer',
            AccountType::Agent, AccountType::AgentStaff => 'agent',
            AccountType::PlatformAdmin => 'admin',
            AccountType::Staff => 'staff',
            AccountType::AgencyAdmin => 'agency_admin',
            default => 'none',
        };
    }

    private function resolveAgencyRole(User $user): ?string
    {
        if ($user->isAgent()) {
            return 'owner';
        }

        if ($user->isAgentStaff()) {
            return 'staff';
        }

        return null;
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
