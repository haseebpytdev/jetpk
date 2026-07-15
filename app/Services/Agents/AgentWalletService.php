<?php

namespace App\Services\Agents;

use App\Enums\AccountType;
use App\Enums\AgentDepositRequestStatus;
use App\Enums\AgentWalletStatus;
use App\Enums\AgentWalletTransactionStatus;
use App\Enums\AgentWalletTransactionType;
use App\Enums\OtaNotificationEvent;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\AgentWallet;
use App\Models\AgentWalletTransaction;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\Communication\BookingEmailPayloadFactory;
use App\Services\Communication\OtaNotificationService;
use App\Services\Finance\Ledger\LedgerEventRecorder;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\References\CompactReferenceGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Agent prepaid wallet: canonical agency wallet resolution, deposit requests, and balance posting on admin approval.
 *
 * Finance model: one operational wallet per agency. Legacy per-agent rows may exist; {@see canonicalWalletForAgency}
 * selects deterministically without merging or deleting history.
 */
class AgentWalletService
{
    public function __construct(
        protected OtaNotificationService $notificationService,
        protected PlatformModuleEnforcer $platformModuleEnforcer,
        protected BookingEmailPayloadFactory $bookingEmailPayloadFactory,
        protected CompactReferenceGenerator $referenceGenerator,
    ) {}

    /**
     * Operational wallet for an agent's agency (never creates a separate staff wallet).
     */
    public function walletFor(Agent $agent): AgentWallet
    {
        $agent->loadMissing('agency');

        return $this->getOrCreateCanonicalWalletForAgency($agent->agency_id);
    }

    /**
     * Resolve the single operational wallet for an agency without creating a row.
     *
     * Priority: (1) active non-zero balance; (2) if several non-zero, latest wallet transaction;
     * (3) agency owner/admin agent wallet; (4) oldest active wallet; (5) oldest wallet overall.
     */
    public function canonicalWalletForAgency(Agency|int $agency): ?AgentWallet
    {
        $agencyId = $agency instanceof Agency ? (int) $agency->id : (int) $agency;

        $wallets = AgentWallet::query()
            ->where('agency_id', $agencyId)
            ->orderBy('id')
            ->get();

        if ($wallets->isEmpty()) {
            return null;
        }

        $active = $wallets->filter(fn (AgentWallet $wallet): bool => $wallet->status->isOperational());
        if ($active->isEmpty()) {
            return null;
        }

        $pool = $active;

        $nonZero = $pool->filter(fn (AgentWallet $wallet): bool => abs((float) $wallet->balance) > 0.001);
        if ($nonZero->count() === 1) {
            return $nonZero->first();
        }

        if ($nonZero->count() > 1) {
            return $this->walletWithLatestTransactionActivity($nonZero);
        }

        $primaryAgent = $this->primaryAgentForAgency($agencyId);
        if ($primaryAgent !== null) {
            $ownerWallet = $pool->firstWhere('agent_id', $primaryAgent->id);
            if ($ownerWallet !== null) {
                return $ownerWallet;
            }
        }

        return $active->sortBy('id')->first();
    }

    public function getOrCreateCanonicalWalletForAgency(Agency|int $agency): AgentWallet
    {
        $existing = $this->canonicalWalletForAgency($agency);
        if ($existing !== null) {
            return $existing;
        }

        $agencyModel = $agency instanceof Agency
            ? $agency
            : Agency::query()->findOrFail((int) $agency);

        $primaryAgent = $this->primaryAgentForAgency($agencyModel);
        if ($primaryAgent === null) {
            throw new InvalidArgumentException('Cannot create canonical wallet: agency has no agent.');
        }

        $primaryAgent->loadMissing('user');

        $existingForAgent = AgentWallet::query()->where('agent_id', $primaryAgent->id)->first();
        if ($existingForAgent !== null) {
            if ($existingForAgent->status !== AgentWalletStatus::Active) {
                $existingForAgent->update(['status' => AgentWalletStatus::Active]);
            }

            return $existingForAgent->fresh();
        }

        return AgentWallet::query()->create([
            'agency_id' => $agencyModel->id,
            'agent_id' => $primaryAgent->id,
            'user_id' => $primaryAgent->user_id,
            'balance' => 0,
            'credit_limit' => null,
            'currency' => 'PKR',
            'status' => AgentWalletStatus::Active,
        ]);
    }

    /**
     * @return array{
     *     wallet: AgentWallet,
     *     wallet_id: int,
     *     balance: float,
     *     currency: string,
     *     owner_label: string,
     *     has_duplicate_wallets: bool,
     *     duplicate_wallet_count: int,
     *     canonical_wallet_id: int
     * }
     */
    public function canonicalWalletSummary(Agency|int $agency): array
    {
        $agencyId = $agency instanceof Agency ? (int) $agency->id : (int) $agency;
        $wallet = $this->getOrCreateCanonicalWalletForAgency($agency);
        $wallet->loadMissing(['agent.user', 'user']);
        $walletCount = AgentWallet::query()->where('agency_id', $agencyId)->count();

        return [
            'wallet' => $wallet,
            'wallet_id' => (int) $wallet->id,
            'balance' => (float) $wallet->balance,
            'currency' => (string) $wallet->currency,
            'owner_label' => $this->walletOwnerLabel($wallet),
            'has_duplicate_wallets' => $walletCount > 1,
            'duplicate_wallet_count' => $walletCount,
            'canonical_wallet_id' => (int) $wallet->id,
        ];
    }

    public function agencyHasHistoricalDuplicateWallets(int $agencyId): bool
    {
        return AgentWallet::query()->where('agency_id', $agencyId)->count() > 1;
    }

    /**
     * @return array{balance: float, pending_deposits: float, currency: string}
     */
    public function agencyBalanceSummary(int $agencyId): array
    {
        $summary = $this->agencyWalletSummary($agencyId);

        return [
            'balance' => $summary['balance'],
            'pending_deposits' => $summary['pending_deposits'],
            'currency' => $summary['currency'],
        ];
    }

    /**
     * Agency-wide wallet totals for platform admin (sums all agent_wallets for the agency).
     *
     * @return array{
     *     balance: float,
     *     pending_deposits: float,
     *     credit_limit: float|null,
     *     credit_enabled: bool,
     *     available_balance: float,
     *     currency: string,
     *     wallet_count: int,
     *     wallets: list<array{id: int, agent_id: int|null, balance: float, credit_limit: float|null}>
     * }
     */
    public function agencyWalletSummary(int $agencyId): array
    {
        $wallets = AgentWallet::query()
            ->where('agency_id', $agencyId)
            ->orderBy('id')
            ->get(['id', 'agent_id', 'balance', 'credit_limit', 'currency', 'status']);

        $canonical = $this->canonicalWalletForAgency($agencyId);
        $canonicalId = $canonical?->id;
        $lastMovements = $this->lastMovementAtByWalletId($wallets->pluck('id')->all());

        $balance = (float) $wallets->sum(fn (AgentWallet $wallet): float => (float) $wallet->balance);
        $pendingDeposits = (float) AgentDepositRequest::query()
            ->where('agency_id', $agencyId)
            ->where('status', AgentDepositRequestStatus::Submitted)
            ->sum('amount');

        $currency = (string) ($wallets->first()?->currency ?? 'PKR');
        $creditEnabled = $this->agencyCreditLimitEnabled($wallets);
        $creditLimit = $creditEnabled
            ? (float) $wallets->sum(fn (AgentWallet $wallet): float => (float) $wallet->credit_limit)
            : null;

        return [
            'balance' => $balance,
            'pending_deposits' => $pendingDeposits,
            'credit_limit' => $creditLimit,
            'credit_enabled' => $creditEnabled,
            'available_balance' => $balance,
            'currency' => $currency,
            'wallet_count' => $wallets->count(),
            'canonical_wallet_id' => $canonicalId,
            'has_duplicate_wallets' => $wallets->count() > 1,
            'wallets' => $wallets->map(function (AgentWallet $wallet) use ($canonicalId, $lastMovements): array {
                $isCanonical = $canonicalId !== null && (int) $wallet->id === (int) $canonicalId;

                return [
                    'id' => (int) $wallet->id,
                    'agent_id' => $wallet->agent_id !== null ? (int) $wallet->agent_id : null,
                    'balance' => (float) $wallet->balance,
                    'credit_limit' => $wallet->credit_limit !== null ? (float) $wallet->credit_limit : null,
                    'status' => $wallet->status instanceof AgentWalletStatus ? $wallet->status->value : (string) $wallet->status,
                    'role_label' => $isCanonical ? 'Canonical wallet' : 'Historical duplicate wallet',
                    'last_movement_at' => $lastMovements[(int) $wallet->id] ?? null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * Credit is shown only when every wallet for the agency has a positive credit limit.
     *
     * @param  Collection<int, AgentWallet>  $wallets
     */
    protected function agencyCreditLimitEnabled(Collection $wallets): bool
    {
        if ($wallets->isEmpty()) {
            return false;
        }

        return $wallets->every(function (AgentWallet $wallet): bool {
            $limit = $wallet->credit_limit;

            return $limit !== null && (float) $limit > 0;
        });
    }

    public function summary(Agent $agent): array
    {
        $wallet = $this->walletFor($agent);
        $balance = (float) $wallet->balance;
        $pendingDeposits = (float) AgentDepositRequest::query()
            ->where('agent_id', $agent->id)
            ->where('status', AgentDepositRequestStatus::Submitted)
            ->sum('amount');
        $creditLimit = $wallet->credit_limit !== null ? (float) $wallet->credit_limit : null;
        $creditEnabled = $creditLimit !== null && $creditLimit > 0;
        $available = $balance + ($creditEnabled ? $creditLimit : 0);

        return [
            'wallet' => $wallet,
            'balance' => $balance,
            'pending_deposits' => $pendingDeposits,
            'credit_limit' => $creditLimit,
            'credit_enabled' => $creditEnabled,
            'available_balance' => $available,
            'currency' => (string) $wallet->currency,
        ];
    }

    /**
     * Read-only agency wallet/deposit summary email for agency admins (manual/on-demand trigger).
     */
    public function sendAgencyWalletDepositSummary(Agency $agency, ?User $actor = null, ?string $periodLabel = null): void
    {
        $agency->loadMissing('agencySetting');
        $periodLabel = trim((string) ($periodLabel ?? now()->format('F Y')));

        if ($this->agencyWalletDepositSummaryRecentlyNotified($agency, $periodLabel)) {
            return;
        }

        $walletSummary = $this->agencyWalletSummary((int) $agency->id);
        $pendingDepositCount = AgentDepositRequest::query()
            ->where('agency_id', $agency->id)
            ->where('status', AgentDepositRequestStatus::Submitted)
            ->count();
        $recentTransactionCount = AgentWalletTransaction::query()
            ->where('agency_id', $agency->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $summaryPayload = array_merge($walletSummary, [
            'pending_deposit_count' => $pendingDepositCount,
            'recent_transaction_count' => $recentTransactionCount,
            'period_label' => $periodLabel,
        ]);

        $universalPayload = $this->bookingEmailPayloadFactory->agencyWalletDepositSummary($agency, $summaryPayload);

        $this->notificationService->send(
            agency: $agency,
            eventKey: OtaNotificationEvent::AgencyWalletDepositSummary->value,
            payload: [
                'agency_id' => $agency->id,
                'agency_name' => (string) ($agency->agencySetting?->display_name ?? $agency->name),
                'period_label' => $periodLabel,
                'wallet_balance' => (float) ($walletSummary['balance'] ?? 0),
                'pending_deposits' => (float) ($walletSummary['pending_deposits'] ?? 0),
                'pending_deposit_count' => $pendingDepositCount,
                'recent_transaction_count' => $recentTransactionCount,
                'currency' => (string) ($walletSummary['currency'] ?? 'PKR'),
                'universal_email' => $universalPayload,
                'routing_note' => 'Agency-scoped wallet/deposit summary; agency_admin bucket only.',
            ],
            actor: $actor,
            fallbackSubject: 'Agency wallet/deposit summary — '.$periodLabel,
            fallbackBody: 'Your agency wallet/deposit summary for '.$periodLabel.' is ready.',
            templateVariables: [
                'period_label' => $periodLabel,
                'agency_name' => (string) ($agency->agencySetting?->display_name ?? $agency->name),
            ],
            recipientContext: [
                'notify_buckets' => ['agency_admin'],
            ],
        );
    }

    protected function agencyWalletDepositSummaryRecentlyNotified(Agency $agency, string $periodLabel): bool
    {
        return CommunicationLog::query()
            ->where('agency_id', $agency->id)
            ->where('event', OtaNotificationEvent::AgencyWalletDepositSummary->value)
            ->where('meta->notification_type', 'agency_wallet_deposit_summary')
            ->where('meta->payload->period_label', $periodLabel)
            ->whereIn('status', ['queued', 'sent', 'sending'])
            ->exists();
    }

    /**
     * @param  array{amount: float|string, payment_method?: string|null, reference?: string|null, agent_note?: string|null, proof_path?: string|null}  $data
     */
    public function submitDepositRequest(Agent $agent, User $actor, array $data): AgentDepositRequest
    {
        $this->platformModuleEnforcer->ensureAgentDepositsEnabled();

        $wallet = $this->walletFor($agent);
        if ($wallet->status !== AgentWalletStatus::Active) {
            throw new InvalidArgumentException('Wallet is not active.');
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than zero.');
        }

        $deposit = DB::transaction(function () use ($agent, $actor, $wallet, $data, $amount): AgentDepositRequest {
            $depositReference = $this->resolveWalletReference($data['reference'] ?? null);

            $deposit = AgentDepositRequest::query()->create([
                'agency_id' => $agent->agency_id,
                'agent_id' => $agent->id,
                'user_id' => $agent->user_id,
                'agent_wallet_id' => $wallet->id,
                'amount' => $amount,
                'currency' => $wallet->currency,
                'payment_method' => $data['payment_method'] ?? null,
                'reference' => $depositReference,
                'proof_path' => $data['proof_path'] ?? null,
                'agent_note' => $data['agent_note'] ?? null,
                'status' => AgentDepositRequestStatus::Submitted,
            ]);

            AgentWalletTransaction::query()->create([
                'agency_id' => $agent->agency_id,
                'agent_id' => $agent->id,
                'user_id' => $agent->user_id,
                'agent_wallet_id' => $wallet->id,
                'agent_deposit_request_id' => $deposit->id,
                'type' => AgentWalletTransactionType::DepositRequest,
                'amount' => $amount,
                'balance_before' => (float) $wallet->balance,
                'balance_after' => (float) $wallet->balance,
                'status' => AgentWalletTransactionStatus::Pending,
                'reference' => $depositReference,
                'description' => 'Deposit request submitted',
                'created_by' => $actor->id,
                'meta' => ['payment_method' => $deposit->payment_method],
            ]);

            return $deposit;
        });

        $agent->loadMissing('agency');
        if ($agent->agency === null) {
            return $deposit;
        }

        $this->notifyDepositEvent(
            agency: $agent->agency,
            event: OtaNotificationEvent::AgentDepositSubmitted,
            deposit: $deposit->load(['agent.user']),
            actor: $actor,
            recipientContext: [
                'applicant_email' => $agent->user?->email,
            ],
        );

        return $deposit;
    }

    public function approveDeposit(AgentDepositRequest $deposit, User $reviewer): AgentDepositRequest
    {
        $this->platformModuleEnforcer->ensureAgentDepositsEnabled();

        if ($deposit->status !== AgentDepositRequestStatus::Submitted) {
            throw new InvalidArgumentException('Deposit is not awaiting approval.');
        }

        $deposit = DB::transaction(function () use ($deposit, $reviewer): AgentDepositRequest {
            $deposit = AgentDepositRequest::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if ($deposit->status !== AgentDepositRequestStatus::Submitted) {
                throw new InvalidArgumentException('Deposit is not awaiting approval.');
            }

            $wallet = AgentWallet::query()->whereKey($deposit->agent_wallet_id)->lockForUpdate()->firstOrFail();
            $balanceBefore = (float) $wallet->balance;
            $amount = (float) $deposit->amount;
            $balanceAfter = round($balanceBefore + $amount, 2);

            $wallet->update(['balance' => $balanceAfter]);

            AgentWalletTransaction::query()
                ->where('agent_deposit_request_id', $deposit->id)
                ->where('type', AgentWalletTransactionType::DepositRequest)
                ->update(['status' => AgentWalletTransactionStatus::Approved]);

            AgentWalletTransaction::query()->create([
                'agency_id' => $deposit->agency_id,
                'agent_id' => $deposit->agent_id,
                'user_id' => $deposit->user_id,
                'agent_wallet_id' => $wallet->id,
                'agent_deposit_request_id' => $deposit->id,
                'type' => AgentWalletTransactionType::DepositApproved,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => AgentWalletTransactionStatus::Posted,
                'reference' => $this->resolveWalletReference($deposit->reference),
                'description' => 'Deposit approved',
                'created_by' => $deposit->user_id,
                'approved_by' => $reviewer->id,
            ]);

            $deposit->update([
                'status' => AgentDepositRequestStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            return $deposit->fresh(['agent.user', 'wallet']);
        });

        app(LedgerEventRecorder::class)->recordAgencyDepositApproved($deposit, $reviewer);

        $deposit->loadMissing('agency');
        if ($deposit->agency === null) {
            return $deposit;
        }

        $this->notifyDepositEvent(
            agency: $deposit->agency,
            event: OtaNotificationEvent::AgentDepositApproved,
            deposit: $deposit,
            actor: $reviewer,
            recipientContext: [
                'applicant_email' => $deposit->agent?->user?->email,
            ],
        );

        return $deposit;
    }

    public function rejectDeposit(AgentDepositRequest $deposit, User $reviewer, string $adminNote): AgentDepositRequest
    {
        $this->platformModuleEnforcer->ensureAgentDepositsEnabled();

        if ($deposit->status !== AgentDepositRequestStatus::Submitted) {
            throw new InvalidArgumentException('Deposit is not awaiting review.');
        }

        $deposit = DB::transaction(function () use ($deposit, $reviewer, $adminNote): AgentDepositRequest {
            $deposit = AgentDepositRequest::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if ($deposit->status !== AgentDepositRequestStatus::Submitted) {
                throw new InvalidArgumentException('Deposit is not awaiting review.');
            }

            $wallet = AgentWallet::query()->whereKey($deposit->agent_wallet_id)->lockForUpdate()->firstOrFail();

            AgentWalletTransaction::query()
                ->where('agent_deposit_request_id', $deposit->id)
                ->where('type', AgentWalletTransactionType::DepositRequest)
                ->update(['status' => AgentWalletTransactionStatus::Rejected]);

            AgentWalletTransaction::query()->create([
                'agency_id' => $deposit->agency_id,
                'agent_id' => $deposit->agent_id,
                'user_id' => $deposit->user_id,
                'agent_wallet_id' => $wallet->id,
                'agent_deposit_request_id' => $deposit->id,
                'type' => AgentWalletTransactionType::DepositRejected,
                'amount' => (float) $deposit->amount,
                'balance_before' => (float) $wallet->balance,
                'balance_after' => (float) $wallet->balance,
                'status' => AgentWalletTransactionStatus::Rejected,
                'reference' => $this->resolveWalletReference($deposit->reference),
                'description' => 'Deposit rejected',
                'created_by' => $deposit->user_id,
                'approved_by' => $reviewer->id,
                'meta' => ['admin_note' => $adminNote],
            ]);

            $deposit->update([
                'status' => AgentDepositRequestStatus::Rejected,
                'admin_note' => $adminNote,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            return $deposit->fresh(['agent.user', 'wallet']);
        });

        $deposit->loadMissing('agency');
        if ($deposit->agency === null) {
            return $deposit;
        }

        $this->notifyDepositEvent(
            agency: $deposit->agency,
            event: OtaNotificationEvent::AgentDepositRejected,
            deposit: $deposit,
            actor: $reviewer,
            recipientContext: [
                'applicant_email' => $deposit->agent?->user?->email,
            ],
        );

        return $deposit;
    }

    /**
     * @param  array<string, mixed>  $recipientContext
     */
    /**
     * @param  Collection<int, AgentWallet>  $wallets
     */
    protected function walletWithLatestTransactionActivity(Collection $wallets): AgentWallet
    {
        $walletIds = $wallets->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $latest = AgentWalletTransaction::query()
            ->whereIn('agent_wallet_id', $walletIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($latest !== null) {
            $match = $wallets->firstWhere('id', (int) $latest->agent_wallet_id);
            if ($match !== null) {
                return $match;
            }
        }

        return $wallets->sortByDesc('id')->first()
            ?? throw new InvalidArgumentException('No wallet available for selection.');
    }

    public function primaryAgentForAgency(Agency|int $agency): ?Agent
    {
        $agencyModel = $agency instanceof Agency
            ? $agency
            : Agency::query()->find((int) $agency);

        if ($agencyModel === null) {
            return null;
        }

        $agents = $agencyModel->relationLoaded('agents')
            ? $agencyModel->agents
            : $agencyModel->agents()->with('user')->orderBy('id')->get();

        $ownerAgent = $agents
            ->filter(fn (Agent $agent): bool => (bool) $agent->is_active && $agent->user?->account_type === AccountType::Agent)
            ->sortBy('id')
            ->first();

        if ($ownerAgent !== null) {
            return $ownerAgent;
        }

        return $agents
            ->filter(fn (Agent $agent): bool => $agent->user?->account_type === AccountType::Agent)
            ->sortBy('id')
            ->first()
            ?? $agents->sortBy('id')->first();
    }

    protected function walletOwnerLabel(AgentWallet $wallet): string
    {
        $wallet->loadMissing(['agent.user', 'user']);

        return trim((string) ($wallet->agent?->user?->name ?? $wallet->user?->name ?? 'Agency wallet'));
    }

    /**
     * @param  list<int>  $walletIds
     * @return array<int, string>
     */
    protected function lastMovementAtByWalletId(array $walletIds): array
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
     * @param  array<string, mixed>  $recipientContext
     */
    protected function notifyDepositEvent(
        Agency $agency,
        OtaNotificationEvent $event,
        AgentDepositRequest $deposit,
        User $actor,
        array $recipientContext = [],
    ): void {
        $agentName = trim((string) ($deposit->agent?->user?->name ?? 'Agent'));

        $this->notificationService->send(
            agency: $agency,
            eventKey: $event->value,
            payload: [
                'agent_name' => $agentName,
                'amount' => number_format((float) $deposit->amount, 2),
                'currency' => (string) $deposit->currency,
                'reference' => (string) ($deposit->reference ?? ''),
                'payment_method' => (string) ($deposit->payment_method ?? ''),
                'deposit_id' => (string) $deposit->id,
            ],
            actor: $actor,
            fallbackSubject: match ($event) {
                OtaNotificationEvent::AgentDepositSubmitted => 'Agent deposit request submitted',
                OtaNotificationEvent::AgentDepositApproved => 'Agent deposit approved',
                OtaNotificationEvent::AgentDepositRejected => 'Agent deposit rejected',
                default => 'Agent wallet notification',
            },
            fallbackBody: match ($event) {
                OtaNotificationEvent::AgentDepositSubmitted => "{$agentName} submitted a deposit request for review.",
                OtaNotificationEvent::AgentDepositApproved => "Your deposit of {$deposit->currency} ".number_format((float) $deposit->amount, 2).' was approved.',
                OtaNotificationEvent::AgentDepositRejected => 'Your deposit request was rejected. Contact finance if you have questions.',
                default => 'Agent wallet update.',
            },
            templateVariables: [
                'agent_name' => $agentName,
                'amount' => number_format((float) $deposit->amount, 2),
                'currency' => (string) $deposit->currency,
            ],
            recipientContext: $recipientContext,
        );
    }

    protected function resolveWalletReference(?string $reference): string
    {
        $trimmed = trim((string) ($reference ?? ''));

        if ($trimmed !== '') {
            return $trimmed;
        }

        return $this->referenceGenerator->generateUnique('agent_wallet_transactions', 'reference', 9, 'W');
    }
}
