<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardSettingsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSettingsController extends Controller
{
    public function __construct(
        protected DashboardSettingsReadService $settings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return DashboardReadOnlyEnvelope::success(
            $this->settings->overview($request->user()),
            staleAfter: now()->addSeconds(300)->toIso8601String(),
        );
    }

    public function general(Request $request): JsonResponse
    {
        return DashboardReadOnlyEnvelope::success(
            $this->settings->general($request->user()),
            staleAfter: now()->addSeconds(300)->toIso8601String(),
        );
    }

    public function security(Request $request): JsonResponse
    {
        return DashboardReadOnlyEnvelope::success(
            $this->settings->security($request->user()),
            staleAfter: now()->addSeconds(300)->toIso8601String(),
        );
    }

    public function notifications(Request $request): JsonResponse
    {
        return DashboardReadOnlyEnvelope::success(
            $this->settings->notifications($request->user()),
            staleAfter: now()->addSeconds(300)->toIso8601String(),
        );
    }

    public function integrations(Request $request): JsonResponse
    {
        return DashboardReadOnlyEnvelope::success(
            $this->settings->integrations($request->user()),
            staleAfter: now()->addSeconds(300)->toIso8601String(),
        );
    }
}
