<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardSessionResource;
use App\Support\BackOffice\BackOfficePortalAccess;
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

        $access = BackOfficePortalAccess::evaluate($user);
        if (($access['ok'] ?? false) !== true) {
            return DashboardReadOnlyEnvelope::error(
                $access['denial_reason'] ?? 'permission_required',
                $access['message'] ?? 'Back-office access denied.',
                403,
            );
        }

        $portalType = $request->query('portal');
        if (! is_string($portalType) || ! in_array($portalType, ['admin', 'staff'], true)) {
            $portalType = $user->isPlatformAdmin() ? 'admin' : 'staff';
        }

        if ($portalType === 'admin' && ! $user->isPlatformAdmin()) {
            return DashboardReadOnlyEnvelope::error('admin_only', 'Platform admin access is required.', 403);
        }

        if ($portalType === 'staff' && ! $user->isStaff() && ! $user->isPlatformAdmin()) {
            return DashboardReadOnlyEnvelope::error('permission_required', 'Staff access is required.', 403);
        }

        return DashboardReadOnlyEnvelope::success(
            DashboardSessionResource::toArray($user, $portalType),
            staleAfter: now()->addMinutes(5)->toIso8601String(),
            recordCount: 1,
        );
    }
}
