<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardSettingsResource;
use App\Models\User;
use App\Support\Dashboard\DashboardPermissionResolver;

class DashboardSettingsReadService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(User $user): array
    {
        DashboardPermissionResolver::assertPermission($user, 'settings.view');

        return DashboardSettingsResource::overview();
    }

    /**
     * @return array<string, mixed>
     */
    public function general(User $user): array
    {
        DashboardPermissionResolver::assertPermission($user, 'settings.view');

        return DashboardSettingsResource::general($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function security(User $user): array
    {
        DashboardPermissionResolver::assertPermission($user, 'settings.view');

        return DashboardSettingsResource::security();
    }

    /**
     * @return array<string, mixed>
     */
    public function notifications(User $user): array
    {
        DashboardPermissionResolver::assertPermission($user, 'settings.view');

        return DashboardSettingsResource::notifications($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function integrations(User $user): array
    {
        DashboardPermissionResolver::assertPermission($user, 'settings.view');

        return DashboardSettingsResource::integrations();
    }
}
