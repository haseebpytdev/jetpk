<?php

namespace App\Http\Middleware;

use App\Support\Ui\UiVersionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strips configured UI preview namespace (/v2) before routing and records preview on the resolver.
 * GET/HEAD only — mutating methods under the preview namespace return 404.
 */
class UiVersionRoutePrefixMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $segment = $this->firstPathSegment($request);

        if ($segment === '' || ! $this->isPreviewNamespaceSegment($segment)) {
            return $next($request);
        }

        if (! $request->isMethodSafe()) {
            abort(404);
        }

        if ($this->shouldSkipPrefix($request, $segment)) {
            return $next($request);
        }

        $request->attributes->set(ProtectClientUiPreview::REQUEST_ATTR_ORIGINAL_PATH, $request->getPathInfo());

        $resolver = app(UiVersionResolver::class);

        if ($this->isPreviewAllowed($segment)) {
            $resolver->setPathPrefixPreview($segment);
            $resolver->setPreviewNamespace(true);
        }

        $this->rewriteRequestUriWithoutPrefix($request, $segment);

        return $next($request);
    }

    protected function firstPathSegment(Request $request): string
    {
        $path = $request->getPathInfo();
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return '';
        }

        $parts = explode('/', $trimmed);

        return strtolower($parts[0] ?? '');
    }

    protected function secondPathSegment(Request $request): string
    {
        $path = $request->getPathInfo();
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return '';
        }

        $parts = explode('/', $trimmed);

        return strtolower($parts[1] ?? '');
    }

    protected function isPreviewNamespaceSegment(string $segment): bool
    {
        if ((bool) config('client_ui.preview_namespace_enabled', true)) {
            $namespace = strtolower((string) config('client_ui.preview_namespace', 'v2'));
            if ($segment === $namespace) {
                return true;
            }
        }

        $allowed = config('ota-ui.channels.site.route_prefix_versions', ['v1', 'v2']);

        return is_array($allowed) && in_array($segment, $allowed, true);
    }

    protected function isPreviewAllowed(string $version): bool
    {
        $resolver = app(UiVersionResolver::class);

        return $resolver->isPreviewAllowed($version, UiVersionResolver::CHANNEL_SITE);
    }

    protected function shouldSkipPrefix(Request $request, string $segment): bool
    {
        $second = $this->secondPathSegment($request);

        $reserved = config('ota-ui.path_prefix_excluded_segments', []);
        if (! is_array($reserved)) {
            $reserved = [];
        }

        if ($second !== '' && in_array($second, $reserved, true)) {
            return true;
        }

        return false;
    }

    protected function rewriteRequestUriWithoutPrefix(Request $request, string $prefixSegment): void
    {
        $path = $request->getPathInfo();
        $queryString = $request->getQueryString();

        $trimmed = trim($path, '/');
        $segments = $trimmed === '' ? [] : explode('/', $trimmed);
        if ($segments === []) {
            return;
        }

        if (strtolower($segments[0] ?? '') === strtolower($prefixSegment)) {
            array_shift($segments);
        }

        $pathOnly = $segments === [] ? '/' : '/'.implode('/', $segments);
        $requestUri = $pathOnly;
        if (is_string($queryString) && $queryString !== '') {
            $requestUri .= '?'.$queryString;
        }

        $server = $request->server->all();
        $server['REQUEST_URI'] = $requestUri;
        $server['PATH_INFO'] = $pathOnly;

        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $request->getContent()
        );
    }
}
