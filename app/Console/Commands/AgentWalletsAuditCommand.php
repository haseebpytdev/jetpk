<?php

namespace App\Console\Commands;

use App\Services\Finance\Wallets\WalletAuditService;
use Illuminate\Console\Command;

class AgentWalletsAuditCommand extends Command
{
    protected $signature = 'agent-wallets:audit
                            {--agency= : Limit audit to one agency id}
                            {--only-duplicates : Show only non-canonical wallets for agencies with multiple wallets}
                            {--only-candidates : Show only cleanup-candidate wallets}
                            {--format=table : Output format: table or json}';

    protected $description = 'Read-only audit of agency wallets, canonical selection, and duplicate cleanup classification (no writes)';

    public function handle(WalletAuditService $auditService): int
    {
        $agencyFilter = $this->option('agency') !== null && $this->option('agency') !== ''
            ? (int) $this->option('agency')
            : null;

        $report = $auditService->build(
            agencyId: $agencyFilter,
            onlyDuplicates: (bool) $this->option('only-duplicates'),
            onlyCandidates: (bool) $this->option('only-candidates'),
        );

        $format = strtolower((string) $this->option('format'));

        if ($format === 'json') {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $summary = $report['summary'] ?? [];

        $this->info('Agent wallet audit (read-only)');
        $this->line('Total agencies: '.($summary['total_agencies'] ?? 0));
        $this->line('Agencies with no wallet: '.($summary['agencies_with_no_wallet'] ?? 0));
        $this->line('Agencies with 1 wallet: '.($summary['agencies_with_one_wallet'] ?? 0));
        $this->line('Agencies with multiple wallets: '.($summary['agencies_with_multiple_wallets'] ?? 0));
        $this->line('Total duplicate wallets (non-canonical slots): '.($summary['total_duplicate_wallets'] ?? 0));
        $this->line('Cleanup candidates: '.($summary['cleanup_candidates'] ?? 0));
        $this->line('Review required: '.($summary['review_required'] ?? 0));
        $this->line('Historical active duplicates: '.($summary['historical_active_duplicates'] ?? 0));
        $this->newLine();

        $agencyRows = $report['agencies'] ?? [];
        if ($agencyRows !== []) {
            $this->comment('Agency summary');
            $this->table(
                ['Agency', 'Name', 'Wallets', 'Canonical', 'Dupes', 'Total bal.', 'Candidates', 'Review'],
                collect($agencyRows)->map(fn (array $row): array => [
                    $row['agency_id'],
                    $row['agency_name'],
                    $row['wallet_count'],
                    $row['canonical_wallet_id'] ?? '—',
                    $row['duplicate_count'],
                    ($row['currency'] ?? 'PKR').' '.number_format((float) $row['total_balance'], 2),
                    $row['cleanup_candidate_count'],
                    $row['review_required_count'],
                ])->all(),
            );
            $this->newLine();
        }

        $walletRows = $report['wallets'] ?? [];
        if ($walletRows === []) {
            $this->comment('No wallet rows matched the filter.');

            return self::SUCCESS;
        }

        $this->comment('Wallet detail ('.count($walletRows).' rows)');
        $this->table(
            [
                'Wallet', 'Agency', 'Agent', 'Balance', 'Status', 'Tx', 'Deposits', 'Ledger', 'Last mvmt',
                'Class', 'Recommendation',
            ],
            collect($walletRows)->map(fn (array $row): array => [
                $row['wallet_id'],
                $row['agency_id'].' '.$row['agency_name'],
                $row['agent_label'],
                ($row['currency'] ?? 'PKR').' '.number_format((float) $row['balance'], 2),
                $row['status'],
                $row['transaction_count'],
                $row['deposit_request_count'],
                $row['ledger_reference_count'],
                $row['last_movement_at'] ?? '—',
                $row['classification_label'],
                $row['recommendation'],
            ])->all(),
        );

        return self::SUCCESS;
    }
}
