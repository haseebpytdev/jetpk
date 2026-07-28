<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardSessionResource;
use App\Support\Dashboard\DashboardPermissionResolver;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSessionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return DashboardReadOnlyEnvelope::error('unauthenticated', 'Sign in is required to view this data.', 401);
        }

        if (! DashboardPermissionResolver::canViewDashboard($user)) {
            return DashboardReadOnlyEnvelope::error('forbidden', 'You do not have permission to view this data.', 403);
        }

        return DashboardReadOnlyEnvelope::success(
            DashboardSessionResource::toArray($user),
            staleAfter: now()->addMinutes(5)->toIso8601String(),
            recordCount: 1,
        );
    }
}
