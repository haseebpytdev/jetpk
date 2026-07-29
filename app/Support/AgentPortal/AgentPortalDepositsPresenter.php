<?php

namespace App\Support\AgentPortal;

use App\Models\AgentDepositRequest;
use App\Services\Agents\AgentWalletService;
use App\Models\Agent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Agent deposit requests JSON for Next.js dashboard.
 */
class AgentPortalDepositsPresenter
{
    public function __construct(
        protected AgentWalletService $walletService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function presentIndex(Agent $agent, LengthAwarePaginator $deposits): array
    {
        $summary = $this->walletService->summary($agent);

        return [
            'ok' => true,
            'summary' => [
                'balance' => (float) $summary['balance'],
                'pending_deposits' => (float) $summary['pending_deposits'],
                'currency' => (string) $summary['currency'],
            ],
            'deposits' => collect($deposits->items())
                ->map(fn (AgentDepositRequest $deposit) => $this->presentListItem($deposit))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $deposits->currentPage(),
                'last_page' => $deposits->lastPage(),
                'per_page' => $deposits->perPage(),
                'total' => $deposits->total(),
                'from' => $deposits->firstItem(),
                'to' => $deposits->lastItem(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentCreateForm(Agent $agent): array
    {
        $summary = $this->walletService->summary($agent);

        return [
            'ok' => true,
            'summary' => [
                'balance' => (float) $summary['balance'],
                'currency' => (string) $summary['currency'],
            ],
            'fields' => [
                'amount' => ['required' => true, 'min' => 1, 'max' => 99999999.99],
                'payment_method' => ['required' => false, 'max_length' => 100],
                'reference' => ['required' => false, 'max_length' => 255],
                'agent_note' => ['required' => false, 'max_length' => 2000],
                'proof' => ['required' => false, 'max_kb' => 5120, 'mimes' => ['jpg', 'jpeg', 'png', 'pdf', 'webp']],
            ],
            'submit_url' => '/laravel/agent/deposits',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentListItem(AgentDepositRequest $deposit): array
    {
        $status = (string) ($deposit->status?->value ?? $deposit->status);
        $proofStatus = filled($deposit->proof_path) ? 'uploaded' : 'missing';

        $nextAction = match ($status) {
            'submitted' => ['code' => 'await_approval', 'label' => 'Awaiting approval'],
            'approved' => ['code' => 'credited', 'label' => 'Credited to wallet'],
            'rejected' => ['code' => 'resubmit', 'label' => 'Submit a new deposit'],
            default => ['code' => 'view', 'label' => 'View details'],
        };

        return [
            'deposit_reference' => (string) ($deposit->reference ?? 'DEP-'.$deposit->id),
            'requested_amount' => (float) $deposit->amount,
            'currency' => (string) ($deposit->currency ?? 'PKR'),
            'date' => $deposit->created_at?->toIso8601String(),
            'method' => (string) ($deposit->payment_method ?? ''),
            'proof_status' => $proofStatus,
            'approval_status' => [
                'code' => $status,
                'label' => ucfirst(str_replace('_', ' ', $status)),
            ],
            'credited_amount' => $status === 'approved' ? (float) $deposit->amount : null,
            'rejection_reason' => $status === 'rejected' && filled($deposit->admin_note)
                ? (string) $deposit->admin_note
                : null,
            'next_action' => $nextAction,
        ];
    }
}
