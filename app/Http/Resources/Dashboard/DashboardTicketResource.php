<?php

namespace App\Http\Resources\Dashboard;

use App\Models\BookingTicket;
use App\Support\Bookings\BookingListPresenter;

final class DashboardTicketResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(BookingTicket $ticket): array
    {
        $ticket->loadMissing(['booking.passengers', 'booking.contact', 'passenger', 'supplierBooking']);
        $booking = $ticket->booking;
        $row = $booking ? BookingListPresenter::toListRow($booking) : [];
        $channel = 'Manual';
        if ($ticket->supplierBooking !== null) {
            $channel = DashboardPnrOrderResource::fromModel($ticket->supplierBooking)['channel'] ?? 'Manual';
        } elseif (filled($ticket->provider)) {
            $channel = str_contains(strtolower((string) $ticket->provider), 'ndc') ? 'Sabre NDC' : 'Sabre GDS';
        }

        $paymentStatus = match (strtolower((string) ($row['payment_status'] ?? ''))) {
            'paid' => 'Paid',
            'partial' => 'Partially Paid',
            'submitted', 'pending' => 'Pending',
            default => 'Unpaid',
        };

        return [
            'id' => self::publicId($ticket),
            'maskedExternalId' => self::maskTicketNumber((string) ($ticket->ticket_number ?? '')),
            'documentType' => self::documentType($ticket),
            'channel' => $channel,
            'airline' => (string) ($ticket->airline_code ?? $row['airline'] ?? '—'),
            'supplier' => ucfirst(str_replace('_', ' ', (string) ($ticket->provider ?? ''))),
            'supplierId' => $ticket->supplierBooking?->supplier_connection_id
                ? 'SC-'.str_pad((string) $ticket->supplierBooking->supplier_connection_id, 5, '0', STR_PAD_LEFT)
                : '',
            'bookingId' => $booking ? DashboardBookingResource::publicId($booking) : (string) $ticket->booking_id,
            'pnrOrderId' => $ticket->supplierBooking
                ? DashboardPnrOrderResource::publicId($ticket->supplierBooking)
                : (string) ($ticket->pnr ?? ''),
            'customerId' => $booking?->customer_id ? 'CU-'.$booking->customer_id : '',
            'travellerName' => self::passengerName($ticket),
            'issueStatus' => self::issueStatus((string) ($ticket->status ?? '')),
            'fulfilmentStatus' => 'Fulfilled',
            'paymentStatus' => $paymentStatus,
            'refundStatus' => 'None',
            'refundEligibility' => 'Not Applicable',
            'exchangeEligibility' => 'Not Applicable',
            'voidStatus' => self::voidStatus((string) ($ticket->void_status ?? '')),
            'issuedDate' => $ticket->issued_at?->format('Y-m-d') ?? '',
            'lastModifiedDate' => $ticket->updated_at?->toIso8601String() ?? '',
            'readinessState' => self::readinessState($ticket),
            'currency' => strtoupper((string) ($booking->currency ?? 'PKR')),
            'fareAmount' => (int) ($row['total_fare'] ?? 0),
            'taxAmount' => 0,
            'totalAmount' => (int) ($row['total_fare'] ?? 0),
            'reviewFlags' => [
                'needsReview' => strtolower((string) ($ticket->status ?? '')) === 'failed',
            ],
        ];
    }

    public static function publicId(BookingTicket $ticket): string
    {
        return 'TKT-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT);
    }

    public static function maskTicketNumber(string $number): string
    {
        $clean = preg_replace('/\s+/', '', $number) ?? '';
        if ($clean === '') {
            return '—';
        }
        if (strlen($clean) <= 6) {
            return str_repeat('•', max(0, strlen($clean) - 2)).substr($clean, -2);
        }

        return substr($clean, 0, 3).'••••'.substr($clean, -3);
    }

    protected static function documentType(BookingTicket $ticket): string
    {
        $provider = strtolower((string) ($ticket->provider ?? ''));
        if (str_contains($provider, 'ndc') || $provider === 'duffel') {
            return 'NDC Fulfilment Document';
        }

        return 'E-Ticket';
    }

    protected static function passengerName(BookingTicket $ticket): string
    {
        if ($ticket->passenger !== null) {
            return trim($ticket->passenger->first_name.' '.$ticket->passenger->last_name);
        }

        return 'Passenger';
    }

    protected static function issueStatus(string $status): string
    {
        return match (strtolower($status)) {
            'issued', 'ticketed' => 'Issued',
            'voided' => 'Voided',
            'failed' => 'Failed',
            'pending' => 'Pending',
            default => 'Not Applicable',
        };
    }

    protected static function voidStatus(string $status): string
    {
        return match (strtolower($status)) {
            'voided' => 'Voided',
            'eligible' => 'Within Window',
            'expired' => 'Window Expired',
            default => 'Not Applicable',
        };
    }

    protected static function readinessState(BookingTicket $ticket): string
    {
        if (filled($ticket->issued_at)) {
            return 'ready';
        }

        return 'pending';
    }
}
