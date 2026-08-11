<?php

namespace App\Services\Dashboard\Api;

use App\Models\AgentApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardAgentApplicationsReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function paginate(User $user, Request $request): array
    {
        Gate::authorize('platform.admin');

        $query = AgentApplication::query()->orderByDesc('created_at');
        $status = (string) $request->query('status', '');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 25)));
        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(static function (AgentApplication $application): array {
            $status = $application->status;
            $contactName = trim(((string) ($application->first_name ?? '')).' '.((string) ($application->last_name ?? '')));

            return [
                'id' => (string) $application->id,
                'agencyName' => (string) ($application->company_name ?? 'Application'),
                'contactName' => $contactName !== '' ? $contactName : (string) ($application->email ?? ''),
                'contactEmail' => (string) ($application->email ?? ''),
                'status' => is_object($status) && property_exists($status, 'value')
                    ? (string) $status->value
                    : (string) $status,
                'submittedAt' => $application->created_at?->toIso8601String() ?? '',
            ];
        })->values()->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pageCount' => $paginator->lastPage(),
            ],
        ];
    }
}
