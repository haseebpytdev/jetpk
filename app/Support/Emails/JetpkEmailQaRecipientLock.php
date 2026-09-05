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

    public static function enforceOrFail(string $target): string
    {
        $normalized = strtolower(trim($target));
        if (! self::isAuthorized($normalized)) {
            throw new InvalidArgumentException('QA recipient lock refused: target is not the authorized inbox.');
        }

        return self::AUTHORIZED_INBOX;
    }

    public static function isAuthorized(string $email): bool
    {
        return strtolower(trim($email)) === self::AUTHORIZED_INBOX;
    }
}
