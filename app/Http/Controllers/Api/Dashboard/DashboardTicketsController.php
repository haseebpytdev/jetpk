<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardTicketsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardTicketsController extends Controller
{
    public function __construct(
        protected DashboardTicketsReadService $tickets,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->tickets->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'tickets' => $result['items'],
                'summary' => $this->summary($result['items']),
                'facets' => $result['facets'],
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(30)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $ticket): JsonResponse
    {
        $detail = $this->tickets->detail($request->user(), $ticket);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested record was not found.', 404, 'TKT-NOT-FOUND');
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
            'totalTickets' => count($items),
            'issuedCount' => count(array_filter($items, static fn (array $row): bool => ($row['issueStatus'] ?? '') === 'Issued')),
            'pendingCount' => count(array_filter($items, static fn (array $row): bool => ($row['issueStatus'] ?? '') === 'Pending')),
            'voidedCount' => count(array_filter($items, static fn (array $row): bool => ($row['voidStatus'] ?? '') === 'Voided')),
            'currency' => $items[0]['currency'] ?? 'PKR',
        ];
    }
}
