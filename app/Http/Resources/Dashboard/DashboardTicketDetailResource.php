<?php

namespace App\Http\Resources\Dashboard;

use App\Models\BookingTicket;

final class DashboardTicketDetailResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(BookingTicket $ticket): array
    {
        $summary = DashboardTicketResource::fromModel($ticket);

        return [
            'summary' => $summary,
            'document' => [
                'type' => $summary['documentType'],
                'maskedNumber' => $summary['maskedExternalId'],
                'issueStatus' => $summary['issueStatus'],
                'voidStatus' => $summary['voidStatus'],
                'readinessState' => $summary['readinessState'],
            ],
            'relationships' => [
                'bookingId' => $summary['bookingId'],
                'pnrOrderId' => $summary['pnrOrderId'],
                'supplierId' => $summary['supplierId'],
            ],
            'passengerDisplaySummary' => $summary['travellerName'],
            'issuedAt' => $ticket->issued_at?->toIso8601String(),
            'updatedAt' => $ticket->updated_at?->toIso8601String(),
        ];
    }
}
