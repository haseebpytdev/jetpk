<?php

namespace App\Support\Media;

/**
 * Normalizes public media URLs for the Next.js frontend (path-first, no localhost).
 */
final class PublicMediaUrl
{
    public static function normalize(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '/')) {
            return $trimmed;
        }

        $parsed = parse_url($trimmed);
        if (! is_array($parsed)) {
            return $trimmed;
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        if (in_array($host, ['127.0.0.1', 'localhost', '0.0.0.0'], true)) {
            $path = (string) ($parsed['path'] ?? '');
            $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

            return $path !== '' ? $path.$query : null;
        }

        $canonicalHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($canonicalHost !== '' && $host === $canonicalHost) {
            $path = (string) ($parsed['path'] ?? '');
            $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

            return $path !== '' ? $path.$query : $trimmed;
        }

        return $trimmed;
    }
}
