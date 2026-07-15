<?php

namespace App\Console\Commands;

use App\Enums\LedgerTransactionStatus;
use App\Models\LedgerTransaction;
use Illuminate\Console\Command;

class LedgerPostingStatusCommand extends Command
{
    protected $signature = 'ledger:posting-status
                            {--recent=20 : Number of recent transactions to show}
                            {--agency= : Filter by agency ID}
                            {--booking= : Filter by booking ID}
                            {--type= : Filter by transaction_type value}';

    protected $description = 'Read-only summary of recent ledger postings (admin/SSH diagnostics only)';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('recent'));

        $query = LedgerTransaction::query()
            ->with('entries.account')
            ->orderByDesc('posted_at')
            ->orderByDesc('id');

        if ($this->option('agency') !== null) {
            $query->where('agency_id', (int) $this->option('agency'));
        }

        if ($this->option('booking') !== null) {
            $query->where('booking_id', (int) $this->option('booking'));
        }

        if ($this->option('type') !== null) {
            $query->where('transaction_type', (string) $this->option('type'));
        }

        $transactions = $query->limit($limit)->get();

        if ($transactions->isEmpty()) {
            $this->info('No ledger transactions found for the current filters.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($transactions as $tx) {
            $debitTotal = round((float) $tx->entries->sum('debit'), 2);
            $creditTotal = round((float) $tx->entries->sum('credit'), 2);
            $balanced = abs($debitTotal - $creditTotal) < 0.01 && $debitTotal > 0;

            $rows[] = [
                $tx->transaction_ref,
                $tx->transaction_type->value,
                $tx->source_type,
                $tx->source_id,
                $tx->agency_id ?? '-',
                $tx->booking_id ?? '-',
                number_format((float) $tx->amount_total, 2),
                $tx->status->value,
                $tx->posted_at?->toDateTimeString() ?? '-',
                number_format($debitTotal, 2),
                number_format($creditTotal, 2),
                $balanced ? 'yes' : 'no',
            ];
        }

        $this->table(
            [
                'transaction_ref',
                'transaction_type',
                'source_type',
                'source_id',
                'agency_id',
                'booking_id',
                'amount_total',
                'status',
                'posted_at',
                'debit_total',
                'credit_total',
                'balanced',
            ],
            $rows,
        );

        $postedCount = $transactions->where('status', LedgerTransactionStatus::Posted)->count();
        $this->line(sprintf('Showing %d transaction(s); %d posted.', $transactions->count(), $postedCount));

        return self::SUCCESS;
    }
}
