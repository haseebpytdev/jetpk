<?php

namespace App\Services\Finance\Wallets;

use App\Enums\AgentWalletStatus;
use App\Enums\WalletAuditClassification;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\AuditLog;
use App\Models\LedgerTransaction;
use App\Services\Agents\AgentWalletService;
use Illuminate\Support\Collection;

/**
 * Duplicate wallet audit and cleanup planning (Finance-Reports-14/16).
 *
 * Classification/reporting is read-only; archive execution is via {@see DuplicateWalletArchiveService}.
 */
class WalletAuditService
{
    public function __construct(
        protected AgentWalletService $walletService,
    ) {}

    /**
     * @return array{
     *     summary: array<string, int|float|null>,
     *     wallets: list<array<string, mixed>>,
     *     agencies: list<array<string, mixed>>
     * }
     */
    public function build(
        ?int $agencyId = null,
        bool $onlyDuplicates = false,
        bool $onlyCandidates = false,
    ): array {
        $agencyQuery = Agency::query()->orderBy('id');
        if ($agencyId !== null) {
            $agencyQuery->whereKey($agencyId);
        }

        $agencies = $agencyQuery->get(Agency::walletAuditSelectColumns());

        $wallets = AgentWallet::query()
            ->when($agencyId !== null, fn ($q) => $q->where('agency_id', $agencyId))
            ->with(['agent.user', 'user', 'agency'])
            ->orderBy('agency_id')
            ->orderBy('id')
            ->get();

        $walletIds = $wallets->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $transactionCounts = $this->transactionCountsByWalletId($walletIds);
        $depositCounts = $this->depositCountsByWalletId($walletIds);
        $lastMovements = $this->lastMovementByWalletId($walletIds);
        $ledgerRefCounts = $this->ledgerReferenceCountsByWalletId($walletIds);

        $walletsByAgency = $wallets->groupBy('agency_id');
        $canonicalByAgency = [];

        foreach ($walletsByAgency as $groupAgencyId => $agencyWallets) {
            $canonical = $this->walletService->canonicalWalletForAgency((int) $groupAgencyId);
            $canonicalByAgency[(int) $groupAgencyId] = $canonical?->id;
        }

        $walletRows = [];
        $agencyRows = [];

        $agenciesWithNoWallet = $agencies->filter(
            fn (Agency $agency): bool => ! $walletsByAgency->has($agency->id),
        )->count();

        $agenciesWithOneWallet = 0;
        $agenciesWithMultipleWallets = 0;
        $totalDuplicateWallets = 0;
        $cleanupCandidates = 0;
        $reviewRequired = 0;
        $historicalActiveDuplicates = 0;

        foreach ($agencies as $agency) {
            $group = $walletsByAgency->get($agency->id, collect());
            $count = $group->count();

            if ($count === 1) {
                $agenciesWithOneWallet++;
            } elseif ($count > 1) {
                $agenciesWithMultipleWallets++;
                $totalDuplicateWallets += $count - 1;
            }

            if ($count === 0) {
                continue;
            }

            $canonicalId = $canonicalByAgency[(int) $agency->id] ?? null;
            $agencyCandidateCount = 0;
            $agencyReviewCount = 0;
            $agencyHistoricalDuplicateCount = 0;

            foreach ($group as $wallet) {
                $walletId = (int) $wallet->id;
                $txCount = $transactionCounts[$walletId] ?? 0;
                $depCount = $depositCounts[$walletId] ?? 0;
                $ledgerRefs = $ledgerRefCounts[$walletId] ?? 0;

                $classification = $this->classificationForWallet(
                    wallet: $wallet,
                    canonicalId: $canonicalId,
                    transactionCount: $txCount,
                    depositCount: $depCount,
                    ledgerRefCount: $ledgerRefs,
                );

                if ($classification === WalletAuditClassification::CleanupCandidate) {
                    $cleanupCandidates++;
                    $agencyCandidateCount++;
                } elseif ($classification === WalletAuditClassification::ReviewRequired) {
                    $reviewRequired++;
                    $agencyReviewCount++;
                } elseif ($classification === WalletAuditClassification::HistoricalActive && $canonicalId !== $walletId) {
                    $historicalActiveDuplicates++;
                    $agencyHistoricalDuplicateCount++;
                }

                $row = $this->walletRow(
                    wallet: $wallet,
                    agency: $agency,
                    canonicalId: $canonicalId,
                    classification: $classification,
                    transactionCount: $txCount,
                    depositCount: $depCount,
                    ledgerRefCount: $ledgerRefs,
                    lastMovement: $lastMovements[$walletId] ?? null,
                );

                $isDuplicateAgency = $count > 1;
                $includeWallet = ! $onlyDuplicates || ($isDuplicateAgency && $classification !== WalletAuditClassification::Canonical);
                $includeWallet = $includeWallet && (! $onlyCandidates || $classification === WalletAuditClassification::CleanupCandidate);

                if ($includeWallet) {
                    $walletRows[] = $row;
                }
            }

            if ($onlyCandidates && $agencyCandidateCount === 0) {
                continue;
            }

            if ($onlyDuplicates && $count <= 1) {
                continue;
            }

            $agencyRows[] = [
                'agency_id' => (int) $agency->id,
                'agency_name' => $this->agencyDisplayName($agency),
                'wallet_count' => $count,
                'canonical_wallet_id' => $canonicalId,
                'duplicate_count' => max(0, $count - 1),
                'total_balance' => round((float) $group->sum('balance'), 2),
                'cleanup_candidate_count' => $agencyCandidateCount,
                'review_required_count' => $agencyReviewCount,
                'historical_active_duplicate_count' => $agencyHistoricalDuplicateCount,
                'has_duplicate_wallets' => $count > 1,
                'currency' => (string) ($group->first()?->currency ?? 'PKR'),
            ];
        }

        return $this->formatBuildResult(
            $agencies,
            $walletRows,
            $agencyRows,
            $agenciesWithNoWallet,
            $agenciesWithOneWallet,
            $agenciesWithMultipleWallets,
            $totalDuplicateWallets,
            $cleanupCandidates,
            $reviewRequired,
            $historicalActiveDuplicates,
            $canonicalByAgency,
        );
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    public function csvRows(
        ?int $agencyId = null,
        bool $onlyDuplicates = false,
        bool $onlyCandidates = false,
    ): array {
        $report = $this->build($agencyId, $onlyDuplicates, $onlyCandidates);

        $rows = [[
            'agency_id', 'agency_name', 'wallet_id', 'agent_label', 'balance', 'status',
            'canonical', 'transaction_count', 'deposit_request_count', 'ledger_reference_count',
            'last_movement_at', 'classification', 'recommendation',
        ]];

        foreach ($report['wallets'] ?? [] as $wallet) {
            $rows[] = [
                $wallet['agency_id'] ?? '',
                $wallet['agency_name'] ?? '',
                $wallet['wallet_id'] ?? '',
                $wallet['agent_label'] ?? '',
                $wallet['balance'] ?? 0,
                $wallet['status'] ?? '',
                ($wallet['is_canonical'] ?? false) ? 'yes' : 'no',
                $wallet['transaction_count'] ?? 0,
                $wallet['deposit_request_count'] ?? 0,
                $wallet['ledger_reference_count'] ?? 0,
                $wallet['last_movement_at'] ?? '',
                $wallet['classification'] ?? '',
                $wallet['recommendation'] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Agency>  $agencies
     * @param  list<array<string, mixed>>  $walletRows
     * @param  list<array<string, mixed>>  $agencyRows
     * @return array{
     *     summary: array<string, int|float|null>,
     *     wallets: list<array<string, mixed>>,
     *     agencies: list<array<string, mixed>>
     * }
     */
    protected function formatBuildResult(
        $agencies,
        array $walletRows,
        array $agencyRows,
        int $agenciesWithNoWallet,
        int $agenciesWithOneWallet,
        int $agenciesWithMultipleWallets,
        int $totalDuplicateWallets,
        int $cleanupCandidates,
        int $reviewRequired,
        int $historicalActiveDuplicates,
        array $canonicalByAgency,
    ): array {
        return [
            'summary' => [
                'total_agencies' => $agencies->count(),
                'agencies_with_no_wallet' => $agenciesWithNoWallet,
                'agencies_with_one_wallet' => $agenciesWithOneWallet,
                'agencies_with_multiple_wallets' => $agenciesWithMultipleWallets,
                'total_duplicate_wallets' => $totalDuplicateWallets,
                'cleanup_candidates' => $cleanupCandidates,
                'review_required' => $reviewRequired,
                'historical_active_duplicates' => $historicalActiveDuplicates,
                'canonical_wallets' => count(array_filter($canonicalByAgency)),
                'wallets_listed' => count($walletRows),
            ],
            'wallets' => $walletRows,
            'agencies' => $agencyRows,
        ];
    }

    public function classificationForWallet(
        AgentWallet $wallet,
        ?int $canonicalId,
        int $transactionCount,
        int $depositCount,
        int $ledgerRefCount,
    ): WalletAuditClassification {
        if ($wallet->status === AgentWalletStatus::Archived) {
            return WalletAuditClassification::ArchivedDuplicate;
        }

        return $this->classify(
            wallet: $wallet,
            canonicalId: $canonicalId,
            transactionCount: $transactionCount,
            depositCount: $depositCount,
            ledgerRefCount: $ledgerRefCount,
        );
    }

    protected function classify(
        AgentWallet $wallet,
        ?int $canonicalId,
        int $transactionCount,
        int $depositCount,
        int $ledgerRefCount,
    ): WalletAuditClassification {
        if ($canonicalId !== null && (int) $wallet->id === (int) $canonicalId) {
            return WalletAuditClassification::Canonical;
        }

        if ($this->needsRelationshipReview($wallet)) {
            return WalletAuditClassification::ReviewRequired;
        }

        $hasNonZeroBalance = abs((float) $wallet->balance) > 0.001;

        if ($hasNonZeroBalance || $transactionCount > 0 || $depositCount > 0 || $ledgerRefCount > 0) {
            if (
                ! $hasNonZeroBalance
                && $transactionCount === 0
                && $depositCount > 0
            ) {
                return WalletAuditClassification::ReviewRequired;
            }

            return WalletAuditClassification::HistoricalActive;
        }

        return WalletAuditClassification::CleanupCandidate;
    }

    protected function needsRelationshipReview(AgentWallet $wallet): bool
    {
        if ($wallet->agency_id === null) {
            return true;
        }

        if ($wallet->agent_id === null) {
            return true;
        }

        $agencyExists = $wallet->relationLoaded('agency')
            ? $wallet->agency !== null
            : Agency::query()->whereKey($wallet->agency_id)->exists();

        if (! $agencyExists) {
            return true;
        }

        return ! Agent::query()->whereKey($wallet->agent_id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    protected function walletRow(
        AgentWallet $wallet,
        Agency $agency,
        ?int $canonicalId,
        WalletAuditClassification $classification,
        int $transactionCount,
        int $depositCount,
        int $ledgerRefCount,
        ?string $lastMovement,
    ): array {
        $wallet->loadMissing(['agent.user', 'user']);

        $archiveMeta = $classification === WalletAuditClassification::ArchivedDuplicate
            ? $this->latestArchiveMetadata((int) $wallet->id)
            : null;

        $recommendation = $classification->recommendation();
        if ($archiveMeta !== null) {
            $recommendation = trim($recommendation.' Archived at '.$archiveMeta['archived_at']
                .' by '.$archiveMeta['archived_by'].'. Reason: '.$archiveMeta['archive_reason']);
        }

        return [
            'wallet_id' => (int) $wallet->id,
            'agency_id' => (int) $agency->id,
            'agency_name' => $this->agencyDisplayName($agency),
            'agent_id' => $wallet->agent_id !== null ? (int) $wallet->agent_id : null,
            'agent_label' => trim((string) ($wallet->agent?->user?->name ?? $wallet->user?->name ?? '—')),
            'balance' => (float) $wallet->balance,
            'currency' => (string) $wallet->currency,
            'status' => $wallet->status instanceof \BackedEnum ? $wallet->status->value : (string) $wallet->status,
            'is_canonical' => $canonicalId !== null && (int) $wallet->id === (int) $canonicalId,
            'transaction_count' => $transactionCount,
            'deposit_request_count' => $depositCount,
            'ledger_reference_count' => $ledgerRefCount,
            'last_movement_at' => $lastMovement,
            'classification' => $classification->value,
            'classification_label' => $classification->label(),
            'recommendation' => $recommendation,
            'archive_metadata' => $archiveMeta,
            'was_cleanup_candidate' => $classification === WalletAuditClassification::ArchivedDuplicate,
        ];
    }

    protected function agencyDisplayName(Agency $agency): string
    {
        return $agency->walletAuditDisplayName();
    }

    /**
     * @return array{archived_at: string, archived_by: string, archive_reason: string}|null
     */
    protected function latestArchiveMetadata(int $walletId): ?array
    {
        $log = AuditLog::query()
            ->where('auditable_type', AgentWallet::class)
            ->where('auditable_id', $walletId)
            ->where('action', DuplicateWalletArchiveService::AUDIT_ACTION)
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
     * @param  list<int>  $walletIds
     * @return array<int, int>
     */
    protected function transactionCountsByWalletId(array $walletIds): array
    {
        if ($walletIds === []) {
            return [];
        }

        return AgentWalletTransaction::query()
            ->whereIn('agent_wallet_id', $walletIds)
            ->selectRaw('agent_wallet_id, COUNT(*) as aggregate')
            ->groupBy('agent_wallet_id')
            ->pluck('aggregate', 'agent_wallet_id')
            ->mapWithKeys(fn ($count, $walletId): array => [(int) $walletId => (int) $count])
            ->all();
    }

    /**
     * @param  list<int>  $walletIds
     * @return array<int, int>
     */
    protected function depositCountsByWalletId(array $walletIds): array
    {
        if ($walletIds === []) {
            return [];
        }

        return AgentDepositRequest::query()
            ->whereIn('agent_wallet_id', $walletIds)
            ->selectRaw('agent_wallet_id, COUNT(*) as aggregate')
            ->groupBy('agent_wallet_id')
            ->pluck('aggregate', 'agent_wallet_id')
            ->mapWithKeys(fn ($count, $walletId): array => [(int) $walletId => (int) $count])
            ->all();
    }

    /**
     * @param  list<int>  $walletIds
     * @return array<int, string>
     */
    protected function lastMovementByWalletId(array $walletIds): array
    {
        if ($walletIds === []) {
            return [];
        }

        return AgentWalletTransaction::query()
            ->whereIn('agent_wallet_id', $walletIds)
            ->selectRaw('agent_wallet_id, MAX(created_at) as last_movement_at')
            ->groupBy('agent_wallet_id')
            ->pluck('last_movement_at', 'agent_wallet_id')
            ->mapWithKeys(fn ($at, $walletId): array => [(int) $walletId => (string) $at])
            ->all();
    }

    /**
     * Ledger rows whose source is an agent wallet transaction tied to this wallet.
     *
     * @param  list<int>  $walletIds
     * @return array<int, int>
     */
    protected function ledgerReferenceCountsByWalletId(array $walletIds): array
    {
        if ($walletIds === []) {
            return [];
        }

        $txIdsByWallet = AgentWalletTransaction::query()
            ->whereIn('agent_wallet_id', $walletIds)
            ->get(['id', 'agent_wallet_id'])
            ->groupBy('agent_wallet_id');

        if ($txIdsByWallet->isEmpty()) {
            return [];
        }

        $allTxIds = $txIdsByWallet->flatten()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $morphClass = (new AgentWalletTransaction)->getMorphClass();

        $ledgerCountsByTxId = LedgerTransaction::query()
            ->where('source_type', $morphClass)
            ->whereIn('source_id', $allTxIds)
            ->selectRaw('source_id, COUNT(*) as aggregate')
            ->groupBy('source_id')
            ->pluck('aggregate', 'source_id')
            ->mapWithKeys(fn ($count, $txId): array => [(int) $txId => (int) $count]);

        $result = [];
        foreach ($txIdsByWallet as $walletId => $transactions) {
            $result[(int) $walletId] = $transactions->sum(
                fn ($tx): int => (int) ($ledgerCountsByTxId[(int) $tx->id] ?? 0),
            );
        }

        return $result;
    }
}
