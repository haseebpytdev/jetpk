<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsurePasswordChangedMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_must_change_password_is_redirected_to_force_change(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $user->forceFill([
            'account_type' => AccountType::PlatformAdmin,
            'must_change_password' => true,
        ])->save();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('password.force'));
    }

    public function test_force_password_change_page_is_accessible(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $user->forceFill(['must_change_password' => true])->save();

        $this->actingAs($user)
            ->get(route('password.force'))
            ->assertOk()
            ->assertSee('Change your password', false);
    }

    public function test_password_change_clears_must_change_password_flag(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $user = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $user->forceFill(['must_change_password' => true])->save();

        $this->actingAs($user)
            ->post(route('password.force.store'), [
                'password' => 'New-Secure-Pass-1!',
                'password_confirmation' => 'New-Secure-Pass-1!',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
    }
}
