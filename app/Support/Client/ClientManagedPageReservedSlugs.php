<?php

namespace App\Support\Client;

/**
 * Reserved public slugs that custom CMS pages must not collide with.
 */
final class ClientManagedPageReservedSlugs
{
    public static function isReserved(string $slug): bool
    {
        return ReservedPublicPath::isReservedFirstSegment($slug);
    }

    public static function normalize(string $slug): string
    {
        return ReservedPublicPath::normalizeSegment($slug);
    }

    public static function isValidFormat(string $slug): bool
    {
        $normalized = self::normalize($slug);

        if ($normalized === '' || $normalized !== strtolower(trim($slug))) {
            return false;
        }

        if (str_contains($slug, '..') || str_contains($slug, '%')) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }
}
