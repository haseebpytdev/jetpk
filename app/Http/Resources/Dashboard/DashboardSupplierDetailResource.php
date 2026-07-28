<?php

namespace App\Http\Resources\Dashboard;

use App\Models\SupplierConnection;

final class DashboardSupplierDetailResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(SupplierConnection $connection): array
    {
        $summary = DashboardSupplierResource::fromModel($connection);

        return [
            'summary' => $summary,
            'configuration' => [
                'environment' => $connection->environment?->value ?? 'sandbox',
                'baseUrlConfigured' => filled($connection->base_url),
                'status' => $connection->status?->value ?? 'inactive',
                'isActive' => $connection->isActive(),
                'lastTestedAt' => $connection->last_tested_at?->toIso8601String(),
                'lastTestStatus' => (string) ($connection->last_test_status ?? ''),
                'channelStates' => $summary['channelStates'],
                'resultSourceState' => $summary['resultSourceState'],
                'capabilities' => $summary['capabilities'],
            ],
            'activity' => [
                'bookingCount' => (int) ($connection->supplier_bookings_count ?? 0),
                'lastBookingActivity' => $connection->updated_at?->toIso8601String(),
                'lastFailureSummary' => DashboardSupplierResource::fromModel($connection)['lastFailureSummary'],
            ],
        ];
    }
}
