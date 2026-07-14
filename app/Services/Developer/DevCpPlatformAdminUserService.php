<?php

namespace App\Services\Developer;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\DeveloperUser;
use App\Models\User;
use App\Services\Platform\PlatformAuditLogger;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Dev CP platform_admin account lifecycle for this deployment (not multi-tenant SaaS).
 */
class DevCpPlatformAdminUserService
{
    public function __construct(
        protected PlatformAuditLogger $auditLogger,
        protected SecurityEventLogger $securityLogger,
    ) {}

    public function resolveDeploymentAgency(?string $agencyName = null, ?string $agencySlug = null): Agency
    {
        $name = trim((string) ($agencyName ?: 'Platform Owner'));
        $slug = Str::slug(trim((string) ($agencySlug ?: config('ota.default_agency_slug', 'platform-owner'))));

        if ($slug === '') {
            throw new InvalidArgumentException('Deployment fallback agency slug could not be resolved.');
        }

        return Agency::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'timezone' => 'UTC',
                'settings' => [],
            ]
        );
    }

    /**
     * @return array{user: User, tempPassword: string, created: bool, agency: Agency}
     */
    public function createPlatformAdmin(
        string $email,
        string $name,
        ?DeveloperUser $developer = null,
        ?Request $request = null,
        ?string $agencyName = null,
        ?string $agencySlug = null,
    ): array {
        $email = strtolower(trim($email));
        $name = trim($name);
        $tempPassword = Str::password(20);

        $result = DB::transaction(function () use ($email, $name, $tempPassword, $agencyName, $agencySlug): array {
            $agency = $this->resolveDeploymentAgency($agencyName, $agencySlug);

            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            $created = $user === null;

            if ($user === null) {
                $user = new User;
                $user->email = $email;
            }

            $user->forceFill([
                'name' => $name,
                'password' => $tempPassword,
                'account_type' => AccountType::PlatformAdmin,
                'status' => UserAccountStatus::Active,
                'current_agency_id' => $agency->id,
                'must_change_password' => true,
                'password_changed_at' => null,
                'email_verified_at' => now(),
            ])->save();

            AgencyUser::query()->firstOrCreate(
                [
                    'agency_id' => $agency->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => 'owner',
                    'agency_role' => AgencyRole::Owner,
                ]
            );

            return [
                'user' => $user->fresh(),
                'tempPassword' => $tempPassword,
                'created' => $created,
                'agency' => $agency,
            ];
        });

        $this->recordAuditAndSecurity(
            action: 'devcp.platform_admin.created',
            eventType: 'devcp.platform_admin.created',
            user: $result['user'],
            developer: $developer,
            request: $request,
            agencyId: $result['agency']->id,
            properties: [
                'user_email' => $result['user']->email,
                'user_created' => $result['created'],
            ],
        );

        return $result;
    }

    /**
     * @return array{user: User, tempPassword: string}
     */
    public function resetPassword(User $user, ?DeveloperUser $developer = null, ?Request $request = null): array
    {
        $this->assertPlatformAdmin($user);

        $tempPassword = Str::password(20);

        $user->forceFill([
            'password' => $tempPassword,
            'must_change_password' => true,
            'password_changed_at' => null,
        ])->save();

        $this->recordAuditAndSecurity(
            action: 'devcp.platform_admin.password_reset',
            eventType: 'devcp.platform_admin.password_reset',
            user: $user,
            developer: $developer,
            request: $request,
            agencyId: $user->current_agency_id,
            properties: [
                'user_email' => $user->email,
            ],
        );

        return [
            'user' => $user->fresh(),
            'tempPassword' => $tempPassword,
        ];
    }

    public function setActiveStatus(
        User $user,
        bool $active,
        ?DeveloperUser $developer = null,
        ?Request $request = null,
    ): User {
        $this->assertPlatformAdmin($user);

        if (! $active && $this->activePlatformAdminCount() <= 1 && $user->status === UserAccountStatus::Active) {
            throw new InvalidArgumentException('Cannot deactivate the last active platform admin for this deployment.');
        }

        $user->forceFill([
            'status' => $active ? UserAccountStatus::Active : UserAccountStatus::Inactive,
        ])->save();

        $this->recordAuditAndSecurity(
            action: 'devcp.platform_admin.status_changed',
            eventType: 'devcp.platform_admin.status_changed',
            user: $user,
            developer: $developer,
            request: $request,
            agencyId: $user->current_agency_id,
            properties: [
                'user_email' => $user->email,
                'status' => $user->status->value,
            ],
        );

        return $user->fresh();
    }

    public function activePlatformAdminCount(): int
    {
        return User::query()
            ->where('account_type', AccountType::PlatformAdmin)
            ->where('status', UserAccountStatus::Active)
            ->count();
    }

    public function assertPlatformAdmin(User $user): void
    {
        if (! $user->isPlatformAdmin()) {
            throw new InvalidArgumentException('User is not a platform admin.');
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function recordAuditAndSecurity(
        string $action,
        string $eventType,
        User $user,
        ?DeveloperUser $developer,
        ?Request $request,
        ?int $agencyId,
        array $properties,
    ): void {
        $this->auditLogger->record(
            action: $action,
            subject: $user,
            developer: $developer,
            agencyId: $agencyId,
            request: $request,
            properties: $properties,
        );

        $this->securityLogger->record(
            eventType: $eventType,
            outcome: 'success',
            actor: $developer,
            agencyId: $agencyId,
            request: $request,
            metadata: array_merge(['user_id' => $user->id], $properties),
        );
    }
}
