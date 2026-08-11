<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardMarkupsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardMarkupsController extends Controller
{
    public function __construct(
        protected DashboardMarkupsReadService $markups,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->markups->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            ['markups' => $result['items']],
            pagination: $result['pagination'],
            staleAfter: now()->addSeconds(60)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }
}
