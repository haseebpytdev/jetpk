<?php

namespace Tests\Feature\Console;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JetpkDash03QaStaffCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('JP_DASH_03_QA_STAFF_PASSWORD=QaStaffPass!234');
        $_ENV['JP_DASH_03_QA_STAFF_PASSWORD'] = 'QaStaffPass!234';
    }

    protected function tearDown(): void
    {
        putenv('JP_DASH_03_QA_STAFF_PASSWORD');
        unset($_ENV['JP_DASH_03_QA_STAFF_PASSWORD']);
        parent::tearDown();
    }

    public function test_create_activate_restore_baseline_and_deactivate(): void
    {
        $this->artisan('jetpk:dash-03-qa-staff', ['action' => 'create'])
            ->expectsOutputToContain('QA_STAFF_CREATED=yes')
            ->expectsOutputToContain('QA_STAFF_BASELINE_ROLE=staff_operator')
            ->assertSuccessful();

        $user = User::query()->where('username', 'jp-dash-03-qa-staff')->first();
        $this->assertNotNull($user);
        $this->assertSame(AccountType::Staff, $user->account_type);
        $this->assertTrue((bool) ($user->meta['jp_dash_03_qa'] ?? false));
        $this->assertNotEmpty($user->meta['staff_permissions'] ?? []);
        $this->assertTrue(
            StaffProfile::query()->where('user_id', $user->id)->where('is_active', true)->exists()
        );

        $this->artisan('jetpk:dash-03-qa-staff', ['action' => 'activate'])
            ->expectsOutputToContain('QA_STAFF_STATUS=Active')
            ->assertSuccessful();

        $this->artisan('jetpk:dash-03-qa-staff', [
            'action' => 'restore-baseline',
            '--preset' => StaffPermission::PresetOperator,
        ])
            ->expectsOutputToContain('QA_STAFF_PERMISSION_DRIFT=0')
            ->expectsOutputToContain('QA_STAFF_BASELINE_ROLE=staff_operator')
            ->assertSuccessful();

        DB::table('sessions')->insert([
            'id' => 'qa-staff-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->artisan('jetpk:dash-03-qa-staff', ['action' => 'deactivate'])
            ->expectsOutputToContain('QA_STAFF_SESSIONS_INVALIDATED=yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame(UserAccountStatus::Suspended, $user->status);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertFalse(
            StaffProfile::query()->where('user_id', $user->id)->where('is_active', true)->exists()
        );
    }

    public function test_staff_status_output_omits_password(): void
    {
        $this->artisan('jetpk:dash-03-qa-staff', ['action' => 'create'])->assertSuccessful();
        $this->artisan('jetpk:dash-03-qa-staff', ['action' => 'status'])->assertSuccessful();
        $text = Artisan::output();
        $this->assertStringNotContainsString('QaStaffPass!234', $text);
        $this->assertStringNotContainsString('jp-dash-03-qa-staff@jetpakistan.pk', $text);
    }
}
