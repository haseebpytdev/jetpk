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

/**
 * Controlled JP-DASH-03 temporary QA Staff identity lifecycle.
 * Never prints email, password, OTP, or remember tokens.
 *
 * Password material: JP_DASH_03_QA_STAFF_PASSWORD (process env only).
 */
class JetpkDash03QaStaffCommand extends Command
{
    private const QA_MARKER = 'jp-dash-03-qa-staff';

    private const QA_AGENCY_SLUG = 'jp-dash-03-qa-agency';

    protected $signature = 'jetpk:dash-03-qa-staff
        {action=status : create|activate|deactivate|restore-baseline|rotate-password|verify-email|status}
        {--preset=staff_operator : Canonical staff permission preset key}';

    protected $description = 'JP-DASH-03 controlled temporary QA Staff identity (sanitized output only)';

    public function handle(): int
    {
        $action = (string) $this->argument('action');

        return match ($action) {
            'create' => $this->createQaStaff(),
            'activate' => $this->activateQaStaff(),
            'deactivate' => $this->deactivateQaStaff(),
            'restore-baseline' => $this->restoreBaseline(),
            'rotate-password' => $this->rotatePassword(),
            'verify-email' => $this->verifyEmail(),
            'status' => $this->reportStatus(),
            default => $this->invalidAction($action),
        };
    }

    private function verifyEmail(): int
    {
        $user = $this->qaUserQuery()->first();
        if ($user === null) {
            $this->line('QA_STAFF_STATUS=missing');

            return self::SUCCESS;
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $this->line('QA_STAFF_EMAIL_VERIFIED=yes');

        return self::SUCCESS;
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
            ->where('username', self::QA_MARKER)
            ->where('meta->jp_dash_03_qa', true);
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

        $agency = Agency::query()->orderBy('id')->first()
            ?? Agency::query()->firstOrCreate(
                ['slug' => self::QA_AGENCY_SLUG],
                [
                    'name' => 'JP-DASH-03 QA Agency',
                    'timezone' => 'Asia/Karachi',
                    'settings' => ['jp_dash_03_qa' => true],
                ],
            );

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

        return self::SUCCESS;
    }

    private function activateQaStaff(): int
    {
        $user = $this->qaUserQuery()->first();
        if ($user === null) {
            $this->line('QA_STAFF_STATUS=missing');

            return self::FAILURE;
        }

        $permissions = array_values(array_unique(array_merge(
            StaffPermission::presetPermissions(StaffPermission::PresetOperator),
            StaffPermission::presetPermissions(StaffPermission::PresetSupport),
        )));

        $meta = $user->meta ?? [];
        $meta['staff_permissions'] = RolePermissionMatrix::normalizeStaffPermissions($permissions);
        $meta['permission_group'] = 'staff_operator+staff_support';
        $meta['jp_dash_03_qa'] = true;

        $user->forceFill([
            'meta' => $meta,
            'status' => UserAccountStatus::Active,
        ])->save();

        StaffProfile::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => true]);

        $this->line('QA_STAFF_STATUS=Active');
        $this->line('QA_STAFF_BASELINE_ROLE=staff_operator+staff_support');
        $this->line('QA_STAFF_ACTIVATED=yes');

        return self::SUCCESS;
    }

    private function deactivateQaStaff(): int
    {
        $user = $this->qaUserQuery()->first();
        if ($user === null) {
            $this->line('QA_STAFF_STATUS=missing');

            return self::SUCCESS;
        }

        $user->forceFill([
            'status' => UserAccountStatus::Suspended,
            'remember_token' => null,
        ])->save();

        StaffProfile::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->line('QA_STAFF_STATUS=Inactive');
        $this->line('QA_STAFF_SESSIONS_INVALIDATED=yes');
        $this->line('QA_STAFF_REMEMBER_TOKEN_INVALIDATED=yes');

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
        if ($permissions === []) {
            $this->line('QA_STAFF_PERMISSION_DRIFT=1');
            $this->error('Invalid staff preset.');

            return self::FAILURE;
        }

        $meta = $user->meta ?? [];
        $meta['staff_permissions'] = RolePermissionMatrix::normalizeStaffPermissions($permissions);
        $meta['permission_group'] = $preset;
        $meta['jp_dash_03_qa'] = true;

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

    private function rotatePassword(): int
    {
        $user = $this->qaUserQuery()->first();
        if ($user === null) {
            $this->line('QA_STAFF_STATUS=missing');

            return self::FAILURE;
        }

        $password = (string) env('JP_DASH_03_QA_STAFF_PASSWORD', '');
        if ($password === '') {
            $this->line('QA_STAFF_PASSWORD_REQUIRED=YES');

            return self::FAILURE;
        }

        $user->forceFill(['password' => Hash::make($password)])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $this->line('QA_STAFF_PASSWORD_ROTATED=yes');
        $this->line('QA_STAFF_SESSIONS_INVALIDATED=yes');

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
        $this->line('QA_STAFF_MARKER='.self::QA_MARKER);

        return self::SUCCESS;
    }
}
