<?php

namespace App\Support\Emails;

/**
 * Prefixes operational email subjects by intended recipient role, not mailbox.
 */
final class EmailRecipientRoleSubjectTagger
{
    public static function apply(string $subject, ?string $intendedRole): string
    {
        $subject = trim($subject);
        $tag = self::tagForRole($intendedRole);
        if ($tag === null) {
            return $subject;
        }

        foreach (['[ADMIN]', '[AGENT]', '[STAFF]', '[OPS]'] as $existing) {
            if (str_starts_with($subject, $existing)) {
                return $subject;
            }
        }

        return $tag.' '.$subject;
    }

    public static function tagForRole(?string $intendedRole): ?string
    {
        $role = strtolower(trim((string) $intendedRole));

        return match (true) {
            in_array($role, ['admin', 'platform_admin', 'agency_admin'], true) => '[ADMIN]',
            str_starts_with($role, 'agent') || in_array($role, ['partner'], true) => '[AGENT]',
            in_array($role, ['staff', 'platform_staff'], true) => '[STAFF]',
            str_starts_with($role, 'ops') || in_array($role, ['operations', 'operations_admin'], true) => '[OPS]',
            default => null,
        };
    }
}
