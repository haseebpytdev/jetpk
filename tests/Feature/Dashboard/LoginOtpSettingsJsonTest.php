<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Auth\LoginOtpSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class LoginOtpSettingsJsonTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ota-developer.enabled' => true]);
        config(['client_route_parity.enabled' => false]);
        $this->makeJetpkProfile();
        $this->seedJetpkAgency();
    }

    public function test_login_otp_json_round_trip_and_restore(): void
    {
        [$admin] = $this->platformAdmin();
        $service = app(LoginOtpSettingsService::class);
        $original = $service->snapshot()['required'];

        $this->actingAs($admin)
            ->getJson('/admin/settings/login-otp?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['snapshot' => ['required', 'source']]);

        $flipped = ! $original;

        $this->actingAs($admin)
            ->patchJson('/admin/settings/login-otp?format=json', [
                'require_login_otp' => $flipped,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('snapshot.required', $flipped);

        $this->actingAs($admin)
            ->getJson('/admin/settings/login-otp?format=json')
            ->assertOk()
            ->assertJsonPath('snapshot.required', $flipped);

        $this->actingAs($admin)
            ->patchJson('/admin/settings/login-otp?format=json', [
                'require_login_otp' => $original,
            ])
            ->assertOk()
            ->assertJsonPath('snapshot.required', $original);
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        return [$admin];
    }
}
