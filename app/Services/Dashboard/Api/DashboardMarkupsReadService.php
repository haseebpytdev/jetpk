<?php

namespace App\Services\Dashboard\Api;

use App\Models\MarkupRule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardMarkupsReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function paginate(User $user, Request $request): array
    {
        Gate::authorize('viewAny', MarkupRule::class);

        $query = MarkupRule::query()->orderByDesc('priority')->orderBy('name');
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 25)));
        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(static function (MarkupRule $rule): array {
            $status = $rule->status;
            $statusValue = is_object($status) && property_exists($status, 'value')
                ? (string) $status->value
                : (string) $status;

            return [
                'id' => (string) $rule->id,
                'name' => (string) $rule->name,
                'ruleType' => is_object($rule->rule_type) ? (string) $rule->rule_type->value : (string) $rule->rule_type,
                'value' => (string) $rule->value,
                'valueType' => is_object($rule->value_type) ? (string) $rule->value_type->value : (string) $rule->value_type,
                'priority' => (int) $rule->priority,
                'status' => $statusValue,
                'isActive' => (bool) $rule->is_active,
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
