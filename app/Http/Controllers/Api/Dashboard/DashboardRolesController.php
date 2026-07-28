<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardRolesReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardRolesController extends Controller
{
    public function __construct(
        protected DashboardRolesReadService $roles,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->roles->list($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'roles' => $result['items'],
                'summary' => $result['summary'],
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(300)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $role): JsonResponse
    {
        $detail = $this->roles->detail($request->user(), $role);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested role was not found.', 404, 'ROL-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success($detail, staleAfter: now()->addSeconds(300)->toIso8601String(), recordCount: 1);
    }
}
