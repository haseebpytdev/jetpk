<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Communication\AuthSecurityEmailNotificationService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginNotificationFailSoftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware([ValidateCsrfToken::class]);
    }

    public function test_login_succeeds_when_auth_security_email_notification_throws(): void
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        $this->mock(AuthSecurityEmailNotificationService::class, function ($mock): void {
            $mock->shouldReceive('notifyLoginSuccess')
                ->once()
                ->andThrow(new \RuntimeException('Simulated mail failure'));
            $mock->shouldReceive('notifyNewDeviceLogin')->zeroOrMoreTimes();
        });

        $this->post('/login', [
            'login' => $admin->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
    }
}
