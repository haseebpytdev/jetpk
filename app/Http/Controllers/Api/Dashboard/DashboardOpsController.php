<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardOpsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JP-OPS-08 operational inbox / live activity / work-queue (EVENT_POLLING).
 * Mark-read is the only non-GET surface required for durable unread state.
 */
class DashboardOpsController extends Controller
{
    public function __construct(
        protected DashboardOpsReadService $ops,
    ) {}

    public function inbox(Request $request): JsonResponse
    {
        $data = $this->ops->inbox($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            $data,
            pagination: $data['pagination'] ?? null,
            staleAfter: now()->addSeconds(2)->toIso8601String(),
            recordCount: count($data['items'] ?? []),
        );
    }

    public function unreadSummary(Request $request): JsonResponse
    {
        $data = $this->ops->unreadSummary($request->user());

        return DashboardReadOnlyEnvelope::success(
            $data,
            staleAfter: now()->addSeconds(2)->toIso8601String(),
            recordCount: 1,
        );
    }

    public function markRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'max:64'],
        ]);

        $data = $this->ops->markRead($request->user(), $validated['ids']);

        return response()->json([
            'ok' => true,
            'data' => $data,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $data = $this->ops->markAllRead($request->user());

        return response()->json([
            'ok' => true,
            'data' => $data,
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $data = $this->ops->events($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            $data,
            staleAfter: now()->addSeconds(2)->toIso8601String(),
            recordCount: count($data['items'] ?? []),
        );
    }

    public function workQueue(Request $request): JsonResponse
    {
        $data = $this->ops->workQueue($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            $data,
            staleAfter: now()->addSeconds(2)->toIso8601String(),
            recordCount: count($data['bookings'] ?? []) + count($data['supportTickets'] ?? []),
        );
    }
}
