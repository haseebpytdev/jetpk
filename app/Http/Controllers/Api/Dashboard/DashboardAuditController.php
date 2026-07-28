<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardAuditReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardAuditController extends Controller
{
    public function __construct(
        protected DashboardAuditReadService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->audit->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'events' => $result['items'],
                'summary' => $result['summary'],
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(30)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $event): JsonResponse
    {
        $detail = $this->audit->detail($request->user(), $event);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested audit event was not found.', 404, 'AUD-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success($detail, staleAfter: now()->addSeconds(30)->toIso8601String(), recordCount: 1);
    }
}
