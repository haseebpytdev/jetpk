<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardPnrOrdersReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardPnrOrdersController extends Controller
{
    public function __construct(
        protected DashboardPnrOrdersReadService $pnrs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->pnrs->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'pnrs' => $result['items'],
                'summary' => $this->summary($result['items']),
                'facets' => $result['facets'],
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(30)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $record): JsonResponse
    {
        $detail = $this->pnrs->detail($request->user(), $record);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested record was not found.', 404, 'PNR-NOT-FOUND');
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
        return [
            'totalRecords' => count($items),
            'gdsPnrCount' => count(array_filter($items, static fn (array $row): bool => ($row['referenceType'] ?? '') === 'GDS PNR')),
            'ndcOrderCount' => count(array_filter($items, static fn (array $row): bool => ($row['referenceType'] ?? '') === 'NDC Order')),
            'reviewRequired' => count(array_filter($items, static fn (array $row): bool => ($row['reviewFlags']['needsReview'] ?? false) === true)),
            'currency' => $items[0]['currency'] ?? 'PKR',
        ];
    }
}
