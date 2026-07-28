<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardPaymentResource;
use App\Services\Dashboard\Api\DashboardPaymentsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardPaymentsController extends Controller
{
    public function __construct(
        protected DashboardPaymentsReadService $payments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->payments->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'transactions' => $result['items'],
                'summary' => $this->summary($result['items']),
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(30)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $payment): JsonResponse
    {
        $result = $this->payments->paginate($request->user(), $request->merge([
            'q' => $payment,
            'pageSize' => 1,
        ]));

        $item = $result['items'][0] ?? null;
        if ($item === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested record was not found.', 404, 'PAY-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success(
            $item,
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

        return [
            'totalDisplayed' => count($items),
            'grossTotal' => array_sum(array_map(static fn (array $row): int => (int) ($row['grossAmount'] ?? 0), $items)),
            'paidTotal' => array_sum(array_map(static fn (array $row): int => (int) ($row['paidAmount'] ?? 0), $items)),
            'outstandingTotal' => array_sum(array_map(static fn (array $row): int => (int) ($row['outstandingAmount'] ?? 0), $items)),
            'currency' => $currency,
        ];
    }
}
