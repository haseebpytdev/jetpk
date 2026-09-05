<?php

namespace App\Support\Emails;

use InvalidArgumentException;

/**
 * One-shot QA recipient override. Fail closed unless the target is the authorized inbox.
 */
final class JetpkEmailQaRecipientLock
{
    public const AUTHORIZED_INBOX = 'myworkhaseeb@gmail.com';

    private static bool $active = false;

    public static function activate(string $target): void
    {
        if (! self::isAuthorized($target)) {
            throw new InvalidArgumentException('QA recipient lock refused: target is not the authorized inbox.');
        }

        self::$active = true;
    }

    public static function deactivate(): void
    {
        self::$active = false;
    }

    public static function isActive(): bool
    {
        return self::$active;
    }

    public static function enforceOrFail(mixed $target): string
    {
        $normalized = self::normalizeOrFail($target);
        if (! self::isAuthorized($normalized)) {
            throw new InvalidArgumentException('QA recipient lock refused: target is not the authorized inbox.');
        }

        return self::AUTHORIZED_INBOX;
    }

    /**
     * Fail closed before SMTP. Roles, integers, booleans, and "0" are never envelope recipients.
     */
    public static function normalizeOrFail(mixed $target): string
    {
        if (is_bool($target) || is_int($target) || is_float($target) || is_array($target) || $target === null) {
            throw new InvalidArgumentException(
                'QA/SMTP recipient must be a string email, not '.get_debug_type($target).'.'
            );
        }
        if (! is_string($target) && ! $target instanceof \Stringable) {
            throw new InvalidArgumentException(
                'QA/SMTP recipient must be a string email, not '.get_debug_type($target).'.'
            );
        }
        $normalized = strtolower(trim((string) $target));
        if ($normalized === '' || $normalized === '0' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('QA/SMTP recipient is not a valid email address.');
        }

        return $normalized;
    }

    public static function isAuthorized(string $email): bool
    {
        return strtolower(trim($email)) === self::AUTHORIZED_INBOX;
    }
}
