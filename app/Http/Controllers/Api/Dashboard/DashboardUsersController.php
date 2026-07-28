<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardUsersReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardUsersController extends Controller
{
    public function __construct(
        protected DashboardUsersReadService $users,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->users->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'users' => $result['items'],
                'summary' => $result['summary'],
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(60)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $user): JsonResponse
    {
        $detail = $this->users->detail($request->user(), $user);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested user was not found.', 404, 'USR-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success($detail, staleAfter: now()->addSeconds(60)->toIso8601String(), recordCount: 1);
    }
}
