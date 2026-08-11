<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardAgentApplicationsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardAgentApplicationsController extends Controller
{
    public function __construct(
        protected DashboardAgentApplicationsReadService $applications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->applications->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            ['applications' => $result['items']],
            pagination: $result['pagination'],
            staleAfter: now()->addSeconds(60)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }
}
