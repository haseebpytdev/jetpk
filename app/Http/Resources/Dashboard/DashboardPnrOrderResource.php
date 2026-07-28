<?php

namespace App\Http\Resources\Dashboard;

use App\Enums\SupplierProvider;
use App\Models\SupplierBooking;
use App\Support\Bookings\BookingListPresenter;
use App\Support\Suppliers\SabreSupplierChannelConfig;

final class DashboardPnrOrderResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(SupplierBooking $record): array
    {
        $record->loadMissing(['booking.passengers', 'booking.contact', 'booking.agent.user', 'supplierConnection']);
        $booking = $record->booking;
        $row = $booking ? BookingListPresenter::toListRow($booking) : [];
        if ($booking !== null) {
            $booking->loadMissing('agent');
        }
        $provider = strtolower((string) ($record->provider ?? ''));
        $channel = self::resolveChannel($provider, $record);
        $recordType = self::recordType($provider, $channel);

        return [
            'id' => self::publicId($record),
            'externalReference' => self::safeLocator($record),
            'referenceType' => $recordType,
            'channel' => $channel,
            'supplierId' => $record->supplier_connection_id ? 'SC-'.str_pad((string) $record->supplier_connection_id, 5, '0', STR_PAD_LEFT) : '',
            'supplierName' => self::supplierName($record),
            'airline' => (string) ($row['airline'] ?? '—'),
            'bookingId' => $booking ? DashboardBookingResource::publicId($booking) : (string) $record->booking_id,
            'customerId' => $booking?->customer_id ? 'CU-'.$booking->customer_id : '',
            'customerName' => (string) ($row['customer_name'] ?? 'Guest'),
            'agentId' => ($booking?->agent_id && $booking->agent) ? DashboardAgentResource::publicId($booking->agent) : null,
            'agentName' => (string) ($row['agent_name'] ?? '') ?: null,
            'travellerCount' => (int) ($row['passengers_count'] ?? 0),
            'travellerNames' => self::passengerDisplayNames($booking),
            'itinerarySummary' => (string) ($row['route'] ?? '—'),
            'origin' => self::splitRoute((string) ($row['route'] ?? ''))[0],
            'destination' => self::splitRoute((string) ($row['route'] ?? ''))[1],
            'departureDate' => (string) ($row['travel_date'] ?? ''),
            'returnDate' => null,
            'tripType' => 'one_way',
            'cabin' => 'Economy',
            'lifecycleStatus' => self::lifecycleStatus((string) ($record->status ?? '')),
            'fulfilmentStatus' => 'Pending',
            'paymentStatus' => self::paymentStatus((string) ($row['payment_status'] ?? '')),
            'ticketingStatus' => self::ticketingStatus((string) ($row['ticketing_status'] ?? '')),
            'ticketingDeadline' => null,
            'cancellationEligibility' => self::cancellationEligibility($booking),
            'queueReviewStatus' => 'None',
            'createdDate' => $record->created_at?->format('Y-m-d') ?? '',
            'lastModifiedDate' => $record->updated_at?->toIso8601String() ?? '',
            'lastSupplierActivity' => $record->created_at_supplier?->toIso8601String(),
            'linkedTicketIds' => [],
            'linkedTransactionIds' => [],
            'bookingValue' => (int) ($row['total_fare'] ?? 0),
            'currency' => strtoupper((string) ($booking->currency ?? 'PKR')),
            'notesSummary' => sprintf('%s record — read-only summary.', $recordType),
            'showGdsTicketingLimitation' => $channel === 'Sabre GDS',
            'recordType' => strtolower(str_replace(' ', '_', $recordType)),
            'supplier' => $provider,
            'retrieveState' => filled($record->pnr) ? 'retrieved' : 'pending',
            'cancellationState' => self::cancellationState($booking),
            'ticketingReadiness' => self::ticketingReadiness($booking),
            'synchronizationStatus' => 'synced',
            'reviewFlags' => [
                'needsReview' => (string) ($record->status ?? '') === 'failed',
            ],
        ];
    }

    public static function publicId(SupplierBooking $record): string
    {
        return 'PNR-'.str_pad((string) $record->id, 5, '0', STR_PAD_LEFT);
    }

    protected static function safeLocator(SupplierBooking $record): string
    {
        $pnr = trim((string) ($record->pnr ?? ''));
        if ($pnr !== '') {
            return $pnr;
        }

        $ref = trim((string) ($record->supplier_reference ?? ''));
        if ($ref !== '') {
            return $ref;
        }

        return '—';
    }

    protected static function supplierName(SupplierBooking $record): string
    {
        if ($record->supplierConnection !== null) {
            return DashboardSupplierResource::fromModel($record->supplierConnection)['supplierName'];
        }

        return ucfirst(str_replace('_', ' ', (string) ($record->provider ?? 'supplier')));
    }

    protected static function resolveChannel(string $provider, SupplierBooking $record): string
    {
        if ($provider === SupplierProvider::Sabre->value) {
            $connection = $record->supplierConnection;
            if ($connection !== null) {
                $config = SabreSupplierChannelConfig::fromConnection($connection);
                $summary = is_array($record->raw_summary) ? $record->raw_summary : [];
                $source = strtolower((string) ($summary['channel'] ?? $summary['source_type'] ?? ''));
                if (str_contains($source, 'ndc')) {
                    return 'Sabre NDC';
                }
                if ($config->ndcEnabled && ! $config->gdsEnabled) {
                    return 'Sabre NDC';
                }

                return 'Sabre GDS';
            }

            return 'Sabre GDS';
        }
        if ($provider === SupplierProvider::PiaNdc->value) {
            return 'Sabre NDC';
        }
        if ($provider === SupplierProvider::Duffel->value) {
            return 'Sabre NDC';
        }

        return 'Manual';
    }

    protected static function recordType(string $provider, string $channel): string
    {
        if ($channel === 'Sabre GDS') {
            return 'GDS PNR';
        }
        if (str_contains($channel, 'NDC') || $provider === SupplierProvider::PiaNdc->value) {
            return 'NDC Order';
        }
        if ($provider === SupplierProvider::Duffel->value) {
            return 'NDC Order';
        }

        return 'Manual Reference';
    }

    /**
     * @return list<string>
     */
    protected static function passengerDisplayNames(?\App\Models\Booking $booking): array
    {
        if ($booking === null) {
            return [];
        }
        $booking->loadMissing('passengers');

        return $booking->passengers
            ->map(static fn ($p): string => trim($p->first_name.' '.$p->last_name))
            ->filter()
            ->take(3)
            ->values()
            ->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected static function splitRoute(string $route): array
    {
        if (preg_match('/([A-Z]{3})\s*[→\-–]\s*([A-Z]{3})/u', $route, $matches) === 1) {
            return [$matches[1], $matches[2]];
        }

        return ['—', '—'];
    }

    protected static function lifecycleStatus(string $status): string
    {
        return match (strtolower($status)) {
            'confirmed', 'active' => 'Active',
            'cancelled' => 'Cancelled',
            'failed' => 'Failed',
            'pending' => 'Pending Supplier',
            default => 'Review Required',
        };
    }

    protected static function paymentStatus(string $status): string
    {
        return match (strtolower($status)) {
            'paid' => 'Paid',
            'partial' => 'Partially Paid',
            'submitted', 'pending' => 'Pending',
            default => 'Unpaid',
        };
    }

    protected static function ticketingStatus(string $status): string
    {
        return match (strtolower($status)) {
            'ticketed', 'issued' => 'Ticketed',
            'pending', 'in_progress' => 'Ready for Ticketing',
            default => 'Not Ticketed',
        };
    }

    protected static function cancellationEligibility(?\App\Models\Booking $booking): string
    {
        if ($booking === null) {
            return 'Unknown';
        }
        if (filled($booking->cancellation_status)) {
            return 'Already Cancelled';
        }

        return 'Supplier Review Required';
    }

    protected static function cancellationState(?\App\Models\Booking $booking): string
    {
        if ($booking === null) {
            return 'unknown';
        }
        if (filled($booking->cancellation_status)) {
            return 'cancelled';
        }

        return 'read_only';
    }

    protected static function ticketingReadiness(?\App\Models\Booking $booking): string
    {
        if ($booking === null) {
            return 'unknown';
        }

        return match (strtolower((string) ($booking->ticketing_status ?? ''))) {
            'ticketed', 'issued' => 'ready',
            'pending' => 'pending',
            default => 'not_ready',
        };
    }
}
