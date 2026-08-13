<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardSystemHealthReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSystemHealthController extends Controller
{
    public function __construct(
        protected DashboardSystemHealthReadService $health,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $result = $this->health->overview($request->user());

        return DashboardReadOnlyEnvelope::success(
            $result,
            staleAfter: now()->addSeconds(30)->toIso8601String(),
            recordCount: count($result['checklist']),
        );
    }

    public function goLive(Request $request): JsonResponse
    {
        $result = $this->health->goLive($request->user());

        return DashboardReadOnlyEnvelope::success(
            $result,
            staleAfter: now()->addSeconds(15)->toIso8601String(),
            recordCount: count($result['checklist']),
        );
    }
}
