<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\Api\DashboardCmsReadService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardCmsController extends Controller
{
    public function __construct(
        protected DashboardCmsReadService $cms,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->cms->paginate($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'pages' => $result['items'],
                'summary' => [
                    'totalDisplayed' => count($result['items']),
                    'published' => count(array_filter($result['items'], static fn (array $row): bool => ($row['status'] ?? '') === 'published')),
                    'draft' => count(array_filter($result['items'], static fn (array $row): bool => ($row['status'] ?? '') === 'draft')),
                ],
                'facets' => $result['facets'],
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(120)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $page): JsonResponse
    {
        $detail = $this->cms->detail($request->user(), $page);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested CMS page was not found.', 404, 'CMS-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success($detail, staleAfter: now()->addSeconds(120)->toIso8601String(), recordCount: 1);
    }

    public function sections(Request $request, string $page): JsonResponse
    {
        $sections = $this->cms->sections($request->user(), $page);
        if ($sections === [] && $this->cms->detail($request->user(), $page) === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested CMS page was not found.', 404, 'CMS-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success(
            ['sections' => $sections],
            staleAfter: now()->addSeconds(120)->toIso8601String(),
            recordCount: count($sections),
        );
    }
}
