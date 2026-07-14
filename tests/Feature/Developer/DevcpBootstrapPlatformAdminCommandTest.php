<?php

namespace Tests\Feature\Developer;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevcpBootstrapPlatformAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuses_when_platform_admin_exists_without_force(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        User::query()->where('email', 'admin@ota.demo')->first()?->forceFill([
            'account_type' => AccountType::PlatformAdmin,
        ])->save();

        $this->artisan('devcp:bootstrap-platform-admin')
            ->assertFailed();
    }

    public function test_creates_agency_and_platform_admin_on_clean_db(): void
    {
        $this->artisan('devcp:bootstrap-platform-admin', [
            '--email' => 'owner@platform.test',
            '--name' => 'Owner',
            '--agency-slug' => 'platform-owner',
        ])
            ->expectsOutputToContain('Platform Admin created for this deployment.')
            ->assertSuccessful();

        $this->assertDatabaseHas('agencies', ['slug' => 'platform-owner']);
        $this->assertDatabaseHas('users', [
            'email' => 'owner@platform.test',
            'account_type' => AccountType::PlatformAdmin->value,
            'must_change_password' => true,
        ]);

        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'devcp.platform_admin.created',
        ]);

        $this->assertDatabaseHas('security_events', [
            'event_type' => 'devcp.platform_admin.created',
            'outcome' => 'success',
        ]);
    }
}
