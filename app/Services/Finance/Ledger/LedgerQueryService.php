<?php

namespace App\Services\Finance\Ledger;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Models\Agency;
use App\Models\LedgerTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Read-only queries for double-entry ledger transactions (list, detail, filters).
 */
class LedgerQueryService
{
    /**
     * @return array{
     *     transactions: LengthAwarePaginator,
     *     filters: array<string, string>,
     *     agencies: Collection<int, Agency>,
     *     scope: string,
     *     perPageOptions: list<int>
     * }
     */
    public function buildIndexPayload(Request $request, ?int $agencyScope = null): array
    {
        $perPage = in_array((int) $request->input('per_page'), [25, 50], true)
            ? (int) $request->input('per_page')
            : 25;

        $transactions = $this->paginate($request, $agencyScope, $perPage);

        $agencies = $agencyScope === null
            ? Agency::query()->orderBy('name')->get(Agency::restrictedSelectColumns())
            : collect();

        return [
            'transactions' => $transactions,
            'filters' => $this->extractFilters($request),
            'agencies' => $agencies,
            'scope' => $agencyScope === null ? 'platform' : 'agency',
            'perPageOptions' => [25, 50],
            'perPage' => $perPage,
        ];
    }

    public function paginate(Request $request, ?int $agencyScope = null, int $perPage = 25): LengthAwarePaginator
    {
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction_sort')->toString() === 'asc' ? 'asc' : 'desc';

        $query = $this->baseQuery($request, $agencyScope)
            ->with([
                'agency:'.Agency::restrictedEagerLoad(),
                'booking:id,booking_reference',
                'entries.account:id,code,name,account_type',
                'reversals:id,reversal_of_id,transaction_ref',
            ])
            ->withSum('entries as debit_total', 'debit')
            ->withSum('entries as credit_total', 'credit');

        if ($sort === 'amount_total') {
            $query->orderBy('amount_total', $direction);
        } else {
            $query->orderByRaw('COALESCE(posted_at, occurred_at, created_at) '.$direction);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findForShow(LedgerTransaction $transaction): LedgerTransaction
    {
        return $transaction->load([
            'agency',
            'booking',
            'customer',
            'actorUser',
            'source',
            'reversalOf',
            'reversals',
            'entries.account',
            'entries.agency',
            'entries.booking',
        ]);
    }

    /**
     * @return array{debit: float, credit: float, balanced: bool}
     */
    public function entryTotals(LedgerTransaction $transaction): array
    {
        $entries = $transaction->relationLoaded('entries')
            ? $transaction->entries
            : $transaction->entries()->get();

        $debit = round((float) $entries->sum('debit'), 2);
        $credit = round((float) $entries->sum('credit'), 2);

        return [
            'debit' => $debit,
            'credit' => $credit,
            'balanced' => abs($debit - $credit) < 0.01,
        ];
    }

    /**
     * @return list<LedgerTransaction>
     */
    public function exportRows(Request $request, ?int $agencyScope = null, int $limit = 5000): array
    {
        return $this->baseQuery($request, $agencyScope)
            ->with(['agency:'.Agency::restrictedEagerLoad(), 'booking:id,booking_reference'])
            ->withSum('entries as debit_total', 'debit')
            ->withSum('entries as credit_total', 'credit')
            ->orderByRaw('COALESCE(posted_at, occurred_at, created_at) desc')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    public function csvRows(Request $request, ?int $agencyScope = null, int $limit = 5000): array
    {
        $transactions = $this->exportRows($request, $agencyScope, $limit);

        $rows = [[
            'transaction_ref', 'transaction_type', 'agency', 'booking_ref', 'source_type', 'source_id',
            'actor_identifier', 'currency', 'amount', 'debit_total', 'credit_total', 'balanced',
            'status', 'posted_at', 'created_at',
        ]];

        foreach ($transactions as $tx) {
            $debit = round((float) ($tx->debit_total ?? 0), 2);
            $credit = round((float) ($tx->credit_total ?? 0), 2);

            $rows[] = [
                $tx->transaction_ref,
                $tx->transaction_type->value,
                $tx->agency?->name,
                $tx->booking?->booking_reference,
                $tx->source_type,
                $tx->source_id,
                $tx->actor_identifier,
                $tx->currency,
                (float) $tx->amount_total,
                $debit,
                $credit,
                abs($debit - $credit) < 0.01 ? 'yes' : 'no',
                $tx->status->value,
                $tx->posted_at?->toIso8601String(),
                $tx->created_at?->toIso8601String(),
            ];
        }

        return $rows;
    }

    protected function baseQuery(Request $request, ?int $agencyScope = null): Builder
    {
        $query = LedgerTransaction::query();

        if ($agencyScope !== null) {
            $query->where('agency_id', $agencyScope);
        } elseif ($request->filled('agency_id')) {
            $query->where('agency_id', (int) $request->input('agency_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('posted_at', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('posted_at', '<=', $request->string('date_to')->toString());
        }

        if ($request->filled('transaction_type')) {
            $type = LedgerTransactionType::tryFrom($request->string('transaction_type')->toString());
            if ($type !== null) {
                $query->where('transaction_type', $type);
            }
        }

        if ($request->filled('status')) {
            $status = LedgerTransactionStatus::tryFrom($request->string('status')->toString());
            if ($status !== null) {
                $query->where('status', $status);
            }
        } elseif ($request->string('posted_filter')->toString() === 'posted_only') {
            $query->where('status', LedgerTransactionStatus::Posted);
        }

        if ($request->filled('transaction_ref')) {
            $query->where('transaction_ref', 'like', '%'.$request->string('transaction_ref')->trim()->toString().'%');
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', 'like', '%'.$request->string('source_type')->trim()->toString().'%');
        }

        if ($request->filled('booking_ref')) {
            $ref = $request->string('booking_ref')->trim()->toString();
            $query->whereHas('booking', fn ($q) => $q->where('booking_reference', 'like', '%'.$ref.'%'));
        }

        if ($request->filled('amount_min')) {
            $query->where('amount_total', '>=', (float) $request->input('amount_min'));
        }

        if ($request->filled('amount_max')) {
            $query->where('amount_total', '<=', (float) $request->input('amount_max'));
        }

        $balanced = $request->string('balanced')->toString();
        if ($balanced === 'yes' || $balanced === 'no') {
            $operator = $balanced === 'yes' ? '<' : '>=';
            $query->whereRaw(
                '(SELECT ABS(COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0)) FROM ledger_entries WHERE ledger_transaction_id = ledger_transactions.id) '.$operator.' 0.01'
            );
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    protected function extractFilters(Request $request): array
    {
        return [
            'agency_id' => $request->string('agency_id')->toString(),
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'transaction_type' => $request->string('transaction_type')->toString(),
            'status' => $request->string('status')->toString(),
            'posted_filter' => $request->string('posted_filter')->toString(),
            'transaction_ref' => $request->string('transaction_ref')->toString(),
            'source_type' => $request->string('source_type')->toString(),
            'booking_ref' => $request->string('booking_ref')->toString(),
            'balanced' => $request->string('balanced')->toString(),
            'amount_min' => $request->string('amount_min')->toString(),
            'amount_max' => $request->string('amount_max')->toString(),
            'sort' => $request->string('sort')->toString(),
            'direction_sort' => $request->string('direction_sort')->toString(),
        ];
    }
}
