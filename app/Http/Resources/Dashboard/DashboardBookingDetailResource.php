<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Booking;
use App\Models\User;
use App\Support\Dashboard\DashboardBookingOperationalPresenter;

final class DashboardBookingDetailResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Booking $booking, ?User $viewer = null): array
    {
        $summary = DashboardBookingResource::fromModel($booking);
        $booking->loadMissing(['passengers', 'fareBreakdown', 'payments', 'latestSupplierBooking', 'tickets']);

        $passengers = $booking->passengers
            ->map(static fn ($p): array => [
                'displayName' => trim(implode(' ', array_filter([$p->title, $p->first_name, $p->last_name]))),
                'type' => (string) ($p->passenger_type ?? 'adult'),
            ])
            ->values()
            ->all();

        $fare = $booking->fareBreakdown;

        $payload = [
            'summary' => $summary,
            'itinerary' => [
                'route' => (string) ($booking->route ?? ''),
                'airline' => (string) ($booking->airline ?? ''),
                'travelDate' => $booking->travel_date?->format('Y-m-d'),
                // RETURN_DATE_SOURCE=meta.search_criteria.return_date|returnDate via summary
                'returnDate' => $summary['returnDate'] ?? null,
            ],
            'passengers' => $passengers,
            'fareSummary' => [
                'currency' => strtoupper((string) ($booking->currency ?? 'PKR')),
                'baseFare' => (int) round((float) ($fare?->base_fare ?? 0)),
                'taxes' => (int) round((float) ($fare?->taxes ?? 0)),
                'fees' => (int) round((float) ($fare?->fees ?? 0)),
                'markup' => (int) round((float) ($fare?->markup ?? 0)),
                'total' => (int) round((float) ($fare?->total ?? 0)),
            ],
            'paymentSummary' => [
                'status' => $summary['paymentStatus'],
                'amountPaid' => $summary['amountPaid'],
                'totalAmount' => $summary['totalAmount'],
                'currency' => $summary['currency'],
            ],
            'pnrSummary' => [
                'pnr' => $summary['pnr'] ?: null,
                'supplierReference' => $summary['supplierReference'],
                'channel' => $summary['channel'],
                'supplier' => $summary['supplier'],
                'supplierStatus' => (string) ($booking->supplier_booking_status ?? 'not_started'),
            ],
            'ticketReadiness' => [
                'ticketingStatus' => $summary['ticketingStatus'],
                'ticketCount' => $booking->tickets->count(),
            ],
            'auditMetadata' => [
                'createdAt' => $booking->created_at?->toIso8601String(),
                'updatedAt' => $booking->updated_at?->toIso8601String(),
                'bookingStatus' => $summary['bookingStatus'],
            ],
        ];

        if ($viewer !== null) {
            $payload = array_merge(
                $payload,
                app(DashboardBookingOperationalPresenter::class)->present($booking, $viewer),
            );
        }

        return $payload;
    }
}
