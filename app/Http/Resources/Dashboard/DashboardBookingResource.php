<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Booking;
use App\Support\Bookings\BookingListPresenter;
use App\Support\Dashboard\DashboardMoneyPresenter;

final class DashboardBookingResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Booking $booking): array
    {
        $row = BookingListPresenter::toListRow($booking);
        $route = (string) ($row['route'] ?? '');
        [$origin, $destination] = self::splitRoute($route);
        $supplier = (string) ($row['supplier_provider'] ?? '');

        return [
            'id' => self::publicId($booking),
            'pnr' => (string) ($row['pnr'] ?? ''),
            'supplierReference' => (string) ($row['supplier_reference'] ?? '') ?: null,
            'bookingDate' => self::dateOnly($booking->created_at?->toIso8601String()),
            'departureDate' => (string) ($row['travel_date'] ?? ''),
            'returnDate' => null,
            'customerName' => (string) ($row['customer_name'] ?? 'Guest'),
            'customerEmail' => self::maskEmail((string) ($row['contact_email'] ?? '')),
            'customerPhone' => self::maskPhone((string) ($row['contact_phone'] ?? '')),
            'passengerCount' => (int) ($row['passengers_count'] ?? 0),
            'origin' => $origin,
            'destination' => $destination,
            'tripType' => 'one_way',
            'airline' => (string) ($row['airline'] ?? ''),
            'supplier' => $supplier,
            'channel' => self::resolveChannel($supplier, $booking),
            'bookingStatus' => self::mapBookingStatus((string) ($row['status'] ?? '')),
            'paymentStatus' => self::mapPaymentStatus((string) ($row['payment_status'] ?? 'unpaid')),
            'ticketingStatus' => self::mapTicketingStatus((string) ($row['ticketing_status'] ?? 'not_started')),
            'currency' => DashboardMoneyPresenter::resolveBookingCurrency($booking),
            'totalAmount' => (int) ($row['total_fare'] ?? 0),
            'amountPaid' => self::paidAmount($booking),
            'agentOrSource' => self::agentOrSource($row),
            'lastUpdated' => $booking->updated_at?->toIso8601String() ?? '',
            'reviewFlags' => [
                'needsAction' => in_array((string) ($row['status_operational'] ?? ''), ['needs_action', 'payment_review'], true),
                'manualReview' => (string) ($row['supplier_status'] ?? '') === 'failed',
            ],
        ];
    }

    public static function publicId(Booking $booking): string
    {
        $ref = (string) ($booking->booking_reference ?? '');
        if ($ref !== '') {
            return $ref;
        }

        return 'BK-'.$booking->id;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected static function agentOrSource(array $row): string
    {
        if (! empty($row['agent_name'])) {
            return 'Agent — '.(string) $row['agent_name'];
        }

        return match ((string) ($row['customer_type'] ?? '')) {
            'agent' => 'Agent portal',
            'customer' => 'Web — Direct',
            default => 'Guest',
        };
    }

    protected static function resolveChannel(string $supplier, Booking $booking): string
    {
        $provider = strtolower($supplier);
        if (str_contains($provider, 'duffel') || str_contains($provider, 'ndc')) {
            return 'ndc';
        }

        $metaProvider = strtolower((string) (($booking->meta['supplier_provider'] ?? '') ?: ''));
        if (str_contains($metaProvider, 'duffel') || str_contains($metaProvider, 'ndc')) {
            return 'ndc';
        }

        return 'gds';
    }

    protected static function mapBookingStatus(string $status): string
    {
        return match ($status) {
            'ticketed', 'confirmed' => 'confirmed',
            'cancelled', 'voided' => 'cancelled',
            'failed' => 'failed',
            default => 'pending',
        };
    }

    protected static function mapPaymentStatus(string $status): string
    {
        return match ($status) {
            'paid' => 'paid',
            'partial' => 'partial',
            'pending', 'submitted' => 'pending',
            default => 'unpaid',
        };
    }

    protected static function mapTicketingStatus(string $status): string
    {
        return match ($status) {
            'ticketed', 'issued' => 'ticketed',
            'pending', 'in_progress' => 'pending',
            default => 'unticketed',
        };
    }

    protected static function paidAmount(Booking $booking): int
    {
        $booking->loadMissing('verifiedPayments');

        return (int) round((float) $booking->verifiedPayments->sum('amount'));
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected static function splitRoute(string $route): array
    {
        if (preg_match('/([A-Z]{3})\s*[→\-–]\s*([A-Z]{3})/u', $route, $matches) === 1) {
            return [$matches[1], $matches[2]];
        }

        return [$route !== '' && $route !== '—' ? $route : '—', '—'];
    }

    protected static function dateOnly(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }

        return substr($iso, 0, 10);
    }

    protected static function maskEmail(string $email): string
    {
        if ($email === '' || $email === 'Unknown') {
            return '—';
        }

        return DashboardSessionResource::maskEmail($email) ?? '—';
    }

    protected static function maskPhone(string $phone): string
    {
        if ($phone === '' || $phone === 'Unknown') {
            return '—';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 4) {
            return '***';
        }

        return '***'.substr($digits, -4);
    }
}
