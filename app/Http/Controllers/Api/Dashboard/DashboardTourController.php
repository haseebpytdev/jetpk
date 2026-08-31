<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\DashboardTourService;
use App\Support\BackOffice\BackOfficePortalAccess;
use App\Support\Dashboard\DashboardPermissionResolver;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DashboardTourController extends Controller
{
    public function __construct(
        protected DashboardTourService $tours,
    ) {}

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
            $this->tours->presentForBackOffice($user, $portalType),
            staleAfter: now()->addMinutes(5)->toIso8601String(),
            recordCount: 1,
        );
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return DashboardReadOnlyEnvelope::error('unauthenticated', 'Sign in is required.', 401);
        }

        if (! DashboardPermissionResolver::canViewDashboard($user)) {
            return DashboardReadOnlyEnvelope::error('forbidden', 'You do not have permission to update this data.', 403);
        }

        if ($request->filled('user_id') || $request->filled('userId')) {
            return DashboardReadOnlyEnvelope::error('invalid_request', 'user_id is not accepted.', 422);
        }

        $validated = $request->validate([
            'tour_key' => ['required', 'string'],
            'status' => ['nullable', 'string', 'in:completed,skipped'],
            'restart' => ['sometimes', 'boolean'],
            'portal' => ['sometimes', 'string', 'in:admin,staff'],
        ]);

        try {
            $tours = $this->tours->update(
                $user,
                (string) $validated['tour_key'],
                isset($validated['status']) ? (string) $validated['status'] : null,
                (bool) ($validated['restart'] ?? false),
            );
        } catch (InvalidArgumentException $e) {
            return DashboardReadOnlyEnvelope::error('invalid_request', $e->getMessage(), 422);
        }

        return DashboardReadOnlyEnvelope::success(
            ['ok' => true, 'tours' => $tours],
            staleAfter: now()->addMinutes(5)->toIso8601String(),
            recordCount: 1,
        );
    }
}
