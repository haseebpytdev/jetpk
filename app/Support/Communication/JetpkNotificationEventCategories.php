<?php

namespace App\Support\Communication;

use App\Enums\OtaNotificationEvent;

/**
 * Category groupings for notification routing admin UI.
 */
final class JetpkNotificationEventCategories
{
    /**
     * @return array<string, list<OtaNotificationEvent>>
     */
    public static function grouped(): array
    {
        $groups = [
            'Booking' => [],
            'Payment' => [],
            'Cancellation / Refund' => [],
            'Supplier' => [],
            'Ticketing / Documents' => [],
            'User / Auth' => [],
            'Agent' => [],
            'Commission / Wallet' => [],
            'Support' => [],
            'Reports' => [],
            'Group Booking' => [],
            'Security' => [],
        ];

        foreach (OtaNotificationEvent::cases() as $event) {
            $groups[self::categoryFor($event)][] = $event;
        }

        return array_filter($groups, static fn (array $items) => $items !== []);
    }

    public static function categoryFor(OtaNotificationEvent $event): string
    {
        $key = $event->value;

        return match (true) {
            str_starts_with($key, 'booking_') || in_array($key, ['cancellation_requested', 'cancellation_status_changed', 'stale_segment_requires_new_search', 'pnr_itinerary_synced', 'pnr_itinerary_sync_failed'], true) => str_contains($key, 'cancel') ? 'Cancellation / Refund' : 'Booking',
            str_starts_with($key, 'payment_') || str_starts_with($key, 'refund_') => str_contains($key, 'refund') ? 'Cancellation / Refund' : 'Payment',
            str_starts_with($key, 'supplier_') || $key === 'fx_conversion_failed' => 'Supplier',
            str_starts_with($key, 'ticket_') || str_contains($key, 'document_') || str_contains($key, '_generated') => 'Ticketing / Documents',
            str_contains($key, 'login') || str_contains($key, 'auth_') || str_contains($key, 'password_') || str_contains($key, 'registered') || str_contains($key, 'user_') || str_contains($key, 'staff_created') || str_contains($key, 'admin_created') => str_contains($key, 'login_failed') || $key === 'auth_new_device_login' ? 'Security' : 'User / Auth',
            str_contains($key, 'agent_') || $key === 'agent_created' => 'Agent',
            str_contains($key, 'commission_') || str_contains($key, 'deposit') || str_contains($key, 'wallet') || str_contains($key, 'payout') || str_contains($key, 'statement') => 'Commission / Wallet',
            str_starts_with($key, 'support_') => 'Support',
            str_contains($key, '_report') || str_contains($key, 'digest') || str_contains($key, 'summary') || str_contains($key, 'ledger') => 'Reports',
            str_starts_with($key, 'group_') => 'Group Booking',
            default => 'Booking',
        };
    }
}
