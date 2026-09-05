<?php

namespace App\Support\Emails;

/**
 * Greeting line from intended recipient role, not commercial/customer payload identity.
 */
final class EmailRecipientRoleGreeting
{
    public static function line(?string $intendedRole, ?string $customerName = null): string
    {
        $role = strtolower(trim((string) $intendedRole));

        return match (true) {
            $role === 'platform_admin' => 'Dear Platform Administrator,',
            $role === 'agency_admin' => 'Dear Agency Administrator,',
            in_array($role, ['admin', 'administrator', 'operations_admin'], true) => 'Dear Administrator,',
            str_contains($role, 'staff') => 'Dear Staff Member,',
            in_array($role, ['booking_agent', 'agent_booking', 'agent', 'partner'], true)
                || str_contains($role, 'agent') => 'Dear Agent,',
            $role === 'finance' || str_contains($role, 'ops') => 'Dear Operations,',
            default => self::customerLine($customerName),
        };
    }

    public static function isStaffFacingRole(?string $intendedRole): bool
    {
        $role = strtolower(trim((string) $intendedRole));
        if ($role === '' || in_array($role, ['customer', 'user', 'guest', 'applicant'], true)) {
            return false;
        }

        return str_contains($role, 'admin')
            || str_contains($role, 'staff')
            || str_contains($role, 'agent')
            || str_contains($role, 'ops')
            || in_array($role, ['finance', 'partner'], true);
    }

    private static function customerLine(?string $customerName): string
    {
        $name = trim((string) $customerName);
        if ($name === '' || preg_match('/^(user|guest|customer|administrator|applicant)$/i', $name) === 1) {
            return 'Hello,';
        }

        return 'Hello '.$name.',';
    }
}
