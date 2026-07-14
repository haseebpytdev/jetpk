<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\AgentWallet;
use App\Models\User;
use App\Services\Finance\Wallets\DuplicateWalletArchiveService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class AgentWalletsArchiveCandidatesCommand extends Command
{
    protected $signature = 'agent-wallets:archive-candidates
                            {--agency= : Limit to one agency id}
                            {--wallet= : Archive preview/apply for one wallet id (requires --agency)}
                            {--dry-run : Preview only; no database writes (default when --apply is omitted)}
                            {--apply : Perform archive for eligible wallets}
                            {--reason= : Required with --apply}
                            {--format=table : Output format: table or json}';

    protected $description = 'Preview or archive zero-balance duplicate cleanup-candidate wallets (status only; no deletes)';

    public function handle(DuplicateWalletArchiveService $archiveService): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply;
        $reason = trim((string) $this->option('reason'));

        if ($apply && strlen($reason) < 10) {
            $this->error('--reason is required with --apply (minimum 10 characters).');

            return self::FAILURE;
        }

        $agencyFilter = $this->option('agency') !== null && $this->option('agency') !== ''
            ? (int) $this->option('agency')
            : null;

        $walletFilter = $this->option('wallet') !== null && $this->option('wallet') !== ''
            ? (int) $this->option('wallet')
            : null;

        if ($walletFilter !== null && $agencyFilter === null) {
            $this->error('--wallet requires --agency.');

            return self::FAILURE;
        }

        $format = strtolower((string) $this->option('format'));
        $actor = $this->systemActor();

        if ($agencyFilter === null) {
            $preview = $archiveService->preview();
            $rows = $this->flattenPreview($preview, $dryRun, $actor, $reason, $archiveService);

            return $this->renderOutput($rows, $format, $dryRun, $preview['summary'] ?? []);
        }

        $batch = $archiveService->archiveEligibleForAgency(
            agency: $agencyFilter,
            actor: $actor,
            reason: $reason !== '' ? $reason : 'CLI dry-run preview',
            dryRun: $dryRun,
            walletId: $walletFilter,
        );

        $rows = $batch['results'];

        if ($format === 'json') {
            $this->line(json_encode([
                'dry_run' => $dryRun,
                'summary' => [
                    'archived_count' => $batch['archived_count'],
                    'skipped_count' => $batch['skipped_count'],
                    'dry_run_count' => $batch['dry_run_count'],
                ],
                'rows' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info($dryRun ? 'Archive candidates (dry-run)' : 'Archive candidates (apply)');
        $this->table(
            ['Wallet', 'Agency', 'Class', 'Eligible', 'Action', 'Reason', 'Before', 'After', 'Actor'],
            collect($rows)->map(fn (array $row): array => [
                $row['wallet_id'],
                $row['agency_id'],
                $row['classification'],
                ($row['eligible'] ?? false) ? 'yes' : 'no',
                $row['action'],
                $row['reason'],
                $row['status_before'] ?? '—',
                $row['status_after'] ?? '—',
                $row['actor'] ?? 'system',
            ])->all(),
        );

        $this->line('Archived: '.$batch['archived_count'].' | Dry-run eligible: '.$batch['dry_run_count'].' | Skipped: '.$batch['skipped_count']);

        return self::SUCCESS;
    }

    /**
     * @param  array{eligible: list<array<string, mixed>>, blocked: list<array<string, mixed>>, summary: array<string, int>}  $preview
     * @return list<array<string, mixed>>
     */
    protected function flattenPreview(
        array $preview,
        bool $dryRun,
        User $actor,
        string $reason,
        DuplicateWalletArchiveService $archiveService,
    ): array {
        $rows = [];

        foreach (array_merge($preview['eligible'] ?? [], $preview['blocked'] ?? []) as $entry) {
            $wallet = AgentWallet::query()->find((int) ($entry['wallet_id'] ?? 0));
            if ($wallet === null) {
                continue;
            }

            if ($dryRun || ! ($entry['eligible'] ?? false)) {
                $result = $archiveService->archiveWallet(
                    $wallet,
                    $actor,
                    $reason !== '' ? $reason : 'CLI dry-run preview',
                    dryRun: true,
                );
            } else {
                $result = $archiveService->archiveWallet(
                    $wallet,
                    $actor,
                    $reason,
                    dryRun: false,
                );
            }

            $rows[] = [
                'wallet_id' => $result->walletId,
                'agency_id' => $result->agencyId,
                'classification' => $entry['classification'] ?? '',
                'eligible' => $entry['eligible'] ?? false,
                'action' => $result->action,
                'reason' => $result->message,
                'status_before' => $result->statusBefore?->value,
                'status_after' => $result->statusAfter?->value,
                'actor' => $result->actorLabel,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int>  $summary
     */
    protected function renderOutput(array $rows, string $format, bool $dryRun, array $summary): int
    {
        if ($format === 'json') {
            $this->line(json_encode([
                'dry_run' => $dryRun,
                'summary' => $summary,
                'rows' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info($dryRun ? 'Archive candidates (dry-run, all agencies)' : 'Archive candidates (apply)');
        $this->line('Eligible: '.($summary['eligible_count'] ?? 0).' | Blocked: '.($summary['blocked_count'] ?? 0));

        if ($rows === []) {
            $this->comment('No wallet rows to display.');

            return self::SUCCESS;
        }

        $this->table(
            ['Wallet', 'Agency', 'Class', 'Eligible', 'Action', 'Reason', 'Before', 'After', 'Actor'],
            collect($rows)->map(fn (array $row): array => [
                $row['wallet_id'],
                $row['agency_id'],
                $row['classification'],
                ($row['eligible'] ?? false) ? 'yes' : 'no',
                $row['action'],
                $row['reason'],
                $row['status_before'] ?? '—',
                $row['status_after'] ?? '—',
                $row['actor'] ?? 'system',
            ])->all(),
        );

        return self::SUCCESS;
    }

    protected function systemActor(): User
    {
        $admin = User::query()->where('account_type', AccountType::PlatformAdmin)->orderBy('id')->first()
            ?? User::query()->where('email', 'admin@ota.demo')->orderBy('id')->first();

        if ($admin === null) {
            throw new InvalidArgumentException('No platform admin user found for CLI archive actor.');
        }

        return $admin;
    }
}
