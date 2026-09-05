<?php

namespace App\Support\Emails;

use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Scenario-specific CTA URLs using existing named routes only.
 */
final class EmailContextualCtaResolver
{
    /**
     * @param  array<string, mixed>  $variables
     * @return array{label: string, url: string}|null
     */
    public static function resolve(string $eventKey, ?string $intendedRole, array $variables): ?array
    {
        $event = strtolower(str_replace(['-', ' '], '_', $eventKey));
        $role = strtolower(trim((string) $intendedRole));
        $staff = EmailRecipientRoleGreeting::isStaffFacingRole($role);

        $bookingUrl = self::firstUrl(
            $variables['manage_booking_url'] ?? null,
            $variables['booking_url'] ?? null,
        );
        $adminBooking = self::firstUrl($variables['admin_booking_url'] ?? null);
        $login = self::named('login') ?? self::firstUrl($variables['login_url'] ?? null);
        $adminHome = self::named('admin.dashboard')
            ?? self::named('admin.home')
            ?? self::named('admin.bookings.index')
            ?? $login;
        $agentApps = self::named('admin.agent-applications.index') ?? $adminHome;
        $wallet = self::named('admin.finance.wallet-audit.index')
            ?? self::named('agent.wallet.index')
            ?? $adminHome;
        $groups = self::named('admin.group-ticketing.index')
            ?? self::named('admin.bookings.index')
            ?? $adminHome;

        if (str_contains($event, 'ticket_issued') || str_contains($event, 'booking_confirmed') || str_contains($event, 'booking_created')) {
            if ($staff && $adminBooking !== null) {
                return ['label' => 'Open in admin', 'url' => $adminBooking];
            }
            if ($bookingUrl !== null) {
                return ['label' => str_contains($event, 'ticket') ? 'View booking' : 'Manage booking', 'url' => $bookingUrl];
            }
        }

        if (str_contains($event, 'pnr') || str_contains($event, 'manual_review') || str_contains($event, 'digest') || str_contains($event, 'daily_admin') || str_contains($event, 'report')) {
            return $adminHome !== null ? ['label' => 'Open admin', 'url' => $adminHome] : null;
        }

        if (str_contains($event, 'wallet') || str_contains($event, 'deposit')) {
            return $wallet !== null ? ['label' => 'Review wallet', 'url' => $wallet] : null;
        }

        if (str_contains($event, 'supplier_release') || str_contains($event, 'group_booking_supplier')) {
            return $groups !== null ? ['label' => 'Open group booking', 'url' => $groups] : null;
        }

        if (str_contains($event, 'group_booking_payment') || str_contains($event, 'group_payment')) {
            return $groups !== null ? ['label' => 'Review payment', 'url' => $groups] : null;
        }

        if (str_contains($event, 'agent_application') || str_contains($event, 'agent_registration')) {
            if ($staff) {
                return $agentApps !== null ? ['label' => 'Review application', 'url' => $agentApps] : null;
            }
        }

        if (str_contains($event, 'admin_created') || str_contains($event, 'staff_created') || str_contains($event, 'account_created')) {
            return $login !== null ? ['label' => 'Sign in', 'url' => $login] : null;
        }

        return null;
    }

    private static function named(string $name): ?string
    {
        if (! Route::has($name)) {
            return null;
        }
        try {
            return route($name, absolute: true);
        } catch (Throwable) {
            return null;
        }
    }

    private static function firstUrl(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }
            $value = trim($candidate);
            if ($value !== '' && $value !== '#') {
                return $value;
            }
        }

        return null;
    }
}
