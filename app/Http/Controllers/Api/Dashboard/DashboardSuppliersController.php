<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardSuppliersReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSuppliersController extends Controller
{
    public function __construct(
        protected DashboardSuppliersReadService $suppliers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->suppliers->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'suppliers' => $result['items'],
                'summary' => $this->summary($result['items']),
                'facets' => $result['facets'],
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(120)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $supplier): JsonResponse
    {
        $detail = $this->suppliers->detail($request->user(), $supplier);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested record was not found.', 404, 'SU-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success(
            $detail,
            staleAfter: now()->addSeconds(120)->toIso8601String(),
            recordCount: 1,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function summary(array $items): array
    {
        $active = count(array_filter($items, static fn (array $row): bool => ($row['operationalStatus'] ?? '') === 'Active'));
        $connected = count(array_filter($items, static fn (array $row): bool => ($row['integrationStatus'] ?? '') === 'Connected'));

        return [
            'totalSuppliers' => count($items),
            'activeSuppliers' => $active,
            'connectedSuppliers' => $connected,
            'suppliersRequiringReview' => count(array_filter($items, static fn (array $row): bool => ($row['reviewFlags']['needsReview'] ?? false) === true)),
            'totalOutstandingSettlements' => 0,
            'recentSupplierActivity' => count($items),
            'currency' => $items[0]['currency'] ?? 'PKR',
        ];
    }
}
