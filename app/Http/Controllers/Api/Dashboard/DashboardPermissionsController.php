<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardRbacMatrixResource;
use App\Services\Dashboard\Api\DashboardPermissionsReadService;
use App\Support\Dashboard\DashboardPermissionResolver;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardPermissionsController extends Controller
{
    public function __construct(
        protected DashboardPermissionsReadService $permissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->permissions->list($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'permissions' => $result['items'],
                'summary' => $result['summary'],
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(300)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $permission): JsonResponse
    {
        $detail = $this->permissions->detail($request->user(), $permission);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested permission was not found.', 404, 'PRM-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success($detail, staleAfter: now()->addSeconds(300)->toIso8601String(), recordCount: 1);
    }

    public function matrix(Request $request): JsonResponse
    {
        DashboardPermissionResolver::assertPermission($request->user(), 'roles.view');

        return DashboardReadOnlyEnvelope::success(
            DashboardRbacMatrixResource::build($request->query('domain')),
            staleAfter: now()->addSeconds(300)->toIso8601String(),
        );
    }
}
