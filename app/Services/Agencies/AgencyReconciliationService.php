<?php

namespace App\Services\Agencies;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Models\Booking;
use App\Models\User;
use App\Services\Agents\AgentApplicationOnboardingService;
use App\Services\Communication\AgencyMessageTemplateSeeder;
use App\Support\Agencies\AgencyScopeResolver;
use App\Support\References\CompactReferenceGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Diagnose and safely repair approved agent-application / agency-owner linkages.
 * Never deletes data or moves bookings between agencies.
 */
class AgencyReconciliationService
{
    public function __construct(
        protected AgencyBrandingService $brandingService,
        protected AgencyMessageTemplateSeeder $messageTemplateSeeder,
        protected AgentApplicationOnboardingService $onboardingService,
        protected CompactReferenceGenerator $referenceGenerator,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function diagnose(): array
    {
        $issues = [];

        foreach ($this->approvedApplications() as $application) {
            $issues[] = $this->diagnoseApplication($application);
        }

        foreach ($this->orphanAgencyOwners() as $user) {
            $issues[] = $this->diagnoseOwnerUser($user);
        }

        return array_values(array_filter($issues, fn (array $row): bool => (bool) ($row['needs_repair'] ?? false)));
    }

    /**
     * @return array{dry_run: bool, repaired: int, skipped: int, rows: array<int, array<string, mixed>>}
     */
    public function reconcile(bool $dryRun = true): array
    {
        $rows = [];
        $repaired = 0;
        $skipped = 0;

        foreach ($this->approvedApplications() as $application) {
            $row = $this->diagnoseApplication($application);
            if (! ($row['needs_repair'] ?? false)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $row['action'] = 'would_repair';
                $rows[] = $row;
                $repaired++;

                continue;
            }

            $rows[] = array_merge($row, $this->repairApplication($application));
            $repaired++;
        }

        foreach ($this->orphanAgencyOwners() as $user) {
            $row = $this->diagnoseOwnerUser($user);
            if (! ($row['needs_repair'] ?? false)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $row['action'] = 'would_repair';
                $rows[] = $row;
                $repaired++;

                continue;
            }

            $rows[] = array_merge($row, $this->repairOwnerUser($user));
            $repaired++;
        }

        return [
            'dry_run' => $dryRun,
            'repaired' => $repaired,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    public function applicationNeedsRepair(AgentApplication $application): bool
    {
        if ($application->status !== 'approved') {
            return false;
        }

        return (bool) ($this->diagnoseApplication($application)['needs_repair'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnoseApplication(AgentApplication $application): array
    {
        $user = User::query()->where('email', $application->email)->first();
        $expectedAgencyName = trim((string) ($application->company_name ?: trim($application->first_name.' '.$application->last_name)));
        $canonical = $this->canonicalAgentForApplication($application, $user);
        $platformAgency = $this->platformAgency();
        $duplicateIds = $user !== null && $canonical !== null
            ? $this->duplicateActiveAgents($user, $canonical)->pluck('id')->all()
            : [];

        $issues = [];
        if ($user === null) {
            $issues[] = 'missing_owner_user';
        } elseif ($user->account_type !== AccountType::Agent) {
            $issues[] = 'owner_not_agent_account_type';
        }
        if ($canonical === null) {
            $issues[] = 'missing_agent_record';
        } elseif (! $canonical->is_active) {
            $issues[] = 'agent_inactive';
        }
        if ($user !== null && $canonical !== null && $user->current_agency_id !== $canonical->agency_id) {
            $issues[] = 'user_agency_mismatch';
        }
        if ($canonical !== null && $platformAgency !== null && $canonical->agency_id === $platformAgency->id && $expectedAgencyName !== '' && ! AgencyScopeResolver::namesMatch($expectedAgencyName, AgencyScopeResolver::displayName($platformAgency))) {
            $issues[] = 'owner_still_on_platform_agency';
        }
        if ($canonical !== null && $expectedAgencyName !== '' && $canonical->agency !== null && ! AgencyScopeResolver::namesMatch($expectedAgencyName, AgencyScopeResolver::displayName($canonical->agency))) {
            $issues[] = 'agency_name_mismatch';
        }
        if ($duplicateIds !== []) {
            $issues[] = 'duplicate_active_agent_rows';
        }

        return [
            'type' => 'application',
            'application_id' => $application->id,
            'email' => $application->email,
            'company_name' => $application->company_name,
            'owner_user_id' => $user?->id,
            'user_id' => $user?->id,
            'canonical_agent_id' => $canonical?->id,
            'agent_id' => $canonical?->id,
            'agency_id' => $canonical?->agency_id,
            'canonical_agent_agency_id' => $canonical?->agency_id,
            'agency_name' => $canonical !== null && $canonical->agency !== null ? AgencyScopeResolver::displayName($canonical->agency) : null,
            'duplicate_active_agent_ids' => $duplicateIds,
            'issues' => $issues,
            'needs_repair' => $issues !== [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function diagnoseOwnerUser(User $user): array
    {
        $canonical = AgencyScopeResolver::canonicalOwnerAgent($user);
        $duplicateIds = $canonical !== null
            ? $this->duplicateActiveAgents($user, $canonical)->pluck('id')->all()
            : [];

        $issues = [];
        if ($canonical === null) {
            $issues[] = 'missing_agent_record';
        } elseif (! $canonical->is_active) {
            $issues[] = 'agent_inactive';
        }
        if ($canonical !== null && $user->current_agency_id !== $canonical->agency_id) {
            $issues[] = 'user_agency_mismatch';
        }
        if ($duplicateIds !== []) {
            $issues[] = 'duplicate_active_agent_rows';
        }

        return [
            'type' => 'owner_user',
            'owner_user_id' => $user->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'canonical_agent_id' => $canonical?->id,
            'agent_id' => $canonical?->id,
            'agency_id' => $canonical?->agency_id,
            'canonical_agent_agency_id' => $canonical?->agency_id,
            'agency_name' => $canonical !== null && $canonical->agency !== null ? AgencyScopeResolver::displayName($canonical->agency) : null,
            'duplicate_active_agent_ids' => $duplicateIds,
            'issues' => $issues,
            'needs_repair' => $issues !== [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function repairApplication(AgentApplication $application): array
    {
        return DB::transaction(function () use ($application): array {
            $before = $this->diagnoseApplication($application);
            $oldUserAgencyId = User::query()->where('email', $application->email)->value('current_agency_id');
            $oldCanonicalAgencyId = $before['canonical_agent_agency_id'] ?? null;

            $user = User::query()->firstOrCreate(
                ['email' => $application->email],
                [
                    'name' => trim($application->first_name.' '.$application->last_name),
                    'password' => bcrypt(str()->random(32)),
                    'account_type' => AccountType::Agent,
                    'status' => UserAccountStatus::Invited,
                    'invited_at' => now(),
                    'meta' => [
                        'phone' => $application->mobile,
                        'city' => $application->city,
                        'company_name' => $application->company_name,
                    ],
                ]
            );

            $agency = $this->resolveTargetAgency($application, $user);
            $user->forceFill([
                'account_type' => AccountType::Agent,
                'current_agency_id' => $agency->id,
                'meta' => array_merge($user->meta ?? [], [
                    'phone' => $application->mobile,
                    'city' => $application->city,
                    'company_name' => $application->company_name,
                ]),
            ])->save();

            $agent = Agent::query()->updateOrCreate(
                ['user_id' => $user->id, 'agency_id' => $agency->id],
                [
                    'code' => Agent::query()->where('user_id', $user->id)->value('code')
                        ?: $this->referenceGenerator->generateUnique('agents', 'code', 7),
                    'commission_percent' => 0,
                    'is_active' => true,
                    'meta' => [
                        'city' => $application->city,
                        'company_name' => $application->company_name,
                        'mobile' => $application->mobile,
                    ],
                ]
            );

            AgencyUser::query()->updateOrCreate(
                ['agency_id' => $agency->id, 'user_id' => $user->id],
                ['role' => AccountType::Agent->value]
            );

            $deactivatedIds = $this->deactivateDuplicateAgents($user->fresh(), $agent->fresh(['agency.agencySetting']));
            $after = $this->diagnoseApplication($application->fresh());

            return [
                'action' => 'repaired',
                'owner_user_id' => $user->id,
                'canonical_agent_id' => $agent->id,
                'old_duplicate_agent_ids' => $before['duplicate_active_agent_ids'] ?? [],
                'duplicate_rows_deactivated' => count($deactivatedIds),
                'deactivated_agent_ids' => $deactivatedIds,
                'old_user_agency_id' => $oldUserAgencyId,
                'new_user_agency_id' => $user->current_agency_id,
                'old_canonical_agent_agency_id' => $oldCanonicalAgencyId,
                'new_canonical_agent_agency_id' => $agent->agency_id,
                'before' => $before,
                'after' => $after,
                'agency_id' => $agency->id,
                'agency_name' => AgencyScopeResolver::displayName($agency),
                'user_id' => $user->id,
                'agent_id' => $agent->id,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function repairOwnerUser(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $before = $this->diagnoseOwnerUser($user);
            $oldUserAgencyId = $user->current_agency_id;
            $oldCanonicalAgencyId = $before['canonical_agent_agency_id'] ?? null;
            $companyName = trim((string) ($user->meta['company_name'] ?? $user->name));
            $agency = $this->findAgencyByName($companyName) ?? $this->createAgency($companyName !== '' ? $companyName : ('Agency for '.$user->email));

            $agent = Agent::query()->updateOrCreate(
                ['user_id' => $user->id, 'agency_id' => $agency->id],
                [
                    'code' => Agent::query()->where('user_id', $user->id)->value('code')
                        ?: $this->referenceGenerator->generateUnique('agents', 'code', 7),
                    'commission_percent' => 0,
                    'is_active' => true,
                    'meta' => is_array($user->meta) ? $user->meta : [],
                ]
            );

            $user->forceFill([
                'account_type' => AccountType::Agent,
                'current_agency_id' => $agency->id,
            ])->save();

            AgencyUser::query()->updateOrCreate(
                ['agency_id' => $agency->id, 'user_id' => $user->id],
                ['role' => AccountType::Agent->value]
            );

            $deactivatedIds = $this->deactivateDuplicateAgents($user->fresh(), $agent->fresh(['agency.agencySetting']));

            return [
                'action' => 'repaired',
                'owner_user_id' => $user->id,
                'canonical_agent_id' => $agent->id,
                'old_duplicate_agent_ids' => $before['duplicate_active_agent_ids'] ?? [],
                'duplicate_rows_deactivated' => count($deactivatedIds),
                'deactivated_agent_ids' => $deactivatedIds,
                'old_user_agency_id' => $oldUserAgencyId,
                'new_user_agency_id' => $user->current_agency_id,
                'old_canonical_agent_agency_id' => $oldCanonicalAgencyId,
                'new_canonical_agent_agency_id' => $agent->agency_id,
                'before' => $before,
                'after' => $this->diagnoseOwnerUser($user->fresh()),
                'agency_id' => $agency->id,
                'agency_name' => AgencyScopeResolver::displayName($agency),
                'agent_id' => $agent->id,
            ];
        });
    }

    protected function canonicalAgentForApplication(AgentApplication $application, ?User $user): ?Agent
    {
        if ($user === null) {
            return null;
        }

        $expectedAgencyName = trim((string) ($application->company_name ?: trim($application->first_name.' '.$application->last_name)));
        $expectedAgency = $this->findAgencyByName($expectedAgencyName);
        $agents = Agent::query()->with(['agency.agencySetting'])->where('user_id', $user->id)->orderBy('id')->get();

        if ($expectedAgency !== null) {
            $onExpected = $agents->where('agency_id', $expectedAgency->id);
            $activeOnExpected = $onExpected->first(fn (Agent $agent): bool => (bool) $agent->is_active);
            if ($activeOnExpected !== null) {
                return $activeOnExpected;
            }
            if ($onExpected->isNotEmpty()) {
                return $onExpected->sortByDesc('id')->first();
            }
        }

        return AgencyScopeResolver::canonicalOwnerAgent(
            $user->load(['agentProfiles.agency.agencySetting']),
            $expectedAgencyName
        );
    }

    /**
     * @return Collection<int, Agent>
     */
    protected function duplicateActiveAgents(User $user, Agent $canonical): Collection
    {
        return Agent::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $canonical->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    protected function deactivateDuplicateAgents(User $user, Agent $canonical): array
    {
        $deactivated = [];
        foreach ($this->duplicateActiveAgents($user, $canonical) as $duplicate) {
            $duplicate->forceFill(['is_active' => false])->save();
            $deactivated[] = (int) $duplicate->id;
        }

        return $deactivated;
    }

    protected function resolveTargetAgency(AgentApplication $application, User $user): Agency
    {
        $byName = $this->findAgencyByName((string) $application->company_name);
        if ($byName !== null) {
            return $byName;
        }

        $canonical = $this->canonicalAgentForApplication($application, $user);
        if ($canonical?->agency !== null && ! $this->isPlatformAgency($canonical->agency)) {
            return $canonical->agency;
        }

        $existingAgent = Agent::query()->where('user_id', $user->id)->orderBy('id')->first();
        if ($existingAgent !== null && ! $this->agentHasBookings($existingAgent) && $this->shouldMoveFromPlatformAgency($application, $existingAgent)) {
            return $this->createAgency(trim((string) ($application->company_name ?: $user->name)));
        }

        if ($existingAgent !== null && $existingAgent->agency !== null) {
            return $existingAgent->agency;
        }

        return $this->createAgency(trim((string) ($application->company_name ?: $user->name)));
    }

    protected function shouldMoveFromPlatformAgency(AgentApplication $application, Agent $agent): bool
    {
        $platform = $this->platformAgency();
        if ($platform === null || $agent->agency_id !== $platform->id) {
            return false;
        }

        $expected = trim((string) ($application->company_name ?: ''));
        if ($expected === '') {
            return false;
        }

        return ! AgencyScopeResolver::namesMatch($expected, AgencyScopeResolver::displayName($platform));
    }

    protected function isPlatformAgency(Agency $agency): bool
    {
        $slug = (string) config('ota.default_agency_slug', '');

        return $slug !== '' && $agency->slug === $slug;
    }

    protected function agentHasBookings(Agent $agent): bool
    {
        return Booking::query()->where('agent_id', $agent->id)->exists();
    }

    protected function createAgency(string $name): Agency
    {
        $name = trim($name) !== '' ? trim($name) : 'Agency';
        $slug = $this->uniqueAgencySlug($name);
        $agency = Agency::query()->create([
            'name' => $name,
            'slug' => $slug,
            'timezone' => 'Asia/Karachi',
        ]);
        $this->brandingService->getSettingsForAgency($agency);
        $agency->agencySetting?->forceFill(['display_name' => $name])->save();

        $this->seedDefaultMessageTemplatesForNewAgency($agency);

        return $agency->fresh(['agencySetting']);
    }

    protected function findAgencyByName(string $name): ?Agency
    {
        $needle = strtolower(trim($name));
        if ($needle === '') {
            return null;
        }

        return Agency::query()
            ->with('agencySetting')
            ->get()
            ->first(fn (Agency $agency): bool => AgencyScopeResolver::namesMatch($needle, AgencyScopeResolver::displayName($agency))
                || AgencyScopeResolver::namesMatch($needle, (string) $agency->name));
    }

    protected function uniqueAgencySlug(string $name): string
    {
        $base = Str::slug($name) ?: 'agency';
        $slug = $base;
        $suffix = 2;
        while (Agency::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    protected function platformAgency(): ?Agency
    {
        $slug = (string) config('ota.default_agency_slug', '');

        return $slug !== ''
            ? Agency::query()->with('agencySetting')->where('slug', $slug)->first()
            : Agency::query()->with('agencySetting')->orderBy('id')->first();
    }

    /**
     * @return Collection<int, AgentApplication>
     */
    protected function approvedApplications(): Collection
    {
        return AgentApplication::query()->where('status', 'approved')->orderBy('id')->get();
    }

    /**
     * @return Collection<int, User>
     */
    protected function orphanAgencyOwners(): Collection
    {
        return User::query()
            ->where('account_type', AccountType::Agent)
            ->whereDoesntHave('agentProfiles')
            ->orderBy('id')
            ->get();
    }

    protected function seedDefaultMessageTemplatesForNewAgency(Agency $agency): void
    {
        try {
            $stats = $this->messageTemplateSeeder->seedAllDefaultsForAgency($agency);
            Log::info('Agency message templates seeded for new agency', [
                'agency_id' => $agency->id,
                'created' => $stats['created'],
                'skipped_existing' => $stats['skipped_existing'],
                'skipped_no_defaults' => $stats['skipped_no_defaults'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Agency message template seeding failed for new agency', [
                'agency_id' => $agency->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
