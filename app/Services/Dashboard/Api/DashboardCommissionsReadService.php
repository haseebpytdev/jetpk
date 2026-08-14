<?php

namespace App\Services\Dashboard\Api;

use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\User;
use App\Services\Agents\AgentCommissionService;
use App\Support\Dashboard\DashboardMoneyPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardCommissionsReadService
{
    public function __construct(
        protected AgentCommissionService $commissionService,
    ) {}

    /**
     * @return array{kpis: array<string, float|int>, agents: list<array<string, mixed>>}
     */
    public function overview(User $user, Request $request): array
    {
        Gate::authorize('viewAny', AgentCommissionEntry::class);

        $agencyId = $user->current_agency_id;
        $agentsQuery = Agent::query()->with(['user']);
        if (! $user->isPlatformAdmin()) {
            $agentsQuery->where('agency_id', $agencyId);
        } elseif ($agencyId) {
            $agentsQuery->where('agency_id', $agencyId);
        }

        $agents = $agentsQuery->orderBy('code')->limit(100)->get();

        $entriesQuery = AgentCommissionEntry::query();
        if ($agencyId) {
            $entriesQuery->where('agency_id', $agencyId);
        }

        $pendingCount = (clone $entriesQuery)->where('status', 'pending')->count();
        $pending = (clone $entriesQuery)->where('status', 'pending')->sum('commission_amount');
        $approvedUnpaid = (clone $entriesQuery)->where('status', 'approved')->sum('commission_amount');
        $paidThisMonth = (clone $entriesQuery)->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('commission_amount');

        return [
            'kpis' => [
                'pending' => (float) $pending,
                'pendingCount' => (int) $pendingCount,
                'approvedUnpaid' => (float) $approvedUnpaid,
                'paidThisMonth' => (float) $paidThisMonth,
                'activeAgents' => $agents->where('is_active', true)->count(),
            ],
            'pendingEntries' => (clone $entriesQuery)
                ->where('status', 'pending')
                ->with(['agent.user'])
                ->orderByDesc('id')
                ->limit(25)
                ->get()
                ->map(function (AgentCommissionEntry $entry): array {
                    return [
                        'id' => (string) $entry->id,
                        'agentName' => (string) ($entry->agent?->user?->name ?? $entry->agent?->code ?? 'Agent'),
                        'amount' => (float) $entry->commission_amount,
                        'amountLabel' => DashboardMoneyPresenter::formatDisplayLabel((float) $entry->commission_amount, 'PKR'),
                        'status' => (string) $entry->status,
                        'createdAt' => $entry->created_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all(),
            'agents' => $agents->map(function (Agent $agent): array {
                $balance = $this->commissionService->calculateBalance($agent);

                return [
                    'id' => (string) $agent->id,
                    'code' => (string) ($agent->code ?? ''),
                    'name' => (string) ($agent->user?->name ?? $agent->code ?? 'Agent'),
                    'balance' => is_array($balance) ? $balance : ['available' => (float) $balance],
                ];
            })->values()->all(),
        ];
    }
}
