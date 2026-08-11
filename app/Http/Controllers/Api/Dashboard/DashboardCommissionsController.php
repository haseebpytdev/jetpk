<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardCommissionsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardCommissionsController extends Controller
{
    public function __construct(
        protected DashboardCommissionsReadService $commissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->commissions->overview($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            $result,
            staleAfter: now()->addSeconds(60)->toIso8601String(),
            recordCount: count($result['agents']),
        );
    }
}
