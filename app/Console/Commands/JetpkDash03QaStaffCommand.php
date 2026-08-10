<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Access\RolePermissionMatrix;
use App\Support\Staff\StaffPermission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Controlled JP-DASH-03 temporary QA Staff identity lifecycle.
 * Never prints email, password, OTP, or remember tokens.
 */
class JetpkDash03QaStaffCommand extends Command
{
    private const QA_MARKER = 'jp-dash-03-qa-staff';

    protected $signature = 'jetpk:dash-03-qa-staff
        {action=status : create|deactivate|restore-baseline|status}
        {--preset=staff_operator : Canonical staff permission preset key}';

    protected $description = 'JP-DASH-03 controlled temporary QA Staff identity (sanitized output only)';

    public function handle(): int
    {
        $action = (string) $this->argument('action');

        return match ($action) {
            'create' => $this->createQaStaff(),
            'deactivate' => $this->deactivateQaStaff(),
            'restore-baseline' => $this->restoreBaseline(),
            'status' => $this->reportStatus(),
            default => $this->invalidAction($action),
        };
    }

    private function invalidAction(string $action): int
    {
        $this->line('QA_STAFF_COMMAND=INVALID_ACTION');
        $this->error("Unknown action: {$action}");

        return self::FAILURE;
    }

    private function qaUserQuery()
    {
        return User::query()
            ->where('account_type', AccountType::Staff)
            ->where(function ($query): void {
                $query->where('username', self::QA_MARKER)
                    ->orWhere('name', 'JP-DASH-03 QA Staff');
            });
    }

    private function createQaStaff(): int
    {
        if ($this->qaUserQuery()->exists()) {
            $this->line('QA_STAFF_CREATED=already_exists');
            $this->reportStatus();

            return self::SUCCESS;
        }

        $password = (string) env('JP_DASH_03_QA_STAFF_PASSWORD', '');
        if ($password === '') {
            $this->line('QA_STAFF_PASSWORD_REQUIRED=YES');
            $this->error('Set JP_DASH_03_QA_STAFF_PASSWORD in the process environment (never commit).');

            return self::FAILURE;
        }

        $agency = Agency::query()->orderBy('id')->first();
        if ($agency === null) {
            $this->line('QA_STAFF_CREATED=no');
            $this->error('No agency available for staff profile.');

            return self::FAILURE;
        }

        $preset = (string) $this->option('preset');
        $permissions = StaffPermission::presetPermissions($preset);
        if ($permissions === []) {
            $this->line('QA_STAFF_CREATED=no');
            $this->error('Invalid staff preset.');

            return self::FAILURE;
        }

        $email = 'jp-dash-03-qa-staff@jetpakistan.pk';

        DB::transaction(function () use ($agency, $password, $permissions, $preset, $email): void {
            $user = User::query()->create([
                'name' => 'JP-DASH-03 QA Staff',
                'email' => $email,
                'username' => self::QA_MARKER,
                'password' => Hash::make($password),
                'account_type' => AccountType::Staff,
                'current_agency_id' => $agency->id,
                'status' => UserAccountStatus::Active,
                'email_verified_at' => now(),
                'meta' => [
                    'staff_permissions' => RolePermissionMatrix::normalizeStaffPermissions($permissions),
                    'permission_group' => $preset,
                    'jp_dash_03_qa' => true,
                ],
            ]);

            AgencyUser::query()->create([
                'agency_id' => $agency->id,
                'user_id' => $user->id,
                'role' => AccountType::Staff->value,
            ]);

            StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id, 'agency_id' => $agency->id],
                [
                    'job_title' => 'Operations Staff',
                    'department' => 'Operations',
                    'is_active' => true,
                ],
            );
        });

        $this->line('QA_STAFF_CREATED=yes');
        $this->line('QA_STAFF_ACCOUNT_TYPE=Staff');
        $this->line('QA_STAFF_BASELINE_ROLE='.$preset);
        $this->line('QA_STAFF_STATUS=Active');
        $this->line('QA_STAFF_EMAIL_CHANNEL_REQUIRED=YES');

        return self::SUCCESS;
    }

    private function deactivateQaStaff(): int
    {
        $user = $this->qaUserQuery()->first();
        if ($user === null) {
            $this->line('QA_STAFF_STATUS=missing');

            return self::SUCCESS;
        }

        $user->forceFill(['status' => UserAccountStatus::Suspended])->save();
        StaffProfile::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        $this->line('QA_STAFF_STATUS=Inactive');

        return self::SUCCESS;
    }

    private function restoreBaseline(): int
    {
        $user = $this->qaUserQuery()->first();
        if ($user === null) {
            $this->line('QA_STAFF_STATUS=missing');

            return self::FAILURE;
        }

        $preset = (string) $this->option('preset');
        $permissions = StaffPermission::presetPermissions($preset);
        $meta = $user->meta ?? [];
        $meta['staff_permissions'] = RolePermissionMatrix::normalizeStaffPermissions($permissions);
        $meta['permission_group'] = $preset;

        $user->forceFill([
            'meta' => $meta,
            'status' => UserAccountStatus::Active,
        ])->save();

        StaffProfile::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => true]);

        $this->line('QA_STAFF_PERMISSION_DRIFT=0');
        $this->line('QA_STAFF_BASELINE_ROLE='.$preset);
        $this->line('QA_STAFF_STATUS=Active');

        return self::SUCCESS;
    }

    private function reportStatus(): int
    {
        $user = $this->qaUserQuery()->first();
        if ($user === null) {
            $this->line('QA_STAFF_CREATED=no');
            $this->line('QA_STAFF_STATUS=missing');

            return self::SUCCESS;
        }

        $this->line('QA_STAFF_CREATED=yes');
        $this->line('QA_STAFF_ACCOUNT_TYPE=Staff');
        $this->line('QA_STAFF_BASELINE_ROLE='.(string) (($user->meta['permission_group'] ?? null) ?: StaffPermission::PresetOperator));
        $this->line('QA_STAFF_STATUS='.$user->status->value);
        $this->line('QA_STAFF_USER_ID='.$user->id);

        return self::SUCCESS;
    }
}
