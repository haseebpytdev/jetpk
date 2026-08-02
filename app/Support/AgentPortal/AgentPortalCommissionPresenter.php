<?php

namespace App\Support\AgentPortal;

use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\AgentCommissionStatement;
use App\Services\Agents\AgentCommissionService;
use Illuminate\Support\Collection;

/**
 * Agent owner commission ledger JSON presenter.
 */
class AgentPortalCommissionPresenter
{
    public function __construct(
        protected AgentCommissionService $commissionService,
    ) {}

    /**
     * @param  Collection<int, AgentCommissionEntry>  $entries
     * @param  Collection<int, AgentCommissionStatement>  $statements
     * @return array<string, mixed>
     */
    public function presentIndex(Agent $agent, Collection $entries, Collection $statements): array
    {
        $pending = (float) $entries->where('status', 'pending')->sum('commission_amount');
        $approved = (float) $entries->where('status', 'approved')->sum('commission_amount');
        $paid = (float) $entries->where('status', 'paid')->sum('commission_amount');

        return [
            'ok' => true,
            'balance' => $this->commissionService->calculateBalance($agent),
            'totals' => [
                'pending' => $pending,
                'approved' => $approved,
                'paid' => $paid,
                'currency' => 'PKR',
            ],
            'entries' => $entries
                ->sortByDesc('created_at')
                ->take(50)
                ->map(fn (AgentCommissionEntry $entry): array => [
                    'id' => $entry->id,
                    'booking_reference' => $entry->booking?->booking_reference,
                    'amount' => (float) $entry->commission_amount,
                    'currency' => (string) ($entry->booking?->currency ?? 'PKR'),
                    'status' => (string) ($entry->status?->value ?? $entry->status),
                    'created_at' => $entry->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'statements' => $statements
                ->sortByDesc('created_at')
                ->take(20)
                ->map(fn (AgentCommissionStatement $statement): array => [
                    'id' => $statement->id,
                    'reference' => $statement->statement_number ?? ('STMT-'.$statement->id),
                    'period_start' => $statement->period_start?->toDateString(),
                    'period_end' => $statement->period_end?->toDateString(),
                    'total_amount' => (float) ($statement->earned_total ?? 0),
                    'status' => (string) ($statement->status?->value ?? $statement->status ?? 'draft'),
                    'detail_url' => '/agent/commissions/statements/'.$statement->id,
                ])
                ->values()
                ->all(),
        ];
    }

    public function presentStatement(AgentCommissionStatement $statement): array
    {
        $statement->loadMissing(['entries.booking']);

        return [
            'ok' => true,
            'statement' => [
                'id' => $statement->id,
                'reference' => $statement->statement_number ?? ('STMT-'.$statement->id),
                'period_start' => $statement->period_start?->toDateString(),
                'period_end' => $statement->period_end?->toDateString(),
                'total_amount' => (float) ($statement->earned_total ?? 0),
                'status' => (string) ($statement->status?->value ?? $statement->status ?? 'draft'),
            ],
            'entries' => $statement->entries
                ->map(fn (AgentCommissionEntry $entry): array => [
                    'booking_reference' => $entry->booking?->booking_reference,
                    'amount' => (float) $entry->commission_amount,
                    'status' => (string) ($entry->status?->value ?? $entry->status),
                ])
                ->values()
                ->all(),
        ];
    }
}
