<?php

namespace App\Support\Client;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Hard client isolation for public flows — no silent fallback to Master/root URLs (8I/8J).
 */
final class ClientNoFallbackGuard
{
    /** @var list<string> */
    public const FORBIDDEN_ROOT_PATHS = [
        '/flights/results',
        '/flights/results/data',
        '/flights/results/search',
        '/flights/results/offer',
        '/flights/results/revalidate-offer',
        '/booking/passengers',
        '/booking/review',
        '/booking/confirmation',
        '/login',
        '/register',
        '/forgot-password',
        '/lookup-booking',
        '/groups/search',
        '/support',
        '/about-us',
        '/agent/register',
    ];

    public function __construct(
        private readonly ClientCheckoutContextResolver $checkoutContext,
    ) {}

    public function activeClientSlug(?Request $request = null): ?string
    {
        return $this->checkoutContext->resolve($request);
    }

    public function isForbiddenRootPath(string $path, ?string $clientSlug = null): bool
    {
        if (ota_single_client_root_slug() !== null) {
            return false;
        }

        $slug = $clientSlug ?? $this->activeClientSlug();
        if ($slug === null || $slug === '') {
            return false;
        }

        $normalized = '/'.ltrim($this->pathOnly($path), '/');
        if ($normalized === '/') {
            return false;
        }

        foreach (self::FORBIDDEN_ROOT_PATHS as $forbidden) {
            if ($normalized === $forbidden || str_starts_with($normalized, $forbidden.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function safeRoute(string $routeName, array $parameters = [], ?string $clientSlug = null): string
    {
        $slug = $clientSlug ?? $this->activeClientSlug();
        $url = client_route($routeName, $parameters, $slug);

        return $this->safeUrl($url, [], $slug);
    }

    /**
     * @param  array<string, mixed>|string|null  $query
     */
    public function safeUrl(string $path, mixed $query = [], ?string $clientSlug = null): string
    {
        $slug = $clientSlug ?? $this->activeClientSlug();
        $normalizedQuery = $this->normalizeQuery($query);
        $queryString = $normalizedQuery !== [] ? '?'.http_build_query($normalizedQuery) : '';

        [$pathPart, $embeddedQuery] = $this->splitPathAndQuery($path);
        $mergedQuery = $this->mergeQueryStrings($embeddedQuery, $normalizedQuery);
        $candidate = str_contains($pathPart, '://')
            ? $pathPart.($mergedQuery !== '' ? (str_contains($pathPart, '?') ? '&' : '?').$mergedQuery : '')
            : $pathPart.($mergedQuery !== '' ? '?'.$mergedQuery : ($queryString !== '' ? $queryString : ''));

        if ($slug === null || $slug === '') {
            return $candidate;
        }

        $pathOnly = $this->pathOnly($candidate);
        $existingQuery = parse_url($candidate, PHP_URL_QUERY);
        $finalQuery = is_string($existingQuery) && $existingQuery !== ''
            ? $existingQuery
            : ($mergedQuery !== '' ? $mergedQuery : '');

        if (! $this->isForbiddenRootPath($pathOnly, $slug)) {
            return $candidate;
        }

        $rewritten = client_url($pathOnly.($finalQuery !== '' ? '?'.$finalQuery : ''), $slug);

        Log::warning('client_no_fallback_guard.rewritten', [
            'client_slug' => $slug,
            'original_path' => $pathOnly,
            'rewritten' => $rewritten,
        ]);

        return $rewritten;
    }

    /**
     * @return list<string>
     */
    public function scanForbiddenRootUrls(string $content, ?string $clientSlug = null): array
    {
        $slug = $clientSlug ?? $this->activeClientSlug();
        if ($slug === null || $slug === '') {
            return [];
        }

        $hits = [];
        foreach (self::FORBIDDEN_ROOT_PATHS as $forbidden) {
            $patterns = [
                'href="'.$forbidden,
                "href='".$forbidden,
                'action="'.$forbidden,
                "action='".$forbidden,
                '="'.$forbidden.'"',
                "='".$forbidden."'",
                $forbidden.'?',
            ];
            foreach ($patterns as $needle) {
                if (str_contains($content, $needle)) {
                    $hits[] = $forbidden;
                    break;
                }
            }
        }

        return array_values(array_unique($hits));
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeQuery(mixed $query): array
    {
        if ($query === null) {
            return [];
        }

        if (is_array($query)) {
            return $query;
        }

        if (! is_string($query)) {
            return [];
        }

        $trimmed = trim($query);
        if ($trimmed === '') {
            return [];
        }

        if (str_starts_with($trimmed, '?')) {
            $trimmed = substr($trimmed, 1);
        }

        $parsed = [];
        parse_str($trimmed, $parsed);

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPathAndQuery(string $path): array
    {
        if (str_contains($path, '://')) {
            $query = parse_url($path, PHP_URL_QUERY);

            return [$path, is_string($query) ? $query : ''];
        }

        $questionPos = strpos($path, '?');
        if ($questionPos === false) {
            return [$path, ''];
        }

        return [
            substr($path, 0, $questionPos),
            substr($path, $questionPos + 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function mergeQueryStrings(string $base, array $extra): string
    {
        $merged = $this->normalizeQuery($base);
        foreach ($extra as $key => $value) {
            $merged[$key] = $value;
        }

        return $merged !== [] ? http_build_query($merged) : '';
    }

    private function pathOnly(string $url): string
    {
        if (str_contains($url, '://')) {
            $path = parse_url($url, PHP_URL_PATH);

            return is_string($path) && $path !== '' ? $path : '/';
        }

        $question = strpos($url, '?');
        $path = $question === false ? $url : substr($url, 0, $question);

        return $path !== '' ? $path : '/';
    }
}
