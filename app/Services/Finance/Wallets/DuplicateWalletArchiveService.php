<?php

namespace App\Services\Finance\Wallets;

use App\Enums\AgentDepositRequestStatus;
use App\Enums\AgentWalletStatus;
use App\Enums\AgentWalletTransactionStatus;
use App\Enums\WalletAuditClassification;
use App\Models\Agency;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\AuditLog;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin-only archive workflow for zero-balance duplicate wallets (Finance-Reports-16).
 *
 * Sets status to archived only; never deletes rows, moves transactions, or mutates balances/ledger.
 */
class DuplicateWalletArchiveService
{
    public const AUDIT_ACTION = 'agent_wallet.duplicate_archived';

    public function __construct(
        protected WalletAuditService $walletAudit,
        protected AgentWalletService $walletService,
    ) {}

    /**
     * @return array{
     *     eligible: list<array<string, mixed>>,
     *     blocked: list<array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    public function preview(?int $agencyId = null): array
    {
        $report = $this->walletAudit->build(agencyId: $agencyId);
        $eligible = [];
        $blocked = [];

        foreach ($report['wallets'] ?? [] as $row) {
            $wallet = AgentWallet::query()->find((int) ($row['wallet_id'] ?? 0));
            if ($wallet === null) {
                continue;
            }

            $result = $this->canArchive($wallet);
            $entry = $this->previewRow($wallet, $row, $result);

            if ($result->eligible) {
                $eligible[] = $entry;
            } else {
                $blocked[] = $entry;
            }
        }

        return [
            'eligible' => $eligible,
            'blocked' => $blocked,
            'summary' => [
                'eligible_count' => count($eligible),
                'blocked_count' => count($blocked),
                'total_listed' => count($eligible) + count($blocked),
            ],
        ];
    }

    public function canArchive(AgentWallet $wallet): ArchiveEligibilityResult
    {
        $wallet->refresh();
        $reasons = [];

        if ($wallet->status === AgentWalletStatus::Archived) {
            $reasons[] = 'Wallet is already archived.';
        } elseif ($wallet->status === AgentWalletStatus::Suspended) {
            $reasons[] = 'Wallet is suspended; resolve suspension before archive.';
        }

        $canonical = $this->walletService->canonicalWalletForAgency((int) $wallet->agency_id);
        $canonicalId = $canonical?->id;

        if ($canonical === null) {
            $reasons[] = 'Agency has no active canonical wallet.';
        } elseif ((int) $wallet->id === (int) $canonical->id) {
            $reasons[] = 'Canonical operational wallet cannot be archived.';
        }

        $metrics = $this->walletMetrics($wallet);
        $classification = $this->walletAudit->classificationForWallet(
            $wallet,
            $canonicalId,
            $metrics['transaction_count'],
            $metrics['deposit_count'],
            $metrics['ledger_ref_count'],
        );

        if ($classification !== WalletAuditClassification::CleanupCandidate) {
            $reasons[] = 'Classification is '.$classification->label().', not a cleanup candidate.';
        }

        if (abs((float) $wallet->balance) > 0.001) {
            $reasons[] = 'Balance must be exactly zero.';
        }

        if ($metrics['transaction_count'] > 0) {
            $reasons[] = 'Wallet has wallet transactions.';
        }

        if ($metrics['deposit_count'] > 0) {
            $reasons[] = 'Wallet has deposit requests.';
        }

        if ($metrics['ledger_ref_count'] > 0) {
            $reasons[] = 'Wallet has ledger references.';
        }

        if ($this->hasPendingWorkflow($wallet)) {
            $reasons[] = 'Wallet has pending deposit or wallet transaction workflow.';
        }

        return new ArchiveEligibilityResult(
            eligible: $reasons === [],
            reasons: $reasons,
            classification: $classification,
            canonicalWalletId: $canonicalId,
        );
    }

    public function archiveWallet(
        AgentWallet $wallet,
        User $actor,
        string $reason,
        bool $dryRun = false,
        ?Request $request = null,
    ): ArchiveResult {
        $eligibility = $this->canArchive($wallet);

        if (! $eligibility->eligible) {
            return new ArchiveResult(
                walletId: (int) $wallet->id,
                agencyId: (int) $wallet->agency_id,
                action: $dryRun ? 'dry-run' : 'skipped',
                success: false,
                message: $eligibility->reason(),
                statusBefore: $wallet->status instanceof AgentWalletStatus ? $wallet->status : AgentWalletStatus::Active,
                statusAfter: $wallet->status instanceof AgentWalletStatus ? $wallet->status : AgentWalletStatus::Active,
                actorLabel: $this->actorLabel($actor),
            );
        }

        $statusBefore = $wallet->status instanceof AgentWalletStatus
            ? $wallet->status
            : AgentWalletStatus::from((string) $wallet->status);

        if ($dryRun) {
            return new ArchiveResult(
                walletId: (int) $wallet->id,
                agencyId: (int) $wallet->agency_id,
                action: 'dry-run',
                success: true,
                message: 'Eligible for archive (dry-run; no changes made).',
                statusBefore: $statusBefore,
                statusAfter: AgentWalletStatus::Archived,
                actorLabel: $this->actorLabel($actor),
            );
        }

        return DB::transaction(function () use ($wallet, $actor, $reason, $statusBefore, $request): ArchiveResult {
            $locked = AgentWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $check = $this->canArchive($locked);

            if (! $check->eligible) {
                return new ArchiveResult(
                    walletId: (int) $locked->id,
                    agencyId: (int) $locked->agency_id,
                    action: 'skipped',
                    success: false,
                    message: $check->reason(),
                    statusBefore: $statusBefore,
                    statusAfter: $locked->status instanceof AgentWalletStatus ? $locked->status : $statusBefore,
                    actorLabel: $this->actorLabel($actor),
                );
            }

            $balanceBefore = (float) $locked->balance;
            $locked->update(['status' => AgentWalletStatus::Archived]);
            $locked->refresh();

            $this->writeAuditLog($locked, $actor, $reason, $statusBefore, $balanceBefore, $request);

            return new ArchiveResult(
                walletId: (int) $locked->id,
                agencyId: (int) $locked->agency_id,
                action: 'archive',
                success: true,
                message: 'Wallet archived.',
                statusBefore: $statusBefore,
                statusAfter: AgentWalletStatus::Archived,
                actorLabel: $this->actorLabel($actor),
            );
        });
    }

    /**
     * @return array{
     *     dry_run: bool,
     *     results: list<array<string, mixed>>,
     *     archived_count: int,
     *     skipped_count: int,
     *     dry_run_count: int
     * }
     */
    public function archiveEligibleForAgency(
        Agency|int $agency,
        User $actor,
        string $reason,
        bool $dryRun = true,
        ?Request $request = null,
        ?int $walletId = null,
    ): array {
        $agencyId = $agency instanceof Agency ? (int) $agency->id : (int) $agency;
        $query = AgentWallet::query()->where('agency_id', $agencyId)->orderBy('id');

        if ($walletId !== null) {
            $query->whereKey($walletId);
        }

        $wallets = $query->get();
        $results = [];
        $archivedCount = 0;
        $skippedCount = 0;
        $dryRunCount = 0;

        foreach ($wallets as $wallet) {
            $result = $this->archiveWallet($wallet, $actor, $reason, $dryRun, $request);
            $row = $this->resultRow($wallet, $result, $this->canArchive($wallet));

            $results[] = $row;

            if ($result->action === 'archive' && $result->success) {
                $archivedCount++;
            } elseif ($result->action === 'dry-run' && $result->success) {
                $dryRunCount++;
            } else {
                $skippedCount++;
            }
        }

        return [
            'dry_run' => $dryRun,
            'results' => $results,
            'archived_count' => $archivedCount,
            'skipped_count' => $skippedCount,
            'dry_run_count' => $dryRunCount,
        ];
    }

    /**
     * @return array{archived_at: string, archived_by: string, archive_reason: string}|null
     */
    public function latestArchiveMetadata(int $walletId): ?array
    {
        $log = AuditLog::query()
            ->where('auditable_type', AgentWallet::class)
            ->where('auditable_id', $walletId)
            ->where('action', self::AUDIT_ACTION)
            ->orderByDesc('id')
            ->with('user')
            ->first();

        if ($log === null) {
            return null;
        }

        $properties = $log->properties ?? [];

        return [
            'archived_at' => (string) ($log->created_at ?? ''),
            'archived_by' => trim((string) ($log->user?->name ?? $log->user?->email ?? 'System')),
            'archive_reason' => (string) ($properties['reason'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $auditRow
     * @return array<string, mixed>
     */
    protected function previewRow(AgentWallet $wallet, array $auditRow, ArchiveEligibilityResult $result): array
    {
        return [
            'wallet_id' => (int) $wallet->id,
            'agency_id' => (int) $wallet->agency_id,
            'agency_name' => $auditRow['agency_name'] ?? '',
            'agent_label' => $auditRow['agent_label'] ?? '',
            'balance' => (float) $wallet->balance,
            'currency' => (string) $wallet->currency,
            'status' => $wallet->status instanceof AgentWalletStatus ? $wallet->status->value : (string) $wallet->status,
            'classification' => $result->classification?->value ?? ($auditRow['classification'] ?? ''),
            'classification_label' => $result->classification?->label() ?? '',
            'is_canonical' => (bool) ($auditRow['is_canonical'] ?? false),
            'eligible' => $result->eligible,
            'reason' => $result->reason(),
            'canonical_wallet_id' => $result->canonicalWalletId,
            'transaction_count' => (int) ($auditRow['transaction_count'] ?? 0),
            'deposit_request_count' => (int) ($auditRow['deposit_request_count'] ?? 0),
            'ledger_reference_count' => (int) ($auditRow['ledger_reference_count'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resultRow(AgentWallet $wallet, ArchiveResult $result, ArchiveEligibilityResult $eligibility): array
    {
        return [
            'wallet_id' => $result->walletId,
            'agency_id' => $result->agencyId,
            'classification' => $eligibility->classification?->value ?? '',
            'eligible' => $eligibility->eligible,
            'action' => $result->action,
            'reason' => $result->message,
            'status_before' => $result->statusBefore?->value,
            'status_after' => $result->statusAfter?->value,
            'actor' => $result->actorLabel,
            'balance' => (float) $wallet->balance,
        ];
    }

    /**
     * @return array{transaction_count: int, deposit_count: int, ledger_ref_count: int}
     */
    protected function walletMetrics(AgentWallet $wallet): array
    {
        $walletId = (int) $wallet->id;
        $txCount = (int) AgentWalletTransaction::query()->where('agent_wallet_id', $walletId)->count();
        $depCount = (int) AgentDepositRequest::query()->where('agent_wallet_id', $walletId)->count();
        $ledgerRefCount = 0;

        if ($txCount > 0) {
            $txIds = AgentWalletTransaction::query()
                ->where('agent_wallet_id', $walletId)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $morphClass = (new AgentWalletTransaction)->getMorphClass();
            $ledgerRefCount = (int) LedgerTransaction::query()
                ->where('source_type', $morphClass)
                ->whereIn('source_id', $txIds)
                ->count();
        }

        return [
            'transaction_count' => $txCount,
            'deposit_count' => $depCount,
            'ledger_ref_count' => $ledgerRefCount,
        ];
    }

    protected function hasPendingWorkflow(AgentWallet $wallet): bool
    {
        $hasPendingDeposit = AgentDepositRequest::query()
            ->where('agent_wallet_id', $wallet->id)
            ->where('status', AgentDepositRequestStatus::Submitted)
            ->exists();

        if ($hasPendingDeposit) {
            return true;
        }

        return AgentWalletTransaction::query()
            ->where('agent_wallet_id', $wallet->id)
            ->where('status', AgentWalletTransactionStatus::Pending)
            ->exists();
    }

    protected function writeAuditLog(
        AgentWallet $wallet,
        User $actor,
        string $reason,
        AgentWalletStatus $statusBefore,
        float $balanceBefore,
        ?Request $request,
    ): void {
        AuditLog::query()->create([
            'agency_id' => $wallet->agency_id,
            'user_id' => $actor->id,
            'action' => self::AUDIT_ACTION,
            'auditable_type' => AgentWallet::class,
            'auditable_id' => $wallet->id,
            'properties' => [
                'wallet_id' => $wallet->id,
                'agency_id' => $wallet->agency_id,
                'status_before' => $statusBefore->value,
                'status_after' => AgentWalletStatus::Archived->value,
                'balance_before' => $balanceBefore,
                'balance_after' => (float) $wallet->balance,
                'reason' => $reason,
                'classification' => WalletAuditClassification::CleanupCandidate->value,
            ],
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    protected function actorLabel(User $actor): string
    {
        return trim((string) ($actor->name ?: $actor->email ?: 'User #'.$actor->id));
    }
}
