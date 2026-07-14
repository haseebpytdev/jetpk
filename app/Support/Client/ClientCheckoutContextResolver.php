<?php

namespace App\Support\Client;

use App\Http\Middleware\PersistClientPreviewContext;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use Illuminate\Http\Request;

/**
 * Resolves and persists client preview slug for checkout handoffs (8G).
 *
 * When fare select navigates from /jetpk/* to passenger checkout, slug must survive
 * AJAX offer payloads, internal redirects, and unprefixed /booking/passengers fallbacks.
 */
final class ClientCheckoutContextResolver
{
    public function __construct(
        private readonly ClientProfileResolver $profileResolver,
        private readonly CurrentClientContext $clientContext,
    ) {}

    public function resolve(?Request $request = null): ?string
    {
        $request ??= request();
        if (! $request instanceof Request) {
            return null;
        }

        $slug = current_client_slug();
        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        $rootSlug = ota_single_client_root_slug();
        if ($rootSlug !== null) {
            return $rootSlug;
        }

        $routeSlug = $request->route('clientSlug');
        if (is_string($routeSlug) && trim($routeSlug) !== '') {
            return trim($routeSlug);
        }

        if ($request->hasSession()) {
            $sessionSlug = $request->session()->get(PersistClientPreviewContext::SESSION_KEY);
            if (is_string($sessionSlug) && trim($sessionSlug) !== '') {
                return trim($sessionSlug);
            }
        }

        $slugFromPath = $this->slugFromPath(trim($request->path(), '/'));
        if ($slugFromPath !== null) {
            return $slugFromPath;
        }

        return $this->slugFromReferer((string) $request->headers->get('referer', ''));
    }

    public function persist(?Request $request, ?string $slug = null): void
    {
        $request ??= request();
        $slug ??= $this->resolve($request);

        if ($slug === null || $slug === '' || ! $request instanceof Request) {
            return;
        }

        $request->attributes->set(PersistClientPreviewContext::REQUEST_ATTR_SLUG, $slug);

        $profile = $this->profileResolver->resolveBySlug($slug);
        if ($profile !== null) {
            $this->clientContext->set($profile);
            $request->attributes->set(PersistClientPreviewContext::REQUEST_ATTR_PROFILE_ID, $profile->id);
        }

        if ($request->hasSession()) {
            $request->session()->put(PersistClientPreviewContext::SESSION_KEY, $slug);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function passengersUrl(array $params, ?Request $request = null): string
    {
        $request ??= request();
        $slug = $this->resolve($request);
        if ($slug !== null) {
            $this->persist($request, $slug);
        }

        $filtered = array_filter(
            $params,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        if ($slug !== null && $slug !== '') {
            return client_safe_route('booking.passengers', $filtered, $slug);
        }

        return route('booking.passengers', $filtered);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function resultsUrl(array $params, ?Request $request = null): string
    {
        $request ??= request();
        $slug = $this->resolve($request);
        if ($slug !== null) {
            $this->persist($request, $slug);
        }

        $filtered = array_filter(
            $params,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        if ($slug !== null && $slug !== '') {
            return client_safe_route('flights.results', $filtered, $slug);
        }

        return route('flights.results', $filtered);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function homeSearchUrl(?Request $request = null): string
    {
        $slug = $this->resolve($request);

        return client_home_flight_search_url($slug);
    }

    public function requiresPrefixedPassengersRedirect(Request $request): bool
    {
        if (ota_single_client_root_slug() !== null) {
            return false;
        }

        $slug = $this->resolve($request);
        if ($slug === null || $slug === '') {
            return false;
        }

        $path = trim($request->path(), '/');
        if ($path === $slug.'/booking/passengers' || str_starts_with($path, $slug.'/booking/passengers')) {
            return false;
        }

        return $path === 'booking/passengers' || str_starts_with($path, 'booking/passengers');
    }

    private function slugFromPath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if ($path === 'jetpk' || str_starts_with($path, 'jetpk/')) {
            return 'jetpk';
        }

        $first = explode('/', $path)[0] ?? '';
        if ($first === '' || ReservedClientPreviewSlugs::isReserved($first)) {
            return null;
        }

        return $this->profileResolver->resolveBySlug($first) !== null ? $first : null;
    }

    private function slugFromReferer(string $referer): ?string
    {
        if ($referer === '') {
            return null;
        }

        $path = parse_url($referer, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $normalized = trim($path, '/');
        if (str_starts_with($normalized, 'jetpk/') || $normalized === 'jetpk') {
            return 'jetpk';
        }

        return $this->slugFromPath($normalized);
    }
}
