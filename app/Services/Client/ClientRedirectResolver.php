<?php

namespace App\Services\Client;

use App\Enums\AccountType;
use App\Http\Middleware\PersistClientPreviewContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Client-aware redirect and path resolution for preview/parity routes (MC-7C/7D).
 *
 * Prefers client_route()/client_url() when preview context or session slug exists;
 * falls back to standard route()/url() for root production URLs.
 */
final class ClientRedirectResolver
{
    public function __construct(
        private readonly Request $request,
        private readonly CurrentClientContext $clientContext,
    ) {}

    public function intended(string $fallbackRouteName = 'dashboard', ?User $user = null): RedirectResponse
    {
        $fallback = $user !== null
            ? $this->dashboardPathForUser($user)
            : client_route($fallbackRouteName);

        return redirect()->intended($fallback);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function route(string $routeName, array $parameters = [], int $status = 302): RedirectResponse
    {
        return redirect()->to($this->pathForRoute($routeName, $parameters), $status);
    }

    public function to(string $path, int $status = 302): RedirectResponse
    {
        return redirect()->to($this->pathForUrl($path), $status);
    }

    public function afterLoginPath(?User $user): string
    {
        return $this->dashboardPathForUser($user);
    }

    public function afterLogoutPath(?string $capturedSessionSlug = null): string
    {
        $slug = $this->resolveSlug(allowSessionFallback: false, capturedSessionSlug: $capturedSessionSlug);

        if ($slug !== null) {
            if ($parityName = client_parity_route_name('home')) {
                return route($parityName, ['clientSlug' => $slug], false);
            }

            return '/'.$slug;
        }

        return '/';
    }

    public function dashboardPathForUser(?User $user): string
    {
        if ($user === null || $user->account_type === null) {
            return $this->pathForRoute('admin.dashboard');
        }

        return match ($user->account_type) {
            AccountType::PlatformAdmin => $this->pathForRoute('admin.dashboard'),
            AccountType::AgencyAdmin => Route::has('account.legacy')
                ? $this->pathForRoute('account.legacy')
                : '/account/legacy',
            AccountType::Staff => Route::has('staff.dashboard')
                ? $this->pathForRoute('staff.dashboard')
                : $this->pathForUrl('/staff'),
            AccountType::Agent, AccountType::AgentStaff => Route::has('agent.dashboard')
                ? $this->pathForRoute('agent.dashboard')
                : $this->pathForUrl('/agent'),
            AccountType::Customer => Route::has('customer.bookings.index')
                ? $this->pathForRoute('customer.bookings.index')
                : (Route::has('customer.dashboard')
                    ? $this->pathForRoute('customer.dashboard')
                    : $this->pathForUrl('/customer')),
        };
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function pathForRoute(string $routeName, array $parameters = []): string
    {
        $slug = $this->resolveSlug(allowSessionFallback: true);

        if ($slug !== null && config('client_route_parity.enabled', true)) {
            $parityName = client_parity_route_name($routeName);
            if ($parityName !== null) {
                $parameters['clientSlug'] = $slug;

                return route($parityName, $parameters, false);
            }
        }

        return route($routeName, $parameters, false);
    }

    public function pathForUrl(string $path): string
    {
        $slug = $this->resolveSlug(allowSessionFallback: true);

        if ($slug === null) {
            return url($path);
        }

        [$pathPart, $queryPart] = client_url_split_path_query($path);
        $normalized = '/'.ltrim($pathPart, '/');
        if ($normalized === '/') {
            $normalized = '';
        }

        $prefixed = '/'.$slug.$normalized;

        return $queryPart !== '' ? $prefixed.'?'.$queryPart : $prefixed;
    }

    public function guestLoginPath(): string
    {
        return $this->pathForRoute('login');
    }

    private function resolveSlug(bool $allowSessionFallback, ?string $capturedSessionSlug = null): ?string
    {
        $routeSlug = $this->request->route('clientSlug');
        if (is_string($routeSlug) && trim($routeSlug) !== '') {
            return trim($routeSlug);
        }

        if ($this->clientContext->isPreview()) {
            $contextSlug = $this->clientContext->slug();
            if (is_string($contextSlug) && $contextSlug !== '') {
                return $contextSlug;
            }
        }

        if ($capturedSessionSlug !== null && trim($capturedSessionSlug) !== '') {
            return trim($capturedSessionSlug);
        }

        if (! $allowSessionFallback || ! $this->request->hasSession()) {
            return null;
        }

        $sessionSlug = $this->request->session()->get(PersistClientPreviewContext::SESSION_KEY);

        return is_string($sessionSlug) && trim($sessionSlug) !== ''
            ? trim($sessionSlug)
            : null;
    }
}
