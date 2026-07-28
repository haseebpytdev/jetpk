<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardReportsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardReportsController extends Controller
{
    public function __construct(
        protected DashboardReportsReadService $reports,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        return $this->respond($request, 'summary');
    }

    public function bookings(Request $request): JsonResponse
    {
        return $this->respond($request, 'bookings');
    }

    public function payments(Request $request): JsonResponse
    {
        return $this->respond($request, 'payments');
    }

    public function suppliers(Request $request): JsonResponse
    {
        return $this->respond($request, 'suppliers');
    }

    public function agents(Request $request): JsonResponse
    {
        return $this->respond($request, 'agents');
    }

    protected function respond(Request $request, string $section): JsonResponse
    {
        $data = $this->reports->section($request->user(), $request, $section);

        return DashboardReadOnlyEnvelope::success(
            $data,
            staleAfter: now()->addSeconds(300)->toIso8601String(),
            recordCount: count($data['tableRows'] ?? []),
            warnings: $data['warnings'] ?? [],
        );
    }
}
