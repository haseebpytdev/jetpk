<?php

namespace App\Support\Emails;

/**
 * Public accessor for JetPK email preview/audit sample payloads.
 *
 * Event-specific scalars below are preview/fixture-only — never used by live send paths.
 */
final class JetpkEmailSampleDataProvider
{
    use JetpkEmailSampleData;

    public static function forType(string $type): array
    {
        return (new self)->sampleData($type);
    }

    public static function forEvent(string $eventKey): array
    {
        $type = JetpkEmailEventContentRegistry::find($eventKey)?->jetpkTypeKey
            ?? JetpkEmailEventTypeMap::typeForEvent($eventKey)
            ?? 'notification';

        $merged = array_merge(self::forType($type), self::eventAuthoritativeScalars($eventKey));

        if (
            trim((string) ($merged['booking_reference'] ?? '')) === ''
            && preg_match('/booking|ticket|refund|payment|pnr|itinerary/i', $eventKey) === 1
        ) {
            $merged['booking_reference'] = 'JPK-2026-004821';
        }

        return self::attachStructuredPreviewBlocks($eventKey, $merged);
    }

    /**
     * @param  array<string, mixed>  $sample
     * @return array<string, mixed>
     */
    private static function attachStructuredPreviewBlocks(string $eventKey, array $sample): array
    {
        $eventKey = strtolower(str_replace(['-', ' '], '_', trim($eventKey)));

        if ($eventKey === 'group_booking_reservation_created') {
            $block = [
                'reference' => (string) ($sample['group_reference'] ?? 'GRP-2026-1192'),
                'route' => (string) ($sample['route'] ?? 'LHE → JED'),
                'seats' => (string) ($sample['seats'] ?? '12'),
                'deadline' => '12 Jul 2026, 6:00 PM',
            ];
            // Blade expects $reservation; preview command also accepts group_reservation.
            $sample['reservation'] = $block;
            $sample['group_reservation'] = $block;
        }

        if ($eventKey === 'ticket_issued' && ! isset($sample['booking'])) {
            $sample['booking'] = [
                'reference' => (string) ($sample['booking_reference'] ?? 'JPK-2026-004821'),
                'status' => (string) ($sample['booking_status'] ?? 'Ticketed'),
                'route' => (string) ($sample['route'] ?? 'Karachi (KHI) → Dubai (DXB)'),
                'amount' => (string) ($sample['amount'] ?? '96,500'),
                'currency' => (string) ($sample['currency'] ?? 'PKR'),
            ];
        }

        if ($eventKey === 'refund_approved') {
            $sample['refund'] = [
                'amount' => (string) ($sample['amount'] ?? '90,000'),
                'currency' => (string) ($sample['currency'] ?? 'PKR'),
                'status' => (string) ($sample['refund_status'] ?? 'Approved'),
            ];
            $sample['payment'] = [
                'amount' => (string) ($sample['amount'] ?? '90,000'),
                'currency' => (string) ($sample['currency'] ?? 'PKR'),
                'status' => (string) ($sample['refund_status'] ?? 'Approved'),
                'reference' => (string) ($sample['payment_reference'] ?? 'TXN-4F9A21C7'),
            ];
        }

        return $sample;
    }

    /**
     * Authoritative preview scalars keyed for JetpkEmailEventRenderer detail_fields.
     * Sample-only; does not invent values on production send paths.
     *
     * @return array<string, scalar|null>
     */
    private static function eventAuthoritativeScalars(string $eventKey): array
    {
        $eventKey = strtolower(str_replace(['-', ' '], '_', trim($eventKey)));

        return match ($eventKey) {
            'ticket_issued' => [
                'booking_reference' => 'JPK-2026-004821',
                'ticket_numbers' => '157-1234567890, 157-1234567891',
                'tickets_count' => '2',
                'route' => 'Karachi (KHI) → Dubai (DXB)',
                'passenger_name' => 'Ayesha Khan',
                'customer_name' => 'Ayesha Khan',
                'booking_status' => 'Ticketed',
                'amount' => '96,500',
                'currency' => 'PKR',
            ],
            'refund_approved' => [
                'booking_reference' => 'JPK-2026-004821',
                'amount' => '90,000',
                'currency' => 'PKR',
                'refund_status' => 'Approved',
                'payment_reference' => 'TXN-4F9A21C7',
                'customer_name' => 'Ayesha Khan',
            ],
            'group_booking_reservation_created' => [
                'group_reference' => 'GRP-2026-1192',
                'booking_reference' => 'JPK-2026-004821',
                'route' => 'LHE → JED',
                'seats' => '12',
                'booking_status' => 'Local hold',
                'payment_status' => 'Unpaid',
                'customer_name' => 'Ayesha Khan',
            ],
            'commission_earned' => [
                'booking_reference' => 'JPK-2026-004821',
                'amount' => '4,825',
                'currency' => 'PKR',
                'agent_name' => 'Sample Travels',
                'payment_reference' => 'CM-2026-8821',
                'customer_name' => 'Ayesha Khan',
            ],
            'support_ticket_created' => [
                'ticket_reference' => 'TKT-88213',
                'ticket_subject' => 'Change passenger name',
                'ticket_status' => 'Open',
                'customer_name' => 'Ayesha Khan',
                'customer_email' => 'ayesha@example.com',
            ],
            'customer_registered' => [
                'customer_name' => 'Ayesha Khan',
                'customer_email' => 'ayesha@example.com',
            ],
            default => [],
        };
    }
}
