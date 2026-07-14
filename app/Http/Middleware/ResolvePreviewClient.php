<?php

namespace App\Http\Middleware;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves an active ClientProfile from the {clientSlug} route parameter for client preview/parity routes.
 *
 * Default deployment slug (config ota_client.slug or haseeb-master) is alias-only: prefixed URLs
 * redirect to canonical root paths (302). Non-default clients keep /{clientSlug}/* parity routes.
 */
class ResolvePreviewClient
{
    public function __construct(
        private readonly ClientProfileResolver $profileResolver,
        private readonly CurrentClientContext $clientContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->hostGuardBlocks($request)) {
            abort(404);
        }

        $slug = trim((string) $request->route('clientSlug'));

        if ($slug === '') {
            abort(404);
        }

        if ($this->profileResolver->isDefaultDeploymentSlug($slug)) {
            $target = $this->canonicalRedirectPath($request, $slug);

            if ($this->pathsAreEquivalent($request, $target)) {
                abort(404);
            }

            return redirect()->to($target, 302);
        }

        $profile = $this->profileResolver->resolveBySlug($slug);

        if ($profile === null) {
            abort(404);
        }

        $this->clientContext->set($profile);

        return $next($request);
    }

    private function hostGuardBlocks(Request $request): bool
    {
        if (! config('client_route_parity.host_guard_enabled', false)) {
            return false;
        }

        $masterHost = trim((string) config('client_route_parity.master_host', ''));

        return $masterHost !== '' && strcasecmp($request->getHost(), $masterHost) !== 0;
    }

    private function canonicalRedirectPath(Request $request, string $slug): string
    {
        $path = '/'.ltrim($request->path(), '/');
        $prefix = '/'.$slug;

        if ($path === $prefix) {
            $remainder = '';
        } elseif (str_starts_with($path, $prefix.'/')) {
            $remainder = substr($path, strlen($prefix) + 1);
        } else {
            $remainder = '';
        }

        if ($remainder === '' || $remainder === 'home') {
            $canonicalPath = '/';
        } else {
            $canonicalPath = '/'.$remainder;
        }

        $query = $request->getQueryString();
        if ($query !== null && $query !== '') {
            return $canonicalPath.'?'.$query;
        }

        return $canonicalPath;
    }

    private function pathsAreEquivalent(Request $request, string $target): bool
    {
        $currentPath = $this->normalizePath($request->getPathInfo());
        $targetPath = $this->normalizePath(parse_url($target, PHP_URL_PATH) ?: '/');

        return $currentPath === $targetPath;
    }

    private function normalizePath(string $path): string
    {
        return rtrim('/'.ltrim($path, '/'), '/') ?: '/';
    }
}
