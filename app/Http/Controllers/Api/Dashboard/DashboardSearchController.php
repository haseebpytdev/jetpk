<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardSearchService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSearchController extends Controller
{
    public function __construct(
        protected DashboardSearchService $searchService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = (string) $request->query('q', '');
        $payload = $this->searchService->search($user, $query);

        return DashboardReadOnlyEnvelope::success(
            $payload,
            recordCount: count($payload['results'] ?? []),
            staleAfter: now()->addSeconds(30)->toIso8601String(),
        );
    }
}
