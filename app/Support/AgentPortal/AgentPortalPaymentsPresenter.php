<?php

namespace App\Support\AgentPortal;

use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWalletTransaction;
use App\Models\BookingPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Agent payment history JSON — wallet transactions, deposits, and booking payment proofs.
 */
class AgentPortalPaymentsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function presentIndex(Agent $agent, Collection $rows, string $filter, int $page, int $perPage): array
    {
        $total = $rows->count();
        $offset = ($page - 1) * $perPage;
        $pageRows = $rows->slice($offset, $perPage)->values();

        return [
            'ok' => true,
            'filter' => $filter,
            'allowed_filters' => ['all', 'pending', 'paid', 'failed'],
            'payments' => $pageRows->all(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? $offset + 1 : null,
                'to' => $total > 0 ? min($offset + $perPage, $total) : null,
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collectPaymentRows(Agent $agent, string $filter): Collection
    {
        $wallet = $agent->wallet;
        $walletId = $wallet?->id;

        $walletRows = $walletId !== null
            ? AgentWalletTransaction::query()
                ->where('agent_wallet_id', $walletId)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (AgentWalletTransaction $tx) => $this->presentWalletTransaction($tx))
            : collect();

        $depositRows = AgentDepositRequest::query()
            ->where('agent_id', $agent->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AgentDepositRequest $deposit) => $this->presentDeposit($deposit));

        $proofRows = BookingPayment::query()
            ->whereHas('booking', fn (Builder $q) => $q->where('agent_id', $agent->id))
            ->with(['booking'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BookingPayment $payment) => $this->presentPaymentProof($payment));

        $merged = $walletRows->concat($depositRows)->concat($proofRows)->sortByDesc('date')->values();

        if ($filter === 'pending') {
            return $merged->filter(fn (array $row) => in_array($row['payment_status']['code'], ['pending', 'submitted', 'processing'], true))->values();
        }

        if ($filter === 'paid') {
            return $merged->filter(fn (array $row) => in_array($row['payment_status']['code'], ['paid', 'verified', 'succeeded', 'approved', 'posted'], true))->values();
        }

        if ($filter === 'failed') {
            return $merged->filter(fn (array $row) => in_array($row['payment_status']['code'], ['failed', 'rejected', 'cancelled', 'declined'], true))->values();
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentWalletTransaction(AgentWalletTransaction $tx): array
    {
        $status = (string) ($tx->status?->value ?? $tx->status ?? 'posted');

        return [
            'reference' => (string) ($tx->reference ?? 'TXN-'.$tx->id),
            'booking_reference' => null,
            'deposit_reference' => $tx->depositRequest?->reference,
            'date' => $tx->created_at?->toIso8601String(),
            'method' => 'wallet',
            'method_label' => 'Wallet',
            'amount' => abs((float) $tx->amount),
            'currency' => (string) ($tx->wallet?->currency ?? 'PKR'),
            'payment_status' => [
                'code' => $status,
                'label' => ucfirst(str_replace('_', ' ', $status)),
            ],
            'source' => 'wallet',
            'retry_available' => false,
            'receipt_available' => false,
            'detail_url' => '/agent/wallet/ledger',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDeposit(AgentDepositRequest $deposit): array
    {
        $status = (string) ($deposit->status?->value ?? $deposit->status);

        return [
            'reference' => (string) ($deposit->reference ?? 'DEP-'.$deposit->id),
            'booking_reference' => null,
            'deposit_reference' => (string) ($deposit->reference ?? 'DEP-'.$deposit->id),
            'date' => $deposit->created_at?->toIso8601String(),
            'method' => (string) ($deposit->payment_method ?? 'bank_transfer'),
            'method_label' => ucfirst(str_replace('_', ' ', (string) ($deposit->payment_method ?? 'bank transfer'))),
            'amount' => (float) $deposit->amount,
            'currency' => (string) ($deposit->currency ?? 'PKR'),
            'payment_status' => [
                'code' => $status,
                'label' => ucfirst(str_replace('_', ' ', $status)),
            ],
            'source' => 'deposit',
            'retry_available' => $status === 'rejected',
            'receipt_available' => false,
            'detail_url' => '/agent/deposits',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPaymentProof(BookingPayment $payment): array
    {
        $booking = $payment->booking;
        $reference = $booking?->booking_reference;
        $status = (string) ($payment->status?->value ?? $payment->status ?? 'pending');

        return [
            'reference' => filled($payment->payment_reference) ? (string) $payment->payment_reference : 'PAY-'.$payment->id,
            'booking_reference' => $booking?->display_reference,
            'deposit_reference' => null,
            'date' => $payment->created_at?->toIso8601String(),
            'method' => (string) ($payment->method?->value ?? $payment->method ?? 'manual'),
            'method_label' => ucfirst(str_replace('_', ' ', (string) ($payment->method?->value ?? 'manual'))),
            'amount' => (float) $payment->amount,
            'currency' => (string) ($payment->currency ?? $booking?->currency ?? 'PKR'),
            'payment_status' => [
                'code' => $status,
                'label' => ucfirst(str_replace('_', ' ', $status)),
            ],
            'booking_status' => $booking ? AgentPortalStatusPresenter::bookingStatus($booking) : null,
            'source' => 'payment_proof',
            'retry_available' => false,
            'receipt_available' => false,
            'detail_url' => $reference ? '/agent/bookings/'.$reference : null,
        ];
    }
}
