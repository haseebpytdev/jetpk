<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardDepositsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardDepositsController extends Controller
{
    public function __construct(
        protected DashboardDepositsReadService $deposits,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->deposits->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            ['deposits' => $result['items']],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(30)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $deposit): JsonResponse
    {
        $item = $this->deposits->detail($request->user(), $deposit);
        if ($item === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested record was not found.', 404, 'DEP-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success(
            $item,
            staleAfter: now()->addSeconds(30)->toIso8601String(),
            recordCount: 1,
        );
    }
}
