<?php

namespace App\Support\Integrations;

use App\Models\User;

/**
 * Platform-admin Integration Hub capabilities (dashboard RBAC keys).
 */
final class IntegrationAuthorization
{
    public const VIEW = 'integrations.view';

    public const MANAGE = 'integrations.manage';

    public const TEST = 'integrations.test';

    public const ACTIVATE = 'integrations.activate';

    public const TEST_PAYMENT = 'integrations.test-payment';

    public const AUDIT = 'integrations.audit';

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return [
            self::VIEW,
            self::MANAGE,
            self::TEST,
            self::ACTIVATE,
            self::TEST_PAYMENT,
            self::AUDIT,
        ];
    }

    public static function can(User $user, string $permission): bool
    {
        if (! $user->isPlatformAdmin()) {
            return false;
        }

        return in_array($permission, self::allKeys(), true);
    }

    public static function assert(User $user, string $permission): void
    {
        abort_unless(self::can($user, $permission), 403, 'Integration permission denied.');
    }
}
