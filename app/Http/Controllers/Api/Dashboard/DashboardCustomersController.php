<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardCustomersReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardCustomersController extends Controller
{
    public function __construct(
        protected DashboardCustomersReadService $customers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->customers->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'customers' => $result['items'],
                'summary' => $this->summary($result['items']),
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(60)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $customer): JsonResponse
    {
        $detail = $this->customers->detail($request->user(), $customer);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested record was not found.', 404, 'CU-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success(
            $detail,
            staleAfter: now()->addSeconds(60)->toIso8601String(),
            recordCount: 1,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function summary(array $items): array
    {
        return [
            'totalDisplayed' => count($items),
            'active' => count(array_filter($items, static fn (array $row): bool => ($row['accountStatus'] ?? '') === 'Active')),
            'verified' => count(array_filter($items, static fn (array $row): bool => ($row['verificationStatus'] ?? '') === 'Verified')),
            'withBookings' => count(array_filter($items, static fn (array $row): bool => (int) ($row['bookingCount'] ?? 0) > 0)),
        ];
    }
}
