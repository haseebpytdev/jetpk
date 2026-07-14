<?php

namespace App\Http\Middleware;

use App\Services\Client\CurrentClientContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Persists client preview slug from URL-resolved context into request attributes and session.
 *
 * Runs after ResolvePreviewClient on parity routes only. Session slug is used for safe
 * post-auth redirects (POST login/logout at root); URL slug always wins over session.
 */
class PersistClientPreviewContext
{
    public const SESSION_KEY = 'ota.preview_client_slug';

    public const REQUEST_ATTR_SLUG = 'ota.preview_client_slug';

    public const REQUEST_ATTR_PROFILE_ID = 'ota.client_profile_id';

    public function __construct(
        private readonly CurrentClientContext $clientContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('dev/cp') || $request->is('dev/cp/*')) {
            return $next($request);
        }

        $slug = $this->resolveSlugFromUrl($request);

        if ($slug === null && $this->clientContext->isPreview()) {
            $slug = $this->clientContext->slug();
        }

        if ($slug !== null && $slug !== '') {
            $request->attributes->set(self::REQUEST_ATTR_SLUG, $slug);

            $profile = $this->clientContext->get();
            if ($profile !== null) {
                $request->attributes->set(self::REQUEST_ATTR_PROFILE_ID, $profile->id);
            }

            if ($request->hasSession()) {
                $request->session()->put(self::SESSION_KEY, $slug);
            }
        }

        return $next($request);
    }

    private function resolveSlugFromUrl(Request $request): ?string
    {
        $routeSlug = $request->route('clientSlug');

        if (is_string($routeSlug) && trim($routeSlug) !== '') {
            return trim($routeSlug);
        }

        return null;
    }
}
