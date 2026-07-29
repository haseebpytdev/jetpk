<?php

namespace App\Support\AgentPortal;

use App\Models\AgentDepositRequest;
use App\Models\AgentWalletTransaction;
use App\Services\Agents\AgentWalletService;
use App\Support\Agents\AgentPermission;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Agent wallet overview JSON for Next.js dashboard.
 */
class AgentPortalWalletPresenter
{
    public function __construct(
        protected AgentWalletService $walletService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(User $user, Agent $agent): array
    {
        $summary = $this->walletService->summary($agent);
        $wallet = $summary['wallet'];

        $pendingDeposits = AgentDepositRequest::query()
            ->where('agent_id', $agent->id)
            ->where('status', 'submitted')
            ->latest('id')
            ->limit(10)
            ->get();

        $recentTransactions = AgentWalletTransaction::query()
            ->where('agent_wallet_id', $wallet->id)
            ->latest('id')
            ->limit(10)
            ->get();

        return [
            'ok' => true,
            'summary' => [
                'balance' => (float) $summary['balance'],
                'available_balance' => (float) $summary['available_balance'],
                'pending_deposits' => (float) $summary['pending_deposits'],
                'credit_limit' => $summary['credit_limit'] !== null ? (float) $summary['credit_limit'] : null,
                'credit_enabled' => (bool) $summary['credit_enabled'],
                'currency' => (string) $summary['currency'],
                'wallet_status' => (string) ($wallet->status?->value ?? $wallet->status),
                'last_updated' => $wallet->updated_at?->toIso8601String(),
            ],
            'pending_deposit_count' => $pendingDeposits->count(),
            'recent_ledger_entries' => $this->presentTransactions($recentTransactions),
            'capabilities' => [
                'can_view_ledger' => $user->hasAgentPermission(AgentPermission::LedgerView),
                'can_create_deposit' => $user->hasAgentPermission(AgentPermission::PaymentsUpload),
            ],
            'quick_actions' => $this->presentQuickActions($user),
        ];
    }

    /**
     * @param  Collection<int, AgentWalletTransaction>  $transactions
     * @return list<array<string, mixed>>
     */
    private function presentTransactions(Collection $transactions): array
    {
        return $transactions->map(fn (AgentWalletTransaction $tx) => [
            'reference' => (string) ($tx->reference ?? 'TXN-'.$tx->id),
            'date' => $tx->created_at?->toIso8601String(),
            'type' => (string) ($tx->type?->value ?? $tx->type),
            'direction' => (float) $tx->amount >= 0 ? 'credit' : 'debit',
            'amount' => abs((float) $tx->amount),
            'currency' => (string) ($tx->wallet?->currency ?? 'PKR'),
            'balance_after' => (float) $tx->balance_after,
            'description' => (string) ($tx->description ?? ''),
            'status' => (string) ($tx->status?->value ?? $tx->status),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentQuickActions(User $user): array
    {
        $actions = [];

        if ($user->hasAgentPermission(AgentPermission::LedgerView)) {
            $actions[] = [
                'code' => 'view_ledger',
                'label' => 'View full ledger',
                'available' => true,
                'url' => '/agent/wallet/ledger',
            ];
        }

        if ($user->hasAgentPermission(AgentPermission::PaymentsUpload)) {
            $actions[] = [
                'code' => 'request_deposit',
                'label' => 'Request deposit',
                'available' => true,
                'url' => '/agent/deposits/new',
            ];
        }

        return $actions;
    }
}
