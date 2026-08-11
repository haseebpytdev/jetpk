<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardSupportTicketsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSupportTicketsController extends Controller
{
    public function __construct(
        protected DashboardSupportTicketsReadService $tickets,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->tickets->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'tickets' => $result['items'],
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(60)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $ticket): JsonResponse
    {
        $detail = $this->tickets->detail($request->user(), $ticket);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested support ticket was not found.', 404, 'SUP-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success($detail, staleAfter: now()->addSeconds(60)->toIso8601String(), recordCount: 1);
    }
}
