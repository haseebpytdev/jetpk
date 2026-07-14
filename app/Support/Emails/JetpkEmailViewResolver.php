<?php

namespace App\Support\Emails;

/**
 * JetpkEmailViewResolver
 *
 * All JetPK email types resolve to the single universal event-content view
 * inside the canonical shell. Type keys map to OTA event keys via JetpkEmailEventTypeMap.
 */
class JetpkEmailViewResolver
{
    /** Only this client uses the JetPK theme. */
    public const CLIENT_SLUG = 'jetpk';

    public const UNIVERSAL_VIEW = 'emails.themes.jetpakistan.universal-event';

    public const SHELL_VIEW = 'emails.themes.jetpakistan.layouts.base';

    /**
     * Resolve the universal JetPK content view for any type.
     * Returns null when the client is not JetPK.
     */
    public static function resolve(string $type, ?string $clientSlug = self::CLIENT_SLUG): ?string
    {
        if ($clientSlug !== self::CLIENT_SLUG) {
            return null;
        }

        $type = static::normalizeType($type);
        if (! static::isKnownType($type)) {
            return null;
        }

        return self::UNIVERSAL_VIEW;
    }

    public static function eventKeyForType(string $type): ?string
    {
        return JetpkEmailEventTypeMap::eventForType(static::normalizeType($type));
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $map = [];
        foreach (array_keys(JetpkEmailEventTypeMap::all()) as $type) {
            $map[$type] = self::UNIVERSAL_VIEW;
        }

        return $map;
    }

    public static function isKnownType(string $type): bool
    {
        return JetpkEmailEventTypeMap::eventForType(static::normalizeType($type)) !== null
            || array_key_exists(static::normalizeType($type), JetpkEmailEventTypeMap::all());
    }

    protected static function normalizeType(string $type): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim($type)));
    }
}
