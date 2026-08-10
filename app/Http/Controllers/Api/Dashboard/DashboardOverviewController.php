<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardOverviewResource;
use App\Services\Dashboard\AgencyDashboardService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use App\Support\Dashboard\DashboardSupplierStatusPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardOverviewController extends Controller
{
    public function __construct(
        protected AgencyDashboardService $dashboardService,
        protected DashboardSupplierStatusPresenter $supplierStatusPresenter,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $dashboard = $this->dashboardService->build($user);
        $portal = $user->isPlatformAdmin() ? 'admin' : 'staff';
        $payload = DashboardOverviewResource::fromAgencyDashboard(
            $dashboard,
            $user,
            $portal,
            $this->dashboardService->buildSupportAlerts($user, $portal),
            $this->supplierStatusPresenter->present(),
        );

        return DashboardReadOnlyEnvelope::success(
            $payload,
            referenceTime: $payload['referenceTime'],
            staleAfter: now()->addSeconds(60)->toIso8601String(),
            recordCount: count($payload['operationalQueues']),
        );
    }
}
