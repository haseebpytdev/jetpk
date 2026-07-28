<?php

namespace App\Http\Resources\Dashboard;

use App\Models\SupplierBooking;

final class DashboardPnrOrderDetailResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(SupplierBooking $record): array
    {
        $summary = DashboardPnrOrderResource::fromModel($record);

        return [
            'summary' => $summary,
            'routeSummary' => [
                'origin' => $summary['origin'],
                'destination' => $summary['destination'],
                'itinerarySummary' => $summary['itinerarySummary'],
            ],
            'passengerSummary' => [
                'count' => $summary['travellerCount'],
                'displayNames' => $summary['travellerNames'],
            ],
            'supplierLocator' => $summary['externalReference'] !== '—' ? $summary['externalReference'] : null,
            'airlineLocator' => null,
            'retrieveState' => $summary['retrieveState'],
            'cancellationState' => $summary['cancellationState'],
            'ticketingReadiness' => $summary['ticketingReadiness'],
            'synchronizationStatus' => $summary['synchronizationStatus'],
            'channelModel' => [
                'channel' => $summary['channel'],
                'recordType' => $summary['referenceType'],
                'gdsFieldsAvailable' => $summary['channel'] === 'Sabre GDS',
                'ndcFieldsAvailable' => str_contains((string) $summary['channel'], 'NDC'),
            ],
        ];
    }
}
