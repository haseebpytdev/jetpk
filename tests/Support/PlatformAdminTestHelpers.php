<?php

namespace Tests\Support;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;

trait PlatformAdminTestHelpers
{
    protected function platformAdmin(): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        return $admin->fresh();
    }

    protected function legacyAgencyAdminFromSeed(): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $legacy = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $legacy->forceFill(['account_type' => AccountType::AgencyAdmin])->save();

        return $legacy->fresh();
    }
}
