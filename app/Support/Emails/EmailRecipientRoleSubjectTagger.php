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

    /**
     * Strip any existing role prefix and apply the intended role tag.
     * Used when a factory-built subject was pre-tagged for a different delivery bucket.
     */
    public static function retag(string $subject, ?string $intendedRole): string
    {
        $subject = trim($subject);
        foreach (['[ADMIN]', '[AGENT]', '[STAFF]', '[OPS]'] as $existing) {
            if (str_starts_with($subject, $existing.' ')) {
                $subject = trim(substr($subject, strlen($existing)));
                break;
            }
            if ($subject === $existing) {
                $subject = '';
                break;
            }
        }

        return self::apply($subject, $intendedRole);
    }

    public static function tagForRole(?string $intendedRole): ?string
    {
        $role = strtolower(trim((string) $intendedRole));

        return match (true) {
            in_array($role, ['admin', 'platform_admin', 'agency_admin'], true) => '[ADMIN]',
            str_contains($role, 'agent') || in_array($role, ['partner'], true) => '[AGENT]',
            str_contains($role, 'staff') => '[STAFF]',
            str_contains($role, 'ops') || in_array($role, ['finance', 'operations', 'operations_admin', 'operations_queue'], true) => '[OPS]',
            default => null,
        };
    }

    /**
     * Map a notification recipient bucket to a role key for subject tagging.
     */
    public static function roleForBucket(?string $bucket): ?string
    {
        $bucket = strtolower(trim((string) $bucket));

        return match (true) {
            in_array($bucket, ['platform_admin', 'admin', 'agency_admin'], true) => 'admin',
            in_array($bucket, ['booking_agent', 'agent_booking', 'agent', 'agent_staff_creator', 'ticket_forwarded_agent'], true) => 'agent',
            in_array($bucket, ['assigned_staff', 'staff_assigned', 'platform_staff', 'staff', 'ticket_assigned_staff'], true) => 'staff',
            in_array($bucket, ['finance', 'operations_queue'], true) => 'finance',
            in_array($bucket, ['customer_party', 'booking_customer', 'applicant', 'user', 'logged_in_user', 'ticket_creator'], true) => 'customer',
            default => $bucket !== '' ? $bucket : null,
        };
    }
}
