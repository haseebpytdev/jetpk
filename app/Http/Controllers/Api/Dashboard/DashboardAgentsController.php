<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardAgentsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardAgentsController extends Controller
{
    public function __construct(
        protected DashboardAgentsReadService $agents,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->agents->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'agents' => $result['items'],
                'summary' => $this->summary($result['items']),
                'facets' => $result['facets'],
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(60)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $agent): JsonResponse
    {
        $detail = $this->agents->detail($request->user(), $agent);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested record was not found.', 404, 'AG-NOT-FOUND');
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
            'totalAgents' => count($items),
            'activeAgents' => count(array_filter($items, static fn (array $row): bool => ($row['accountStatus'] ?? '') === 'Active')),
            'verifiedAgents' => count(array_filter($items, static fn (array $row): bool => ($row['verificationStatus'] ?? '') === 'Verified')),
            'agentsWithBookings' => count(array_filter($items, static fn (array $row): bool => (int) ($row['bookingCount'] ?? 0) > 0)),
            'currency' => $items[0]['currency'] ?? 'PKR',
        ];
    }
}
