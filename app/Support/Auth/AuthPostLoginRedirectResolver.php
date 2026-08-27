<?php

namespace App\Support\Auth;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Client\ClientRedirectResolver;
use App\Support\Auth\CheckoutReturnIntent;
use Illuminate\Http\Request;

/**
 * Canonical post-login redirect precedence for all JetPakistan auth surfaces.
 *
 * Precedence:
 * 1. Disabled/suspended account (caller handles denial before redirect)
 * 2. Pending OTP (handled upstream by LoginOtpService)
 * 3. Must change password
 * 4. Customer email verification required
 * 5. Role landing dashboard
 */
final class AuthPostLoginRedirectResolver
{
    public function __construct(
        private readonly ClientRedirectResolver $clientRedirectResolver,
    ) {}

    public function resolvePath(User $user, ?Request $request = null): string
    {
        if ($user->must_change_password ?? false) {
            return PublicAuthRedirectAllowlist::sanitize(
                $this->clientRedirectResolver->pathForRoute('password.force'),
                '/password/force-change',
            );
        }

        if ($user->account_type === AccountType::Customer && ! $user->hasVerifiedEmail()) {
            if ($request !== null && $this->hasIntendedEmailVerificationUrl($request)) {
                return PublicAuthRedirectAllowlist::sanitize(
                    $this->clientRedirectResolver->pathForRoute('verification.notice'),
                    '/verify-email',
                );
            }

            return PublicAuthRedirectAllowlist::sanitize(
                $this->clientRedirectResolver->pathForRoute('verification.notice'),
                '/verify-email',
            );
        }

        if ($request !== null) {
            $checkoutResume = $this->safeCheckoutResumePath($request);
            if ($checkoutResume !== null) {
                return $checkoutResume;
            }
        }

        return PublicAuthRedirectAllowlist::sanitize(
            $this->clientRedirectResolver->dashboardPathForUser($user),
            '/',
        );
    }

    private function safeCheckoutResumePath(Request $request): ?string
    {
        $intended = $request->session()->get('url.intended');
        if (! is_string($intended) || $intended === '') {
            return null;
        }

        $path = parse_url($intended, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $query = parse_url($intended, PHP_URL_QUERY);
        $candidate = $path.(is_string($query) && $query !== '' ? '?'.$query : '');

        if (! CheckoutReturnIntent::isAllowedCheckoutReturn($candidate)
            && ! CheckoutReturnIntent::isAllowedCheckoutReturn($path)) {
            return null;
        }

        $sanitized = PublicAuthRedirectAllowlist::sanitize($candidate, '');

        return $sanitized !== '' ? $sanitized : null;
    }

    private function hasIntendedEmailVerificationUrl(Request $request): bool
    {
        $intended = $request->session()->get('url.intended');
        if (! is_string($intended) || $intended === '') {
            return false;
        }

        $path = parse_url($intended, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/verify-email/[^/]+/[^/]+$#', $path) === 1;
    }
}
