<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardCommunicationsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardCommunicationsController extends Controller
{
    public function __construct(
        protected DashboardCommunicationsReadService $communications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->communications->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'logs' => $result['items'],
                'summary' => $result['summary'],
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(30)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }
}
