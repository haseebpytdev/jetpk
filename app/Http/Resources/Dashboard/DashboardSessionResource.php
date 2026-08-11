<?php

namespace App\Http\Resources\Dashboard;

use App\Models\User;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use App\Support\BackOffice\BackOfficePortalAccess;
use App\Support\Dashboard\DashboardPermissionResolver;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;

final class DashboardSessionResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(User $user, ?string $portalType = null): array
    {
        $access = BackOfficePortalAccess::evaluate($user);
        $portal = $portalType ?? ($user->isPlatformAdmin() ? 'admin' : 'staff');
        $capabilities = app(BackOfficeCapabilitiesPresenter::class)->present($user, $portal);

        return [
            'id' => (string) $user->id,
            'displayName' => $user->name,
            'email' => self::maskEmail($user->email),
            'roles' => DashboardPermissionResolver::roleLabels($user),
            'permissions' => DashboardPermissionResolver::effectivePermissionKeys($user),
            'accountType' => $user->account_type->value,
            'accountStatus' => $user->status->value,
            'staffType' => $user->isStaff() ? 'staff' : ($user->isAgentPortalUser() ? 'agent' : ($user->isPlatformAdmin() ? 'admin' : null)),
            'portalType' => $portal,
            'platformRole' => $user->isPlatformAdmin() ? 'platform_admin' : ($user->isStaff() ? 'staff' : $user->account_type->value),
            'sessionUsable' => ($access['ok'] ?? false) === true,
            'denialReason' => $access['denial_reason'] ?? null,
            'requiresPasswordChange' => (bool) data_get($user->meta, 'requires_password_change', false),
            'requiresEmailVerification' => $user->email_verified_at === null,
            'landingRoute' => $user->isPlatformAdmin() ? '/admin/dashboard' : '/staff/dashboard',
            'navigation' => $capabilities['navigation'] ?? [],
            'navigationGroups' => $capabilities['navigation_groups'] ?? [],
            'capabilities' => $capabilities['capabilities'] ?? [],
            'schemaVersion' => DashboardReadOnlyEnvelope::SCHEMA_VERSION,
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return null;
        }

        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.'***@'.$domain;
    }
}
