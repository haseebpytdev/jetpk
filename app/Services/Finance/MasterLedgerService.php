<?php

namespace App\Services\Finance;

use App\Enums\AgentDepositRequestStatus;
use App\Enums\AgentWalletTransactionStatus;
use App\Enums\AgentWalletTransactionType;
use App\Models\Agency;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Read-only platform and agency wallet ledger queries (append-only transaction history).
 */
class MasterLedgerService
{
    /**
     * @return array{
     *     transactions: LengthAwarePaginator,
     *     summary: array<string, float|int|string>,
     *     filters: array<string, string>,
     *     agencies: Collection<int, Agency>,
     *     scope: string
     * }
     */
    public function buildIndex(User $user, Request $request, ?int $agencyId = null): array
    {
        $filters = $this->resolveFilters($request);
        $query = $this->baseQuery($user, $agencyId);
        $this->applyFilters($query, $filters);

        $summaryQuery = clone $query;
        $effectiveAgencyId = $agencyId;
        if ($effectiveAgencyId === null && $filters['agency_id'] !== '' && ctype_digit($filters['agency_id'])) {
            $effectiveAgencyId = (int) $filters['agency_id'];
        }
        $summary = $this->buildSummary($summaryQuery, $effectiveAgencyId);

        $transactions = (clone $query)
            ->with([
                'agency.agencySetting',
                'agent.user',
                'wallet',
                'creator',
                'approver',
                'user',
                'depositRequest',
            ])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $this->attachBookingReferences($transactions);

        $agencies = $agencyId === null && $user->isPlatformAdmin()
            ? Agency::query()->with('agencySetting')->orderBy('name')->get(['id', 'name'])
            : collect();

        return [
            'transactions' => $transactions,
            'summary' => $summary,
            'filters' => $filters,
            'agencies' => $agencies,
            'scope' => $agencyId !== null ? 'agency' : 'platform',
        ];
    }

    public function findForShow(User $user, AgentWalletTransaction $transaction, ?int $agencyId = null): AgentWalletTransaction
    {
        $query = $this->baseQuery($user, $agencyId)->whereKey($transaction->id);
        abort_if(! $query->exists(), 404);

        $transaction->load([
            'agency.agencySetting',
            'agent.user',
            'creator',
            'approver',
            'user',
            'depositRequest',
        ]);

        $bookingId = is_array($transaction->meta) ? (int) ($transaction->meta['booking_id'] ?? 0) : 0;
        if ($bookingId > 0) {
            $transaction->setRelation(
                'booking',
                Booking::query()->find($bookingId),
            );
        }

        return $transaction;
    }

    protected function baseQuery(User $user, ?int $agencyId): Builder
    {
        $query = AgentWalletTransaction::query();

        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        } elseif ($user->isPlatformAdmin() || $user->isStaff()) {
            return $query;
        } else {
            $agent = $user->agent();
            abort_if($agent === null, 403);
            $query->where('agency_id', $agent->agency_id);
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    protected function resolveFilters(Request $request): array
    {
        $direction = $request->string('direction')->toString();
        if (! in_array($direction, ['', 'credit', 'debit'], true)) {
            $direction = '';
        }

        $status = $request->string('status')->toString();
        if ($status !== '' && AgentWalletTransactionStatus::tryFrom($status) === null) {
            $status = '';
        }

        $type = $request->string('type')->toString();
        if ($type !== '' && AgentWalletTransactionType::tryFrom($type) === null) {
            $type = '';
        }

        return [
            'agency_id' => $request->string('agency_id')->toString(),
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'type' => $type,
            'status' => $status,
            'direction' => $direction,
            'booking_ref' => $request->string('booking_ref')->trim()->toString(),
            'actor' => $request->string('actor')->trim()->toString(),
            'currency' => strtoupper($request->string('currency')->trim()->toString()),
            'q' => $request->string('q')->trim()->toString(),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['agency_id'] !== '' && ctype_digit($filters['agency_id'])) {
            $query->where('agency_id', (int) $filters['agency_id']);
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['direction'] === 'credit') {
            $query->whereColumn('balance_after', '>', 'balance_before');
        } elseif ($filters['direction'] === 'debit') {
            $query->whereColumn('balance_after', '<', 'balance_before');
        }

        if ($filters['currency'] !== '' && strlen($filters['currency']) === 3) {
            $query->whereHas('wallet', fn (Builder $w): Builder => $w->where('currency', $filters['currency']));
        }

        if ($filters['booking_ref'] !== '') {
            $bookingIds = Booking::query()
                ->where('booking_reference', 'like', '%'.$filters['booking_ref'].'%')
                ->pluck('id');
            if ($bookingIds->isEmpty()) {
                $query->whereRaw('0 = 1');
            } else {
                $query->where(function (Builder $q) use ($bookingIds): void {
                    foreach ($bookingIds as $id) {
                        $q->orWhere('meta->booking_id', (int) $id);
                    }
                });
            }
        }

        if ($filters['actor'] !== '') {
            $term = '%'.$filters['actor'].'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->whereHas('creator', fn (Builder $u): Builder => $u->where('name', 'like', $term)->orWhere('email', 'like', $term))
                    ->orWhereHas('approver', fn (Builder $u): Builder => $u->where('name', 'like', $term)->orWhere('email', 'like', $term))
                    ->orWhereHas('user', fn (Builder $u): Builder => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
            });
        }

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('reference', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }
    }

    /**
     * @return array<string, float|int|string>
     */
    protected function buildSummary(Builder $query, ?int $agencyId): array
    {
        $creditExpr = 'SUM(CASE WHEN balance_after > balance_before THEN amount ELSE 0 END)';
        $debitExpr = 'SUM(CASE WHEN balance_after < balance_before THEN amount ELSE 0 END)';

        $totals = (clone $query)
            ->selectRaw("COALESCE({$creditExpr}, 0) as total_credits")
            ->selectRaw("COALESCE({$debitExpr}, 0) as total_debits")
            ->toBase()
            ->first();

        $totalCredits = (float) ($totals->total_credits ?? 0);
        $totalDebits = (float) ($totals->total_debits ?? 0);

        $depositQuery = AgentDepositRequest::query();
        if ($agencyId !== null) {
            $depositQuery->where('agency_id', $agencyId);
        }

        $pendingDeposits = (float) (clone $depositQuery)
            ->where('status', AgentDepositRequestStatus::Submitted)
            ->sum('amount');
        $approvedDeposits = (float) (clone $depositQuery)
            ->where('status', AgentDepositRequestStatus::Approved)
            ->sum('amount');

        $walletExposure = 0.0;
        $walletQuery = AgentWallet::query();
        if ($agencyId !== null) {
            $walletQuery->where('agency_id', $agencyId);
        }
        $walletExposure = (float) (clone $walletQuery)->sum('balance');

        return [
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'net_balance' => $totalCredits - $totalDebits,
            'pending_deposits' => $pendingDeposits,
            'approved_deposits' => $approvedDeposits,
            'refund_liabilities' => 0.0,
            'agency_wallet_exposure' => $walletExposure,
            'currency' => 'PKR',
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, AgentWalletTransaction>  $transactions
     */
    protected function attachBookingReferences(LengthAwarePaginator $transactions): void
    {
        $bookingIds = $transactions->getCollection()
            ->map(fn (AgentWalletTransaction $tx): int => is_array($tx->meta) ? (int) ($tx->meta['booking_id'] ?? 0) : 0)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($bookingIds->isEmpty()) {
            return;
        }

        $bookings = Booking::query()
            ->whereIn('id', $bookingIds)
            ->get(['id', 'reference'])
            ->keyBy('id');

        $transactions->getCollection()->transform(function (AgentWalletTransaction $tx) use ($bookings): AgentWalletTransaction {
            $bookingId = is_array($tx->meta) ? (int) ($tx->meta['booking_id'] ?? 0) : 0;
            if ($bookingId > 0 && $bookings->has($bookingId)) {
                $tx->setRelation('booking', $bookings->get($bookingId));
            }

            return $tx;
        });
    }
}
