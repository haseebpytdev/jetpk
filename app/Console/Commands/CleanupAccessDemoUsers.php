<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Safely rename legacy demo usernames and promote the real platform owner without deleting data.
 */
class CleanupAccessDemoUsers extends Command
{
    protected $signature = 'ota:cleanup-access-demo-users
                            {--dry-run : Preview changes without saving}
                            {--force : Required to apply changes}
                            {--deactivate-legacy : Set legacy @ota.demo users inactive after renaming}
                            {--password= : Optional password applied to the promoted platform owner}';

    protected $description = 'Safely rename legacy demo usernames and promote the real platform owner';

    /**
     * @var list<array{email: string, username_before: string|null, username_after: string|null, account_type_before: string|null, account_type_after: string|null, status_before: string|null, status_after: string|null, action: string}>
     */
    protected array $changeLog = [];

    protected bool $usernameColumnExists = false;

    /**
     * @var list<array{email: string, from: string, to: string}>
     */
    protected array $legacyRenames = [
        ['email' => 'agent@ota.demo', 'from' => 'agent', 'to' => 'legacyagent'],
        ['email' => 'staff@ota.demo', 'from' => 'staff', 'to' => 'legacystaff'],
        ['email' => 'customer@ota.demo', 'from' => 'customer', 'to' => 'legacycustomer'],
    ];

    public function handle(): int
    {
        $this->usernameColumnExists = Schema::hasColumn('users', 'username');
        if (! $this->usernameColumnExists) {
            $this->comment('Users table has no username column; only account_type changes will apply.');
        }

        $this->buildChangePlan();

        if ($this->changeLog === []) {
            $this->info('No access demo cleanup changes needed.');

            return self::SUCCESS;
        }

        if (! $this->validateTargetUsernames()) {
            return self::FAILURE;
        }

        $this->info('OTA access demo cleanup'.($this->option('dry-run') ? ' (dry-run)' : '').'…');
        $this->printChangeLog();

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->comment('Dry-run complete. No changes were saved.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->error('Refusing to apply changes without --force.');

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            foreach ($this->changeLog as $entry) {
                $this->applyChange($entry);
            }
        });

        $this->newLine();
        $this->info('Access demo cleanup complete.');

        return self::SUCCESS;
    }

    protected function buildChangePlan(): void
    {
        $this->planOwnerPromotion();
        $this->planLegacyRenames();
    }

    protected function planOwnerPromotion(): void
    {
        $ownerEmail = trim((string) config('ota.access_demo.owner_email', 'myworkhaseeb@gmail.com'));
        if ($ownerEmail === '') {
            return;
        }

        $owner = User::query()->where('email', $ownerEmail)->first();
        if ($owner === null) {
            $this->comment('Platform owner '.$ownerEmail.' not found; skipping owner promotion.');

            return;
        }

        $accountTypeBefore = $owner->account_type?->value;
        $accountTypeAfter = AccountType::PlatformAdmin->value;

        if ($accountTypeBefore === $accountTypeAfter && ! $this->passwordProvided()) {
            return;
        }

        $this->changeLog[] = [
            'email' => $ownerEmail,
            'username_before' => $this->usernameColumnExists ? $owner->username : null,
            'username_after' => $this->usernameColumnExists ? $owner->username : null,
            'account_type_before' => $accountTypeBefore,
            'account_type_after' => $accountTypeAfter,
            'status_before' => $owner->status?->value,
            'status_after' => $owner->status?->value,
            'action' => 'promote_owner',
        ];
    }

    protected function planLegacyRenames(): void
    {
        foreach ($this->legacyRenames as $rename) {
            $user = User::query()->where('email', $rename['email'])->first();
            if ($user === null) {
                continue;
            }

            $usernameBefore = $this->usernameColumnExists ? $user->username : null;
            $usernameAfter = $usernameBefore;
            $statusBefore = $user->status?->value;
            $statusAfter = $statusBefore;
            $action = 'skip';

            if ($this->usernameColumnExists) {
                if ($usernameBefore === $rename['to']) {
                    $action = 'legacy_already_renamed';
                } elseif ($usernameBefore === $rename['from']) {
                    $usernameAfter = $rename['to'];
                    $action = 'rename_legacy_username';
                } elseif ($usernameBefore !== null && $usernameBefore !== $rename['from']) {
                    $this->comment(sprintf(
                        'Skipping %s — username "%s" is not the legacy conflict username "%s".',
                        $rename['email'],
                        $usernameBefore,
                        $rename['from'],
                    ));

                    continue;
                }
            }

            if ($this->option('deactivate-legacy') && $statusBefore !== UserAccountStatus::Inactive->value) {
                $statusAfter = UserAccountStatus::Inactive->value;
                $action = $action === 'skip' ? 'deactivate_legacy' : $action.'+deactivate';
            }

            if ($action === 'skip' || ($action === 'legacy_already_renamed' && $statusBefore === $statusAfter)) {
                continue;
            }

            $this->changeLog[] = [
                'email' => $rename['email'],
                'username_before' => $usernameBefore,
                'username_after' => $usernameAfter,
                'account_type_before' => $user->account_type?->value,
                'account_type_after' => $user->account_type?->value,
                'status_before' => $statusBefore,
                'status_after' => $statusAfter,
                'action' => $action,
            ];
        }
    }

    protected function validateTargetUsernames(): bool
    {
        if (! $this->usernameColumnExists) {
            return true;
        }

        $valid = true;

        foreach ($this->changeLog as $entry) {
            if ($entry['username_after'] === null || $entry['username_after'] === $entry['username_before']) {
                continue;
            }

            $targetUser = User::query()->where('email', $entry['email'])->first();
            $owner = User::query()->where('username', $entry['username_after'])->first();

            if ($owner === null) {
                continue;
            }

            if ($targetUser !== null && $owner->id === $targetUser->id) {
                continue;
            }

            $this->warn(sprintf(
                'Target username "%s" for %s is already used by %s (#%d). Refusing to overwrite.',
                $entry['username_after'],
                $entry['email'],
                $owner->email,
                $owner->id,
            ));
            $valid = false;
        }

        return $valid;
    }

    /**
     * @param  array{email: string, username_before: string|null, username_after: string|null, account_type_before: string|null, account_type_after: string|null, status_before: string|null, status_after: string|null, action: string}  $entry
     */
    protected function applyChange(array $entry): void
    {
        $user = User::query()->where('email', $entry['email'])->first();
        if ($user === null) {
            return;
        }

        $attributes = [];

        if ($entry['account_type_after'] !== null && $entry['account_type_after'] !== $entry['account_type_before']) {
            $attributes['account_type'] = $entry['account_type_after'];
        }

        if ($this->usernameColumnExists
            && $entry['username_after'] !== null
            && $entry['username_after'] !== $entry['username_before']) {
            $attributes['username'] = $entry['username_after'];
        }

        if ($entry['status_after'] !== null && $entry['status_after'] !== $entry['status_before']) {
            $attributes['status'] = $entry['status_after'];
        }

        if ($entry['action'] === 'promote_owner' && $this->passwordProvided()) {
            $attributes['password'] = Hash::make((string) $this->option('password'));
        }

        if ($attributes !== []) {
            $user->update($attributes);
        }
    }

    protected function passwordProvided(): bool
    {
        return filled($this->option('password'));
    }

    protected function printChangeLog(): void
    {
        foreach ($this->changeLog as $entry) {
            $line = sprintf(
                '[%s] %s: account_type %s → %s',
                $this->option('dry-run') ? 'dry-run' : 'apply',
                $entry['email'],
                $entry['account_type_before'] ?? '(unknown)',
                $entry['account_type_after'] ?? '(unchanged)',
            );

            if ($this->usernameColumnExists) {
                $line .= sprintf(
                    ' (username: %s → %s)',
                    $entry['username_before'] ?? '(none)',
                    $entry['username_after'] ?? '(unchanged)',
                );
            }

            if ($entry['status_before'] !== $entry['status_after']) {
                $line .= sprintf(
                    ' (status: %s → %s)',
                    $entry['status_before'] ?? '(unknown)',
                    $entry['status_after'] ?? '(unchanged)',
                );
            }

            $this->line($line);
        }
    }
}
