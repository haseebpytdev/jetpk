<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\User;
use App\Support\Agencies\AgencyRoleResolver;
use App\Support\Agents\AgentPermission;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class AgencyRolesBackfillCommand extends Command
{
    protected $signature = 'agency-roles:backfill
                            {--dry-run : Preview changes without writing}
                            {--agency= : Limit to one agency ID}
                            {--user= : Limit to one user ID}';

    protected $description = 'Backfill agency_users.agency_role from account type and agent staff permissions';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $agencyFilter = $this->option('agency') !== null ? (int) $this->option('agency') : null;
        $userFilter = $this->option('user') !== null ? (int) $this->option('user') : null;

        if ($dryRun) {
            $this->info('Dry run — no database changes will be made.');
        } else {
            $this->warn('Writing agency_role values to agency_users.');
        }

        $stats = [
            'scanned' => 0,
            'would_update' => 0,
            'updated' => 0,
            'skipped_existing' => 0,
            'skipped_ineligible' => 0,
            'missing_user' => 0,
            'missing_agency' => 0,
            'ambiguous' => 0,
        ];

        $query = AgencyUser::query()
            ->when($agencyFilter !== null, fn (Builder $q): Builder => $q->where('agency_id', $agencyFilter))
            ->when($userFilter !== null, fn (Builder $q): Builder => $q->where('user_id', $userFilter))
            ->orderBy('id');

        $query->chunkById(100, function ($rows) use ($dryRun, &$stats): void {
            $userIds = $rows->pluck('user_id')->unique()->values()->all();
            $agencyIds = $rows->pluck('agency_id')->unique()->values()->all();

            $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');
            $agencies = Agency::query()->whereIn('id', $agencyIds)->get()->keyBy('id');

            foreach ($rows as $membership) {
                $stats['scanned']++;

                if (! $agencies->has($membership->agency_id)) {
                    $stats['missing_agency']++;
                    $this->warn(sprintf(
                        'Missing agency #%d for agency_users #%d (user #%d).',
                        $membership->agency_id,
                        $membership->id,
                        $membership->user_id,
                    ));

                    continue;
                }

                if (! $users->has($membership->user_id)) {
                    $stats['missing_user']++;
                    $this->warn(sprintf(
                        'Missing user #%d for agency_users #%d (agency #%d).',
                        $membership->user_id,
                        $membership->id,
                        $membership->agency_id,
                    ));

                    continue;
                }

                if (AgencyRole::fromNullable($membership->agency_role) !== null) {
                    $stats['skipped_existing']++;

                    continue;
                }

                /** @var User $user */
                $user = $users->get($membership->user_id);

                if (! $this->isEligibleForBackfill($user, $membership)) {
                    $stats['skipped_ineligible']++;

                    continue;
                }

                $resolved = $this->resolveBackfillRole($user, $membership);

                if ($this->isAmbiguousInference($user, $resolved)) {
                    $stats['ambiguous']++;
                }

                $stats['would_update']++;

                $this->line(sprintf(
                    '[%s] agency_users #%d user #%d (%s) agency #%d → %s (legacy role: %s)',
                    $dryRun ? 'dry-run' : 'update',
                    $membership->id,
                    $user->id,
                    $user->email,
                    $membership->agency_id,
                    $resolved->value,
                    (string) ($membership->role ?? '—'),
                ));

                if (! $dryRun) {
                    AgencyUser::query()
                        ->where('id', $membership->id)
                        ->update(['agency_role' => $resolved->value]);
                    $stats['updated']++;
                }
            }
        });

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn (int $count, string $metric): array => [
                match ($metric) {
                    'skipped_ineligible' => 'Skipped ineligible (non-agency portal)',
                    default => str_replace('_', ' ', ucfirst($metric)),
                },
                (string) $count,
            ])->values()->all(),
        );

        if ($dryRun && $stats['would_update'] > 0) {
            $this->comment('Re-run without --dry-run to apply these changes.');
        }

        return self::SUCCESS;
    }

    protected function isEligibleForBackfill(User $user, AgencyUser $membership): bool
    {
        if (in_array($user->account_type, [AccountType::Agent, AccountType::AgentStaff], true)) {
            return true;
        }

        return in_array((string) ($membership->role ?? ''), [
            AccountType::Agent->value,
            AccountType::AgentStaff->value,
        ], true);
    }

    protected function isAgentPortalOwner(User $user, AgencyUser $membership): bool
    {
        if ($user->account_type === AccountType::Agent) {
            return true;
        }

        return (string) ($membership->role ?? '') === AccountType::Agent->value;
    }

    protected function resolveBackfillRole(User $user, AgencyUser $membership): AgencyRole
    {
        if ($this->isAgentPortalOwner($user, $membership)) {
            return AgencyRole::Owner;
        }

        $permissions = is_array($user->meta['agent_permissions'] ?? null)
            ? $user->meta['agent_permissions']
            : [];

        return AgencyRoleResolver::inferFromAgentStaffPermissions($permissions);
    }

    protected function isAmbiguousInference(User $user, AgencyRole $resolved): bool
    {
        if ($user->account_type !== AccountType::AgentStaff) {
            return false;
        }

        $permissions = is_array($user->meta['agent_permissions'] ?? null)
            ? $user->meta['agent_permissions']
            : [];

        if ($permissions === []) {
            return false;
        }

        $signals = 0;
        if (in_array(AgentPermission::StaffManage, $permissions, true)) {
            $signals++;
        }
        if (
            in_array(AgentPermission::LedgerManage, $permissions, true)
            || (
                in_array(AgentPermission::LedgerView, $permissions, true)
                && in_array(AgentPermission::WalletView, $permissions, true)
                && in_array(AgentPermission::PaymentsUpload, $permissions, true)
            )
        ) {
            $signals++;
        }
        if (
            in_array(AgentPermission::BookingsCreate, $permissions, true)
            && in_array(AgentPermission::TravelersManage, $permissions, true)
        ) {
            $signals++;
        }
        if (in_array(AgentPermission::SupportManage, $permissions, true)) {
            $signals++;
        }

        return $signals > 1;
    }
}
