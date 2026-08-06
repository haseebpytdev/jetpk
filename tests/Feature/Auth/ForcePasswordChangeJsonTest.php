<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcePasswordChangeJsonTest extends TestCase
{
    use RefreshDatabase;

    private function customerWithForcedPassword(): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $user->forceFill([
            'account_type' => AccountType::Customer,
            'must_change_password' => true,
        ])->save();

        return $user->fresh();
    }

    public function test_force_password_html_page_remains_available(): void
    {
        $user = $this->customerWithForcedPassword();

        $this->actingAs($user)
            ->get(route('password.force'))
            ->assertOk()
            ->assertSee('Change your password', false);
    }

    public function test_session_api_remains_available_when_password_change_required(): void
    {
        $user = $this->customerWithForcedPassword();

        $this->actingAs($user)
            ->getJson('/api/public/auth/session')
            ->assertOk()
            ->assertJsonPath('requires_password_change', true);
    }

    public function test_json_show_returns_required_state_for_authenticated_user(): void
    {
        $user = $this->customerWithForcedPassword();

        $this->actingAs($user)
            ->getJson('/password/force-change?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('requires_password_change', true);
    }

    public function test_json_show_redirects_when_change_not_required(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $user->forceFill(['must_change_password' => false])->save();

        $this->actingAs($user)
            ->getJson('/password/force-change?format=json')
            ->assertOk()
            ->assertJsonPath('requires_password_change', false)
            ->assertJsonPath('redirect', '/customer/bookings');
    }

    public function test_unauthenticated_json_show_is_rejected(): void
    {
        $this->getJson('/password/force-change?format=json')
            ->assertUnauthorized();
    }

    public function test_valid_json_password_change_clears_requirement_and_returns_redirect(): void
    {
        $user = $this->customerWithForcedPassword();

        $this->actingAs($user)
            ->postJson('/password/force-change?format=json', [
                'password' => 'New-Secure-Pass-1!',
                'password_confirmation' => 'New-Secure-Pass-1!',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('redirect', '/customer/bookings');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_validation_failure_returns_safe_json_errors(): void
    {
        $user = $this->customerWithForcedPassword();

        $this->actingAs($user)
            ->postJson('/password/force-change?format=json', [
                'password' => 'short',
                'password_confirmation' => 'mismatch',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_unauthenticated_json_store_is_rejected(): void
    {
        $this->postJson('/password/force-change?format=json', [
            'password' => 'New-Secure-Pass-1!',
            'password_confirmation' => 'New-Secure-Pass-1!',
        ])->assertUnauthorized();
    }

    public function test_user_without_requirement_cannot_submit_json_store(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $user->forceFill(['must_change_password' => false])->save();

        $this->actingAs($user)
            ->postJson('/password/force-change?format=json', [
                'password' => 'New-Secure-Pass-1!',
                'password_confirmation' => 'New-Secure-Pass-1!',
            ])
            ->assertForbidden();
    }

    public function test_agent_json_store_redirects_to_agent_dashboard(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $user->forceFill([
            'account_type' => AccountType::Agent,
            'must_change_password' => true,
        ])->save();

        $this->actingAs($user)
            ->postJson('/password/force-change?format=json', [
                'password' => 'New-Secure-Pass-1!',
                'password_confirmation' => 'New-Secure-Pass-1!',
            ])
            ->assertOk()
            ->assertJsonPath('redirect', '/agent');
    }
}
