<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Booking;
use App\Support\Dashboard\DashboardMoneyPresenter;

final class DashboardBookingDetailResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Booking $booking): array
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
        $totalMinor = (int) round((float) ($fare?->total ?? 0));
        $totalMoney = DashboardMoneyPresenter::presentBookingTotal($booking, $totalMinor);
        $baseMoney = DashboardMoneyPresenter::presentMinorUnits(
            (int) round((float) ($fare?->base_fare ?? 0)),
            $totalMoney['currency'],
            $totalMoney['currencySource'],
        );

        return [
            'summary' => $summary,
            'itinerary' => [
                'route' => (string) ($booking->route ?? ''),
                'airline' => (string) ($booking->airline ?? ''),
                'travelDate' => $booking->travel_date?->format('Y-m-d'),
                'returnDate' => null,
            ],
            'passengers' => $passengers,
            'fareSummary' => [
                'currency' => $totalMoney['currency'],
                'currencyStatus' => $totalMoney['currencyStatus'],
                'currencySource' => $totalMoney['currencySource'],
                'baseFare' => $baseMoney['amountMinor'],
                'baseFareMoney' => $baseMoney,
                'taxes' => (int) round((float) ($fare?->taxes ?? 0)),
                'fees' => (int) round((float) ($fare?->fees ?? 0)),
                'markup' => (int) round((float) ($fare?->markup ?? 0)),
                'total' => $totalMoney['amountMinor'],
                'totalMoney' => $totalMoney,
            ],
            'paymentSummary' => [
                'status' => $summary['paymentStatus'],
                'amountPaid' => $summary['amountPaid'],
                'amountPaidMoney' => $summary['amountPaidMoney'],
                'totalAmount' => $summary['totalAmount'],
                'totalMoney' => $summary['totalMoney'],
                'currency' => $summary['currency'],
                'currencyStatus' => $summary['currencyStatus'],
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
    }
}
