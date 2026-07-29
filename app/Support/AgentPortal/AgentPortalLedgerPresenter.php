<?php

namespace App\Support\AgentPortal;

use App\Models\AgentWalletTransaction;
use App\Services\Agents\AgentWalletService;
use App\Models\Agent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Agent wallet ledger JSON for Next.js dashboard.
 */
class AgentPortalLedgerPresenter
{
    public function __construct(
        protected AgentWalletService $walletService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function presentIndex(
        Agent $agent,
        LengthAwarePaginator $transactions,
        array $filters,
    ): array {
        $summary = $this->walletService->summary($agent);

        return [
            'ok' => true,
            'summary' => [
                'balance' => (float) $summary['balance'],
                'available_balance' => (float) $summary['available_balance'],
                'currency' => (string) $summary['currency'],
            ],
            'filters' => $filters,
            'allowed_filters' => [
                'type' => ['credit', 'debit', 'deposit', 'booking', 'adjustment'],
                'status' => ['pending', 'posted', 'reversed'],
            ],
            'entries' => collect($transactions->items())
                ->map(fn (AgentWalletTransaction $tx) => $this->presentEntry($tx))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentEntry(AgentWalletTransaction $tx): array
    {
        $tx->loadMissing(['depositRequest', 'creator']);

        $bookingReference = null;
        $depositReference = null;
        if ($tx->depositRequest !== null) {
            $depositReference = (string) ($tx->depositRequest->reference ?? 'DEP-'.$tx->depositRequest->id);
        }

        $meta = is_array($tx->meta) ? $tx->meta : [];
        if (filled($meta['booking_reference'] ?? null)) {
            $bookingReference = (string) $meta['booking_reference'];
        }

        return [
            'reference' => (string) ($tx->reference ?? 'TXN-'.$tx->id),
            'date' => $tx->created_at?->toIso8601String(),
            'type' => (string) ($tx->type?->value ?? $tx->type),
            'direction' => (float) $tx->amount >= 0 ? 'credit' : 'debit',
            'amount' => abs((float) $tx->amount),
            'currency' => (string) ($tx->wallet?->currency ?? 'PKR'),
            'balance_after' => (float) $tx->balance_after,
            'booking_reference' => $bookingReference,
            'deposit_reference' => $depositReference,
            'description' => (string) ($tx->description ?? ''),
            'status' => (string) ($tx->status?->value ?? $tx->status),
            'created_by' => $tx->creator?->name,
        ];
    }
}
