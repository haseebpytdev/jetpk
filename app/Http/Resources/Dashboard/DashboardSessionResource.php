<?php

namespace App\Http\Resources\Dashboard;

use App\Models\User;
use App\Support\Dashboard\DashboardPermissionResolver;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;

final class DashboardSessionResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'displayName' => $user->name,
            'email' => self::maskEmail($user->email),
            'roles' => DashboardPermissionResolver::roleLabels($user),
            'permissions' => DashboardPermissionResolver::effectivePermissionKeys($user),
            'accountType' => $user->account_type->value,
            'accountStatus' => $user->status->value,
            'staffType' => $user->isStaff() ? 'staff' : ($user->isAgentPortalUser() ? 'agent' : ($user->isPlatformAdmin() ? 'admin' : null)),
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
