<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardBookingsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardBookingsController extends Controller
{
    public function __construct(
        protected DashboardBookingsReadService $bookings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->bookings->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'bookings' => $result['items'],
                'summary' => $this->summary($result['items']),
                'facets' => $result['facets'],
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(30)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $booking): JsonResponse
    {
        $detail = $this->bookings->detail($request->user(), $booking);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested record was not found.', 404, 'BK-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success(
            $detail,
            staleAfter: now()->addSeconds(30)->toIso8601String(),
            recordCount: 1,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function summary(array $items): array
    {
        $currency = $items[0]['currency'] ?? 'PKR';
        $confirmed = count(array_filter($items, static fn (array $row): bool => ($row['bookingStatus'] ?? '') === 'confirmed'));
        $pending = count(array_filter($items, static fn (array $row): bool => ($row['bookingStatus'] ?? '') === 'pending'));
        $cancelledOrFailed = count(array_filter($items, static fn (array $row): bool => in_array($row['bookingStatus'] ?? '', ['cancelled', 'failed'], true)));
        $paid = count(array_filter($items, static fn (array $row): bool => ($row['paymentStatus'] ?? '') === 'paid'));
        $outstanding = array_sum(array_map(static fn (array $row): int => max(0, (int) ($row['totalAmount'] ?? 0) - (int) ($row['amountPaid'] ?? 0)), $items));

        return [
            'totalDisplayed' => count($items),
            'confirmed' => $confirmed,
            'pending' => $pending,
            'cancelledOrFailed' => $cancelledOrFailed,
            'paid' => $paid,
            'outstandingAmount' => $outstanding,
            'currency' => $currency,
        ];
    }
}
